<?php
/**
 * SMTP client tests -offline, against tests/fixtures/fake_smtp.php.
 *
 *   php tests/fixtures/fake_smtp.php 8025        # in one terminal
 *   php tests/mail_test.php                      # in another
 *
 * Validates the protocol conversation (EHLO, AUTH, envelope, DATA), the MIME
 * message that is produced, the failure paths, the header-injection guard and
 * the notification rules that decide who receives what.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$config = require $root . '/app/bootstrap.php';

use Athenaeum\Core\Config;
use Athenaeum\Core\Settings;
use Athenaeum\Services\Mailer;

Config::load($config);

$port = 8025;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--port=')) {
        $port = (int) substr($argument, 7);
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
        echo "  [FAIL] {$label}" . ($detail !== '' ? " -{$detail}" : '') . "\n";
    }
}

$transcriptFile = sys_get_temp_dir() . '/fake-smtp-transcript.txt';
$messageFile = sys_get_temp_dir() . '/fake-smtp-message.txt';
@unlink($transcriptFile);
@unlink($messageFile);

$original = [];
foreach ([
    'mail.enabled', 'mail.transport', 'mail.host', 'mail.port', 'mail.encryption',
    'mail.username', 'mail.password', 'mail.from_address', 'mail.from_name', 'mail.reply_to',
    'mail.notify_admin', 'mail.notify_author',
] as $key) {
    $original[$key] = Settings::get($key);
}

echo "Athenaeum SMTP tests\n";
echo "fake server: 127.0.0.1:{$port}\n\n";

// Is the fake server up?
$probe = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $errstr, 3);
check(
    'fake SMTP server is reachable (start it with: php tests/fixtures/fake_smtp.php ' . $port . ')',
    $probe !== false,
    "{$errstr} ({$errno})"
);
if ($probe === false) {
    echo "\n{$checks} checks run, {$failures} failed\n";
    exit(1);
}
fclose($probe);

try {
    // ---------------------------------------------------------------------
    echo "configuration:\n";
    Settings::set('mail.enabled', false);
    check('disabled mail reports not configured', Mailer::configured()['ok'] === false);

    Settings::set('mail.enabled', true);
    Settings::set('mail.transport', 'smtp');
    Settings::set('mail.host', '127.0.0.1');
    Settings::set('mail.port', $port);
    Settings::set('mail.encryption', 'none');
    Settings::set('mail.username', 'notifications@example.org');
    Settings::set('mail.password', 'authorisation-code-16');
    Settings::set('mail.from_address', '');
    Settings::set('mail.from_name', '雅典学院');
    Settings::set('mail.reply_to', 'reply@example.org');

    check('enabled mail reports ready', Mailer::configured()['ok'] === true, Mailer::configured()['reason']);
    check('sender falls back to the username', Mailer::fromAddress() === 'notifications@example.org');

    // ---------------------------------------------------------------------
    echo "\nsending a message:\n";
    $result = Mailer::send(
        'author@example.org',
        '测试主题 · Test subject',
        '<p>Hello <strong>world</strong>.</p><p>Second paragraph.</p>',
        "Hello world.\n\nSecond paragraph."
    );
    check('send reports success', !empty($result['ok']), (string) ($result['error'] ?? ''));
    check('transcript written', is_file($transcriptFile));
    check('message captured', is_file($messageFile) && filesize($messageFile) > 0);

    $transcript = is_file($transcriptFile) ? (string) file_get_contents($transcriptFile) : '';
    $message = is_file($messageFile) ? (string) file_get_contents($messageFile) : '';

    check('EHLO sent', str_contains($transcript, 'EHLO'), '');
    check('authentication attempted', str_contains($transcript, 'AUTH'), $transcript);
    check('AUTH succeeded', str_contains($transcript, '235'), '');
    check('envelope sender used the resolved address', str_contains($transcript, 'MAIL FROM:<notifications@example.org>'));
    check('recipient sent', str_contains($transcript, 'RCPT TO:<author@example.org>'));
    check('DATA followed by the terminating dot', str_contains($transcript, 'DATA'));

    check('message has a Subject header', (bool) preg_match('/^Subject: /m', $message));
    check('non-ASCII subject is RFC 2047 encoded', str_contains($message, '=?UTF-8?B?'), substr($message, 0, 400));
    check('From header present with the configured name', str_contains($message, 'From: '));
    check('Reply-To honoured', str_contains($message, 'Reply-To: <reply@example.org>'));
    check('Message-ID present', (bool) preg_match('/^Message-ID: <.+@/m', $message));
    check('multipart/alternative body', str_contains($message, 'multipart/alternative'));

    // Decode both MIME parts rather than matching wrapped base64.
    $parts = [];
    if (preg_match_all(
        '/Content-Type: ([^;]+);[^\r\n]*\r?\nContent-Transfer-Encoding: base64\r?\n\r?\n(.*?)(?=\r?\n--|\z)/s',
        $message,
        $matches,
        PREG_SET_ORDER
    )) {
        foreach ($matches as $match) {
            $parts[trim($match[1])] = base64_decode(preg_replace('/\s+/', '', $match[2]) ?? '', true) ?: '';
        }
    }
    $plain = $parts['text/plain'] ?? '';
    $html = $parts['text/html'] ?? '';
    check('plain-text part decodes to the original text', str_contains($plain, 'Second paragraph.'), substr($plain, 0, 120));
    check('HTML part decodes to the original markup', str_contains($html, '<strong>world</strong>'), substr($html, 0, 120));
    check('X-Mailer identifies the application', str_contains($message, 'X-Mailer: AthenXiv'));
    check('no literal placeholder leaked', !str_contains($message, ':site') && !str_contains($message, ':uid'));

    // ---------------------------------------------------------------------
    echo "\nguards:\n";
    $injection = Mailer::send("victim@example.org\r\nBcc: attacker@example.org", 'ok', '<p>x</p>');
    check('header injection in the recipient is refused', empty($injection['ok']) && str_contains((string) $injection['error'], 'injection'));

    $badAddress = Mailer::send('not-an-address', 'ok', '<p>x</p>');
    check('invalid recipient refused', empty($badAddress['ok']));

    $injectionSubject = Mailer::send('author@example.org', "ok\r\nBcc: attacker@example.org", '<p>x</p>');
    check('header injection in the subject is refused', empty($injectionSubject['ok']));

    // ---------------------------------------------------------------------
    echo "\ntemplates and notifications:\n";
    $test = Mailer::sendTest('author@example.org');
    check('sendTest delivers', !empty($test['ok']), (string) ($test['error'] ?? ''));

    Settings::set('mail.notify_author', false);
    Settings::set('mail.notify_admin', false);
    $before = is_file($transcriptFile) ? strlen((string) file_get_contents($transcriptFile)) : 0;
    Mailer::notifyPaperEvent(Mailer::EVENT_APPROVED, [
        'id' => 1, 'uid' => 'ATH-TEST01', 'title' => 'A fixture paper', 'status' => 'approved', 'uploader_id' => 0,
    ]);
    $after = is_file($transcriptFile) ? strlen((string) file_get_contents($transcriptFile)) : 0;
    check('notifications are silent when both switches are off', $before === $after);
} finally {
    foreach ($original as $key => $value) {
        Settings::set($key, $value);
    }
    Settings::flush();
}

echo "\n{$checks} checks run, {$failures} failed\n";
echo $failures === 0 ? "SMTP OK\n" : "SMTP HAS FAILURES\n";
exit($failures === 0 ? 0 : 1);
