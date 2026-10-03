<?php

declare(strict_types=1);

namespace Athenaeum\Core;

/**
 * Tiny cURL wrapper used for the OpenTimestamps calendars and outbound checks.
 */
final class Http
{
    /** @var string|null */
    private static ?string $caBundle = null;

    /**
     * Resolve a CA bundle. Shared hosts usually have one; Windows dev boxes
     * often do not, which is why we probe a few well known locations instead
     * of silently disabling verification.
     */
    public static function caBundle(): ?string
    {
        if (self::$caBundle !== null) {
            return self::$caBundle === '' ? null : self::$caBundle;
        }
        $candidates = [];
        $configured = (string) Config::get('http.ca_bundle', '');
        if ($configured !== '') {
            $candidates[] = $configured;
        }
        foreach (['openssl.cafile', 'curl.cainfo'] as $ini) {
            $value = (string) ini_get($ini);
            if ($value !== '') {
                $candidates[] = $value;
            }
        }
        // The bundle we ship. Shared hosting frequently has neither a system
        // trust store readable from inside open_basedir nor a configured
        // curl.cainfo, and cURL then fails with
        // "error setting certificate verify locations: CAfile: CApath: none".
        $candidates[] = ATHENAEUM_ROOT . '/resources/certs/cacert.pem';
        $candidates = array_merge($candidates, [
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/etc/ssl/cert.pem',
            '/usr/local/etc/openssl/cert.pem',
            'C:\\Program Files\\Git\\mingw64\\etc\\ssl\\certs\\ca-bundle.crt',
            'C:\\Program Files\\Git\\usr\\ssl\\certs\\ca-bundle.crt',
        ]);
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && @is_file($candidate) && @is_readable($candidate)) {
                return self::$caBundle = $candidate;
            }
        }
        return self::$caBundle = '';
    }

    public static function tlsAvailable(): bool
    {
        return self::caBundle() !== null;
    }

    /**
     * @param array<string,string> $headers
     * @param array<string,mixed>  $options timeout, connect_timeout, user_agent, follow
     * @return array{ok:bool,status:int,body:string,headers:array<string,string>,error:?string}
     */
    public static function request(string $method, string $url, ?string $body = null, array $headers = [], array $options = []): array
    {
        if (!extension_loaded('curl')) {
            return self::failure('The cURL extension is not available on this server.');
        }

        $ch = curl_init($url);
        $responseHeaders = [];
        $headerLines = [];

        $timeout = (int) ($options['timeout'] ?? Config::get('http.timeout', 20));
        $connectTimeout = (int) ($options['connect_timeout'] ?? Config::get('http.connect_timeout', 8));
        $userAgent = (string) ($options['user_agent'] ?? Config::get('http.user_agent', 'AthenXiv'));

        $headerList = [];
        foreach ($headers as $name => $value) {
            $headerList[] = $name . ': ' . $value;
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_FOLLOWLOCATION => (bool) ($options['follow'] ?? true),
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_USERAGENT      => $userAgent,
            CURLOPT_HTTPHEADER     => $headerList,
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders, &$headerLines): int {
                $length = strlen($line);
                $trimmed = trim($line);
                if ($trimmed === '') {
                    return $length;
                }
                if (str_starts_with($trimmed, 'HTTP/')) {
                    $responseHeaders = [];
                    return $length;
                }
                $parts = explode(':', $trimmed, 2);
                if (count($parts) === 2) {
                    $key = strtolower(trim($parts[0]));
                    $responseHeaders[$key] = trim($parts[1]);
                }
                return $length;
            },
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $verify = (bool) Config::get('http.verify_tls', true);
        if ($verify) {
            $ca = self::caBundle();
            if ($ca === null) {
                curl_close($ch);
                return self::failure('No CA certificate bundle found; TLS verification is impossible.');
            }
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_CAINFO, $ca);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $result = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch) ?: null;
        curl_close($ch);

        if ($result === false) {
            return self::failure($error ?? 'Unknown transport error', $status);
        }

        return [
            'ok'      => $status >= 200 && $status < 400,
            'status'  => $status,
            'body'    => (string) $result,
            'headers' => $responseHeaders,
            'error'   => $status >= 400 ? 'HTTP ' . $status : null,
        ];
    }

    /** @return array{ok:bool,status:int,body:string,headers:array<string,string>,error:?string} */
    public static function get(string $url, array $headers = [], array $options = []): array
    {
        return self::request('GET', $url, null, $headers, $options);
    }

    public static function post(string $url, ?string $body = null, array $headers = [], array $options = []): array
    {
        return self::request('POST', $url, $body, $headers, $options);
    }

    private static function failure(string $error, int $status = 0): array
    {
        return ['ok' => false, 'status' => $status, 'body' => '', 'headers' => [], 'error' => $error];
    }
}
