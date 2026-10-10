<?php
/**
 * TEST FIXTURE (local only): create or update a user with a known password.
 *
 *   php tests/fixtures/make_user.php someone@example.org some-password
 *
 * Like set_reset_code.php next to it, this exists so an HTTP-level test can work
 * without a browser. Never point it at a live database: it overwrites the
 * password of any account whose address you name.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$config = require $root . '/app/bootstrap.php';

use Athenaeum\Core\Config;
use Athenaeum\Models\User;

Config::load($config);

$email = mb_strtolower(trim((string) ($argv[1] ?? '')));
$password = (string) ($argv[2] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
    fwrite(STDERR, "usage: make_user.php <email> <password of 10+ chars>\n");
    exit(1);
}

$existing = User::findByEmail($email);
if ($existing !== null) {
    User::updatePassword((int) $existing['id'], $password);
    echo "updated {$email} (id {$existing['id']})\n";
    exit(0);
}

$id = User::register(['email' => $email, 'password' => $password, 'nickname' => 'Probe User']);
echo "created {$email} (id {$id})\n";
