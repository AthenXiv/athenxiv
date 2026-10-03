<?php
/**
 * Write the per-language homepage copy into resources/lang/*.php.
 *
 * The site copy that describes AthenXiv as a philosophy platform is replaced by
 * multidisciplinary wording, translated for all thirty locales (the translations
 * live in database/hero-i18n/<locale>.json, written by translators, and this
 * script is the single place that applies them to the runtime language files).
 *
 * Only the seven keys listed below are touched; alignment and the rest of each
 * file are left exactly as they were.
 *
 * Run:  php bin/apply-hero-i18n.php [--dry-run]
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$dryRun = in_array('--dry-run', $argv, true);

const KEYS = [
    'common.tagline',
    'home.hero_title',
    'home.hero_subtitle',
    'meta.description',
    'meta.keywords',
    'page.about_p1',
    'page.guidelines_p1',
];

$escape = static fn (string $value): string => str_replace(['\\', "'"], ['\\\\', "\\'"], $value);

$files = glob($root . '/database/hero-i18n/*.json') ?: [];
sort($files);

$applied = 0;
$missing = [];
$problems = [];

foreach ($files as $file) {
    $locale = basename($file, '.json');
    $langFile = $root . '/resources/lang/' . $locale . '.php';
    if (!is_file($langFile)) {
        $problems[] = "{$locale}: no language file at resources/lang/{$locale}.php";
        continue;
    }
    $values = json_decode((string) file_get_contents($file), true);
    if (!is_array($values)) {
        $problems[] = "{$locale}: cannot parse " . basename($file);
        continue;
    }

    $code = (string) file_get_contents($langFile);
    $changed = 0;

    foreach (KEYS as $key) {
        $value = (string) ($values[$key] ?? '');
        if (trim($value) === '') {
            $missing[] = "{$locale}:{$key}";
            continue;
        }
        $pattern = "/^([ \\t]*'" . preg_quote($key, '/') . "'[ \\t]*=>[ \\t]*)'(?:[^'\\\\]|\\\\.)*'/m";
        $replacement = '$1\'' . $escape($value) . '\'';
        $updated = preg_replace($pattern, $replacement, $code, 1, $count);
        if ($updated === null) {
            $problems[] = "{$locale}:{$key}: regex failure";
            continue;
        }
        if ($count === 0) {
            $missing[] = "{$locale}:{$key} (key absent)";
            continue;
        }
        $code = $updated;
        $changed += $count;
    }

    if ($changed > 0 && !$dryRun) {
        file_put_contents($langFile, $code);
    }
    if ($changed > 0) {
        $applied++;
    }
    printf("%-6s %2d key(s) %s\n", $locale, $changed, $dryRun ? '(dry run)' : 'written');
}

echo "\n";
printf("locales touched: %d/%d\n", $applied, count($files));
if ($missing !== []) {
    echo "unresolved:\n";
    foreach ($missing as $item) {
        echo '  ' . $item . "\n";
    }
}
if ($problems !== []) {
    echo "problems:\n";
    foreach ($problems as $item) {
        echo '  ' . $item . "\n";
    }
    exit(1);
}
echo $missing === [] ? "all keys applied\n" : "completed with gaps\n";
