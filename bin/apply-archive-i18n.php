<?php
/**
 * Apply the archived-work translations to resources/lang/*.php.
 *
 * The notice on an archived paper ("Archived open-access work, imported from …")
 * and its date lines existed only in English and Chinese, so every other locale
 * fell back to English. Translations live in database/archive-i18n/<locale>.json;
 * this script is the only thing that writes them into the runtime language files.
 *
 * Keys already present are replaced, keys that are missing are inserted next to
 * the other archived-work keys, so the files keep their grouped layout.
 *
 * Run:  php bin/apply-archive-i18n.php [--dry-run]
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$dryRun = in_array('--dry-run', $argv, true);

// Keys that belong to the archived-work block, in the order they appear in en.php.
const KEY_ORDER = [
    'paper.rights_public_domain',
    'paper.rights_unknown',
    'paper.rights_hint',
    'paper.origin_published',
    'paper.origin_published_short',
    'paper.copyright_expired',
    'paper.copyright_status',
    'paper.added_to_site',
    'paper.archive_notice',
    'paper.archive_source_default',
];

$escape = static fn (string $value): string => str_replace(['\\', "'"], ['\\\\', "\\'"], $value);

$files = glob($root . '/database/archive-i18n/*.json') ?: [];
sort($files);

$applied = 0;
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
    $insertedKeys = [];

    foreach (KEY_ORDER as $key) {
        $value = (string) ($values[$key] ?? '');
        if (trim($value) === '') {
            $problems[] = "{$locale}: {$key} missing from the translation file";
            continue;
        }
        $pattern = "/^([ \\t]*'" . preg_quote($key, '/') . "'[ \\t]*=>[ \\t]*)'(?:[^'\\\\]|\\\\.)*'/m";
        $updated = preg_replace($pattern, '$1\'' . $escape($value) . '\'', $code, 1, $count);
        if ($updated === null || $count === 0) {
            $insertedKeys[$key] = $value; // not present yet: queue for insertion
            continue;
        }
        $code = $updated;
        $changed += $count;
    }

    // Insert missing keys after the last archived-work key already in the file.
    if ($insertedKeys !== []) {
        // Anchor, best first: the archived-work keys (keeps the grouping), then
        // the PDF download label every locale has, then any paper.* key at all.
        // Locales that never had any of these keys need the fallbacks.
        $anchors = [
            "/^([ \t]*)'(paper\.(?:rights|origin|copyright|archive)[a-z_]*|paper\.added_to_site)'[ \t]*=>[^\n]*$/m",
            "/^([ \t]*)'paper\.download_pdf'[ \t]*=>[^\n]*$/m",
            "/^([ \t]*)'paper\.[a-z_]+'[ \t]*=>[^\n]*$/m",
        ];
        $offset = null;
        foreach ($anchors as $anchorPattern) {
            if (preg_match_all($anchorPattern, $code, $matches, PREG_OFFSET_CAPTURE) > 0) {
                $last = end($matches[0]);
                $offset = $last[1] + strlen($last[0]);
                break;
            }
        }
        if ($offset === null) {
            $problems[] = "{$locale}: no anchor at all for " . implode(', ', array_keys($insertedKeys));
        } else {
            $block = '';
            foreach (KEY_ORDER as $key) {
                if (!isset($insertedKeys[$key])) {
                    continue;
                }
                $block .= "\n    '" . $key . "' => '" . $escape($insertedKeys[$key]) . "',";
                $changed++;
            }
            $code = substr($code, 0, $offset) . $block . substr($code, $offset);
        }
    }

    if ($changed === 0) {
        printf("%-6s no change\n", $locale);
        continue;
    }
    if (!$dryRun) {
        file_put_contents($langFile, $code);
    }
    $applied++;
    printf("%-6s %d change(s) %s\n", $locale, $changed, $dryRun ? '(dry run)' : 'written');
}

echo "\n";
printf("locales touched: %d/%d\n", $applied, count($files));
foreach ($problems as $problem) {
    echo '  ! ' . $problem . "\n";
}
exit($problems === [] ? 0 : 1);
