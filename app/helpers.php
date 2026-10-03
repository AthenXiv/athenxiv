<?php
/**
 * Global view/controller helpers. Kept deliberately small and side-effect free.
 */

declare(strict_types=1);

use Athenaeum\Core\Auth;
use Athenaeum\Core\Config;
use Athenaeum\Core\Csrf;
use Athenaeum\Core\I18n;
use Athenaeum\Core\Router;
use Athenaeum\Core\Session;
use Athenaeum\Core\Settings;
use Athenaeum\Core\Str;

if (!function_exists('e')) {
    /** Escape for HTML text/attribute context. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('__')) {
    /** Translate a key. */
    function __(string $key, array $replace = []): string
    {
        return I18n::trans($key, $replace);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Settings::get($key, $default);
    }
}

if (!function_exists('url')) {
    /** Named route → absolute URL, or a raw path when no route matches. */
    function url(string $name, array $params = []): string
    {
        $path = ($name === '' || $name[0] === '/') ? $name : Router::url($name, $params);
        return Config::baseUrl() . Config::withFrontController($path);
    }
}

if (!function_exists('public_path')) {
    /**
     * Absolute path of a file that ships in public/.
     *
     * Two layouts exist: the document root is public/ (the normal case), or the
     * document root is the project root and public/'s contents were copied up
     * (how athenxiv.com is deployed). Probing only the first one silently
     * disabled the PDF.js viewer in production.
     */
    function public_path(string $relative): ?string
    {
        foreach ([ATHENAEUM_ROOT . '/public', ATHENAEUM_ROOT] as $base) {
            $candidate = $base . '/' . ltrim($relative, '/');
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        return null;
    }
}

if (!function_exists('public_url')) {
    /** Public URL of a file in public/ (null when it is not deployed). */
    function public_url(string $relative): ?string
    {
        $relative = ltrim($relative, '/');
        return public_path($relative) === null ? null : Config::baseUrl() . '/' . $relative;
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = public_path($path);
        $version = $file !== null ? (string) filemtime($file) : ATHENAEUM_VERSION;
        return Config::baseUrl() . '/' . $path . '?v=' . $version;
    }
}

if (!function_exists('storage_url')) {
    /** Media route that streams a file out of storage/. */
    function storage_url(string $kind, string $filename): string
    {
        $path = '/media/' . rawurlencode($kind) . '/' . implode('/', array_map('rawurlencode', explode('/', $filename)));
        return Config::baseUrl() . Config::withFrontController($path);
    }
}

if (!function_exists('site_text')) {
    /**
     * Site copy that the operator may override from the admin panel.
     *
     * Returns the value stored under $key when it is set, otherwise the
     * translation of $fallback (default: the same key) for the visitor's
     * language. That keeps thirty translated defaults while still letting an
     * administrator rewrite the wording on the live site.
     *
     * @param array<string,string|int> $params
     */
    function site_text(string $key, array $params = [], ?string $fallback = null): string
    {
        $override = trim(Settings::string($key, ''));
        if ($override !== '') {
            return $override;
        }
        return __($fallback ?? $key, $params);
    }
}

if (!function_exists('path_url')) {
    /**
     * Absolute URL for an application path, front controller included.
     *
     * On a host that cannot rewrite (athenxiv.com), a bare "/papers" is a 404:
     * every generated link has to carry the front controller. Views and
     * partials that build URLs by hand must go through this.
     */
    function path_url(string $path): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }
        return Config::baseUrl() . Config::withFrontController($path);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return Session::old($key, $default);
    }
}

if (!function_exists('errors')) {
    /** @return array<string,string[]> */
    function errors(): array
    {
        return Session::errors();
    }
}

if (!function_exists('error_for')) {
    function error_for(string $field): string
    {
        $all = Session::errors();
        return is_array($all[$field] ?? null) ? (string) ($all[$field][0] ?? '') : '';
    }
}

if (!function_exists('has_error')) {
    function has_error(string $field): bool
    {
        return isset(Session::errors()[$field]);
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('current_path')) {
    function current_path(): string
    {
        return \Athenaeum\Core\App::request()->path();
    }
}

if (!function_exists('is_active')) {
    /** Marks nav items; matches the exact path or a prefix. */
    function is_active(string $path, bool $prefix = false): bool
    {
        $current = current_path();
        return $prefix ? str_starts_with($current, $path) : $current === $path;
    }
}

if (!function_exists('locale')) {
    function locale(): string
    {
        return I18n::locale();
    }
}

if (!function_exists('lang_switch_url')) {
    function lang_switch_url(string $locale): string
    {
        $params = $_GET;
        $params['lang'] = $locale;
        return current_path() . '?' . http_build_query($params);
    }
}

if (!function_exists('human_size')) {
    function human_size(?int $bytes): string
    {
        return Str::humanSize($bytes);
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $value, bool $withTime = false): string
    {
        return I18n::date($value, $withTime);
    }
}

if (!function_exists('time_ago')) {
    function time_ago(?string $value): string
    {
        return Str::timeAgo($value);
    }
}

if (!function_exists('excerpt')) {
    function excerpt(?string $value, int $length = 240): string
    {
        return Str::excerpt($value, $length);
    }
}

if (!function_exists('markdown')) {
    function markdown(string $source): string
    {
        return \Athenaeum\Core\Markdown::render($source);
    }
}

if (!function_exists('absolute_url')) {
    function absolute_url(string $path = '/'): string
    {
        return Config::baseUrl() . '/' . ltrim($path, '/');
    }
}
