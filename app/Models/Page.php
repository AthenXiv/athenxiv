<?php

declare(strict_types=1);

namespace Athenaeum\Models;

use Athenaeum\Core\Database;
use Athenaeum\Core\I18n;
use Athenaeum\Core\Model;

/**
 * Admin-editable content pages (关于本站 / 投稿指南 / 关于 AthenXiv /
 * 时间戳存证如何运作 …). Titles and Markdown bodies are stored per locale and
 * fall back to English — then to the shipped defaults in resources/lang.
 */
final class Page extends Model
{
    protected static string $table = 'pages';

    protected static array $booleans = ['is_system'];

    /** Slugs the application itself links to. */
    public const SYSTEM = [
        'about'        => 'page.about_title',
        'guidelines'   => 'page.guidelines_title',
        'athenaeum'    => 'page.athenaeum_title',
        'timestamping' => 'page.timestamping_how_title',
    ];

    public static function findBySlug(string $slug): ?array
    {
        return self::findBy('slug', $slug);
    }

    /** @return array<int,array<string,mixed>> */
    public static function ordered(): array
    {
        return self::all([], 'sort_order ASC, id ASC');
    }

    /** @return array<int,array<string,mixed>> */
    public static function systemPages(): array
    {
        return self::all(['is_system' => 1], 'sort_order ASC, id ASC');
    }

    /** Create any system page that does not exist yet (idempotent). */
    public static function ensureSystemPage(string $slug, array $titles, array $contents, int $sortOrder = 0): int
    {
        $existing = self::findBySlug($slug);
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        return self::create([
            'slug'       => $slug,
            'titles'     => json_encode($titles, JSON_UNESCAPED_UNICODE),
            'contents'   => json_encode($contents, JSON_UNESCAPED_UNICODE),
            'is_system'  => 1,
            'sort_order' => $sortOrder,
            'created_at' => Database::instance()->now(),
        ]);
    }

    /** Localised title, falling back through locale → English → shipped key. */
    public static function title(?array $page, string $slug, ?string $locale = null): string
    {
        $value = self::localised($page, 'titles', $locale);
        if ($value !== null) {
            return $value;
        }
        $key = self::SYSTEM[$slug] ?? null;
        return $key === null ? ucfirst($slug) : __($key);
    }

    /**
     * Localised Markdown body. Returns '' when the admin has not written
     * anything yet for this locale *and* no English text exists either.
     */
    public static function content(?array $page, ?string $locale = null): string
    {
        return self::localised($page, 'contents', $locale) ?? '';
    }

    private static function localised(?array $page, string $column, ?string $locale): ?string
    {
        if ($page === null) {
            return null;
        }
        $raw = $page[$column] ?? null;
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return null;
        }
        $locale ??= I18n::locale();
        // Fallback order matters: an untranslated locale should read English
        // rather than the Chinese source, which a Thai or Spanish visitor
        // cannot use at all.
        foreach ([$locale, explode('-', $locale)[0], 'en', 'zh-CN'] as $candidate) {
            if (!empty($decoded[$candidate]) && is_string($decoded[$candidate])) {
                return $decoded[$candidate];
            }
        }
        return null;
    }

    /** @return array<string,string> locale → text (only non-empty entries) */
    public static function texts(array $page, string $column): array
    {
        $raw = $page[$column] ?? null;
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $locale => $text) {
            if (is_string($text) && trim($text) !== '') {
                $out[(string) $locale] = $text;
            }
        }
        return $out;
    }

    /** Locales in which this page has content — used by the language switcher. */
    public static function availableLocales(array $page): array
    {
        return array_keys(self::texts($page, 'contents'));
    }

    /** Update one locale of a page (empty string removes that locale). */
    public static function saveLocale(int $pageId, string $locale, string $title, string $content): void
    {
        $page = self::find($pageId);
        if ($page === null) {
            return;
        }
        $titles = self::texts($page, 'titles');
        $contents = self::texts($page, 'contents');

        if (trim($title) === '') {
            unset($titles[$locale]);
        } else {
            $titles[$locale] = trim($title);
        }
        if (trim($content) === '') {
            unset($contents[$locale]);
        } else {
            $contents[$locale] = $content;
        }

        self::update($pageId, [
            'titles'     => json_encode($titles, JSON_UNESCAPED_UNICODE),
            'contents'   => json_encode($contents, JSON_UNESCAPED_UNICODE),
            'updated_by' => \Athenaeum\Core\Auth::id(),
        ]);
    }

    /**
     * Load translations shipped as JSON files: database/pages/<locale>.json
     *
     *   { "about": {"title": "…", "content": "…"}, "guidelines": {…}, … }
     *
     * Only empty locales are filled unless $force is set, so an administrator's
     * own edits are never overwritten by a deployment.
     *
     * @return array{filled:int,skipped:int,files:int,locales:array<int,string>}
     */
    public static function importTranslations(string $directory, bool $force = false): array
    {
        $stats = ['filled' => 0, 'skipped' => 0, 'files' => 0, 'locales' => []];
        foreach (glob(rtrim($directory, '/\\') . '/*.json') ?: [] as $file) {
            $locale = basename($file, '.json');
            $decoded = json_decode((string) file_get_contents($file), true);
            if (!is_array($decoded)) {
                continue;
            }
            $stats['files']++;
            $stats['locales'][] = $locale;
            foreach ($decoded as $slug => $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $page = self::findBySlug((string) $slug);
                if ($page === null) {
                    continue;
                }
                $content = trim((string) ($entry['content'] ?? ''));
                if ($content === '') {
                    continue;
                }
                $existing = trim((string) (self::texts($page, 'contents')[$locale] ?? ''));
                if ($existing !== '' && !$force) {
                    $stats['skipped']++;
                    continue;
                }
                self::saveLocale(
                    (int) $page['id'],
                    $locale,
                    trim((string) ($entry['title'] ?? '')) !== '' ? (string) $entry['title'] : (string) $slug,
                    $content
                );
                $stats['filled']++;
            }
        }
        sort($stats['locales']);
        return $stats;
    }

    /** Rough word count so the admin list can show something useful. */
    public static function size(array $page): int
    {        return mb_strlen(implode('', self::texts($page, 'contents')));
    }
}
