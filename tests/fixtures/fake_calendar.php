<?php
/**
 * A miniature OpenTimestamps calendar for offline testing.
 *
 *   php -S 127.0.0.1:8199 tests/fixtures/fake_calendar.php
 *
 * Implements just enough of the protocol to exercise the whole pipeline
 * without touching the public calendars:
 *
 *   POST /digest             body = 32 raw bytes → a pending chain
 *                            (digest → sha256 → PendingAttestation(self))
 *   GET  /timestamp/<hex>    → a subtree carrying a Bitcoin attestation,
 *                            i.e. the "upgrade succeeded" answer
 *
 * The Bitcoin height and the "confirmed" delay are configurable through the
 * query string of the URL used as the calendar (e.g.
 * `http://127.0.0.1:8199?height=845123`) or environment variables.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use Athenaeum\Core\Config;
use Athenaeum\Services\OpenTimestamps;

Config::load(require dirname(__DIR__, 2) . '/config/config.php');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$query = [];
parse_str((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY), $query);

$height = (int) ($query['height'] ?? getenv('FAKE_OTS_HEIGHT') ?: 845123);
// Simulate "not confirmed yet" for the first N upgrade attempts.
$pendingTimes = (int) ($query['pending_times'] ?? getenv('FAKE_OTS_PENDING_TIMES') ?: 0);
$counterFile = sys_get_temp_dir() . '/fake-calendar-counter';

$class = \Athenaeum\Models\Timestamp::class;

if ($path === '/digest' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $digest = file_get_contents('php://input') ?: '';
    if (strlen($digest) !== 32) {
        http_response_code(400);
        header('Content-Type: text/plain');
        echo 'invalid Content-Length';
        return;
    }

    // digest --sha256--> commitment --pending(self)-->
    $commitment = hash('sha256', $digest, true);
    $uri = 'http://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8199');
    // A PendingAttestation payload contains the URI and *nothing else*; an
    // extra trailing field makes the official client report trailing garbage.
    $pendingPayload = OpenTimestamps::writeVarBytes($uri);
    $child = [
        'msg'          => $commitment,
        'attestations' => [['tag' => OpenTimestamps::TAG_PENDING, 'payload' => $pendingPayload]],
        'ops'          => [],
    ];
    $root = [
        'msg'          => $digest,
        'attestations' => [],
        'ops'          => [['tag' => "\x08", 'arg' => null, 'child' => $child]],
    ];

    header('Content-Type: application/vnd.opentimestamps.v1');
    echo OpenTimestamps::serializeNode($root);
    return;
}

if (str_starts_with($path, '/timestamp/')) {
    $hex = substr($path, strlen('/timestamp/'));
    if ($hex === '' || !ctype_xdigit($hex)) {
        http_response_code(400);
        header('Content-Type: text/plain');
        echo 'commitment must be hex-encoded bytes';
        return;
    }
    $commitment = (string) hex2bin($hex);

    if ($pendingTimes > 0) {
        $attempts = (int) @file_get_contents($counterFile);
        @file_put_contents($counterFile, (string) ($attempts + 1));
        if ($attempts < $pendingTimes) {
            http_response_code(404);
            header('Content-Type: text/plain');
            echo 'Pending confirmation in Bitcoin blockchain';
            return;
        }
    }

    $payload = OpenTimestamps::writeVarUint($height);
    $node = [
        'msg'          => $commitment,
        'attestations' => [['tag' => OpenTimestamps::TAG_BITCOIN, 'payload' => $payload]],
        'ops'          => [],
    ];

    header('Content-Type: application/vnd.opentimestamps.v1');
    echo OpenTimestamps::serializeNode($node);
    return;
}

if ($path === '/reset') {
    @unlink($counterFile);
    header('Content-Type: text/plain');
    echo 'counter reset';
    return;
}

http_response_code(404);
header('Content-Type: text/plain');
echo 'Not found';
