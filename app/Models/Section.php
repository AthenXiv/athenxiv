<?php

declare(strict_types=1);

namespace Athenaeum\Models;

use Athenaeum\Core\Database;
use Athenaeum\Core\I18n;
use Athenaeum\Core\Model;

/**
 * Sections are the admin-defined "areas" (一区 / 二区 / 三区 / 预印本 …).
 * Names are stored per locale in a JSON column with an English fallback.
 */
final class Section extends Model
{
    protected static string $table = 'sections';

    protected static array $booleans = ['is_default', 'is_public'];

    public static function findBySlug(string $slug): ?array
    {
        return self::findBy('slug', $slug);
    }

    /** @return array<int,array<string,mixed>> */
    public static function ordered(bool $publicOnly = false): array
    {
        $where = $publicOnly ? ['is_public' => 1] : [];
        return self::all($where, 'sort_order ASC, id ASC');
    }

    public static function defaultSection(): ?array
    {
        $sections = self::ordered(true);
        foreach ($sections as $section) {
            if ((int) $section['is_default'] === 1) {
                return $section;
            }
        }
        return $sections[0] ?? null;
    }

    /** Localised name with fallbacks: requested locale → en → zh-CN → slug. */
    public static function name(array $section, ?string $locale = null): string
    {
        return self::localised($section, 'names', $locale)
            ?? (string) ($section['slug'] ?? '');
    }

    public static function description(array $section, ?string $locale = null): string
    {
        return self::localised($section, 'descriptions', $locale) ?? '';
    }

    private static function localised(array $row, string $column, ?string $locale): ?string
    {
        $raw = $row[$column] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return is_string($raw) ? $raw : null;
        }
        $locale ??= I18n::locale();
        // English before Chinese: a locale we have not translated should read
        // English rather than the Chinese source.
        $candidates = [$locale, explode('-', $locale)[0], 'en', 'zh-CN'];
        foreach ($candidates as $candidate) {
            if (!empty($decoded[$candidate])) {
                return (string) $decoded[$candidate];
            }
        }
        foreach ($decoded as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }
        return null;
    }

    /** @return array<int,array<string,mixed>> */
    public static function withCounts(bool $publicOnly = true): array
    {
        $counts = Paper::countsBySection();
        $map = [];
        foreach ($counts as $row) {
            $map[(int) $row['section_id']] = (int) $row['total'];
        }
        $sections = self::ordered($publicOnly);
        foreach ($sections as &$section) {
            $section['paper_count'] = $map[(int) $section['id']] ?? 0;
        }
        unset($section);
        return $sections;
    }

    /** @param array<string,string> $names locale → name */
    public static function createSection(string $slug, array $names, array $descriptions = [], int $sortOrder = 0, bool $isDefault = false): int
    {
        if ($isDefault) {
            Database::instance()->query('UPDATE {{sections}} SET is_default = 0');
        }
        return self::create([
            'slug'         => $slug,
            'names'        => json_encode($names, JSON_UNESCAPED_UNICODE),
            'descriptions' => json_encode($descriptions, JSON_UNESCAPED_UNICODE),
            'sort_order'   => $sortOrder,
            'is_default'   => $isDefault ? 1 : 0,
            'is_public'    => 1,
            'created_at'   => Database::instance()->now(),
        ]);
    }

    public static function setDefault(int $id): void
    {
        $db = Database::instance();
        $db->query('UPDATE {{sections}} SET is_default = 0');
        self::update($id, ['is_default' => 1]);
    }

    public static function safeDelete(int $id): bool
    {
        $inUse = (int) Database::instance()->scalar(
            'SELECT COUNT(*) FROM {{papers}} WHERE section_id = :id',
            ['id' => $id]
        );
        if ($inUse > 0) {
            return false;
        }
        self::delete($id);
        return true;
    }
}
