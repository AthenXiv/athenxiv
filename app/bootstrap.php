<?php
/**
 * AthenXiv — bootstrap.
 *
 * Zero Composer runtime dependencies on purpose: the whole platform must run
 * on an ordinary shared host (virtual hosting) with plain PHP + PDO, which is
 * also what the Google Scholar indexing guidelines expect of a paper host.
 */

declare(strict_types=1);

if (defined('ATHENAEUM_BOOTSTRAPPED')) {
    // Idempotent: a second `require` (a web installer that pulls in a bin/
    // script, a test harness, …) must still receive the configuration instead
    // of null.
    return $GLOBALS['ATHENAEUM_CONFIG'] ?? [];
}
define('ATHENAEUM_BOOTSTRAPPED', true);

define('ATHENAEUM_ROOT', dirname(__DIR__));
define('ATHENAEUM_START', microtime(true));
define('ATHENAEUM_VERSION', '1.0.0');

// ---------------------------------------------------------------------------
// Error handling
// ---------------------------------------------------------------------------
$configFile = ATHENAEUM_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    echo '<h1>AthenXiv is not configured</h1>'
       . '<p>Copy <code>config/config.example.php</code> to <code>config/config.php</code>.</p>';
    exit(1);
}

/** @var array $ATHENAEUM_CONFIG */
$ATHENAEUM_CONFIG = require $configFile;

$debug = !empty($ATHENAEUM_CONFIG['app']['debug']);
error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

// Some hosts force display_errors=On with php_admin_value (the control panel
// writes it into the PHP-FPM pool), and neither ini_set nor .user.ini can
// override that — a warning would then be printed in the middle of a page,
// server path and all. Taking the error over ourselves makes the output
// independent of the host setting: every reported warning is logged instead of
// printed, and `return true` tells PHP not to print it as well.
set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
    if ((error_reporting() & $severity) === 0) {
        return true; // silenced with @ or below the reporting level
    }
    try {
        \Athenaeum\Core\Logger::warning($message, ['file' => $file, 'line' => $line]);
    } catch (Throwable) {
        error_log($message . ' in ' . $file . ':' . $line);
    }
    return true;
});

date_default_timezone_set($ATHENAEUM_CONFIG['app']['timezone'] ?? 'UTC');

// ---------------------------------------------------------------------------
// Autoloader (PSR-4-ish, no Composer required)
// ---------------------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Athenaeum\\Core\\'          => ATHENAEUM_ROOT . '/app/Core/',
        'Athenaeum\\Models\\'        => ATHENAEUM_ROOT . '/app/Models/',
        'Athenaeum\\Services\\'      => ATHENAEUM_ROOT . '/app/Services/',
        'Athenaeum\\Controllers\\'   => ATHENAEUM_ROOT . '/app/Controllers/',
        'Athenaeum\\Support\\'       => ATHENAEUM_ROOT . '/app/Support/',
    ];
    foreach ($prefixes as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            $file = $dir . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});

// ---------------------------------------------------------------------------
// Storage layout (created on first run, also by bin/install.php)
// ---------------------------------------------------------------------------
$storage = $ATHENAEUM_CONFIG['paths']['storage'];
$directories = [
    $storage,
    $ATHENAEUM_CONFIG['paths']['uploads'],
    $ATHENAEUM_CONFIG['paths']['uploads'] . '/papers',
    $ATHENAEUM_CONFIG['paths']['uploads'] . '/attachments',
    $ATHENAEUM_CONFIG['paths']['uploads'] . '/avatars',
    $ATHENAEUM_CONFIG['paths']['uploads'] . '/branding',
    $ATHENAEUM_CONFIG['paths']['ots'],
    $ATHENAEUM_CONFIG['paths']['logs'],
    $ATHENAEUM_CONFIG['paths']['cache'],
];
// Only a SQLite deployment needs a directory for the database file; creating
// it on MySQL leaves an unused folder inside the web root.
if (($ATHENAEUM_CONFIG['db']['driver'] ?? 'sqlite') === 'sqlite' && !empty($ATHENAEUM_CONFIG['db']['sqlite_path'])) {
    $directories[] = dirname($ATHENAEUM_CONFIG['db']['sqlite_path']);
}
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

$GLOBALS['ATHENAEUM_CONFIG'] = $ATHENAEUM_CONFIG;

require ATHENAEUM_ROOT . '/app/helpers.php';


return $ATHENAEUM_CONFIG;
