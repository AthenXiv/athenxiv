<?php

declare(strict_types=1);

namespace Athenaeum\Models;

use Athenaeum\Core\Database;
use Athenaeum\Core\I18n;
use Athenaeum\Core\Model;

/**
 * Subject categories (形而上学 / 伦理学 / 逻辑学 …), admin managed.
 */
final class Category extends Model
{
    protected static string $table = 'categories';

    protected static array $booleans = [];

    public static function findBySlug(string $slug): ?array
    {
        return self::findBy('slug', $slug);
    }

    /** @return array<int,array<string,mixed>> */
    public static function ordered(): array
    {
        return self::all([], 'sort_order ASC, id ASC');
    }

    public static function name(array $category, ?string $locale = null): string
    {
        $raw = $category['names'] ?? null;
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return (string) ($category['slug'] ?? '');
        }
        $locale ??= I18n::locale();
        // English before Chinese: see the note in Page::localised().
        foreach ([$locale, explode('-', $locale)[0], 'en', 'zh-CN'] as $candidate) {
            if (!empty($decoded[$candidate])) {
                return (string) $decoded[$candidate];
            }
        }
        foreach ($decoded as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }
        return (string) ($category['slug'] ?? '');
    }

    /** @return array<int,array<string,mixed>> */
    public static function withCounts(): array
    {
        $rows = Database::instance()->select(
            "SELECT category_id, COUNT(*) AS total FROM {{papers}} WHERE status = 'approved' AND visibility = 'public' GROUP BY category_id"
        );
        $direct = [];
        foreach ($rows as $row) {
            if ($row['category_id'] === null) {
                continue;
            }
            $direct[(int) $row['category_id']] = (int) $row['total'];
        }

        $categories = self::ordered();
        $parents = [];
        foreach ($categories as $category) {
            $parents[(int) $category['id']] = (int) ($category['parent_id'] ?? 0);
        }

        // A paper filed under 统计学 belongs to 数学 as well: roll every node's
        // own count up through its ancestors, so the number shown next to a
        // parent equals what filtering by that parent actually returns (the
        // filter already expands to the whole subtree).
        $totals = $direct;
        foreach ($direct as $id => $count) {
            $parent = $parents[$id] ?? 0;
            $guard = 0;
            while ($parent > 0 && isset($parents[$parent]) && $guard++ < 32) {
                $totals[$parent] = ($totals[$parent] ?? 0) + $count;
                $parent = $parents[$parent];
            }
        }

        foreach ($categories as &$category) {
            $category['paper_count'] = $totals[(int) $category['id']] ?? 0;
        }
        unset($category);
        return $categories;
    }

    /**
     * Delete a category, but only when it is a leaf: a node that still has
     * children or papers must be emptied first so nothing is orphaned.
     */
    public static function safeDelete(int $id): bool
    {
        $db = Database::instance();
        $inUse = (int) $db->scalar(
            'SELECT COUNT(*) FROM {{papers}} WHERE category_id = :id',
            ['id' => $id]
        );
        if ($inUse > 0) {
            return false;
        }
        $children = (int) $db->scalar(
            'SELECT COUNT(*) FROM {{categories}} WHERE parent_id = :id',
            ['id' => $id]
        );
        if ($children > 0) {
            return false;
        }
        self::delete($id);
        return true;
    }

    // =====================================================================
    // Tree helpers (multi-level subject areas)
    // =====================================================================

    /**
     * The whole taxonomy as a nested array: each node gains `children` and
     * `depth`, plus the paper counts of `withCounts()` when asked.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function tree(bool $withCounts = true): array
    {
        $rows = $withCounts ? self::withCounts() : self::ordered();
        $byId = [];
        foreach ($rows as $row) {
            $row['children'] = [];
            $row['depth'] = 0;
            $byId[(int) $row['id']] = $row;
        }

        $roots = [];
        foreach ($byId as $id => $row) {
            $parentId = $row['parent_id'] !== null ? (int) $row['parent_id'] : 0;
            if ($parentId !== 0 && isset($byId[$parentId])) {
                $byId[$parentId]['children'][] = &$byId[$id];
            } else {
                $roots[] = &$byId[$id];
            }
        }
        unset($row);

        // Depth + aggregate counts (a branch totals its own papers plus those
        // of its descendants, which is what a visitor expects to see).
        $decorate = static function (array &$node, int $depth) use (&$decorate): int {
            $node['depth'] = $depth;
            $total = (int) ($node['paper_count'] ?? 0);
            foreach ($node['children'] as $index => $child) {
                $total += $decorate($node['children'][$index], $depth + 1);
            }
            $node['branch_count'] = $total;
            return $total;
        };
        foreach ($roots as $index => $root) {
            $decorate($roots[$index], 0);
        }

        return $roots;
    }

    /** Flat list with an indent prefix, for <select> boxes. @return array<int,array<string,mixed>> */
    public static function flat(bool $withCounts = false): array
    {
        $out = [];
        $walk = static function (array $nodes) use (&$walk, &$out): void {
            foreach ($nodes as $node) {
                $children = $node['children'] ?? [];
                $node['indent'] = str_repeat('— ', (int) ($node['depth'] ?? 0));
                unset($node['children']);
                $out[] = $node;
                if ($children !== []) {
                    $walk($children);
                }
            }
        };
        $walk(self::tree($withCounts));
        return $out;
    }

    /** Ancestors of a node, root first: useful for breadcrumbs. */
    public static function pathOf(int $id): array
    {
        $path = [];
        $guard = 0;
        $current = self::find($id);
        while ($current !== null && $guard++ < 20) {
            array_unshift($path, $current);
            $parentId = $current['parent_id'] !== null ? (int) $current['parent_id'] : 0;
            $current = $parentId > 0 ? self::find($parentId) : null;
        }
        return $path;
    }

    /** "数学 / 分析数学" for display. */
    public static function pathLabel(int $id, string $separator = ' / '): string
    {
        return implode($separator, array_map(
            static fn (array $node): string => self::name($node),
            self::pathOf($id)
        ));
    }

    /** A node plus every descendant id — used to filter by a whole branch. */
    public static function descendantIds(int $id): array
    {
        $ids = [$id];
        $frontier = [$id];
        $guard = 0;
        while ($frontier !== [] && $guard++ < 20) {
            $placeholders = implode(', ', array_fill(0, count($frontier), '?'));
            $rows = Database::instance()->query(
                'SELECT id FROM {{categories}} WHERE parent_id IN (' . $placeholders . ')',
                $frontier
            )->fetchAll();
            $frontier = [];
            foreach ($rows as $row) {
                $childId = (int) $row['id'];
                if (!in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $frontier[] = $childId;
                }
            }
        }
        return $ids;
    }

    /**
     * Insert a nested taxonomy, skipping existing slugs and re-parenting rows
     * that already exist (so an upgrade does not duplicate the old flat list).
     *
     * @param array<int,array<string,mixed>> $nodes
     * @return array{created:int,reparented:int,skipped:int}
     */
    public static function seedTree(array $nodes, ?int $parentId = null, int &$sortBase = 0, ?array &$stats = null): array
    {
        $stats ??= ['created' => 0, 'reparented' => 0, 'skipped' => 0];
        $position = 0;

        foreach ($nodes as $node) {
            $slug = (string) ($node['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $names = is_array($node['names'] ?? null) ? $node['names'] : [];
            $existing = self::findBySlug($slug);

            if ($existing === null) {
                $sort = $sortBase + $position;
                $id = self::create([
                    'slug'       => $slug,
                    'names'      => json_encode($names, JSON_UNESCAPED_UNICODE),
                    'parent_id'  => $parentId,
                    'sort_order' => $sort,
                    'created_at' => Database::instance()->now(),
                ]);
                $stats['created']++;
            } else {
                $id = (int) $existing['id'];
                $currentParent = $existing['parent_id'] !== null ? (int) $existing['parent_id'] : null;
                $updates = [];
                if ($currentParent !== $parentId) {
                    $updates['parent_id'] = $parentId;
                    $stats['reparented']++;
                }
                // Fill in names for locales the row does not have yet.
                $merged = json_decode((string) $existing['names'], true);
                $merged = is_array($merged) ? $merged : [];
                $changed = false;
                foreach ($names as $locale => $text) {
                    if (empty($merged[$locale])) {
                        $merged[$locale] = $text;
                        $changed = true;
                    }
                }
                if ($changed) {
                    $updates['names'] = json_encode($merged, JSON_UNESCAPED_UNICODE);
                }
                if ($updates !== []) {
                    self::update($id, $updates);
                } else {
                    $stats['skipped']++;
                }
            }

            $position += 10;
            if (!empty($node['children']) && is_array($node['children'])) {
                $childBase = $sortBase + $position;
                self::seedTree($node['children'], $id, $childBase, $stats);
            }
        }

        return $stats;
    }
}
