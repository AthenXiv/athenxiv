<?php

declare(strict_types=1);

namespace Athenaeum\Core;

use RuntimeException;

/**
 * Incoming HTTP request. Everything is read-only and lazily normalised.
 */
final class Request
{
    private string $method;

    private string $path;

    private ?array $jsonCache = null;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->path = self::normalisePath($_SERVER['REQUEST_URI'] ?? '/');
    }

    public static function normalisePath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rawurldecode($path);

        // PATH_INFO deployments carry the front controller in the URI
        // (/index.php/paper/ATH-XXXX). The router only ever sees the
        // application path, so drop that segment — and never let it be used to
        // shadow a real route.
        $segment = \Athenaeum\Core\Config::frontController();
        if ($segment !== '') {
            $prefix = '/' . $segment;
            if ($path === $prefix) {
                $path = '/';
            } elseif (str_starts_with($path, $prefix . '/')) {
                $path = substr($path, strlen($prefix));
            }
        }

        $path = '/' . ltrim($path, '/');
        if ($path !== '/' ) {
            $path = rtrim($path, '/');
        }
        return $path === '' ? '/' : $path;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
    }

    /** Raw input value from POST, then GET. */
    public function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    public function str(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        if (is_array($value)) {
            return $default;
        }
        return trim((string) $value);
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key): bool
    {
        $value = $this->input($key);
        return in_array($value, ['1', 1, true, 'true', 'on', 'yes'], true);
    }

    /**
     * Read a field whose name contains a dot — `site.name`, `upload.max_pdf_mb`.
     *
     * PHP rewrites dots in POST/GET keys to underscores, so a form field named
     * `site.name` arrives as `site_name`. We accept both spellings: the literal
     * one first (when the field is built programmatically), then the mangled one.
     */
    private function dottedLookup(string $key): mixed
    {
        $direct = $this->input($key, null);
        if ($direct !== null && $direct !== '') {
            return $direct;
        }
        if (str_contains($key, '.')) {
            $mangled = $this->input(str_replace('.', '_', $key), null);
            if ($mangled !== null) {
                return $mangled;
            }
        }
        return $direct;
    }

    public function dotted(string $key, string $default = ''): string
    {
        $value = $this->dottedLookup($key);
        if (is_array($value) || $value === null) {
            return $default;
        }
        return trim((string) $value);
    }

    public function dottedInt(string $key, int $default = 0): int
    {
        $value = $this->dottedLookup($key);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function dottedBool(string $key): bool
    {
        $value = $this->dottedLookup($key);
        return in_array($value, ['1', 1, true, 'true', 'on', 'yes'], true);
    }

    public function array(string $key): array
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? [];
        return is_array($value) ? $value : [];
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    /** @return array<string,mixed> */
    public function json(): array
    {
        if ($this->jsonCache !== null) {
            return $this->jsonCache;
        }
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        return $this->jsonCache = (is_array($decoded) ? $decoded : []);
    }

    /** @return array<string,mixed>|null */
    public function file(string $key): ?array
    {
        $file = $_FILES[$key] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $file;
    }

    public function header(string $name, string $default = ''): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$key])) {
            return (string) $_SERVER[$key];
        }
        if (strcasecmp($name, 'Content-Type') === 0) {
            return (string) ($_SERVER['CONTENT_TYPE'] ?? $default);
        }
        return $default;
    }

    public function ip(): string
    {
        $trusted = (array) Config::get('app.trusted_proxies', []);
        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if ($trusted !== [] && in_array($remote, $trusted, true)) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if ($forwarded !== '') {
                $first = trim(explode(',', $forwarded)[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    return $first;
                }
            }
        }
        return $remote;
    }

    public function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    /** Best-effort browser locale list, most preferred first: ['zh-CN','zh','en']. */
    public function acceptedLanguages(): array
    {
        $header = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        if ($header === '') {
            return [];
        }
        $entries = [];
        foreach (explode(',', $header) as $chunk) {
            $parts = explode(';', trim($chunk));
            $tag = trim($parts[0]);
            if ($tag === '') {
                continue;
            }
            $quality = 1.0;
            foreach (array_slice($parts, 1) as $param) {
                $param = trim($param);
                if (str_starts_with($param, 'q=')) {
                    $quality = (float) substr($param, 2);
                }
            }
            $entries[] = ['tag' => $tag, 'q' => $quality];
        }
        usort($entries, static fn (array $a, array $b): int => $b['q'] <=> $a['q']);
        return array_values(array_unique(array_map(static fn (array $e): string => $e['tag'], $entries)));
    }

    public function isAjax(): bool
    {
        return strtolower($this->header('X-Requested-With')) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        return str_contains(strtolower($this->header('Accept')), 'application/json') || $this->isAjax();
    }

    public function referer(string $fallback = '/'): string
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($referer === '') {
            return $fallback;
        }
        $parts = parse_url($referer);
        if (!is_array($parts) || !isset($parts['host'])) {
            return $fallback;
        }
        $host = $parts['host'];
        $selfHost = parse_url(Config::baseUrl(), PHP_URL_HOST);
        if ($selfHost !== null && strcasecmp($host, (string) $selfHost) !== 0) {
            return $fallback;
        }
        return self::normalisePath($referer);
    }
}
