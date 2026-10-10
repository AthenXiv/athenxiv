<?php

declare(strict_types=1);

namespace Athenaeum\Core;

use Throwable;

/**
 * Database backed site settings with hard-coded defaults, so the application
 * boots (and shows a useful message) even before the schema exists.
 */
final class Settings
{
    /** @var array<string,mixed>|null */
    private static ?array $values = null;

    /** Whether the cached values came from the settings table (not defaults). */
    private static bool $loaded = false;

    /** Reason for the last default fallback, kept for diagnostics. */
    private static ?string $lastError = null;

    /** Defaults are the source of truth for keys and types. */
    public const DEFAULTS = [
        // Branding — editable in the admin panel.
        'site.name'              => 'AthenXiv',
        'site.name_en'           => 'AthenXiv',
        'site.tagline'           => '一个开放的、跨学科的论文发布与存证平台',
        'site.tagline_en'        => 'An open, multidisciplinary archive for timestamped research',
        // Homepage hero copy. Empty means "use the translation for the
        // visitor's language"; a value here overrides every language, which is
        // what an operator wants when the built-in wording does not fit.
        'home.hero_title'        => '',
        'home.hero_subtitle'     => '',
        'home.hero_cta_label'    => '',
        'site.logo_path'         => '',      // relative to storage/uploads/branding
        'site.favicon_path'      => '',
        'site.footer_text'       => '',
        'site.contact_email'     => 'admin@example.org',
        'site.icp'               => '',
        'site.analytics'         => '',

        // Homepage notice (Markdown) — the colour and closability are choices.
        'home.notice'            => '',
        'notice.color'           => 'info',   // info|success|warning|danger|neutral|#rrggbb
        'notice.dismissible'     => true,
        'notice.revision'        => '',

        // Accounts.
        'registration.open'      => true,
        'registration.verify_email' => true,
        'registration.reset_password' => true,
        'registration.default_role' => 'user',
        'moderation.auto_approve' => false,
        'moderation.notify_email' => '',

        // Upload limits (MB for files, KB for images).
        'upload.max_pdf_mb'        => 10,
        'upload.max_attachment_mb' => 20,
        'upload.max_attachments'   => 5,
        'upload.max_avatar_kb'     => 512,
        'upload.max_logo_kb'       => 1024,
        'upload.allowed_attachment_ext' => 'zip,rar,7z,tar,gz,tgz,bz2,xz',
        'upload.pdf_message'       => '',   // custom "file too large" hint

        // Paper version management.
        'versions.enabled'       => true,
        'versions.keep_files'    => true,
        'versions.max'           => 30,

        // OpenTimestamps.
        'ots.enabled'            => true,
        'ots.require_for_publish' => false,
        'ots.calendars'          => 'https://a.pool.opentimestamps.org,https://b.pool.opentimestamps.org,https://a.pool.eternitywall.com,https://ots.btc.catallaxy.com',
        'ots.verify_url'         => 'https://opentimestamps.org/#stamp-and-verify',
        'ots.auto_upgrade'       => true,

        // AI review (any OpenAI-compatible /chat/completions endpoint).
        'ai.enabled'             => false,
        'ai.mode'                => 'off',   // off|semi|auto
        'ai.base_url'            => 'https://api.openai.com/v1',
        'ai.api_key'             => '',
        'ai.model'               => 'gpt-4o-mini',
        'ai.temperature'         => 0,
        'ai.max_input_chars'     => 12000,
        'ai.timeout'             => 90,
        'ai.read_pdf'            => true,
        'ai.auto_publish'        => false,
        'ai.min_confidence'      => 80,
        'ai.assign_section'      => true,
        'ai.assign_category'     => true,
        'ai.create_categories'   => true,
        'ai.models'              => '',
        'ai.system_prompt'       => '',
        'ai.rubric_extra'        => '',

        // Outgoing mail.
        'mail.enabled'           => false,
        'mail.transport'         => 'smtp',  // smtp|mail
        'mail.host'              => 'smtp.qq.com',
        'mail.port'              => 465,
        'mail.encryption'        => 'ssl',   // ssl|tls|none
        'mail.username'          => '',
        'mail.password'          => '',
        'mail.from_address'      => '',
        'mail.from_name'         => '',
        'mail.reply_to'          => '',
        'mail.notify_admin'      => true,
        'mail.notify_author'     => true,

        // Presentation.
        'ui.default_locale'      => 'en',
        'ui.locales'             => 'en,zh-CN,zh-TW,ja,ko,fr,de,es,pt-BR,it,ru,uk,pl,nl,sv,da,fi,tr,ar,fa,he,hi,id,vi,th,el,cs,ro,hu,ca',
        'ui.papers_per_page'     => 12,
        'ui.allow_profile_markdown' => true,
        'ui.show_view_counts'    => true,
    ];

    /** @return array<string,mixed> */
    public static function all(): array
    {
        if (self::$values !== null) {
            return self::$values;
        }

        $values = self::DEFAULTS;
        try {
            $db = Database::instance();
            if ($db->hasTable('settings')) {
                foreach ($db->select('SELECT setting_key, setting_value FROM {{settings}}') as $row) {
                    $values[$row['setting_key']] = self::decode($row['setting_value']);
                }
                self::$loaded = true;
            }
        } catch (Throwable $error) {
            // Not installed yet (fresh checkout, no tables) — defaults are the
            // honest answer. But make it visible: silently serving defaults to
            // a caller that then *writes them back* is how a live site loses its
            // SMTP host. See self::loadedFromDatabase().
            self::$lastError = $error->getMessage();
            Logger::warning('settings fell back to defaults', ['error' => $error->getMessage()]);
        }

        return self::$values = $values;
    }

    /** True when the values above really came from the settings table. */
    public static function loadedFromDatabase(): bool
    {
        if (self::$values === null) {
            self::all();
        }
        return self::$loaded;
    }

    /** Why the last load fell back to defaults, if it did. */
    public static function loadError(): ?string
    {
        return self::$lastError;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        if (array_key_exists($key, $all)) {
            return $all[$key];
        }
        return $default ?? self::DEFAULTS[$key] ?? null;
    }

    public static function bool(string $key): bool
    {
        return (bool) self::get($key, false);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::get($key, $default);
        return is_scalar($value) ? (string) $value : $default;
    }

    /** @return string[] */
    public static function list(string $key): array
    {
        $value = self::get($key, '');
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value), static fn ($v) => $v !== ''));
        }
        return array_values(array_filter(array_map('trim', explode(',', (string) $value)), static fn ($v) => $v !== ''));
    }

    public static function set(string $key, mixed $value): void
    {
        $all = self::all();
        $all[$key] = $value;
        self::$values = $all;

        $encoded = self::encode($value);
        $db = Database::instance();
        $exists = $db->scalar('SELECT COUNT(*) FROM {{settings}} WHERE setting_key = :k', ['k' => $key]);
        if ((int) $exists > 0) {
            $db->update('settings', [
                'setting_value' => $encoded,
                'updated_at'    => $db->now(),
            ], 'setting_key = :k', ['k' => $key]);
        } else {
            $db->insert('settings', [
                'setting_key'   => $key,
                'setting_value' => $encoded,
                'updated_at'    => $db->now(),
            ]);
        }
    }

    /** @param array<string,mixed> $values */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            self::set($key, $value);
        }
    }

    public static function flush(): void
    {
        self::$values = null;
    }

    private static function encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'null';
    }

    private static function decode(?string $raw): mixed
    {
        if ($raw === null) {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $raw;
        }
        return $decoded;
    }

    /** Localised site name for the current locale. */
    public static function siteName(): string
    {
        $locale = I18n::locale();
        $primary = self::string('site.name');
        $english = self::string('site.name_en');
        if ($locale === 'en' && $english !== '') {
            return $english;
        }
        return $primary !== '' ? $primary : ($english !== '' ? $english : 'AthenXiv');
    }

    public static function siteTagline(): string
    {
        $locale = I18n::locale();
        $primary = self::string('site.tagline');
        $english = self::string('site.tagline_en');
        if ($locale === 'en' && $english !== '') {
            return $english;
        }
        return $primary !== '' ? $primary : $english;
    }
}
