<?php

declare(strict_types=1);

namespace Athenaeum\Models;

use Athenaeum\Core\Config;
use Athenaeum\Core\Database;
use Athenaeum\Core\Model;
use Athenaeum\Core\Str;

/**
 * A submitted paper: metadata, review state, files and provenance.
 */
final class Paper extends Model
{
    protected static string $table = 'papers';

    protected static array $booleans = ['size_exempt', 'is_featured'];

    /** Pending review, published, or one of the terminal/withdrawn states. */
    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_TAKEDOWN  = 'taken_down';
    public const STATUS_DRAFT     = 'draft';

    /**
     * Where a paper came from. Submissions are what the site is for; archived
     * works are public-domain / openly licensed papers imported in bulk to give
     * the archive substance (an OpenAlex backfill). The difference is visible:
     * an archived work carries a rights marker next to its title, shows its
     * original publication date and copyright status, and has neither a version
     * history nor a timestamp history.
     */
    public const ORIGIN_SUBMISSION = 'submission';
    public const ORIGIN_ARCHIVE    = 'archive';

    /** Licences that place a work in the public domain. */
    public const PUBLIC_DOMAIN_LICENCES = ['publicdomain', 'cc0', 'cc-zero', 'pddl'];

    public static function isArchived(array $paper): bool
    {
        return (string) ($paper['origin'] ?? self::ORIGIN_SUBMISSION) === self::ORIGIN_ARCHIVE;
    }

    /**
     * Rights marker shown after the title of an archived paper: "Public Domain"
     * when the work is in the public domain, otherwise the licence it was
     * released under (CC-BY, CC-BY-SA …).
     */
    public static function rightsLabel(array $paper): string
    {
        $licence = strtolower(trim((string) ($paper['license'] ?? '')));
        $licence = str_replace([' ', '_'], '-', $licence);
        if ($licence === '' ) {
            return __('paper.rights_unknown');
        }
        if (in_array($licence, self::PUBLIC_DOMAIN_LICENCES, true)) {
            return __('paper.rights_public_domain');
        }
        return strtoupper($licence);
    }

    public static function rightsBadgeClass(array $paper): string
    {
        $licence = strtolower(trim((string) ($paper['license'] ?? '')));
        return in_array(str_replace([' ', '_'], '-', $licence), self::PUBLIC_DOMAIN_LICENCES, true)
            ? 'badge badge--ok'
            : 'badge badge--info';
    }

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_WITHDRAWN,
        self::STATUS_TAKEDOWN,
    ];

    /**
     * Statuses the owner may still edit / submit.
     *
     * Published papers are included on purpose: an author must always be able to
     * correct their own work. Editing one puts it back into the review queue
     * (see PaperService::update), so readers never silently get a text the
     * editors have not seen. Only an administrator can touch a taken-down paper.
     */
    public const EDITABLE_BY_OWNER = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING,
        self::STATUS_REJECTED,
        self::STATUS_WITHDRAWN,
        self::STATUS_APPROVED,
    ];

    /**
     * Language codes accepted by the upload form. The full catalogue lives in
     * Athenaeum\Core\Languages; `other` asks the author to type the name.
     */
    public static function languageCodes(): array
    {
        return array_merge(array_keys(\Athenaeum\Core\Languages::CATALOGUE), [\Athenaeum\Core\Languages::OTHER]);
    }

    /** Kept for templates that only need a short list. */
    public const LANGUAGES = ['en', 'zh-CN', 'ja', 'ko', 'fr', 'de', 'other'];

    public const LINK_KINDS = ['doi', 'arxiv', 'philarchive', 'github', 'dataset', 'video', 'slides', 'blog', 'other'];

    public const AI_STATUSES = ['none', 'queued', 'running', 'done', 'failed'];

    /** Human readable language of a paper (custom names win). */
    public static function languageLabel(array $paper): string
    {
        return \Athenaeum\Core\Languages::label(
            (string) ($paper['language'] ?? ''),
            $paper['language_custom'] ?? null
        );
    }

    public static function aiStatusLabel(array $paper): string
    {
        $status = (string) ($paper['ai_status'] ?? 'none');
        $key = 'ai.status_' . $status;
        $translated = __($key);
        return $translated === $key ? $status : $translated;
    }

    public static function aiDecisionLabel(?string $decision): string
    {
        if ($decision === null || $decision === '') {
            return '';
        }
        $key = 'ai.decision_' . $decision;
        $translated = __($key);
        return $translated === $key ? $decision : $translated;
    }

    public static function aiBadgeClass(array $paper): string
    {
        return match ((string) ($paper['ai_decision'] ?? '')) {
            'approve' => 'badge--ok',
            'reject'  => 'badge--danger',
            'review'  => 'badge--warn',
            default   => (string) ($paper['ai_status'] ?? '') === 'failed' ? 'badge--danger' : 'badge--muted',
        };
    }

    /** Saved AI payload (tags, suggested slugs, token usage). */
    public static function aiPayload(array $paper): array
    {
        $raw = $paper['ai_payload'] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function findByUid(string $uid): ?array
    {
        return self::findBy('uid', trim($uid));
    }

    public static function generateUid(): string
    {
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $uid = Str::paperUid();
            if (self::findByUid($uid) === null) {
                return $uid;
            }
        }
        return 'ATH-' . bin2hex(random_bytes(5));
    }

    /** Canonical public URL for a paper. */
    public static function publicUrl(array $paper): string
    {
        return url('paper.show', ['uid' => $paper['uid']]);
    }

    /** Is this paper visible in listings? */
    public static function isPublic(array $paper): bool
    {
        if (($paper['visibility'] ?? 'public') !== 'public') {
            return false;
        }
        if ($paper['status'] === self::STATUS_APPROVED) {
            return true;
        }
        // A paper that has been published once stays readable while a *new*
        // revision waits for review: readers keep the reviewed text (the
        // queued file is never served) and the URL stays alive for crawlers.
        // Withdrawn, taken-down, rejected and draft papers stay hidden.
        return $paper['status'] === self::STATUS_PENDING && !empty($paper['published_at']);
    }

    public static function statusLabel(string $status): string
    {
        return __('status.' . $status);
    }

    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            self::STATUS_APPROVED  => 'badge badge--ok',
            self::STATUS_PENDING   => 'badge badge--warn',
            self::STATUS_REJECTED  => 'badge badge--danger',
            self::STATUS_WITHDRAWN => 'badge badge--muted',
            self::STATUS_TAKEDOWN  => 'badge badge--danger',
            default                => 'badge',
        };
    }

    // -----------------------------------------------------------------
    // Relations
    // -----------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public static function authors(int $paperId): array
    {
        return PaperAuthor::forPaper($paperId);
    }

    /** @return array<int,array<string,mixed>> */
    public static function attachments(int $paperId): array
    {
        return Attachment::forPaper($paperId);
    }

    /** @return array<int,array<string,mixed>> */
    public static function links(int $paperId): array
    {
        return PaperLink::forPaper($paperId);
    }

    public static function timestamp(int $paperId, string $target = 'pdf', ?int $attachmentId = null): ?array
    {
        $where = ['paper_id' => $paperId, 'target_type' => $target];
        if ($attachmentId !== null) {
            $where['attachment_id'] = $attachmentId;
        }
        return Database::instance()->selectOne(
            'SELECT * FROM {{timestamps}} WHERE paper_id = :paper_id AND target_type = :target_type'
            . ($attachmentId !== null ? ' AND attachment_id = :attachment_id' : '')
            . ' ORDER BY id DESC LIMIT 1',
            $where
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function timestamps(int $paperId): array
    {
        return Database::instance()->select(
            'SELECT * FROM {{timestamps}} WHERE paper_id = :id ORDER BY id ASC',
            ['id' => $paperId]
        );
    }

    public static function sectionOf(array $paper): ?array
    {
        return $paper['section_id'] ? Section::find((int) $paper['section_id']) : null;
    }

    public static function categoryOf(array $paper): ?array
    {
        return $paper['category_id'] ? Category::find((int) $paper['category_id']) : null;
    }

    public static function uploader(array $paper): ?array
    {
        return User::find((int) $paper['uploader_id']);
    }

    /** Author list rendered as a single line. */
    public static function authorLine(array $paper): string
    {
        $authors = self::authors((int) $paper['id']);
        if ($authors === []) {
            $uploader = self::uploader($paper);
            return $uploader === null ? '' : (string) ($uploader['display_name'] ?: $uploader['nickname']);
        }
        $names = array_map(static fn (array $a): string => (string) $a['name'], $authors);
        return implode(', ', $names);
    }

    // -----------------------------------------------------------------
    // Queries
    // -----------------------------------------------------------------

    /**
     * Public listing with filters.
     *
     * @param array{section?:string,category?:string,q?:string,language?:string,sort?:string,featured?:bool} $filters
     * @return array{items:array,total:int,page:int,perPage:int,pages:int}
     */
    public static function search(array $filters = [], int $page = 1, int $perPage = 12): array
    {
        $db = Database::instance();
        $where = ["p.status = 'approved'", "p.visibility = 'public'"];
        $params = [];

        if (!empty($filters['section'])) {
            $section = Section::findBySlug((string) $filters['section']);
            if ($section === null) {
                return ['items' => [], 'total' => 0, 'page' => 1, 'perPage' => $perPage, 'pages' => 1];
            }
            $where[] = 'p.section_id = :section_id';
            $params['section_id'] = (int) $section['id'];
        }
        if (!empty($filters['category'])) {
            $category = Category::findBySlug((string) $filters['category']);
            if ($category === null) {
                return ['items' => [], 'total' => 0, 'page' => 1, 'perPage' => $perPage, 'pages' => 1];
            }
            // Filtering by a branch must include everything beneath it, which
            // is what a visitor browsing "数学" expects to see.
            $ids = Category::descendantIds((int) $category['id']);
            $placeholders = [];
            foreach ($ids as $index => $id) {
                $key = 'category_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $id;
            }
            $where[] = 'p.category_id IN (' . implode(', ', $placeholders) . ')';
        }

        // Multi-select area filter from the search sidebar: the union of the
        // chosen branches, each expanded to its descendants.
        if (!empty($filters['categories']) && is_array($filters['categories'])) {
            $ids = [];
            foreach ($filters['categories'] as $one) {
                $one = trim((string) $one);
                if ($one === '') {
                    continue;
                }
                $category = ctype_digit($one) ? Category::find((int) $one) : Category::findBySlug($one);
                if ($category === null) {
                    continue;
                }
                foreach (Category::descendantIds((int) $category['id']) as $id) {
                    $ids[$id] = true;
                }
            }
            if ($ids === []) {
                // Every requested area was unknown: return nothing rather than
                // silently ignoring the filter.
                return ['items' => [], 'total' => 0, 'page' => 1, 'perPage' => $perPage, 'pages' => 1];
            }
            $placeholders = [];
            foreach (array_keys($ids) as $index => $id) {
                $key = 'multicat_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = (int) $id;
            }
            $where[] = 'p.category_id IN (' . implode(', ', $placeholders) . ')';
        }
        if (!empty($filters['language'])) {
            $where[] = 'p.language = :language';
            $params['language'] = (string) $filters['language'];
            // Two papers may share the code "x-custom"? No: custom codes are
            // derived from the typed name, but an explicit label disambiguates.
            if (!empty($filters['language_custom'])) {
                $where[] = 'p.language_custom = :language_custom';
                $params['language_custom'] = (string) $filters['language_custom'];
            }
        }
        if (!empty($filters['featured'])) {
            $where[] = 'p.is_featured = 1';
        }
        if (!empty($filters['q'])) {
            $term = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $filters['q']) . '%';
            // One placeholder per use: MySQL with native prepared statements
            // rejects a repeated named parameter with HY093 (SQLite tolerates
            // it, which is why this only broke in production).
            $where[] = '(p.title LIKE :q1 OR p.abstract LIKE :q2 OR p.keywords LIKE :q3 OR p.uid LIKE :q4'
                . ' OR EXISTS (SELECT 1 FROM {{paper_authors}} a WHERE a.paper_id = p.id AND a.name LIKE :q5))';
            for ($i = 1; $i <= 5; $i++) {
                $params['q' . $i] = $term;
            }
        }

        $order = match ($filters['sort'] ?? 'newest') {
            'oldest'    => 'p.published_at ASC, p.id ASC',
            'title'     => 'p.title ASC',
            'downloads' => 'p.downloads DESC, p.id DESC',
            'views'     => 'p.views DESC, p.id DESC',
            default     => 'p.published_at DESC, p.id DESC',
        };

        $clause = ' WHERE ' . implode(' AND ', $where);
        $total = (int) $db->scalar('SELECT COUNT(*) FROM {{papers}} p' . $clause, $params);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        $items = $db->select(
            'SELECT p.* FROM {{papers}} p' . $clause . ' ORDER BY ' . $order
            . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        );

        return ['items' => $items, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'pages' => $pages];
    }

    /** Admin listing, all statuses. */
    public static function adminSearch(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $db = Database::instance();
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'p.status = :status';
            $params['status'] = (string) $filters['status'];
        }
        if (!empty($filters['section_id'])) {
            $where[] = 'p.section_id = :section_id';
            $params['section_id'] = (int) $filters['section_id'];
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'p.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['uploader_id'])) {
            $where[] = 'p.uploader_id = :uploader_id';
            $params['uploader_id'] = (int) $filters['uploader_id'];
        }
        if (!empty($filters['q'])) {
            $term = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $filters['q']) . '%';
            $where[] = '(p.title LIKE :q1 OR p.uid LIKE :q2)';
            $params['q1'] = $term;
            $params['q2'] = $term;
        }

        $order = match ($filters['sort'] ?? 'newest') {
            'oldest' => 'p.id ASC',
            'title'  => 'p.title ASC',
            default  => 'p.id DESC',
        };

        $clause = ' WHERE ' . implode(' AND ', $where);
        $total = (int) $db->scalar('SELECT COUNT(*) FROM {{papers}} p' . $clause, $params);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        $items = $db->select(
            'SELECT p.* FROM {{papers}} p' . $clause . ' ORDER BY ' . $order
            . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        );

        return ['items' => $items, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'pages' => $pages];
    }

    /** @return array<int,array<string,mixed>> */
    public static function forUser(int $userId, ?string $status = null): array
    {
        $db = Database::instance();
        if ($status !== null) {
            return $db->select(
                'SELECT * FROM {{papers}} WHERE uploader_id = :id AND status = :status ORDER BY id DESC',
                ['id' => $userId, 'status' => $status]
            );
        }
        return self::all(['uploader_id' => $userId], 'id DESC');
    }

    public static function countForUser(int $userId, ?array $statuses = null): int
    {
        $db = Database::instance();
        if ($statuses === null) {
            return (int) $db->scalar('SELECT COUNT(*) FROM {{papers}} WHERE uploader_id = :id', ['id' => $userId]);
        }
        $quoted = implode(', ', array_map(static fn (string $s): string => "'" . $s . "'", $statuses));
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM {{papers}} WHERE uploader_id = :id AND status IN (' . $quoted . ')',
            ['id' => $userId]
        );
    }

    public static function pendingCount(): int
    {
        return (int) Database::instance()->scalar(
            "SELECT COUNT(*) FROM {{papers}} WHERE status = 'pending'"
        );
    }

    public static function approvedCount(): int
    {
        return (int) Database::instance()->scalar(
            "SELECT COUNT(*) FROM {{papers}} WHERE status = 'approved' AND visibility = 'public'"
        );
    }

    /** @return array<int,array<string,mixed>> `section_id` → published count */
    public static function countsBySection(): array
    {
        return Database::instance()->select(
            "SELECT section_id, COUNT(*) AS total FROM {{papers}} WHERE status = 'approved' AND visibility = 'public' GROUP BY section_id"
        );
    }

    public static function incrementViews(int $paperId): void
    {
        Database::instance()->query(
            'UPDATE {{papers}} SET views = views + 1 WHERE id = :id',
            ['id' => $paperId]
        );
    }

    public static function incrementDownloads(int $paperId): void
    {
        Database::instance()->query(
            'UPDATE {{papers}} SET downloads = downloads + 1 WHERE id = :id',
            ['id' => $paperId]
        );
    }

    /** Owner-visible status summary used by the dashboard. */
    public static function statusSummary(int $userId): array
    {
        $rows = Database::instance()->select(
            'SELECT status, COUNT(*) AS total FROM {{papers}} WHERE uploader_id = :id GROUP BY status',
            ['id' => $userId]
        );
        $summary = array_fill_keys(self::STATUSES, 0);
        foreach ($rows as $row) {
            $summary[(string) $row['status']] = (int) $row['total'];
        }
        return $summary;
    }

    // -----------------------------------------------------------------
    // File access helpers
    // -----------------------------------------------------------------

    public static function pdfDiskPath(array $paper): ?string
    {
        $relative = trim((string) ($paper['pdf_path'] ?? ''));
        if ($relative === '') {
            return null;
        }
        // `pdf_path` is relative to storage/uploads and already contains the
        // `papers/YYYY/MM` prefix written by Uploader::store().
        $full = Config::path('uploads', $relative);
        return is_file($full) ? $full : null;
    }

    /**
     * Delete a paper, its relations and all of its files. Used by "purge" in
     * the admin panel; ordinary takedown only changes the status.
     */
    public static function purge(int $paperId): void
    {
        $paper = self::find($paperId);
        if ($paper === null) {
            return;
        }
        $db = Database::instance();

        foreach (self::attachments($paperId) as $attachment) {
            $path = Config::path('uploads', (string) $attachment['path']);
            if (is_file($path)) {
                @unlink($path);
            }
        }
        foreach (self::timestamps($paperId) as $timestamp) {
            $otsPath = Config::path('ots', (string) $timestamp['ots_path']);
            if ($timestamp['ots_path'] && is_file($otsPath)) {
                @unlink($otsPath);
            }
        }
        $pdf = self::pdfDiskPath($paper);
        if ($pdf !== null) {
            @unlink($pdf);
        }

        $db->delete('paper_authors', 'paper_id = :id', ['id' => $paperId]);
        $db->delete('attachments', 'paper_id = :id', ['id' => $paperId]);
        $db->delete('paper_links', 'paper_id = :id', ['id' => $paperId]);
        $db->delete('timestamps', 'paper_id = :id', ['id' => $paperId]);
        $db->delete('papers', 'id = :id', ['id' => $paperId]);
    }

    public static function summaryForIndexing(array $paper): string
    {
        return Str::excerpt((string) $paper['abstract'], 400);
    }
}
