<?php
/**
 * Athenaeum — front controller.
 *
 * The document root of the site must point at `public/`. Everything else
 * (application code, configuration, uploaded files) lives above it, which is
 * exactly what a normal shared-hosting "public_html" layout expects.
 */

declare(strict_types=1);

// Two layouts are supported, because shared hosts differ:
//   * document root = public/            → the application lives one level up
//   * document root = the project root   → the application lives right here
//     (the usual situation when the host's web root cannot be moved; the
//     contents of public/ were copied up so assets stay reachable)
$appRoot = is_file(__DIR__ . '/app/bootstrap.php') ? __DIR__ : dirname(__DIR__);

$config = require $appRoot . '/app/bootstrap.php';

use Athenaeum\Core\App;
use Athenaeum\Core\I18n;
use Athenaeum\Core\View;

App::boot($config);

// Search-engine visits are journalled: shared hosting usually keeps the access
// log out of reach, and this is the only way to see whether Googlebot came.
try {
    \Athenaeum\Core\CrawlerJournal::observe(
        (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        (string) ($_SERVER['REQUEST_URI'] ?? ''),
        (string) ($_SERVER['REMOTE_ADDR'] ?? '')
    );
} catch (\Throwable $crawlerJournalError) {
    // Diagnostics must never break a request.
}

View::init(ATHENAEUM_ROOT . '/resources/views');
I18n::init();

require ATHENAEUM_ROOT . '/routes/web.php';

App::run();
