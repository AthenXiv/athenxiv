<?php
/**
 * AthenXiv migration runner.
 *
 *   php bin/migrate.php                 # apply everything that is missing
 *   php bin/migrate.php --dry-run       # report without touching the database
 *   php bin/migrate.php --seed-only     # only (re)seed pages, taxonomy, versions
 *   php bin/migrate.php --force-pages=about      # overwrite one system page's
 *                                       # copy from database/seed_pages.php
 *                                       # (comma-separated slugs; add no value
 *                                       # to refresh all system pages). Only the
 *                                       # locales present in the seed (zh-CN + en)
 *                                       # are touched; other locales keep their
 *                                       # translations. Note: this overwrites
 *                                       # administrator edits to those pages.
 *
 * Every step is idempotent: it checks for the table/column/row first, so the
 * command can be run repeatedly — after a code update, on a fresh install, or
 * on a database that was created by an older version of the schema.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$config = require $root . '/app/bootstrap.php';

use Athenaeum\Core\Config;
use Athenaeum\Core\Database;
use Athenaeum\Core\Logger;
use Athenaeum\Core\Settings;
use Athenaeum\Models\Category;
use Athenaeum\Models\Page;
use Athenaeum\Models\Paper;
use Athenaeum\Models\PaperVersion;

Config::load($config);

$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--([a-z0-9\-]+)(?:=(.*))?$/i', $argument, $matches)) {
        $options[$matches[1]] = $matches[2] ?? true;
    }
}
$dryRun = !empty($options['dry-run']);
$seedOnly = !empty($options['seed-only']);
$forcePages = $options['force-pages'] ?? null;
if ($forcePages === true) {
    // bare --force-pages refreshes every system page
    $forcePages = [];
} elseif (is_string($forcePages) && $forcePages !== '') {
    $forcePages = array_values(array_filter(array_map('trim', explode(',', $forcePages))));
} else {
    $forcePages = null;
}

$db = Database::instance();
$changes = 0;

function step(string $label, bool $applied, bool $dryRun, int &$changes): void
{
    if (!$applied) {
        echo "  [--]  {$label} (already present)\n";
        return;
    }
    $changes++;
    echo '  [' . ($dryRun ? 'DRY' : 'ok ') . "]  {$label}\n";
}

if (!Settings::bool('ots.enabled')) {
    // touch Settings so the table is definitely loaded before we compare
    Settings::all();
}

echo "AthenXiv migration\n";
echo '  driver: ' . $db->driver() . "\n\n";

// ---------------------------------------------------------------------------
// 1. Schema: tables
// ---------------------------------------------------------------------------
echo "schema:\n";

if (!$seedOnly) {
    $applied = $dryRun ? !$db->hasTable('paper_versions') : $db->createTable(
        'paper_versions',
        'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
        . ' paper_id BIGINT UNSIGNED NOT NULL,'
        . ' version_no INT NOT NULL DEFAULT 1,'
        . ' label VARCHAR(40) NULL,'
        . ' note TEXT NULL,'
        . ' pdf_path VARCHAR(255) NOT NULL,'
        . ' pdf_name VARCHAR(255) NULL,'
        . ' pdf_size BIGINT UNSIGNED NOT NULL DEFAULT 0,'
        . ' pdf_sha256 CHAR(64) NULL,'
        . ' uploaded_by BIGINT UNSIGNED NULL,'
        . ' size_exempt TINYINT(1) NOT NULL DEFAULT 0,'
        . ' created_at DATETIME NOT NULL,'
        . ' PRIMARY KEY (id),'
        . ' UNIQUE KEY uniq_paper_version (paper_id, version_no),'
        . ' KEY idx_versions_paper (paper_id)'
        . ' ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'id INTEGER PRIMARY KEY AUTOINCREMENT,'
        . ' paper_id INTEGER NOT NULL,'
        . ' version_no INTEGER NOT NULL DEFAULT 1,'
        . ' label TEXT,'
        . ' note TEXT,'
        . ' pdf_path TEXT NOT NULL,'
        . ' pdf_name TEXT,'
        . ' pdf_size INTEGER NOT NULL DEFAULT 0,'
        . ' pdf_sha256 TEXT,'
        . ' uploaded_by INTEGER,'
        . ' size_exempt INTEGER NOT NULL DEFAULT 0,'
        . ' created_at TEXT NOT NULL,'
        . ' UNIQUE (paper_id, version_no)'
    );
    step('table paper_versions', $applied, $dryRun, $changes);

    $applied = $dryRun ? !$db->hasTable('pages') : $db->createTable(
        'pages',
        'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
        . ' slug VARCHAR(80) NOT NULL,'
        . ' titles TEXT NULL,'
        . ' contents MEDIUMTEXT NULL,'
        . ' is_system TINYINT(1) NOT NULL DEFAULT 0,'
        . ' sort_order INT NOT NULL DEFAULT 0,'
        . ' updated_by BIGINT UNSIGNED NULL,'
        . ' created_at DATETIME NOT NULL,'
        . ' updated_at DATETIME NULL,'
        . ' PRIMARY KEY (id), UNIQUE KEY uniq_pages_slug (slug)'
        . ' ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'id INTEGER PRIMARY KEY AUTOINCREMENT,'
        . ' slug TEXT NOT NULL UNIQUE,'
        . ' titles TEXT,'
        . ' contents TEXT,'
        . ' is_system INTEGER NOT NULL DEFAULT 0,'
        . ' sort_order INTEGER NOT NULL DEFAULT 0,'
        . ' updated_by INTEGER,'
        . ' created_at TEXT NOT NULL,'
        . ' updated_at TEXT'
    );
    step('table pages', $applied, $dryRun, $changes);

    // --- one-time e-mail codes (registration, later password reset) --------
    $applied = $dryRun ? !$db->hasTable('email_verifications') : $db->createTable(
        'email_verifications',
        'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
        . ' email VARCHAR(190) NOT NULL,'
        . ' code_hash VARCHAR(120) NOT NULL,'
        . ' purpose VARCHAR(20) NOT NULL DEFAULT \'register\','
        . ' attempts INT NOT NULL DEFAULT 0,'
        . ' expires_at DATETIME NOT NULL,'
        . ' created_at DATETIME NOT NULL,'
        . ' updated_at DATETIME NULL,'
        . ' ip VARCHAR(64) NULL,'
        . ' PRIMARY KEY (id), KEY idx_email_codes (email, purpose)'
        . ' ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'id INTEGER PRIMARY KEY AUTOINCREMENT,'
        . ' email TEXT NOT NULL,'
        . ' code_hash TEXT NOT NULL,'
        . ' purpose TEXT NOT NULL DEFAULT \'register\','
        . ' attempts INTEGER NOT NULL DEFAULT 0,'
        . ' expires_at TEXT NOT NULL,'
        . ' created_at TEXT NOT NULL,'
        . ' updated_at TEXT,'
        . ' ip TEXT'
    );
    step('table email_verifications', $applied, $dryRun, $changes);
    // Every Model::update() writes updated_at, so the column has to exist even
    // though this table is only ever inserted into or deleted from.
    $applied = $dryRun
        ? !$db->hasColumn('email_verifications', 'updated_at')
        : $db->addColumn('email_verifications', 'updated_at', 'DATETIME NULL', 'TEXT');
    step('email_verifications.updated_at', $applied, $dryRun, $changes);

    // NULL = uploaded but not approved yet; the public never sees that file.
    $applied = $dryRun
        ? !$db->hasColumn('paper_versions', 'published_at')
        : $db->addColumn('paper_versions', 'published_at', 'DATETIME NULL', 'TEXT');
    step('paper_versions.published_at', $applied, $dryRun, $changes);
    if (!$dryRun) {
        // Every version that exists today belongs to a published paper.
        $db->query('UPDATE {{paper_versions}} SET published_at = created_at WHERE published_at IS NULL');
    }

    // NULL = uploaded but not approved yet; the public never sees it.
    $applied = $dryRun
        ? !$db->hasColumn('paper_versions', 'published_at')
        : $db->addColumn('paper_versions', 'published_at', 'DATETIME NULL', 'TEXT');
    step('paper_versions.published_at', $applied, $dryRun, $changes);
    if (!$dryRun) {
        // Everything that exists today belongs to an already published paper.
        $db->query('UPDATE {{paper_versions}} SET published_at = created_at WHERE published_at IS NULL');
    }

    // --- columns on papers -------------------------------------------------
    $columns = [
        ['version_no',      'INT NOT NULL DEFAULT 1',            'INTEGER NOT NULL DEFAULT 1'],
        ['language_custom', 'VARCHAR(80) NULL',                  'TEXT'],
        ['ai_status',       "VARCHAR(20) NOT NULL DEFAULT 'none'", "TEXT NOT NULL DEFAULT 'none'"],
        ['ai_decision',     'VARCHAR(20) NULL',                  'TEXT'],
        ['ai_confidence',   'INT NULL',                          'INTEGER'],
        ['ai_reason',       'TEXT NULL',                         'TEXT'],
        ['ai_model',        'VARCHAR(80) NULL',                  'TEXT'],
        ['ai_reviewed_at',  'DATETIME NULL',                     'TEXT'],
        ['ai_payload',      'MEDIUMTEXT NULL',                   'TEXT'],
        ['category_other',  'VARCHAR(190) NULL',                 'TEXT'],
        // Where a paper came from. 'submission' is a normal upload; 'archive'
        // is a public-domain / openly licensed work imported in bulk (OpenAlex),
        // which is shown differently: a rights marker next to the title, the
        // original publication date and the copyright expiry date, and no
        // version or timestamp history.
        ['origin',              "VARCHAR(20) NOT NULL DEFAULT 'submission'", "TEXT NOT NULL DEFAULT 'submission'"],
        ['origin_source',       'VARCHAR(80) NULL',              'TEXT'],
        ['origin_published_at', 'DATE NULL',                     'TEXT'],
        ['copyright_expired_at', 'DATE NULL',                    'TEXT'],
    ];
    foreach ($columns as [$column, $mysqlType, $sqliteType]) {
        $applied = $dryRun ? !$db->hasColumn('papers', $column) : $db->addColumn('papers', $column, $mysqlType, $sqliteType);
        step("papers.{$column}", $applied, $dryRun, $changes);
    }

    // --- columns on timestamps ---------------------------------------------
    // Page-content proofs live in the same table as paper proofs
    // (target_type = 'page', paper_id = 0, page_id = the content page).
    $applied = $dryRun
        ? !$db->hasColumn('timestamps', 'page_id')
        : $db->addColumn('timestamps', 'page_id', 'BIGINT UNSIGNED NULL', 'INTEGER');
    step('timestamps.page_id', $applied, $dryRun, $changes);

    // --- index for the AI queue -------------------------------------------
    if (!$db->isSqlite() && !$dryRun) {
        try {
            $db->pdo()->exec('CREATE INDEX idx_papers_ai_status ON ' . $db->quoteIdentifier($db->table('papers')) . ' (ai_status)');
            step('index papers.ai_status', true, $dryRun, $changes);
        } catch (Throwable) {
            step('index papers.ai_status', false, $dryRun, $changes);
        }
    }
}

// ---------------------------------------------------------------------------
// 2. Editable pages
// ---------------------------------------------------------------------------
if ($dryRun) {
    echo "\ndata steps (pages, taxonomy, version backfill, retired links) are\n"
        . "skipped in --dry-run mode because the new tables do not exist yet.\n";
    echo "\n{$changes} schema change(s) would be applied.\n";
    exit(0);
}

echo "\npages:\n";
$seedPages = require $root . '/database/seed_pages.php';
foreach ($seedPages as $slug => $page) {
    $existing = Page::findBySlug((string) $slug);
    if ($existing !== null) {
        $wanted = $forcePages === [] || (is_array($forcePages) && in_array((string) $slug, $forcePages, true));
        if ($wanted) {
            $titles = (array) ($page['titles'] ?? []);
            $contents = (array) ($page['contents'] ?? []);
            $current = Page::texts($existing, 'contents');
            $refreshed = 0;
            foreach ($contents as $locale => $text) {
                if (!is_string($text) || ($current[$locale] ?? '') === $text) {
                    continue;
                }
                Page::saveLocale((int) $existing['id'], (string) $locale, (string) ($titles[$locale] ?? ''), $text);
                $refreshed++;
            }
            step("page {$slug} refreshed ({$refreshed} locale(s))", $refreshed > 0, $dryRun, $changes);
        } else {
            step("page {$slug}", false, $dryRun, $changes);
        }
        continue;
    }
    if (!$dryRun) {
        Page::ensureSystemPage(
            (string) $slug,
            (array) $page['titles'],
            (array) $page['contents'],
            (int) ($page['sort_order'] ?? 0)
        );
    }
    step("page {$slug} seeded", true, $dryRun, $changes);
}

// Shipped translations (database/pages/<locale>.json) fill the locales the
// administrator has not written yet — never overwrite their own work.
if (!$dryRun) {
    $imported = Page::importTranslations($root . '/database/pages');
    step(
        sprintf('page translations: %d filled from %d file(s)', $imported['filled'], $imported['files']),
        $imported['filled'] > 0,
        $dryRun,
        $changes
    );
}

// ---------------------------------------------------------------------------
// 3. Subject taxonomy
// ---------------------------------------------------------------------------
echo "\ntaxonomy:\n";
$seedFile = $root . '/database/seed_categories.php';
if (is_file($seedFile)) {
    $tree = require $seedFile;
    if (is_array($tree) && $tree !== []) {
        if ($dryRun) {
            $count = 0;
            $walk = static function (array $nodes) use (&$walk, &$count): void {
                foreach ($nodes as $node) {
                    $count++;
                    if (!empty($node['children'])) {
                        $walk($node['children']);
                    }
                }
            };
            $walk($tree);
            echo "  [DRY]  taxonomy has {$count} nodes (not applied)\n";
        } else {
            $sortBase = 0;
            $stats = Category::seedTree($tree, null, $sortBase);
            printf(
                "  [ok]  taxonomy: %d created, %d re-parented, %d unchanged\n",
                $stats['created'],
                $stats['reparented'],
                $stats['skipped']
            );
            $changes += $stats['created'] + $stats['reparented'];
        }
    } else {
        echo "  [!!]  database/seed_categories.php did not return an array\n";
    }
} else {
    echo "  [!!]  database/seed_categories.php missing — taxonomy not seeded\n";
}

// ---------------------------------------------------------------------------
// 4. Backfill version rows
// ---------------------------------------------------------------------------
echo "\nversions:\n";
$papers = Paper::all([], 'id ASC');
$backfilled = 0;
foreach ($papers as $paper) {
    if (empty($paper['pdf_path'])) {
        continue;
    }
    $has = (int) $db->scalar('SELECT COUNT(*) FROM {{paper_versions}} WHERE paper_id = :id', ['id' => (int) $paper['id']]);
    if ($has > 0) {
        continue;
    }
    if (!$dryRun) {
        PaperVersion::backfill($paper);
    }
    $backfilled++;
}
step("v1 rows created for {$backfilled} existing paper(s)", $backfilled > 0, $dryRun, $changes);

// ---------------------------------------------------------------------------
// 5. Retire platforms that are no longer offered
// ---------------------------------------------------------------------------
echo "\nlinked accounts:\n";
$retired = ['zhihu', 'weibo', 'bilibili', 'douban', 'wechat'];
$placeholders = implode(', ', array_fill(0, count($retired), '?'));
$count = (int) $db->scalar(
    'SELECT COUNT(*) FROM {{user_links}} WHERE platform IN (' . $placeholders . ')',
    $retired
);
if ($count > 0 && !$dryRun) {
    $db->query('DELETE FROM {{user_links}} WHERE platform IN (' . $placeholders . ')', $retired);
}
step("removed {$count} link(s) on retired platforms", $count > 0, $dryRun, $changes);

// ---------------------------------------------------------------------------
// 6. Settings that need a nudge
// ---------------------------------------------------------------------------
echo "\nsettings:\n";
if (!$dryRun) {
    // New defaults are picked up automatically via Settings::DEFAULTS; write the
    // ones an administrator should be able to see in the panel right away.
    foreach (['notice.color', 'notice.dismissible', 'versions.enabled', 'ai.mode', 'mail.host', 'ui.locales'] as $key) {
        if (!array_key_exists($key, Settings::all())) {
            Settings::set($key, Settings::DEFAULTS[$key]);
            step("setting {$key}", true, $dryRun, $changes);
        }
    }
}
echo "  [ok]  settings defaults are merged at runtime\n";

echo "\n" . ($changes === 0
    ? "Nothing to do — the database is up to date.\n"
    : "{$changes} change(s) applied" . ($dryRun ? ' (dry run, nothing written)' : '') . ".\n");

Logger::info('migrate finished', ['changes' => $changes, 'dry_run' => $dryRun]);
