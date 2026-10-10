<?php

declare(strict_types=1);

namespace Athenaeum\Core;

/**
 * Internationalisation: six locales, Accept-Language detection, English
 * fallback and per-request override (?lang=de, cookie, or user preference).
 */
final class I18n
{
    /** Native names, used by the language switcher. */
    /**
     * Interface locales. `rtl` drives the `dir` attribute in the layouts.
     * A locale only becomes selectable once its file exists in resources/lang,
     * so this table may list more languages than are currently shipped.
     */
    public const CATALOGUE = [
        'en'    => ['name' => 'English',            'flag' => 'EN', 'rtl' => false],
        'zh-CN' => ['name' => '简体中文',            'flag' => '简', 'rtl' => false],
        'zh-TW' => ['name' => '繁體中文',            'flag' => '繁', 'rtl' => false],
        'ja'    => ['name' => '日本語',              'flag' => '日', 'rtl' => false],
        'ko'    => ['name' => '한국어',              'flag' => '한', 'rtl' => false],
        'fr'    => ['name' => 'Français',           'flag' => 'FR', 'rtl' => false],
        'de'    => ['name' => 'Deutsch',            'flag' => 'DE', 'rtl' => false],
        'es'    => ['name' => 'Español',            'flag' => 'ES', 'rtl' => false],
        'pt-BR' => ['name' => 'Português (Brasil)', 'flag' => 'PT', 'rtl' => false],
        'it'    => ['name' => 'Italiano',           'flag' => 'IT', 'rtl' => false],
        'ru'    => ['name' => 'Русский',            'flag' => 'RU', 'rtl' => false],
        'uk'    => ['name' => 'Українська',         'flag' => 'UK', 'rtl' => false],
        'pl'    => ['name' => 'Polski',             'flag' => 'PL', 'rtl' => false],
        'nl'    => ['name' => 'Nederlands',         'flag' => 'NL', 'rtl' => false],
        'sv'    => ['name' => 'Svenska',            'flag' => 'SV', 'rtl' => false],
        'da'    => ['name' => 'Dansk',              'flag' => 'DA', 'rtl' => false],
        'fi'    => ['name' => 'Suomi',              'flag' => 'FI', 'rtl' => false],
        'tr'    => ['name' => 'Türkçe',             'flag' => 'TR', 'rtl' => false],
        'ar'    => ['name' => 'العربية',             'flag' => 'ع',  'rtl' => true],
        'fa'    => ['name' => 'فارسی',               'flag' => 'فا', 'rtl' => true],
        'he'    => ['name' => 'עברית',               'flag' => 'ע',  'rtl' => true],
        'hi'    => ['name' => 'हिन्दी',               'flag' => 'HI', 'rtl' => false],
        'id'    => ['name' => 'Bahasa Indonesia',   'flag' => 'ID', 'rtl' => false],
        'vi'    => ['name' => 'Tiếng Việt',         'flag' => 'VI', 'rtl' => false],
        'th'    => ['name' => 'ไทย',                'flag' => 'TH', 'rtl' => false],
        'el'    => ['name' => 'Ελληνικά',           'flag' => 'EL', 'rtl' => false],
        'cs'    => ['name' => 'Čeština',            'flag' => 'CS', 'rtl' => false],
        'ro'    => ['name' => 'Română',             'flag' => 'RO', 'rtl' => false],
        'hu'    => ['name' => 'Magyar',             'flag' => 'HU', 'rtl' => false],
        'ca'    => ['name' => 'Català',             'flag' => 'CA', 'rtl' => false],
    ];

    private const FALLBACK = 'en';

    private static string $locale = self::FALLBACK;

    /** @var array<string,array<string,string>> */
    private static array $loaded = [];

    private static bool $initialised = false;

    public static function init(): void
    {
        if (self::$initialised) {
            return;
        }
        self::$initialised = true;

        $enabled = self::enabledLocales();
        $fallback = self::normalise((string) Settings::get('ui.default_locale', 'en')) ?: self::FALLBACK;
        if (!in_array($fallback, $enabled, true)) {
            $fallback = self::FALLBACK;
        }

        $candidates = [];

        // 1. explicit ?lang=xx (also persisted in a cookie so links keep it)
        $query = $_GET['lang'] ?? null;
        if (is_string($query) && $query !== '') {
            $candidates[] = $query;
            if (PHP_SAPI !== 'cli' && !headers_sent()) {
                setcookie('athenaeum_locale', self::normalise($query), [
                    'expires'  => time() + 31536000,
                    'path'     => '/',
                    'samesite' => 'Lax',
                ]);
            }
        }

        // 2. signed-in user preference
        $user = Auth::user();
        if ($user !== null && !empty($user['locale'])) {
            $candidates[] = (string) $user['locale'];
        }

        // 3. cookie
        if (isset($_COOKIE['athenaeum_locale']) && is_string($_COOKIE['athenaeum_locale'])) {
            $candidates[] = $_COOKIE['athenaeum_locale'];
        }

        // 4. session
        $sessionLocale = Session::get('locale');
        if (is_string($sessionLocale) && $sessionLocale !== '') {
            $candidates[] = $sessionLocale;
        }

        // 5. the browser / operating system
        foreach (App::request()->acceptedLanguages() as $tag) {
            $candidates[] = $tag;
        }

        foreach ($candidates as $candidate) {
            $match = self::match($candidate, $enabled);
            if ($match !== null) {
                self::$locale = $match;
                Session::set('locale', $match);
                return;
            }
        }

        // 6. nothing matched → English (or the configured default)
        self::$locale = $fallback;
        Session::set('locale', self::$locale);
    }

    /**
     * Loose matching: `de-DE` finds `de`, `zh-Hant` finds `zh-TW`, and a bare
     * `zh` (no region, no script) lands on `zh-CN`.
     */
    public static function match(string $tag, array $enabled): ?string
    {
        $normalised = self::normalise($tag);
        if ($normalised === '') {
            return null;
        }
        foreach ($enabled as $locale) {
            if (strcasecmp($locale, $normalised) === 0) {
                return $locale;
            }
        }
        $primary = explode('-', $normalised)[0];
        foreach ($enabled as $locale) {
            if (strcasecmp(explode('-', $locale)[0], $primary) === 0) {
                return $locale;
            }
        }
        return null;
    }

    /**
     * Best interface locale for a language tag, or null when we have no
     * translation for it.
     *
     * Used where the language of the *content* differs from the language of the
     * reader: a paper written in Simplified Chinese is announced to a
     * Traditional-Chinese author in the `zh-TW` interface, and vice versa,
     * because both variants are shipped. Paper languages outnumber interface
     * locales (74 vs 30), so "no match" is normal and the caller falls back to
     * the site default.
     */
    public static function bestMatch(?string $tag): ?string
    {
        $tag = (string) $tag;
        if ($tag === '') {
            return null;
        }
        $enabled = self::enabledLocales();
        // `match()` walks the enabled list in order, so when a language has two
        // enabled variants (pt-PT against pt-BR) the configured order decides.
        $match = self::match($tag, $enabled);
        if ($match !== null) {
            return $match;
        }
        // A hand-typed language arrives as a name, not a tag ("Deutsch",
        // "Bahasa Indonesia"). Compare it with the native names we display.
        $needle = mb_strtolower(trim($tag));
        if ($needle === '') {
            return null;
        }
        foreach ($enabled as $locale) {
            $name = (string) (self::CATALOGUE[$locale]['name'] ?? '');
            if ($name !== '' && mb_strtolower($name) === $needle) {
                return $locale;
            }
        }
        return null;
    }

    public static function normalise(string $tag): string
    {
        $tag = trim($tag);
        if ($tag === '') {
            return '';
        }
        $tag = str_replace('_', '-', $tag);
        $parts = explode('-', $tag);
        $language = strtolower($parts[0]);
        if (count($parts) === 1) {
            return $language;
        }
        $region = strtoupper($parts[1]);
        $script = ucfirst(strtolower($parts[1] ?? ''));
        // Chinese is the one language whose *script*, not just its region,
        // changes the interface we should serve. zh-TW / zh-HK / zh-MO /
        // zh-Hant are Traditional; zh-CN / zh-SG / zh-Hans are Simplified.
        // Pin the script when we ship it, and fall back to the other variant
        // rather than to the wrong script.
        if ($language === 'zh') {
            $traditional = in_array($region, ['TW', 'HK', 'MO'], true) || $script === 'Hant';
            if ($traditional) {
                if (self::hasLocale('zh-TW')) {
                    return 'zh-TW';
                }
                return self::hasLocale('zh-CN') ? 'zh-CN' : 'zh';
            }
            if (self::hasLocale('zh-CN')) {
                return 'zh-CN';
            }
            return self::hasLocale('zh-TW') ? 'zh-TW' : 'zh';
        }
        $candidate = $language . '-' . $region;
        if (self::hasLocale($candidate)) {
            return $candidate;
        }
        return $language . '-' . $script;
    }

    private static function hasLocale(string $locale): bool
    {
        return is_file(self::path($locale));
    }

    /** @return string[] */
    public static function enabledLocales(): array
    {
        $configured = Settings::list('ui.locales');
        $available = [];
        foreach ($configured as $locale) {
            $normalised = $locale === 'zh' ? 'zh-CN' : $locale;
            if (is_file(self::path($normalised))) {
                $available[] = $normalised;
            }
        }
        if ($available === []) {
            $available = [self::FALLBACK];
        }
        return $available;
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function setLocale(string $locale): void
    {
        $enabled = self::enabledLocales();
        $match = self::match($locale, $enabled);
        if ($match !== null) {
            self::$locale = $match;
            Session::set('locale', $match);
        }
    }

    public static function isRtl(): bool
    {
        return (bool) (self::CATALOGUE[self::$locale]['rtl'] ?? false);
    }

    public static function direction(): string
    {
        return self::isRtl() ? 'rtl' : 'ltr';
    }

    /**
     * Locales with a language file on disk, whether or not they are enabled
     * for visitors. The page editor uses this so a translation can be written
     * before the locale is switched on.
     *
     * @return string[]
     */
    public static function availableLocales(): array
    {
        $found = [];
        foreach (glob(ATHENAEUM_ROOT . '/resources/lang/*.php') ?: [] as $file) {
            $found[] = basename($file, '.php');
        }
        $ordered = [];
        foreach (array_keys(self::CATALOGUE) as $code) {
            if (in_array($code, $found, true)) {
                $ordered[] = $code;
            }
        }
        foreach ($found as $code) {
            if (!in_array($code, $ordered, true) && $code !== 'en') {
                $ordered[] = $code;
            }
        }
        if (in_array('en', $found, true)) {
            array_unshift($ordered, 'en');
        }
        return array_values(array_unique($ordered));
    }

    /** Translate a key, `:placeholder` substitution, English fallback. */
    public static function trans(string $key, array $replace = []): string
    {
        $strings = self::load(self::$locale);
        $value = $strings[$key] ?? null;

        if ($value === null && self::$locale !== self::FALLBACK) {
            $value = self::load(self::FALLBACK)[$key] ?? null;
        }
        if ($value === null) {
            return $key;
        }

        // "singular|plural" — only used by the relative-time strings.
        if (str_contains($value, '|') && array_key_exists('count', $replace)) {
            $forms = explode('|', $value);
            $count = (float) $replace['count'];
            $value = $count == 1.0 ? $forms[0] : ($forms[1] ?? $forms[0]);
        }

        if ($replace === []) {
            return $value;
        }
        $pairs = [];
        foreach ($replace as $name => $replacement) {
            $pairs[':' . $name] = (string) $replacement;
        }
        return strtr($value, $pairs);
    }

    /** @return array<string,string> */
    private static function load(string $locale): array
    {
        if (isset(self::$loaded[$locale])) {
            return self::$loaded[$locale];
        }
        $file = self::path($locale);
        $strings = is_file($file) ? require $file : [];
        if (!is_array($strings)) {
            $strings = [];
        }
        return self::$loaded[$locale] = $strings;
    }

    private static function path(string $locale): string
    {
        $safe = preg_replace('/[^A-Za-z\-]/', '', $locale) ?? 'en';
        return ATHENAEUM_ROOT . '/resources/lang/' . $safe . '.php';
    }

    /** All translations, for client side use. */
    public static function javascript(): array
    {
        return self::load(self::$locale);
    }

    public static function catalogue(): array
    {
        $out = [];
        foreach (self::enabledLocales() as $locale) {
            $out[$locale] = self::CATALOGUE[$locale] ?? ['name' => $locale, 'flag' => strtoupper(substr($locale, 0, 2))];
        }
        return $out;
    }

    /** Date formatting that follows the active locale. */
    public static function date(?string $value, bool $withTime = false): string
    {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
            return '—';
        }
        $timestamp = strtotime($value . ' UTC');
        if ($timestamp === false) {
            return $value;
        }
        $patterns = [
            'en'    => $withTime ? 'M j, Y H:i' : 'M j, Y',
            'zh-CN' => $withTime ? 'Y年n月j日 H:i' : 'Y年n月j日',
            'zh-TW' => $withTime ? 'Y年n月j日 H:i' : 'Y年n月j日',
            'ja'    => $withTime ? 'Y年n月j日 H:i' : 'Y年n月j日',
            'ko'    => $withTime ? 'Y년 n월 j일 H:i' : 'Y년 n월 j일',
            'fr'    => $withTime ? 'j M Y H:i' : 'j M Y',
            'de'    => $withTime ? 'j. M Y H:i' : 'j. M Y',
        ];
        $pattern = $patterns[self::$locale] ?? $patterns['en'];
        return date($pattern, $timestamp) . ' UTC';
    }
}
