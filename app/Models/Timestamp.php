<?php

declare(strict_types=1);

namespace Athenaeum\Models;

use Athenaeum\Core\Database;
use Athenaeum\Core\Model;

/**
 * OpenTimestamps proof rows: one per stamped file, regardless of whether the
 * paper was later approved — the proof is about the file, not the review.
 */
final class Timestamp extends Model
{
    protected static string $table = 'timestamps';

    protected static array $booleans = [];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_FAILED    = 'failed';

    /** target_type used when the proof covers a content page, not a paper. */
    public const TARGET_PAGE = 'page';

    public static function forPaper(int $paperId): array
    {
        return self::all(['paper_id' => $paperId], 'id ASC');
    }

    /** Every proof ever created for a content page, oldest first. */
    public static function forPage(int $pageId): array
    {
        return self::all(['page_id' => $pageId, 'target_type' => self::TARGET_PAGE], 'id ASC');
    }

    /** The proof already covering this exact page content, if any. */
    public static function findForPageContent(int $pageId, string $sha256): ?array
    {
        return Database::instance()->selectOne(
            'SELECT * FROM {{timestamps}} WHERE page_id = :pid AND target_type = :target AND file_sha256 = :hash ORDER BY id ASC LIMIT 1',
            ['pid' => $pageId, 'target' => self::TARGET_PAGE, 'hash' => $sha256]
        );
    }

    public static function findForFile(string $sha256, int $paperId, string $target = 'pdf'): ?array
    {
        return Database::instance()->selectOne(
            'SELECT * FROM {{timestamps}} WHERE paper_id = :pid AND file_sha256 = :hash AND target_type = :target ORDER BY id DESC LIMIT 1',
            ['pid' => $paperId, 'hash' => $sha256, 'target' => $target]
        );
    }

    /** Proofs that still need a calendar upgrade (Bitcoin attestation). */
    public static function pendingForUpgrade(int $limit = 25, int $minAgeSeconds = 3600): array
    {
        $db = Database::instance();
        $threshold = gmdate('Y-m-d H:i:s', time() - $minAgeSeconds);
        return $db->select(
            "SELECT * FROM {{timestamps}} WHERE status = :status AND attempts < 200"
            . ' AND (last_attempt_at IS NULL OR last_attempt_at <= :threshold)'
            . ' ORDER BY id ASC LIMIT ' . (int) $limit,
            ['status' => self::STATUS_PENDING, 'threshold' => $threshold]
        );
    }

    public static function statusLabel(string $status): string
    {
        return __('ots.status_' . $status);
    }

    /**
     * One- or two-word status for tables and chips.
     *
     * The full sentence ("Submitted to the calendars, awaiting Bitcoin
     * confirmation") is right on the paper page but stretches the little round
     * badge in the dashboard lists out of shape.
     */
    public static function shortStatusLabel(string $status): string
    {
        $key = 'ots.short_' . $status;
        $short = __($key);
        return $short === $key ? self::statusLabel($status) : $short;
    }

    /** Public verification URL for the .ots file of this proof. */
    public static function verifyUrl(array $timestamp): string
    {
        $base = (string) \Athenaeum\Core\Settings::get('ots.verify_url', 'https://opentimestamps.org/');
        return $base !== '' ? $base : 'https://opentimestamps.org/';
    }

    public static function proofDownloadUrl(array $timestamp, string $paperUid): string
    {
        return url('paper.timestamp.download', ['uid' => $paperUid, 'timestamp' => $timestamp['id']]);
    }

    /** Public download URL for a page proof (no paper, so no uid). */
    public static function pageProofUrl(array $timestamp): string
    {
        return url('page.timestamp.proof', ['timestamp' => (int) $timestamp['id']]);
    }

    /** Public download URL for the exact text a page proof commits to. */
    public static function pageSnapshotUrl(array $timestamp): string
    {
        return url('page.timestamp.snapshot', ['timestamp' => (int) $timestamp['id']]);
    }

    public static function shortHash(string $hash): string
    {
        return substr($hash, 0, 16) . '…' . substr($hash, -8);
    }
}
