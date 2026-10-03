<?php

declare(strict_types=1);

namespace Athenaeum\Core;

/**
 * Outgoing HTTP response, including HTTP range streaming for PDF previews.
 */
final class Response
{
    private int $status = 200;

    /** @var array<string,string> */
    private array $headers = [];

    private string $body = '';

    public function __construct(string $body = '', int $status = 200, array $headers = [])
    {
        $this->body = $body;
        $this->status = $status;
        $this->headers = $headers;
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    public static function text(string $text, int $status = 200): self
    {
        return new self($text, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        // Every redirect goes through here, so this is the one place that has to
        // remember the front controller: on a host without URL rewriting a bare
        // "/papers" is a 404. Absolute URLs are left alone.
        if ($url !== '' && preg_match('#^https?://#i', $url) !== 1) {
            $url = Config::withFrontController($url);
        }
        return new self('', $status, ['Location' => $url]);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
            // Baseline hardening on every response. (The shipped .htaccess sets
            // the same headers for Apache, but nginx ignores those files, so the
            // application has to send them itself.)
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('X-Frame-Options: SAMEORIGIN');
            header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            return; // headers only, as the protocol requires
        }
        echo $this->body;
    }

    /**
     * Stream a file from disk with `Accept-Ranges` support. Used for PDF
     * previews and attachment downloads, so the storage directory can stay
     * outside the web root on shared hosting.
     */
    public static function file(
        string $path,
        string $downloadName = '',
        string $contentType = 'application/octet-stream',
        bool $inline = false
    ): self {
        if (!is_file($path) || !is_readable($path)) {
            return new self('File not found', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $size = filesize($path) ?: 0;
        $mtime = filemtime($path) ?: time();
        $etag = '"' . md5($path . '|' . $size . '|' . $mtime) . '"';

        $disposition = $inline ? 'inline' : 'attachment';
        $filename = $downloadName !== '' ? $downloadName : basename($path);
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $filename) ?? 'file';
        $ascii = str_replace(['"', '\\'], '_', $ascii);
        $dispositionHeader = sprintf(
            '%s; filename="%s"; filename*=UTF-8\'\'%s',
            $disposition,
            $ascii,
            rawurlencode($filename)
        );

        $headers = [
            'Content-Type'        => $contentType,
            'Content-Disposition' => $dispositionHeader,
            'Accept-Ranges'       => 'bytes',
            'ETag'                => $etag,
            'Last-Modified'       => gmdate('D, d M Y H:i:s', $mtime) . ' GMT',
            'Cache-Control'       => $inline ? 'private, max-age=300' : 'private, no-store',
        ];

        // Conditional request → 304, keeps PDF.js snappy.
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if ($ifNoneMatch !== '' && trim($ifNoneMatch) === $etag) {
            return new self('', 304, $headers);
        }

        $start = 0;
        $end = $size > 0 ? $size - 1 : 0;
        $range = $_SERVER['HTTP_RANGE'] ?? '';

        if ($range !== '' && preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
            if ($m[1] === '' && $m[2] !== '') {
                // suffix range: last N bytes
                $length = (int) $m[2];
                $start = max(0, $size - $length);
                $end = $size - 1;
            } else {
                $start = (int) $m[1];
                $end = $m[2] !== '' ? (int) $m[2] : $size - 1;
            }
            if ($start > $end || $start >= $size) {
                return new self('', 416, $headers + [
                    'Content-Range' => 'bytes */' . $size,
                ]);
            }
            $end = min($end, $size - 1);
            $headers['Content-Range'] = sprintf('bytes %d-%d/%d', $start, $end, $size);
            $headers['Content-Length'] = (string) ($end - $start + 1);
            $status = 206;
        } else {
            $headers['Content-Length'] = (string) $size;
            $status = 200;
        }

        return (new self('', $status, $headers))->withStream($path, $start, $end);
    }

    private ?string $streamPath = null;

    private int $streamStart = 0;

    private int $streamEnd = 0;

    /** True when this response must be written with sendFile(). */
    public function isFileResponse(): bool
    {
        return $this->streamPath !== null;
    }

    private function withStream(string $path, int $start, int $end): self
    {
        $this->streamPath = $path;
        $this->streamStart = $start;
        $this->streamEnd = $end;
        return $this;
    }

    /** Sends headers, then streams (honours a HEAD request). */
    public function sendFile(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('X-Frame-Options: SAMEORIGIN');
        }

        if ($this->streamPath === null || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            return;
        }

        // Discard any output buffering so the byte offsets stay correct.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $handle = fopen($this->streamPath, 'rb');
        if ($handle === false) {
            return;
        }
        fseek($handle, $this->streamStart);
        $remaining = $this->streamEnd - $this->streamStart + 1;
        $chunkSize = 256 * 1024;
        while ($remaining > 0 && !feof($handle)) {
            $chunk = fread($handle, (int) min($chunkSize, $remaining));
            if ($chunk === false || $chunk === '') {
                break;
            }
            echo $chunk;
            $remaining -= strlen($chunk);
            flush();
        }
        fclose($handle);
    }
}
