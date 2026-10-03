<?php

declare(strict_types=1);

namespace Athenaeum\Models;

use Athenaeum\Core\Config;
use Athenaeum\Core\Model;

/**
 * Supplementary files (archives only by default: .zip/.7z/.tar.gz …).
 */
final class Attachment extends Model
{
    protected static string $table = 'attachments';

    protected static array $booleans = ['size_exempt'];

    protected static bool $timestamps = false;

    /** @return array<int,array<string,mixed>> */
    public static function forPaper(int $paperId): array
    {
        return self::all(['paper_id' => $paperId], 'id ASC');
    }

    public static function diskPath(array $attachment): ?string
    {
        $relative = trim((string) ($attachment['path'] ?? ''));
        if ($relative === '') {
            return null;
        }
        // Relative to storage/uploads; includes the `attachments/YYYY/MM` prefix.
        $full = Config::path('uploads', $relative);
        return is_file($full) ? $full : null;
    }

    public static function incrementDownloads(int $id): void
    {
        \Athenaeum\Core\Database::instance()->query(
            'UPDATE {{attachments}} SET downloads = downloads + 1 WHERE id = :id',
            ['id' => $id]
        );
    }

    /** @return string[] */
    public static function allowedExtensions(): array
    {
        $raw = (string) \Athenaeum\Core\Settings::get('upload.allowed_attachment_ext', 'zip');
        return array_values(array_filter(array_map(
            static fn (string $ext): string => strtolower(trim($ext, ". \t")),
            explode(',', $raw)
        )));
    }

    public static function extensionAllowed(string $filename): bool
    {
        $lower = strtolower($filename);
        foreach (self::allowedExtensions() as $extension) {
            if ($extension !== '' && str_ends_with($lower, '.' . $extension)) {
                return true;
            }
        }
        return false;
    }
}
