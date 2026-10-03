<?php
/**
 * OpenTimestamps upgrade test — offline, against tests/fixtures/fake_calendar.php.
 *
 *   php -S 127.0.0.1:8199 tests/fixtures/fake_calendar.php     # in one terminal
 *   php tests/ots_upgrade_test.php                             # in another
 *
 * It proves the part of the pipeline that only runs hours after an upload in
 * production: reading a stored .ots, asking the calendar embedded in the
 * pending attestation for an upgraded subtree, merging it back at the right
 * node, re-serialising the file and flipping the record to "confirmed" with the
 * Bitcoin block height.
 *
 * With --python it additionally validates the upgraded .ots with the official
 * python-opentimestamps library (requires `pip install opentimestamps`).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$config = require $root . '/app/bootstrap.php';

use Athenaeum\Core\Config;
use Athenaeum\Core\Database;
use Athenaeum\Core\Settings;
use Athenaeum\Models\Timestamp;
use Athenaeum\Services\OpenTimestamps;

Config::load($config);

$calendarUrl = 'http://127.0.0.1:8199';
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--calendar=')) {
        $calendarUrl = substr($argument, 11);
    }
}

$checks = 0;
$failures = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $checks, $failures;
    $checks++;
    if ($ok) {
        echo "  [ok]   {$label}\n";
    } else {
        $failures++;
        echo "  [FAIL] {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

echo "OpenTimestamps upgrade test\n";
echo "fake calendar: {$calendarUrl}\n\n";

// ---------------------------------------------------------------------------
// Reachability of the fake calendar
// ---------------------------------------------------------------------------
$probe = \Athenaeum\Core\Http::get($calendarUrl . '/timestamp/' . str_repeat('a', 64), [], ['timeout' => 5]);
check(
    'fake calendar is reachable (start it with: php -S 127.0.0.1:8199 tests/fixtures/fake_calendar.php)',
    in_array($probe['status'], [200, 404], true),
    'status ' . $probe['status'] . ' ' . (string) $probe['error']
);
if (!in_array($probe['status'], [200, 404], true)) {
    echo "\n{$failures} of {$checks} checks failed\n";
    exit(1);
}

// ---------------------------------------------------------------------------
// Prepare an isolated paper id and a scratch file
// ---------------------------------------------------------------------------
$paperId = 990001 + random_int(0, 900);
$scratch = sys_get_temp_dir() . '/athenaeum-upgrade-' . bin2hex(random_bytes(6)) . '.pdf';
file_put_contents($scratch, "%PDF-1.4\nupgrade test payload " . random_bytes(64) . "\n%%EOF\n");
$digest = (string) hash_file('sha256', $scratch);
echo "scratch file : {$scratch}\ndigest       : {$digest}\npaper id     : {$paperId} (synthetic)\n\n";

$originalCalendars = Settings::get('ots.calendars');
Settings::set('ots.calendars', $calendarUrl);
Settings::set('ots.enabled', true);

$db = Database::instance();
$proofPath = null;
$rowId = null;

try {
    // -----------------------------------------------------------------------
    // 1. Stamp through the real code path
    // -----------------------------------------------------------------------
    echo "stamp (submitting to the fake calendar):\n";
    $result = OpenTimestamps::stamp($paperId, $scratch, 'upgrade-test.pdf', 'pdf', null);
    check('stamp() reports success', !empty($result['ok']), (string) ($result['error'] ?? ''));
    $rowId = (int) ($result['timestamp_id'] ?? 0);
    check('a timestamp row was created', $rowId > 0);

    $row = $rowId > 0 ? Timestamp::find($rowId) : null;
    check('row is pending', ($row['status'] ?? '') === Timestamp::STATUS_PENDING, (string) ($row['status'] ?? ''));
    check('the pending commitment was recorded', (int) ($row['calendar_count'] ?? 0) === 1,
        'calendar_count=' . (string) ($row['calendar_count'] ?? '?'));

    $proofPath = $row !== null ? Config::path('ots', (string) $row['ots_path']) : null;
    check('proof file written', $proofPath !== null && is_file($proofPath));

    $tree = $proofPath !== null ? OpenTimestamps::parse((string) file_get_contents($proofPath)) : null;
    check('proof parses', $tree !== null);
    check('proof commits to the file digest', ($tree['digest'] ?? '') === hex2bin($digest));
    $tips = $tree === null ? [] : OpenTimestamps::pendingCommitments($tree['root']);
    check('one pending tip pointing at the fake calendar', count($tips) === 1 && $tips[0]['uri'] === $calendarUrl,
        json_encode(array_map(static fn (array $t): string => $t['uri'], $tips)));
    check('no bitcoin attestation yet', $tree !== null && OpenTimestamps::findBitcoinHeight($tree['root']) === null);

    // -----------------------------------------------------------------------
    // 2. "Not confirmed yet" must not break anything
    // -----------------------------------------------------------------------
    echo "\nupgrade while the calendar still answers 404:\n";
    \Athenaeum\Core\Http::get($calendarUrl . '/reset', [], ['timeout' => 5]);
    $pendingRow = Timestamp::find($rowId);
    $before = (string) file_get_contents((string) $proofPath);
    $outcome = OpenTimestamps::upgradeRow($pendingRow);
    check('upgrade reports no change', $outcome['changed'] === false, json_encode($outcome));
    check('proof file untouched', (string) file_get_contents((string) $proofPath) === $before);
    check('row still pending', (Timestamp::find($rowId)['status'] ?? '') === Timestamp::STATUS_PENDING);

    // -----------------------------------------------------------------------
    // 3. The real upgrade
    // -----------------------------------------------------------------------
    echo "\nupgrade once the calendar has a Bitcoin attestation:\n";
    $pendingRow = Timestamp::find($rowId);
    $outcome = OpenTimestamps::upgradeRow($pendingRow);
    check('upgrade merged a subtree', $outcome['changed'] === true, json_encode($outcome));
    check('row is confirmed', $outcome['status'] === Timestamp::STATUS_CONFIRMED, (string) $outcome['status']);

    $upgraded = Timestamp::find($rowId);
    check('bitcoin height stored', (int) $upgraded['bitcoin_height'] === 845123, (string) $upgraded['bitcoin_height']);
    check('bitcoin time recorded', !empty($upgraded['bitcoin_time']), (string) ($upgraded['bitcoin_time'] ?? ''));
    check('upgraded_at recorded', !empty($upgraded['upgraded_at']));
    check('attempt counter incremented', (int) $upgraded['attempts'] > (int) $pendingRow['attempts']);
    check('no error recorded', empty($upgraded['last_error']), (string) ($upgraded['last_error'] ?? ''));

    // -----------------------------------------------------------------------
    // 4. The stored file is a valid, upgraded proof
    // -----------------------------------------------------------------------
    echo "\nrewritten proof file:\n";
    $bytes = (string) file_get_contents((string) $proofPath);
    $tree = OpenTimestamps::parse($bytes);
    check('rewritten proof parses', $tree !== null);
    check('still commits to the same file', ($tree['digest'] ?? '') === hex2bin($digest));
    check('bitcoin height readable from the file', $tree !== null && OpenTimestamps::findBitcoinHeight($tree['root']) === 845123);
    $types = $tree === null ? [] : array_column(OpenTimestamps::attestations($tree['root']), 'type');
    check('file carries a bitcoin attestation', in_array('bitcoin', $types, true), implode(',', $types));
    check('serialisation is stable (parse → serialize → identical)',
        $tree !== null && OpenTimestamps::serializeProof($tree) === $bytes);
    check('magic header intact', str_starts_with($bytes, OpenTimestamps::HEADER_MAGIC));

    // -----------------------------------------------------------------------
    // 5. upgradePending() picks up rows by itself
    // -----------------------------------------------------------------------
    echo "\nbatch upgrade (what the cron job runs):\n";
    $stats = OpenTimestamps::upgradePending(10, 0);
    check('batch run completes', isset($stats['checked'], $stats['upgraded'], $stats['confirmed']), json_encode($stats));
    echo '       ' . json_encode($stats) . "\n";

    // -----------------------------------------------------------------------
    // 6. Optional: validate with the official implementation
    // -----------------------------------------------------------------------
    if (in_array('--python', $argv, true)) {
        echo "\nofficial python-opentimestamps validation:\n";
        $command = sprintf(
            'python %s %s %s 2>&1',
            escapeshellarg($root . '/tests/verify_ots.py'),
            escapeshellarg((string) $proofPath),
            escapeshellarg($scratch)
        );
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        echo '       ' . implode("\n       ", array_slice($output, -6)) . "\n";
        check('official library accepts the upgraded proof', $exitCode === 0, 'exit code ' . $exitCode);
        check('official library sees the bitcoin attestation',
            (bool) array_filter($output, static fn (string $line): bool => str_contains($line, 'bitcoin attestations : 1')));
    }
} finally {
    // -----------------------------------------------------------------------
    // Cleanup: never leave test rows or files behind
    // -----------------------------------------------------------------------
    if ($rowId !== null && $rowId > 0) {
        $db->delete('timestamps', 'id = :id', ['id' => $rowId]);
    }
    $db->delete('timestamps', 'paper_id = :id', ['id' => $paperId]);
    if ($proofPath !== null && is_file($proofPath)) {
        @unlink($proofPath);
        @rmdir(dirname($proofPath));
    }
    @unlink($scratch);
    Settings::set('ots.calendars', $originalCalendars);
}

echo "\n{$checks} checks run, {$failures} failed\n";
echo $failures === 0 ? "UPGRADE PIPELINE OK\n" : "UPGRADE PIPELINE HAS FAILURES\n";
exit($failures === 0 ? 0 : 1);
