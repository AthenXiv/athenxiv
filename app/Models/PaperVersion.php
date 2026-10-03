<?php

declare(strict_types=1);

namespace Athenaeum\Models;

use Athenaeum\Core\Config;
use Athenaeum\Core\Database;
use Athenaeum\Core\Model;
use Athenaeum\Core\Settings;

/**
 * One uploaded PDF revision of a paper.
 *
 * The newest revision is mirrored on `papers.pdf_*` so the reader, download and
 * timestamping paths never have to care about versions; every revision keeps its
 * own file *and its own OpenTimestamps proof*, which is the whole point of the
 * feature: a v2 upload must not overwrite the evidence for v1.
 */
final class PaperVersion extends Model
{
    protected static string $table = 'paper_versions';

    protected static array $booleans = ['size_exempt'];

    protected static bool $timestamps = false;

    /** @return array<int,array<string,mixed>> newest first */
    public static function forPaper(int $paperId): array
    {
        return self::all(['paper_id' => $paperId], 'version_no DESC');
    }

    public static function current(int $paperId): ?array
    {
        return Database::instance()->selectOne(
            'SELECT * FROM {{paper_versions}} WHERE paper_id = :id ORDER BY version_no DESC LIMIT 1',
            ['id' => $paperId]
        );
    }

    public static function findByNumber(int $paperId, int $versionNo): ?array
    {
        return Database::instance()->selectOne(
            'SELECT * FROM {{paper_versions}} WHERE paper_id = :pid AND version_no = :no LIMIT 1',
            ['pid' => $paperId, 'no' => $versionNo]
        );
    }

    public static function nextVersionNumber(int $paperId): int
    {
        $highest = (int) Database::instance()->scalar(
            'SELECT MAX(version_no) FROM {{paper_versions}} WHERE paper_id = :id',
            ['id' => $paperId]
        );
        return $highest + 1;
    }

    /**
     * Record a revision. Called with the freshly stored file data.
     *
     * @param array{path:string,original_name:string,size:int,sha256:string} $file
     */
    public static function record(
        int $paperId,
        array $file,
        ?string $note,
        ?int $uploadedBy,
        bool $sizeExempt = false
    ): int {
        $versionNo = self::nextVersionNumber($paperId);
        return self::create([
            'paper_id'    => $paperId,
            'version_no'  => $versionNo,
            'label'       => 'v' . $versionNo,
            'note'        => $note,
            'pdf_path'    => $file['path'],
            'pdf_name'    => $file['original_name'],
            'pdf_size'    => $file['size'],
            'pdf_sha256'  => $file['sha256'],
            'uploaded_by' => $uploadedBy,
            'size_exempt' => $sizeExempt ? 1 : 0,
            'created_at'  => Database::instance()->now(),
        ]);
    }

    /** Backfill a v1 row for papers uploaded before versioning existed. */
    public static function backfill(array $paper): void
    {
        if (empty($paper['pdf_path'])) {
            return;
        }
        $exists = (int) Database::instance()->scalar(
            'SELECT COUNT(*) FROM {{paper_versions}} WHERE paper_id = :id',
            ['id' => (int) $paper['id']]
        );
        if ($exists > 0) {
            return;
        }
        self::create([
            'paper_id'    => (int) $paper['id'],
            'version_no'  => 1,
            'label'       => 'v1',
            'note'        => null,
            'pdf_path'    => (string) $paper['pdf_path'],
            'pdf_name'    => (string) ($paper['pdf_name'] ?? ''),
            'pdf_size'    => (int) ($paper['pdf_size'] ?? 0),
            'pdf_sha256'  => (string) ($paper['pdf_sha256'] ?? ''),
            'uploaded_by' => (int) $paper['uploader_id'],
            'size_exempt' => (int) ($paper['size_exempt'] ?? 0),
            'created_at'  => (string) ($paper['submitted_at'] ?? $paper['created_at']),
        ]);
        Paper::update((int) $paper['id'], ['version_no' => 1]);
    }

    public static function diskPath(array $version): ?string
    {
        $relative = trim((string) ($version['pdf_path'] ?? ''));
        if ($relative === '') {
            return null;
        }
        $full = Config::path('uploads', $relative);
        return is_file($full) ? $full : null;
    }

    public static function uploader(array $version): ?array
    {
        return $version['uploaded_by'] ? User::find((int) $version['uploaded_by']) : null;
    }

    /** Prune old revisions when `versions.max` is exceeded (files are kept). */
    public static function prune(int $paperId, int $keep = 0): int
    {
        $keep = $keep > 0 ? $keep : Settings::int('versions.max', 30);
        $versions = self::forPaper($paperId);
        if (count($versions) <= $keep) {
            return 0;
        }
        $removed = 0;
        foreach (array_slice($versions, $keep) as $version) {
            if (Settings::bool('versions.keep_files')) {
                // Keep the proof-bearing file, drop only the index row? No:
                // losing the row would hide a proof, so we keep the row too and
                // simply do not prune. Documented behaviour.
                break;
            }
            $path = self::diskPath($version);
            if ($path !== null) {
                @unlink($path);
            }
            self::delete((int) $version['id']);
            $removed++;
        }
        return $removed;
    }

    /** Every timestamp proof belonging to this paper's revisions. */
    public static function timestampFor(array $version): ?array
    {
        return Database::instance()->selectOne(
            'SELECT * FROM {{timestamps}} WHERE paper_id = :pid AND file_sha256 = :hash ORDER BY id DESC LIMIT 1',
            ['pid' => (int) $version['paper_id'], 'hash' => (string) $version['pdf_sha256']]
        );
    }
}
