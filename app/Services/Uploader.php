<?php

declare(strict_types=1);

namespace Athenaeum\Services;

use Athenaeum\Core\Config;
use Athenaeum\Core\Logger;
use Athenaeum\Core\Settings;
use Athenaeum\Core\Str;

/**
 * Upload handling. Files are stored outside the web root under a
 * year/month folder with a random name; the original name is kept only as
 * metadata, which removes a whole class of path-injection problems.
 */
final class Uploader
{
    public const KIND_PDF        = 'pdf';
    public const KIND_ARCHIVE    = 'archive';
    public const KIND_IMAGE      = 'image';

    /** Magic bytes, used when the fileinfo extension is unavailable. */
    private const SIGNATURES = [
        'pdf'  => ["\x25\x50\x44\x46\x2D"],                     // %PDF-
        'zip'  => ["\x50\x4B\x03\x04", "\x50\x4B\x05\x06", "\x50\x4B\x07\x08"],
        'rar'  => ["\x52\x61\x72\x21\x1A\x07"],                 // Rar!
        '7z'   => ["\x37\x7A\xBC\xAF\x27\x1C"],
        'gz'   => ["\x1F\x8B"],
        'bz2'  => ["\x42\x5A\x68"],                             // BZh
        'xz'   => ["\xFD\x37\x7A\x58\x5A\x00"],
        'tar'  => [],                                           // detected by extension only
    ];

    private const IMAGE_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    /**
     * @param array<string,mixed> $file    $_FILES entry
     * @param array{kind:string,max_bytes:int,exempt?:bool,subdir?:string} $options
     * @return array{ok:bool,error?:string,data?:array{path:string,stored_name:string,original_name:string,size:int,mime:string,sha256:string,absolute:string}}
     */
    public static function store(array $file, array $options): array
    {
        $kind = (string) ($options['kind'] ?? self::KIND_ARCHIVE);
        $maxBytes = (int) ($options['max_bytes'] ?? 0);
        $exempt = !empty($options['exempt']);
        $subdir = (string) ($options['subdir'] ?? self::subdirFor($kind));

        $error = self::uploadError($file, $maxBytes, $exempt);
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }

        // Server-side importers (the OpenAlex backfill) fetch a file themselves
        // and hand over its path; it is not an HTTP upload, so is_uploaded_file()
        // is false for it. Only an in-process caller can set this flag — the
        // value never comes from the request, because PHP builds $_FILES itself.
        $trustedLocal = !empty($options['trusted_local']);

        $originalName = Str::filename((string) ($file['name'] ?? 'file'));
        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath)) {
            return ['ok' => false, 'error' => __('upload.error_no_file')];
        }
        if (!$trustedLocal && PHP_SAPI !== 'cli' && !is_uploaded_file($tmpPath)) {
            return ['ok' => false, 'error' => __('upload.error_invalid')];
        }

        $mime = self::detectMime($tmpPath);
        $extension = self::resolveExtension($kind, $originalName, $tmpPath, $mime);
        if ($extension === null) {
            return ['ok' => false, 'error' => self::typeError($kind)];
        }

        $size = (int) filesize($tmpPath);
        $sha256 = hash_file('sha256', $tmpPath) ?: '';
        if ($sha256 === '') {
            return ['ok' => false, 'error' => __('upload.error_read')];
        }

        $relativeDir = trim($subdir, '/') . '/' . gmdate('Y/m');
        $targetDir = Config::path('uploads', $relativeDir);
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            Logger::error('upload: cannot create directory ' . $targetDir);
            return ['ok' => false, 'error' => __('upload.error_storage')];
        }

        $storedName = gmdate('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $targetPath = $targetDir . '/' . $storedName;

        if (!@move_uploaded_file($tmpPath, $targetPath) && !@rename($tmpPath, $targetPath)) {
            Logger::error('upload: cannot move file to ' . $targetPath);
            return ['ok' => false, 'error' => __('upload.error_storage')];
        }
        @chmod($targetPath, 0644);

        return [
            'ok'   => true,
            'data' => [
                'path'          => $relativeDir . '/' . $storedName,
                'stored_name'   => $storedName,
                'original_name' => $originalName,
                'size'          => $size,
                'mime'          => $mime,
                'sha256'        => $sha256,
                'absolute'      => $targetPath,
            ],
        ];
    }

    private static function subdirFor(string $kind): string
    {
        return match ($kind) {
            self::KIND_PDF   => 'papers',
            self::KIND_IMAGE => 'avatars',
            default          => 'attachments',
        };
    }

    /** @param array<string,mixed> $file */
    public static function uploadError(array $file, int $maxBytes, bool $exempt = false): ?string
    {
        $code = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        switch ($code) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return __('upload.error_php_limit', [
                    'limit' => human_size(self::phpUploadLimit()),
                ]);
            case UPLOAD_ERR_PARTIAL:
                return __('upload.error_partial');
            case UPLOAD_ERR_NO_FILE:
                return __('upload.error_no_file');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('upload.error_no_tmp');
            case UPLOAD_ERR_CANT_WRITE:
                return __('upload.error_cant_write');
            case UPLOAD_ERR_EXTENSION:
                return __('upload.error_extension');
            default:
                return __('upload.error_unknown');
        }

        $size = (int) ($file['size'] ?? 0);
        if (!$exempt && $maxBytes > 0 && $size > $maxBytes) {
            return __('upload.error_too_large', [
                'limit'   => human_size($maxBytes),
                'contact' => (string) Settings::get('site.contact_email', ''),
            ]);
        }
        return null;
    }

    /** The lowest of PHP's own limits (upload_max_filesize / post_max_size). */
    public static function phpUploadLimit(): int
    {
        $toBytes = static function (string $value): int {
            $value = trim($value);
            if ($value === '') {
                return 0;
            }
            $unit = strtolower(substr($value, -1));
            $number = (int) $value;
            return match ($unit) {
                'g' => $number * 1024 * 1024 * 1024,
                'm' => $number * 1024 * 1024,
                'k' => $number * 1024,
                default => $number,
            };
        };
        $limits = array_filter([
            $toBytes((string) ini_get('upload_max_filesize')),
            $toBytes((string) ini_get('post_max_size')),
        ]);
        return $limits === [] ? 0 : min($limits);
    }

    public static function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = @finfo_file($finfo, $path);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }
        return 'application/octet-stream';
    }

    /** Sniff a file against the signature table. */
    public static function matchesSignature(string $path, string $type): bool
    {
        $signatures = self::SIGNATURES[$type] ?? [];
        if ($signatures === []) {
            return true; // tar & friends: extension check only
        }
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $head = (string) fread($handle, 16);
        fclose($handle);
        foreach ($signatures as $signature) {
            if (str_starts_with($head, $signature)) {
                return true;
            }
        }
        return false;
    }

    /** Decide the final extension, or null when the type is unacceptable. */
    private static function resolveExtension(string $kind, string $originalName, string $tmpPath, string $mime): ?string
    {
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        if ($kind === self::KIND_PDF) {
            $looksPdf = $mime === 'application/pdf'
                || $extension === 'pdf'
                || self::matchesSignature($tmpPath, 'pdf');
            $isReallyPdf = self::matchesSignature($tmpPath, 'pdf') || $mime === 'application/pdf';
            return $looksPdf && $isReallyPdf ? 'pdf' : null;
        }

        if ($kind === self::KIND_IMAGE) {
            $info = @getimagesize($tmpPath);
            $detected = is_array($info) ? (string) ($info['mime'] ?? '') : $mime;
            if (isset(self::IMAGE_MIMES[$detected])) {
                return self::IMAGE_MIMES[$detected];
            }
            // SVG is text based and getimagesize() cannot see it.
            if ($extension === 'svg' && (str_contains($mime, 'svg') || self::looksLikeSvg($tmpPath))) {
                return 'svg';
            }
            return null;
        }

        // Archives: extension must be whitelisted AND the signature must match
        // the family the extension claims (blocks "photo.jpg" style tricks).
        if (!\Athenaeum\Models\Attachment::extensionAllowed($originalName)) {
            return null;
        }
        $family = self::familyForExtension($extension);
        if ($family !== null && !self::matchesSignature($tmpPath, $family)) {
            return null;
        }
        return $extension !== '' ? $extension : 'zip';
    }

    private static function familyForExtension(string $extension): ?string
    {
        $map = [
            'zip'  => 'zip',
            'rar'  => 'rar',
            '7z'   => '7z',
            'gz'   => 'gz',
            'tgz'  => 'gz',
            'bz2'  => 'bz2',
            'xz'   => 'xz',
            'tar'  => 'tar',
        ];
        return $map[$extension] ?? null;
    }

    private static function looksLikeSvg(string $path): bool
    {
        $head = (string) @file_get_contents($path, false, null, 0, 512);
        return stripos($head, '<svg') !== false;
    }

    private static function typeError(string $kind): string
    {
        return match ($kind) {
            self::KIND_PDF   => __('upload.error_pdf_type'),
            self::KIND_IMAGE => __('upload.error_image_type'),
            default          => __('upload.error_archive_type', [
                'extensions' => implode(', ', \Athenaeum\Models\Attachment::allowedExtensions()),
            ]),
        };
    }

    /**
     * Optional downscale for images when GD is present. Keeps avatars small
     * without hard-failing on hosts without GD.
     */
    public static function shrinkImage(string $path, int $maxDimension = 512): void
    {
        if (!extension_loaded('gd')) {
            return;
        }
        $info = @getimagesize($path);
        if (!is_array($info)) {
            return;
        }
        [$width, $height] = $info;
        $mime = (string) ($info['mime'] ?? '');
        if ($width <= $maxDimension && $height <= $maxDimension) {
            return;
        }
        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/gif'  => @imagecreatefromgif($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default      => false,
        };
        if ($source === false) {
            return;
        }
        $ratio = min($maxDimension / max(1, $width), $maxDimension / max(1, $height));
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));
        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        if ($canvas === false) {
            imagedestroy($source);
            return;
        }
        if ($mime === 'image/png' || $mime === 'image/gif' || $mime === 'image/webp') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);
        }
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        match ($mime) {
            'image/jpeg' => imagejpeg($canvas, $path, 88),
            'image/png'  => imagepng($canvas, $path, 8),
            'image/gif'  => imagegif($canvas, $path),
            'image/webp' => function_exists('imagewebp') ? imagewebp($canvas, $path, 88) : null,
            default      => null,
        };
        imagedestroy($canvas);
        imagedestroy($source);
    }
}
