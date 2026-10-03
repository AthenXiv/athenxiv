<?php
/**
 * Apply a flat key/value JSON translation set to resources/lang/<locale>.php.
 *
 * Written for the footer line but deliberately generic, because the same shape
 * keeps coming up (hero copy, registration terms, archived-work notice): one
 * folder of <locale>.json files, each a flat object of language keys.
 *
 * Keys already in the language file are replaced; missing keys are inserted
 * after a stable anchor so the files keep their grouped layout.
 *
 * Usage:  php bin/apply-lang-json.php database/footer-i18n [--dry-run]
 *         (the anchor defaults to 'common.' keys; override with --after=key)
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$folder = $argv[1] ?? '';
$dryRun = in_array('--dry-run', $argv, true);
$afterKey = null;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--after=')) {
        $afterKey = substr($argument, 8);
    }
}

if ($folder === '' || !is_dir($root . '/' . $folder)) {
    fwrite(STDERR, "usage: php bin/apply-lang-json.php <folder> [--dry-run] [--after=key]\n");
    exit(1);
}

$escape = static fn (string $value): string => str_replace(['\\', "'"], ['\\\\', "\\'"], $value);

$files = glob($root . '/' . $folder . '/*.json') ?: [];
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
    if (!is_array($values) || $values === []) {
        $problems[] = "{$locale}: invalid or empty JSON";
        continue;
    }

    $code = (string) file_get_contents($langFile);
    $changed = 0;
    $missing = [];

    foreach ($values as $key => $value) {
        $value = (string) $value;
        if (trim($value) === '' || !is_string($key)) {
            $problems[] = "{$locale}: empty value for " . (string) $key;
            continue;
        }
        $pattern = "/^([ \\t]*'" . preg_quote($key, '/') . "'[ \\t]*=>[ \\t]*)'(?:[^'\\\\]|\\\\.)*'/m";
        $updated = preg_replace($pattern, '$1\'' . $escape($value) . '\'', $code, 1, $count);
        if ($updated === null || $count === 0) {
            $missing[$key] = $value;
            continue;
        }
        $code = $updated;
        $changed += $count;
    }

    if ($missing !== []) {
        // Anchor, most specific first: the requested key, then any key with the
        // same prefix before the dot, then any key at all.
        $anchors = [];
        if ($afterKey !== null) {
            $anchors[] = "/^([ \\t]*)'" . preg_quote($afterKey, '/') . "'[ \\t]*=>[^\\n]*$/m";
        }
        $prefix = strstr((string) array_key_first($missing), '.', true) ?: '';
        if ($prefix !== '') {
            $anchors[] = "/^([ \\t]*)'" . preg_quote($prefix, '/') . "\\.[a-z_]+'[ \\t]*=>[^\\n]*$/m";
        }
        $anchors[] = "/^([ \\t]*)'[a-z_]+\\.[a-z_]+'[ \\t]*=>[^\\n]*$/m";

        $offset = null;
        foreach ($anchors as $anchor) {
            if (preg_match_all($anchor, $code, $matches, PREG_OFFSET_CAPTURE) > 0) {
                $last = end($matches[0]);
                $offset = $last[1] + strlen($last[0]);
                break;
            }
        }
        if ($offset === null) {
            $problems[] = "{$locale}: no anchor for " . implode(', ', array_keys($missing));
        } else {
            $block = '';
            foreach ($missing as $key => $value) {
                $block .= "\n    '" . $key . "' => '" . $escape($value) . "',";
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
