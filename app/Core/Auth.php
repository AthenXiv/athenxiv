<?php

declare(strict_types=1);

namespace Athenaeum\Core;

use Athenaeum\Models\LoginAttempt;
use Athenaeum\Models\User;

/**
 * Authentication: session based, with throttling and ban enforcement.
 */
final class Auth
{
    private static ?array $user = null;

    private static bool $resolved = false;

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user === null ? null : (int) $user['id'];
    }

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;

        $id = Session::get('user_id');
        if (!is_int($id) && !ctype_digit((string) $id)) {
            return self::$user = null;
        }
        $user = User::find((int) $id);
        if ($user === null) {
            Session::forget('user_id');
            return self::$user = null;
        }
        return self::$user = $user;
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        return $user !== null && ($user['role'] ?? '') === 'admin' && ($user['status'] ?? '') !== 'banned';
    }

    public static function isBanned(): bool
    {
        $user = self::user();
        return $user !== null && ($user['status'] ?? '') === 'banned';
    }

    public static function login(array $user, bool $remember = true): void
    {
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        self::$user = $user;
        self::$resolved = true;
        User::touchLogin((int) $user['id'], App::request()->ip());
    }

    public static function logout(): void
    {
        self::$user = null;
        self::$resolved = true;
        Session::forget('user_id');
    }

    /**
     * @return array{ok:bool,error?:string,user?:array}
     */
    public static function attempt(string $email, string $password): array
    {
        $ip = App::request()->ip();
        $window = (int) Config::get('security.login_window', 900);
        $max = (int) Config::get('security.login_max_attempts', 8);

        if (LoginAttempt::tooMany($ip, $email, $max, $window)) {
            return ['ok' => false, 'error' => __('auth.too_many_attempts')];
        }

        $user = User::findByEmail($email);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            LoginAttempt::record($ip, $email, false);
            return ['ok' => false, 'error' => __('auth.invalid_credentials')];
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            User::updatePassword((int) $user['id'], $password);
        }

        if (($user['status'] ?? 'active') === 'banned') {
            LoginAttempt::record($ip, $email, false);
            return ['ok' => false, 'error' => __('auth.account_banned')];
        }

        LoginAttempt::record($ip, $email, true);
        LoginAttempt::clear($ip, $email);
        self::login($user);
        return ['ok' => true, 'user' => $user];
    }
}
