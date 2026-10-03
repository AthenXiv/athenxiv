<?php
/**
 * Apply the registration-copy translations to resources/lang/*.php.
 *
 * Companion to bin/apply-hero-i18n.php: the operator asked for a different
 * acceptance sentence (user policy instead of submission guidelines, with the
 * policy as a link) and for a note about the spam folder after the verification
 * code, in all thirty languages. The translations live in
 * database/terms-i18n/<locale>.json; this script is the only place that writes
 * them into the runtime language files.
 *
 * The new key `auth.terms_policy` is appended after `auth.terms` when missing.
 *
 * Run:  php bin/apply-terms-i18n.php [--dry-run]
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$dryRun = in_array('--dry-run', $argv, true);

const REPLACE_KEYS = ['auth.terms', 'auth.code_sent'];
const INSERT_AFTER = ['auth.terms_policy' => 'auth.terms'];

$escape = static fn (string $value): string => str_replace(['\\', "'"], ['\\\\', "\\'"], $value);

$files = glob($root . '/database/terms-i18n/*.json') ?: [];
sort($files);

$applied = 0;
$missing = [];
$problems = [];

foreach ($files as $file) {
    $locale = basename($file, '.json');
    $langFile = $root . '/resources/lang/' . $locale . '.php';
    if (!is_file($langFile)) {
        $problems[] = "{$locale}: no language file";
        continue;
    }
    $values = json_decode((string) file_get_contents($file), true);
    if (!is_array($values)) {
        $problems[] = "{$locale}: invalid JSON";
        continue;
    }

    $code = (string) file_get_contents($langFile);
    $changed = 0;

    // 1. Replace existing keys.
    foreach (REPLACE_KEYS as $key) {
        $value = (string) ($values[$key] ?? '');
        if (trim($value) === '') {
            $missing[] = "{$locale}:{$key}";
            continue;
        }
        $pattern = "/^([ \\t]*'" . preg_quote($key, '/') . "'[ \\t]*=>[ \\t]*)'(?:[^'\\\\]|\\\\.)*'/m";
        $updated = preg_replace($pattern, '$1\'' . $escape($value) . '\'', $code, 1, $count);
        if ($updated === null || $count === 0) {
            $missing[] = "{$locale}:{$key} (not found)";
            continue;
        }
        $code = $updated;
        $changed += $count;
    }

    // 2. Insert the new key right after its anchor when absent.
    foreach (INSERT_AFTER as $key => $anchor) {
        $value = (string) ($values[$key] ?? '');
        if (trim($value) === '') {
            $missing[] = "{$locale}:{$key}";
            continue;
        }
        if (preg_match("/^[ \\t]*'" . preg_quote($key, '/') . "'[ \\t]*=>/m", $code) === 1) {
            continue; // already present
        }
        $anchorPattern = "/^([ \\t]*'" . preg_quote($anchor, '/') . "'[ \\t]*=>[ \\t]*)'(?:[^'\\\\]|\\\\.)*',[ \\t]*$/m";
        $inserted = preg_replace(
            $anchorPattern,
            '$0' . "\n" . "    '" . $key . "' => '" . $escape($value) . "',",
            $code,
            1,
            $count
        );
        if ($inserted === null || $count === 0) {
            $missing[] = "{$locale}:{$key} (anchor {$anchor} not found)";
            continue;
        }
        $code = $inserted;
        $changed += $count;
    }

    if ($changed > 0 && !$dryRun) {
        file_put_contents($langFile, $code);
    }
    if ($changed > 0) {
        $applied++;
    }
    printf("%-6s %d change(s) %s\n", $locale, $changed, $dryRun ? '(dry run)' : 'written');
}

echo "\n";
printf("locales touched: %d/%d\n", $applied, count($files));
foreach (array_merge($missing, $problems) as $item) {
    echo '  ! ' . $item . "\n";
}
exit($problems === [] ? 0 : 1);
