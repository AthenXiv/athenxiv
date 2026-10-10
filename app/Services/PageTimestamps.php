<?php

declare(strict_types=1);

namespace Athenaeum\Services;

use Athenaeum\Core\Settings;
use Athenaeum\Models\Page;
use Athenaeum\Models\Timestamp;

/**
 * Timestamp proofs for the site's own content pages.
 *
 * A paper proof commits to a PDF; a page proof commits to a deterministic text
 * snapshot of an editable page (关于本站, 投稿指南, 时间戳存证如何运作, 用户政策 …).
 * It is how the site can show, verifiably, when its own mission statement was
 * written — the wording does not merely claim priority, the proof shows it.
 *
 * The snapshot is deliberately stable: no dates, no build metadata, locales in
 * a fixed order. The same page content always produces the same digest, which
 * is what lets a visitor re-hash it and confirm nothing has moved.
 */
final class PageTimestamps
{
    /** Only stamp when the administrator has OpenTimestamps switched on. */
    public static function enabled(): bool
    {
        return Settings::bool('ots.enabled');
    }

    /**
     * The canonical text a page proof commits to.
     *
     * @param array<string,mixed> $page a pages row
     */
    public static function snapshot(array $page): string
    {
        $titles = Page::texts($page, 'titles');
        $contents = Page::texts($page, 'contents');
        $locales = array_keys($contents);
        sort($locales, SORT_STRING);

        $lines = [
            'AthenXiv content page',
            'slug: ' . (string) ($page['slug'] ?? ''),
            'locales: ' . implode(', ', $locales),
            '',
        ];
        foreach ($locales as $locale) {
            $lines[] = '===== ' . $locale . ' =====';
            $lines[] = 'title: ' . (string) ($titles[$locale] ?? '');
            $lines[] = '';
            $lines[] = (string) $contents[$locale];
            $lines[] = '';
        }
        return implode("\n", $lines);
    }

    public static function digest(array $page): string
    {
        return hash('sha256', self::snapshot($page));
    }

    /**
     * Create a proof for the page's current content unless one already exists.
     * Returns the timestamp row (existing or freshly created), or null when the
     * page has no content to commit to.
     *
     * @param array<string,mixed> $page
     * @return array<string,mixed>|null
     */
    public static function ensure(array $page): ?array
    {
        if (!self::enabled()) {
            return null;
        }
        if ((int) ($page['id'] ?? 0) <= 0 || trim((string) ($page['slug'] ?? '')) === '') {
            return null;
        }
        $snapshot = self::snapshot($page);
        if (trim($snapshot) === '') {
            return null;
        }

        $result = OpenTimestamps::stampPage((int) $page['id'], (string) $page['slug'], $snapshot);
        $id = (int) ($result['timestamp_id'] ?? 0);
        return $id > 0 ? Timestamp::find($id) : null;
    }

    /**
     * All proofs for a page, oldest first, decorated for the OTS partial.
     *
     * @param array<string,mixed> $page
     * @return array<int,array<string,mixed>>
     */
    public static function proofs(array $page): array
    {
        $out = [];
        foreach (Timestamp::forPage((int) ($page['id'] ?? 0)) as $row) {
            $out[] = self::present($row, (string) ($page['slug'] ?? ''));
        }
        return $out;
    }

    /**
     * Split a page's proofs into the one matching the *current* content (shown
     * up front so a visitor can verify the page they are reading) and the rest
     * (older versions, behind a toggle).
     *
     * @param array<string,mixed> $page
     * @return array{current:?array<string,mixed>,history:array<int,array<string,mixed>>,earliest:?array<string,mixed>}
     */
    public static function overview(array $page): array
    {
        $rows = self::proofs($page);
        if ($rows === []) {
            return ['current' => null, 'history' => [], 'earliest' => null];
        }
        $earliest = $rows[0];

        $digest = self::digest($page);
        $current = null;
        $history = [];
        foreach ($rows as $row) {
            if ($current === null && (string) $row['file_sha256'] === $digest) {
                $current = $row;
                continue;
            }
            $history[] = $row;
        }
        // Nothing matches the live content (the page was edited without a
        // re-stamp): fall back to the newest proof and keep the rest as history.
        if ($current === null) {
            $current = $rows[count($rows) - 1];
            $history = array_slice($rows, 0, count($rows) - 1);
        }
        $history = array_reverse($history);

        return [
            'current'  => $current,
            'history'  => $history,
            'earliest' => $earliest,
        ];
    }

    /**
     * Decorate a raw timestamp row with the URLs the OTS partial expects.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function present(array $row, string $slug): array
    {
        $row['target_type'] = Timestamp::TARGET_PAGE;
        $row['file_name'] = $row['file_name'] ?: ($slug . '.txt');
        $row['verify_url'] = OpenTimestamps::verifyUrl();
        $row['proof_url'] = !empty($row['ots_path']) ? Timestamp::pageProofUrl($row) : null;
        $row['snapshot_url'] = !empty($row['ots_path']) ? Timestamp::pageSnapshotUrl($row) : null;
        return $row;
    }
}
