<?php
/**
 * Stamp the site's own content pages with OpenTimestamps.
 *
 *   php bin/ots-stamp-pages.php            # stamp any page whose current text
 *                                          # has no proof yet (idempotent)
 *   php bin/ots-stamp-pages.php --slug=about
 *   php bin/ots-stamp-pages.php --list     # show each page's proof state only
 *
 * A page proof commits the page's text (title + body, every locale, in a fixed
 * order) to the public calendars. Run this once after a deploy so that the
 * pages carry a verifiable date; editing a page later re-stamps it, so the
 * command is a safety net rather than a required step.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$config = require $root . '/app/bootstrap.php';

use Athenaeum\Core\Config;
use Athenaeum\Core\Settings;
use Athenaeum\Models\Page;
use Athenaeum\Models\Timestamp;
use Athenaeum\Services\PageTimestamps;

Config::load($config);

$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--([a-z0-9\-]+)(?:=(.*))?$/i', $argument, $matches)) {
        $options[$matches[1]] = $matches[2] ?? true;
    }
}

$listOnly = !empty($options['list']);
$slugFilter = isset($options['slug']) && is_string($options['slug']) ? $options['slug'] : '';

if (!PageTimestamps::enabled()) {
    echo "OpenTimestamps is disabled in the settings; nothing to do.\n";
    exit(0);
}

$pages = Page::ordered();
$done = 0;
$failed = 0;
$skipped = 0;

foreach ($pages as $page) {
    $slug = (string) $page['slug'];
    if ($slugFilter !== '' && $slug !== $slugFilter) {
        continue;
    }
    $digest = PageTimestamps::digest($page);

    if ($listOnly) {
        $existing = Timestamp::findForPageContent((int) $page['id'], $digest);
        printf(
            "%-14s %s\n",
            $slug,
            $existing === null
                ? 'no proof for the current text'
                : sprintf('%s · %s', (string) $existing['status'], (string) ($existing['submitted_at'] ?? '—'))
        );
        continue;
    }

    $before = Timestamp::findForPageContent((int) $page['id'], $digest);
    $row = PageTimestamps::ensure($page);
    if ($row === null) {
        printf("[skip] %s (no content)\n", $slug);
        $skipped++;
        continue;
    }
    if ((string) $row['status'] === Timestamp::STATUS_FAILED) {
        printf("[fail] %s — %s\n", $slug, (string) ($row['last_error'] ?? 'unknown error'));
        $failed++;
        continue;
    }
    printf(
        "[%s] %s — %s (#%d)\n",
        $before === null ? 'ok  ' : 'have',
        $slug,
        (string) $row['status'],
        (int) $row['id']
    );
    $done++;
}

if ($listOnly) {
    exit(0);
}
printf("\nstamped %d, failed %d, skipped %d\n", $done, $failed, $skipped);
exit($failed > 0 ? 1 : 0);
