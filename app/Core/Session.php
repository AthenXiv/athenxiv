<?php

declare(strict_types=1);

namespace Athenaeum\Core;

/**
 * Session handling with one-request "flash" bags.
 *
 * Flash data written during a request is visible to the *next* request, which
 * is what redirect-back-with-errors needs. Reads never consume the bag, so a
 * layout, a partial and the view can all inspect the same errors.
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started) {
            return;
        }
        if (PHP_SAPI === 'cli') {
            self::$started = true;
            self::bootFlash();
            return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $lifetime = (int) Config::get('security.session_lifetime', 1209600);
            $request = new Request();
            session_name((string) Config::get('security.session_name', 'athenaeum_session'));
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $request->isSecure(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
        self::$started = true;
        self::bootFlash();
    }

    /** Promote the pending flash bag to the active one. */
    private static function bootFlash(): void
    {
        if (PHP_SAPI === 'cli' && !isset($_SESSION)) {
            $_SESSION = [];
        }
        $_SESSION['_flash'] = is_array($_SESSION['_flash_next'] ?? null) ? $_SESSION['_flash_next'] : [];
        unset($_SESSION['_flash_next']);
        $_SESSION['_old'] = is_array($_SESSION['_old_next'] ?? null) ? $_SESSION['_old_next'] : [];
        unset($_SESSION['_old_next']);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $value;
    }

    /** Flash for the next request. */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_next'][$key] = $value;
    }

    /** Read a flash value from the current request's bag (non-destructive). */
    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        return $value;
    }

    /** @return array<string,mixed> */
    public static function allFlash(): array
    {
        return is_array($_SESSION['_flash'] ?? null) ? $_SESSION['_flash'] : [];
    }

    /** Keep submitted input for the next request so forms can re-render. */
    public static function flashInput(array $input, array $except = ['password', 'password_confirmation', '_token']): void
    {
        foreach ($except as $key) {
            unset($input[$key]);
        }
        $_SESSION['_old_next'] = $input;
    }

    public static function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_old'][$key] ?? $default;
    }

    public static function clearOld(): void
    {
        unset($_SESSION['_old'], $_SESSION['_old_next']);
    }

    /** @return array<string,string[]> */
    public static function errors(): array
    {
        $errors = $_SESSION['_flash']['errors'] ?? [];
        return is_array($errors) ? $errors : [];
    }

    public static function regenerate(): void
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $cookieName = session_name() ?: 'athenaeum_session';
        $_SESSION = [];
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie($cookieName, '', [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => 'Lax',
                ]);
            }
            session_destroy();
        }
        self::$started = false;
    }
}
