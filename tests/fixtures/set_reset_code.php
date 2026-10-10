<?php
/**
 * TEST FIXTURE (local only): plant a known reset code for an address.
 *
 *   php tests/fixtures/set_reset_code.php someone@example.org 123456
 *
 * Never deploy this: it exists so an HTTP-level test can exercise the reset
 * controller without reading a mail spool.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$config = require $root . '/app/bootstrap.php';

use Athenaeum\Core\Config;
use Athenaeum\Models\EmailVerification;

Config::load($config);

$email = (string) ($argv[1] ?? '');
$code = (string) ($argv[2] ?? '');
if ($email === '' || !preg_match('/^\d{6}$/', $code)) {
    fwrite(STDERR, "usage: set_reset_code.php <email> <6-digit code>\n");
    exit(1);
}

// Drop whatever is outstanding, then plant the known one through the model so
// the hash is produced exactly as the application would produce it.
$db = \Athenaeum\Core\Database::instance();
$db->delete('email_verifications', 'email = :email', ['email' => mb_strtolower($email)]);

$issued = EmailVerification::issue($email, EmailVerification::PURPOSE_RESET);
if (empty($issued['ok'])) {
    fwrite(STDERR, 'issue failed: ' . ($issued['error'] ?? '?') . "\n");
    exit(1);
}

// Overwrite the generated code with the requested one (same hashing path).
$row = $db->selectOne(
    'SELECT id FROM {{email_verifications}} WHERE email = :email AND purpose = :purpose ORDER BY id DESC LIMIT 1',
    ['email' => mb_strtolower($email), 'purpose' => EmailVerification::PURPOSE_RESET]
);
if ($row === null) {
    fwrite(STDERR, "row missing\n");
    exit(1);
}

$reflection = new ReflectionClass(EmailVerification::class);
$hash = $reflection->getMethod('hash');
$hash->setAccessible(true);
$db->update(
    'email_verifications',
    ['code_hash' => $hash->invoke(null, $code)],
    'id = :id',
    ['id' => (int) $row['id']]
);

echo "planted {$code} for {$email}\n";
