<?php
/**
 * Create or promote an administrator.
 *
 *   php bin/create-admin.php --email=you@example.org --password=secret --nickname=You
 *   php bin/create-admin.php --uid=U7K4M2QF --role=editor
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$config = require $root . '/app/bootstrap.php';

use Athenaeum\Core\Config;
use Athenaeum\Models\AuditLog;
use Athenaeum\Models\User;

Config::load($config);

$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--([a-z0-9\-]+)(?:=(.*))?$/i', $argument, $matches)) {
        $options[$matches[1]] = $matches[2] ?? true;
    }
}

$email = (string) ($options['email'] ?? '');
$uid = (string) ($options['uid'] ?? '');
$password = (string) ($options['password'] ?? '');
$nickname = (string) ($options['nickname'] ?? 'Administrator');
$role = (string) ($options['role'] ?? 'admin');

if (!in_array($role, User::ROLES, true)) {
    fwrite(STDERR, "Unknown role '{$role}'. Known: " . implode(', ', User::ROLES) . "\n");
    exit(1);
}

if ($email === '' && $uid === '' && PHP_SAPI === 'cli' && stream_isatty(STDIN)) {
    $email = trim((string) readline('e-mail (empty to abort): '));
    if ($email !== '') {
        $password = (string) readline('password (min 10 chars): ');
        $nickname = trim((string) readline('nickname: ')) ?: $nickname;
    }
}

if ($email === '' && $uid === '') {
    fwrite(STDERR, "Nothing to do: pass --email=… or --uid=…\n");
    exit(1);
}

$user = $uid !== '' ? User::findByUid($uid) : User::findByEmail($email);
if ($user !== null) {
    User::update((int) $user['id'], ['role' => $role]);
    if ($password !== '') {
        if (mb_strlen($password) < 10) {
            fwrite(STDERR, "Password must be at least 10 characters.\n");
            exit(1);
        }
        User::updatePassword((int) $user['id'], $password);
    }
    AuditLog::record('user.role', 'user', (int) $user['id'], ['role' => $role, 'via' => 'cli']);
    echo "[ok] {$user['uid']} ({$user['email']}) is now '{$role}'\n";
    exit(0);
}

if ($password === '' || mb_strlen($password) < 10) {
    fwrite(STDERR, "Creating a new account needs --password=… (min 10 characters).\n");
    exit(1);
}

$id = User::createByAdmin([
    'email'        => $email,
    'password'     => $password,
    'nickname'     => $nickname,
    'display_name' => $nickname,
    'role'         => $role,
    'status'       => 'active',
]);
$created = User::find($id);
echo "[ok] created {$created['uid']} ({$created['email']}) as '{$role}'\n";
