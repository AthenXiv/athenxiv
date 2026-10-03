<?php
/**
 * Import the shipped page translations (database/pages/<locale>.json).
 *
 *   php bin/import-pages.php            # fill locales that are still empty
 *   php bin/import-pages.php --force    # overwrite, e.g. after a copy fix
 *   php bin/import-pages.php --dry-run
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/app/bootstrap.php';

use Athenaeum\Core\Config;
use Athenaeum\Models\Page;

$config = require $root . '/config/config.php';
Config::load($config);

$force = in_array('--force', $argv, true);
$dryRun = in_array('--dry-run', $argv, true);
$directory = $root . '/database/pages';

if (!is_dir($directory)) {
    fwrite(STDERR, "no translation directory at {$directory}\n");
    exit(1);
}

echo "page translations\n" . str_repeat('-', 60) . "\n";

if ($dryRun) {
    $files = glob($directory . '/*.json') ?: [];
    echo '[dry] ' . count($files) . " locale file(s): "
        . implode(', ', array_map(static fn (string $f): string => basename($f, '.json'), $files)) . "\n";
    exit(0);
}

$stats = Page::importTranslations($directory, $force);
printf(
    "files: %d · locales: %s\nfilled: %d · skipped (already translated): %d\n",
    $stats['files'],
    $stats['locales'] === [] ? '—' : implode(', ', $stats['locales']),
    $stats['filled'],
    $stats['skipped']
);

foreach (Page::ordered() as $page) {
    $locales = Page::availableLocales($page);
    printf("  %-14s %2d locale(s)%s\n", (string) $page['slug'], count($locales),
        in_array('en', $locales, true) ? '' : '  ← no English!');
}
