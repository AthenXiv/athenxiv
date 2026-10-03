<?php
/**
 * Translation completeness check.
 *
 *   php tests/lang_audit.php
 *
 * 1. extracts every __() key used in PHP code and in views,
 * 2. reports keys used but missing from en.php (a real bug: the raw key would
 *    be printed to users),
 * 3. reports keys each locale is missing (falls back to English),
 * 4. reports keys defined but never used (dead weight).
 */

declare(strict_types=1);

$root = dirname(__DIR__);

// Audit every locale that actually ships, not a hard-coded list: English first,
// then the rest in a stable order so the report is easy to diff.
$languages = ['en'];
foreach (glob($root . '/resources/lang/*.php') ?: [] as $file) {
    $code = basename($file, '.php');
    if ($code !== 'en') {
        $languages[] = $code;
    }
}
sort($languages);
array_unshift($languages, 'en');
$languages = array_values(array_unique($languages));

/** @return string[] */
function scan_usage(string $root): array
{
    $keys = [];
    $directories = [$root . '/app', $root . '/resources/views', $root . '/routes'];
    foreach ($directories as $directory) {
        if (!is_dir($directory)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $code = (string) file_get_contents($file->getPathname());
            preg_match_all(
                '/__\(\s*[\'"]([A-Za-z0-9_.\-]+)[\'"]/',
                $code,
                $matches
            );
            foreach ($matches[1] as $key) {
                $keys[$key] = ($keys[$key] ?? 0) + 1;
            }
            // Dynamic families: __('status.' . $x), __('audit.' . $x), ...
            preg_match_all('/__\(\s*[\'"]([A-Za-z0-9_]+)\.[\'"]\s*\./', $code, $families);
            foreach ($families[1] as $family) {
                $keys['*' . $family . '.*'] = ($keys['*' . $family . '.*'] ?? 0) + 1;
            }
        }
    }
    return $keys;
}

$usage = scan_usage($root);
$defined = [];
foreach ($languages as $language) {
    $file = $root . '/resources/lang/' . $language . '.php';
    $defined[$language] = is_file($file) ? require $file : [];
}

$en = $defined['en'];
$failures = 0;

echo "translation audit\n";
echo str_repeat('-', 70) . "\n";

// 1. used but not defined in English
$missingInEnglish = [];
foreach ($usage as $key => $count) {
    if (str_starts_with($key, '*')) {
        $family = trim($key, '*');
        if (!str_ends_with($family, '.')) {
            $family .= '.';
        }
        $hasFamily = false;
        foreach (array_keys($en) as $definedKey) {
            if (str_starts_with($definedKey, $family)) {
                $hasFamily = true;
                break;
            }
        }
        if (!$hasFamily) {
            $missingInEnglish[] = $key . ' (family)';
        }
        continue;
    }
    // Dynamic concatenations ("status." . $x, "ots.status_" . $x) are prefix
    // families, not keys.
    if (str_ends_with($key, '.') || str_ends_with($key, '_')) {
        $hasPrefix = false;
        foreach (array_keys($en) as $definedKey) {
            if (str_starts_with($definedKey, $key)) {
                $hasPrefix = true;
                break;
            }
        }
        if (!$hasPrefix) {
            $missingInEnglish[] = $key . '* (prefix family)';
        }
        continue;
    }
    if (!array_key_exists($key, $en)) {
        $missingInEnglish[] = $key;
    }
}
echo 'keys used in code but missing from en.php : ' . count($missingInEnglish) . "\n";
foreach ($missingInEnglish as $key) {
    echo "  ! {$key}\n";
    $failures++;
}

// 2. per-locale completeness
echo "\nper-locale completeness\n";
foreach ($languages as $language) {
    $strings = $defined[$language];
    $missing = array_diff(array_keys($en), array_keys($strings));
    $extra = array_diff(array_keys($strings), array_keys($en));
    printf(
        "  %-6s keys %4d   missing %3d   extra %3d   %s\n",
        $language,
        count($strings),
        count($missing),
        count($extra),
        $missing === [] && $extra === [] ? 'complete' : 'INCOMPLETE'
    );
    if ($missing !== []) {
        echo '         missing: ' . implode(', ', array_slice($missing, 0, 12))
            . (count($missing) > 12 ? ' …' : '') . "\n";
    }
}

// 3. defined but unused (informational only)
$unused = [];
foreach (array_keys($en) as $key) {
    if (!isset($usage[$key])) {
        $family = explode('.', $key)[0];
        if (isset($usage['*' . $family . '.*'])) {
            continue;
        }
        $unused[] = $key;
    }
}
echo "\ndefined but never referenced : " . count($unused) . "\n";
foreach (array_slice($unused, 0, 15) as $key) {
    echo "  - {$key}\n";
}
if (count($unused) > 15) {
    echo '  … ' . (count($unused) - 15) . " more\n";
}

echo "\n" . ($failures === 0
    ? "OK: every key used in the code exists in en.php\n"
    : "{$failures} key(s) used in code are missing from en.php\n");

exit($failures === 0 ? 0 : 1);
