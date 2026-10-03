<?php

declare(strict_types=1);

namespace Athenaeum\Core;

/**
 * Dot-notation access to the configuration array, with environment overrides.
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $items = [];

    private static bool $loaded = false;

    public static function load(array $items): void
    {
        self::$items = $items;
        self::$loaded = true;
        self::applyEnvironment();
    }

    /** Environment variables win over the config file (handy for containers). */
    private static function applyEnvironment(): void
    {
        $map = [
            'ATHENAEUM_APP_URL'      => 'app.url',
            'ATHENAEUM_APP_ENV'      => 'app.env',
            'ATHENAEUM_APP_DEBUG'    => 'app.debug',
            'ATHENAEUM_APP_KEY'      => 'app.key',
            'ATHENAEUM_TIMEZONE'     => 'app.timezone',
            'ATHENAEUM_DB_DRIVER'    => 'db.driver',
            'ATHENAEUM_DB_HOST'      => 'db.host',
            'ATHENAEUM_DB_PORT'      => 'db.port',
            'ATHENAEUM_DB_DATABASE'  => 'db.database',
            'ATHENAEUM_DB_USERNAME'  => 'db.username',
            'ATHENAEUM_DB_PASSWORD'  => 'db.password',
            'ATHENAEUM_DB_PREFIX'    => 'db.prefix',
            'ATHENAEUM_SQLITE_PATH'  => 'db.sqlite_path',
            'ATHENAEUM_CA_BUNDLE'    => 'http.ca_bundle',
            'ATHENAEUM_FORCE_HTTPS'  => 'security.force_https',
        ];
        foreach ($map as $env => $key) {
            $value = getenv($env);
            if ($value === false || $value === '') {
                continue;
            }
            if (in_array($value, ['true', 'false'], true)) {
                $value = $value === 'true';
            } elseif (is_numeric($value)) {
                $value += 0;
            }
            self::set($key, $value);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $cursor = self::$items;
        foreach ($segments as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return $default;
            }
            $cursor = $cursor[$segment];
        }
        return $cursor;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $cursor = &self::$items;
        foreach ($segments as $segment) {
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor = &$cursor[$segment];
        }
        $cursor = $value;
    }

    public static function all(): array
    {
        return self::$items;
    }

    public static function isDebug(): bool
    {
        return (bool) self::get('app.debug', false);
    }

    /** Absolute path helper: Config::path('uploads') . '/papers/x.pdf' */
    public static function path(string $key, string $append = ''): string
    {
        $base = (string) self::get('paths.' . $key, '');
        return $append === '' ? $base : rtrim($base, '/\\') . '/' . ltrim($append, '/\\');
    }

    /** Public base URL without trailing slash, auto-detected when unset. */
    public static function baseUrl(): string
    {
        $configured = trim((string) self::get('app.url', ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        return ($https ? 'https://' : 'http://') . $host;
    }

    /**
     * Front-controller segment used in generated URLs, without slashes.
     *
     * Normally empty: with a proper rewrite (Apache mod_rewrite, nginx
     * `try_files … /index.php?$query_string`) the application sees clean paths
     * like /paper/ATH-XXXX. Hosts that cannot rewrite — this project is deployed
     * on one — fall back to PATH_INFO, where every URL carries the front
     * controller (e.g. /index.php/paper/ATH-XXXX). Set
     * `app.front_controller` to make the generator emit that form; the router
     * strips it again on the way in.
     */
    public static function frontController(): string
    {
        return trim((string) self::get('app.front_controller', ''), '/');
    }

    /** Prefix a path with the front controller when one is configured. */
    public static function withFrontController(string $path): string
    {
        $segment = self::frontController();
        $path = '/' . ltrim($path, '/');
        if ($segment === '') {
            return $path;
        }
        // The site root becomes /index.php (no trailing slash).
        if ($path === '/') {
            return '/' . $segment;
        }
        // Never double-prefix an already-prefixed path.
        if ($path === '/' . $segment || str_starts_with($path, '/' . $segment . '/')) {
            return $path;
        }
        return '/' . $segment . $path;
    }
}
