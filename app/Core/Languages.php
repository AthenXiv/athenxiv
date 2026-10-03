<?php

declare(strict_types=1);

namespace Athenaeum\Core;

/**
 * Catalogue of languages a *paper* can be written in — separate from the
 * interface locales in I18n.
 *
 * A submission may also name a language that is not listed here ("Other" in the
 * upload form): the typed name is stored in `papers.language_custom` under a
 * generated code (`x-…`), and once such a paper is published the language shows
 * up in the search filter automatically, because the filter is built from the
 * languages actually present in published papers.
 */
final class Languages
{
    /** code => [native name, English name, right-to-left?] */
    public const CATALOGUE = [
        'en'    => ['English', 'English', false],
        'zh-CN' => ['简体中文', 'Chinese (Simplified)', false],
        'zh-TW' => ['繁體中文', 'Chinese (Traditional)', false],
        'ja'    => ['日本語', 'Japanese', false],
        'ko'    => ['한국어', 'Korean', false],
        'fr'    => ['Français', 'French', false],
        'de'    => ['Deutsch', 'German', false],
        'es'    => ['Español', 'Spanish', false],
        'pt'    => ['Português', 'Portuguese', false],
        'pt-BR' => ['Português (Brasil)', 'Portuguese (Brazil)', false],
        'it'    => ['Italiano', 'Italian', false],
        'nl'    => ['Nederlands', 'Dutch', false],
        'sv'    => ['Svenska', 'Swedish', false],
        'da'    => ['Dansk', 'Danish', false],
        'nb'    => ['Norsk bokmål', 'Norwegian Bokmål', false],
        'is'    => ['Íslenska', 'Icelandic', false],
        'fi'    => ['Suomi', 'Finnish', false],
        'et'    => ['Eesti', 'Estonian', false],
        'lv'    => ['Latviešu', 'Latvian', false],
        'lt'    => ['Lietuvių', 'Lithuanian', false],
        'pl'    => ['Polski', 'Polish', false],
        'cs'    => ['Čeština', 'Czech', false],
        'sk'    => ['Slovenčina', 'Slovak', false],
        'sl'    => ['Slovenščina', 'Slovenian', false],
        'hr'    => ['Hrvatski', 'Croatian', false],
        'sr'    => ['Српски', 'Serbian', false],
        'bs'    => ['Bosanski', 'Bosnian', false],
        'hu'    => ['Magyar', 'Hungarian', false],
        'ro'    => ['Română', 'Romanian', false],
        'bg'    => ['Български', 'Bulgarian', false],
        'el'    => ['Ελληνικά', 'Greek', false],
        'ru'    => ['Русский', 'Russian', false],
        'uk'    => ['Українська', 'Ukrainian', false],
        'be'    => ['Беларуская', 'Belarusian', false],
        'tr'    => ['Türkçe', 'Turkish', false],
        'az'    => ['Azərbaycan', 'Azerbaijani', false],
        'kk'    => ['Қазақша', 'Kazakh', false],
        'uz'    => ['Oʻzbek', 'Uzbek', false],
        'hy'    => ['Հայերեն', 'Armenian', false],
        'ka'    => ['ქართული', 'Georgian', false],
        'he'    => ['עברית', 'Hebrew', true],
        'ar'    => ['العربية', 'Arabic', true],
        'fa'    => ['فارسی', 'Persian', true],
        'ur'    => ['اردو', 'Urdu', true],
        'ps'    => ['پښتو', 'Pashto', true],
        'ku'    => ['Kurdî', 'Kurdish', true],
        'hi'    => ['हिन्दी', 'Hindi', false],
        'bn'    => ['বাংলা', 'Bengali', false],
        'pa'    => ['ਪੰਜਾਬੀ', 'Punjabi', false],
        'gu'    => ['ગુજરાતી', 'Gujarati', false],
        'mr'    => ['मराठी', 'Marathi', false],
        'ta'    => ['தமிழ்', 'Tamil', false],
        'te'    => ['తెలుగు', 'Telugu', false],
        'kn'    => ['ಕನ್ನಡ', 'Kannada', false],
        'ml'    => ['മലയാളം', 'Malayalam', false],
        'si'    => ['සිංහල', 'Sinhala', false],
        'ne'    => ['नेपाली', 'Nepali', false],
        'id'    => ['Bahasa Indonesia', 'Indonesian', false],
        'ms'    => ['Bahasa Melayu', 'Malay', false],
        'vi'    => ['Tiếng Việt', 'Vietnamese', false],
        'th'    => ['ไทย', 'Thai', false],
        'km'    => ['ខ្មែរ', 'Khmer', false],
        'lo'    => ['ລາວ', 'Lao', false],
        'my'    => ['မြန်မာ', 'Burmese', false],
        'tl'    => ['Filipino', 'Filipino', false],
        'sw'    => ['Kiswahili', 'Swahili', false],
        'am'    => ['አማርኛ', 'Amharic', false],
        'ha'    => ['Hausa', 'Hausa', false],
        'yo'    => ['Yorùbá', 'Yoruba', false],
        'zu'    => ['isiZulu', 'Zulu', false],
        'af'    => ['Afrikaans', 'Afrikaans', false],
        'ca'    => ['Català', 'Catalan', false],
        'gl'    => ['Galego', 'Galician', false],
        'eu'    => ['Euskara', 'Basque', false],
        'cy'    => ['Cymraeg', 'Welsh', false],
        'ga'    => ['Gaeilge', 'Irish', false],
        'la'    => ['Latina', 'Latin', false],
        'eo'    => ['Esperanto', 'Esperanto', false],
    ];

    /** Sentinel used by the upload form for "a language that is not listed". */
    public const OTHER = 'other';

    private const CUSTOM_PREFIX = 'x-';

    /** Turn free text into a stable custom language code: "Klingon" → "x-klingon". */
    public static function customCode(string $typedName): string
    {
        $slug = Str::slug($typedName, 40);
        return self::CUSTOM_PREFIX . ($slug === '' ? 'und' : $slug);
    }

    public static function isCustom(?string $code): bool
    {
        return is_string($code) && str_starts_with($code, self::CUSTOM_PREFIX);
    }

    /** Display name for a stored language code, honouring a custom label. */
    public static function label(?string $code, ?string $custom = null): string
    {
        $code = (string) $code;
        if ($custom !== null && trim($custom) !== '') {
            return trim($custom);
        }
        if (self::isCustom($code)) {
            $bare = str_replace(self::CUSTOM_PREFIX, '', $code);
            return $bare === 'und' ? __('common.unknown') : ucfirst(str_replace('-', ' ', $bare));
        }
        $meta = self::CATALOGUE[$code] ?? null;
        if ($meta === null) {
            return $code === '' ? __('common.unknown') : strtoupper($code);
        }
        return $meta[0];
    }

    public static function englishName(?string $code): string
    {
        $meta = self::CATALOGUE[(string) $code] ?? null;
        return $meta === null ? (string) $code : $meta[1];
    }

    /**
     * Extra search terms so the picker can be reached by the names people
     * actually type: endonyms of other scripts, English exonyms and the common
     * alternative names ("Farsi" for Persian, "Mandarin" for Chinese, ...).
     */
    private const ALIASES = [
        'zh-CN' => 'chinese mandarin 中文 简体 简体中文 putonghua',
        'zh-TW' => 'chinese mandarin cantonese 繁體 繁体 繁體中文 taiwan',
        'ja'    => 'japanese 日本語 nihongo',
        'ko'    => 'korean 한국어 hangugeo',
        'fa'    => 'farsi persian پارسی',
        'ar'    => 'arabic العربية',
        'he'    => 'hebrew עברית ivrit',
        'el'    => 'greek ελληνικά ellinika',
        'hi'    => 'hindi हिन्दी devanagari',
        'th'    => 'thai ไทย',
        'vi'    => 'vietnamese tiếng việt',
        'id'    => 'indonesian bahasa indonesia',
        'ru'    => 'russian русский',
        'uk'    => 'ukrainian українська',
        'es'    => 'spanish español castellano',
        'pt-BR' => 'portuguese português brasileiro brazil',
        'fr'    => 'french français',
        'de'    => 'german deutsch',
        'it'    => 'italian italiano',
        'nl'    => 'dutch nederlands',
        'pl'    => 'polish polski',
        'cs'    => 'czech čeština',
        'ro'    => 'romanian română',
        'hu'    => 'hungarian magyar',
        'tr'    => 'turkish türkçe',
        'sv'    => 'swedish svenska',
        'da'    => 'danish dansk',
        'fi'    => 'finnish suomi',
        'ca'    => 'catalan català',
        'en'    => 'english',
    ];

    /** Everything a search box should match for one language: native, English, code, aliases. */
    public static function searchTerms(string $code): string
    {
        $native = self::CATALOGUE[$code][0] ?? '';
        $english = self::englishName($code);
        return mb_strtolower(trim($native . ' ' . $english . ' ' . $code . ' ' . (self::ALIASES[$code] ?? '')));
    }

    public static function isRtl(?string $code): bool
    {
        $meta = self::CATALOGUE[(string) $code] ?? null;
        return $meta !== null && $meta[2] === true;
    }

    /** Does the catalogue know this code? */
    public static function exists(string $code): bool
    {
        return isset(self::CATALOGUE[$code]);
    }

    /** @return array<string,string> code → native name, sorted by code */
    public static function options(): array
    {
        $options = [];
        foreach (self::CATALOGUE as $code => $meta) {
            $options[$code] = $meta[0];
        }
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);
        return $options;
    }

    /**
     * Languages that must appear in the "language" filter of the archive:
     * every language actually used by a published paper, plus the ones the
     * admin keeps in the catalogue. Custom entries keep the author's spelling.
     *
     * @return array<int,array{code:string,label:string,custom:?string,count:int}>
     */
    public static function filterOptions(): array
    {
        $rows = Database::instance()->select(
            'SELECT language, language_custom, COUNT(*) AS total'
            . ' FROM {{papers}} WHERE status = :status AND visibility = :visibility'
            . ' GROUP BY language, language_custom ORDER BY total DESC',
            ['status' => \Athenaeum\Models\Paper::STATUS_APPROVED, 'visibility' => 'public']
        );

        $options = [];
        $seen = [];
        foreach ($rows as $row) {
            $code = (string) ($row['language'] ?? '');
            $custom = $row['language_custom'] !== null ? (string) $row['language_custom'] : null;
            if ($code === '') {
                continue;
            }
            $key = $code . '|' . (string) $custom;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $options[] = [
                'code'   => $code,
                'label'  => self::label($code, $custom),
                'custom' => $custom,
                'count'  => (int) $row['total'],
            ];
        }

        // A custom code that was never used yet (e.g. just added by an admin)
        // is not listed: the filter reflects reality, which is the point.
        usort($options, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        return $options;
    }

    /** True when a freshly submitted language value is acceptable. */
    public static function validateSubmission(string $code, string $custom): bool
    {
        if ($code === self::OTHER) {
            return mb_strlen(trim($custom)) >= 2 && mb_strlen(trim($custom)) <= 60;
        }
        return $code !== '' && (self::exists($code) || self::isCustom($code));
    }
}
