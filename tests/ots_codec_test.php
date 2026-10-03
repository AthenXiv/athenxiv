<?php
/**
 * Codec validation for the OpenTimestamps implementation.
 *
 * The fixtures in tests/fixtures/ are produced by the official
 * python-opentimestamps library (see make_ots_fixtures.py). Parsing them and
 * serializing them again must reproduce the original bytes exactly — that is
 * the only honest way to know the PHP codec speaks the real format.
 *
 * Usage:  php tests/ots_codec_test.php [--live]
 *         --live also submits one random digest to the calendars.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$config = require $root . '/app/bootstrap.php';
\Athenaeum\Core\Config::load($config);

use Athenaeum\Core\Http;
use Athenaeum\Services\OpenTimestamps;

require $root . '/app/helpers.php';

$fixtures = $root . '/tests/fixtures';
$live = in_array('--live', $argv, true);
$failures = 0;
$checks = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;
    if ($ok) {
        echo "  [ok]   {$label}\n";
    } else {
        $failures++;
        echo "  [FAIL] {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

function firstDifference(string $a, string $b): string
{
    $length = min(strlen($a), strlen($b));
    for ($i = 0; $i < $length; $i++) {
        if ($a[$i] !== $b[$i]) {
            return sprintf('first difference at byte %d (0x%02x vs 0x%02x)', $i, ord($a[$i]), ord($b[$i]));
        }
    }
    return sprintf('length differs: %d vs %d bytes', strlen($a), strlen($b));
}

echo "OpenTimestamps codec test\n";
echo "TLS CA bundle: " . (Http::caBundle() ?? '(none found)') . "\n\n";

foreach (['single.ots', 'pending.ots', 'confirmed.ots'] as $name) {
    $path = $fixtures . '/' . $name;
    echo "{$name}:\n";
    if (!is_file($path)) {
        check('fixture present', false, "missing {$path}");
        continue;
    }
    $original = (string) file_get_contents($path);

    $tree = OpenTimestamps::parse($original);
    check('parses', $tree !== null);
    if ($tree === null) {
        continue;
    }

    check('header digest matches payload', $tree['digest'] === hash_file('sha256', $fixtures . '/payload.bin', true));
    check('file hash op is sha256', $tree['file_hash_op'] === "\x08");

    $reserialized = OpenTimestamps::serializeProof($tree);
    check(
        'round-trips byte-identically',
        $reserialized === $original,
        $reserialized === $original ? '' : firstDifference($reserialized, $original)
            . sprintf(' (rebuilt %d bytes, original %d)', strlen($reserialized), strlen($original))
    );

    $pending = OpenTimestamps::pendingCommitments($tree['root']);
    $height = OpenTimestamps::findBitcoinHeight($tree['root']);
    echo '       pending tips: ' . count($pending) . ', bitcoin height: ' . ($height ?? 'none') . "\n";

    if ($name === 'single.ots') {
        check('single calendar pending tip', count($pending) === 1);
        check('no bitcoin attestation yet', $height === null);
        check('pending tip carries a calendar URI', str_starts_with($pending[0]['uri'] ?? '', 'https://'));
    }
    if ($name === 'pending.ots') {
        check('four pending tips (four aggregators)', count($pending) === 4, 'got ' . count($pending));
        check('all URIs are official calendars', array_reduce(
            $pending,
            static fn (bool $carry, array $entry): bool => $carry
                && (str_contains($entry['uri'], '.calendar.opentimestamps.org')
                    || str_contains($entry['uri'], '.calendar.eternitywall.com')
                    || str_contains($entry['uri'], '.calendar.catallaxy.com')),
            true
        ));
    }
    if ($name === 'confirmed.ots') {
        check('highest bitcoin height found', $height === 845126, 'got ' . var_export($height, true));
        check('pending tips survive alongside the bitcoin proof', count($pending) === 4, 'got ' . count($pending));
        $types = array_column(OpenTimestamps::attestations($tree['root']), 'type');
        check('bitcoin attestations present', in_array('bitcoin', $types, true));
        check('pending attestations present', in_array('pending', $types, true));
    }
    echo "\n";
}

// --- varint primitives ------------------------------------------------------
echo "primitives:\n";
check('varuint(0)', OpenTimestamps::writeVarUint(0) === "\x00");
check('varuint(1)', OpenTimestamps::writeVarUint(1) === "\x01");
check('varuint(127)', OpenTimestamps::writeVarUint(127) === "\x7f");
check('varuint(128)', OpenTimestamps::writeVarUint(128) === "\x80\x01");
check('varuint(845123)', OpenTimestamps::writeVarUint(845123) === "\xc3\xca\x33");
echo "\n";

// --- proof construction -----------------------------------------------------
echo "proof construction:\n";
$digest = hash('sha256', 'athenaeum', true);
// BitcoinBlockHeaderAttestation(42): marker + 8 byte tag + varbytes(varuint(42))
$proof = OpenTimestamps::buildProof($digest, "\x00" . OpenTimestamps::TAG_BITCOIN . "\x01\x2a");
check('magic prefix', str_starts_with($proof, OpenTimestamps::HEADER_MAGIC));
check('version byte is 1', $proof[31] === "\x01");
check('hash op is sha256', $proof[32] === "\x08");
check('digest embedded verbatim', substr($proof, 33, 32) === $digest);
$reparsed = OpenTimestamps::parse($proof);
check('built proof parses back', $reparsed !== null && $reparsed['digest'] === $digest);
check('bitcoin height read back', $reparsed !== null && OpenTimestamps::findBitcoinHeight($reparsed['root']) === 42);
echo "\n";

// --- optional live calendar round trip -------------------------------------
if ($live) {
    echo "live calendar submission (one digest):\n";
    $liveDigest = hash('sha256', 'athenaeum-live-' . random_bytes(8), true);
    $result = OpenTimestamps::submitDigest($liveDigest);
    check('a calendar answered', !empty($result['ok']), (string) ($result['error'] ?? ''));
    if (!empty($result['ok'])) {
        $liveProof = OpenTimestamps::buildProof($liveDigest, (string) $result['body']);
        $tree = OpenTimestamps::parse($liveProof);
        check('live proof parses', $tree !== null);
        $tips = $tree === null ? [] : OpenTimestamps::pendingCommitments($tree['root']);
        check('live proof has a pending tip', count($tips) >= 1, 'got ' . count($tips));
        check(
            'round-trips byte-identically',
            $tree !== null && OpenTimestamps::serializeProof($tree) === $liveProof
        );
        echo '       calendars used: ' . implode(', ', $result['calendars'] ?? []) . "\n";
        echo '       tips: ' . count($tips) . "\n";
    }
    echo "\n";
}

echo $failures === 0
    ? "ALL {$checks} CHECKS PASSED\n"
    : "{$failures} of {$checks} CHECKS FAILED\n";

exit($failures === 0 ? 0 : 1);
