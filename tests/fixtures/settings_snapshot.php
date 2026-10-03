<?php
/**
 * Save or restore the live AI / mail / notice configuration.
 *
 * The end-to-end tests have to point the application at local fixtures
 * (127.0.0.1 SMTP server, mock OpenAI endpoint). Without this helper they would
 * leave those test values behind in the real settings — which is exactly how a
 * production site ends up trying to send mail to 127.0.0.1:8025.
 *
 *   php tests/fixtures/settings_snapshot.php save    /tmp/snapshot.json
 *   php tests/fixtures/settings_snapshot.php restore /tmp/snapshot.json
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/bootstrap.php';

use Athenaeum\Core\Config;
use Athenaeum\Core\Settings;

Config::load(require $root . '/config/config.php');

$action = $argv[1] ?? '';
$file = $argv[2] ?? (sys_get_temp_dir() . '/athenaeum-settings-snapshot.json');

// Only the keys a test run may touch. `site.` is in the list because the
// end-to-end scripts post the whole settings form back; `ui.`/`upload.`/`ots.`
// for the same reason.
$prefixes = [
    'ai.', 'mail.', 'notice.', 'home.notice', 'versions.', 'registration.',
    'site.', 'ui.', 'upload.', 'ots.',
];

$snapshot = static function () use ($prefixes): array {
    $out = [];
    foreach (Settings::all() as $key => $value) {
        foreach ($prefixes as $prefix) {
            if (str_starts_with((string) $key, $prefix)) {
                $out[(string) $key] = $value;
                break;
            }
        }
    }
    return $out;
};

if ($action === 'save') {
    // Refuse to write a snapshot of the *defaults*: on a machine whose php.ini
    // does not load a PDO driver, Settings::all() silently returns the built-in
    // defaults, and restoring that file would wipe the operator's real SMTP and
    // AI configuration. Fail loudly instead so the caller can retry properly.
    if (!Settings::loadedFromDatabase()) {
        fwrite(STDERR, "cannot read the settings table: " . (Settings::loadError() ?? 'unknown error')
            . "\n  hint: run this with pdo_sqlite loaded (PHP_CMD=\"php -d extension=pdo_sqlite\")\n");
        exit(3);
    }
    $data = $snapshot();
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo 'saved ' . count($data) . " key(s) to {$file}\n";
    exit(0);
}

if ($action === 'restore') {
    if (!is_file($file)) {
        fwrite(STDERR, "no snapshot at {$file}\n");
        exit(1);
    }
    if (!Settings::loadedFromDatabase()) {
        fwrite(STDERR, "cannot read the settings table: " . (Settings::loadError() ?? 'unknown error') . "\n");
        exit(3);
    }
    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data)) {
        fwrite(STDERR, "snapshot is not valid JSON\n");
        exit(1);
    }
    $current = $snapshot();
    foreach ($data as $key => $value) {
        Settings::set($key, $value);
    }
    // Anything the test added that was not there before is removed.
    foreach ($current as $key => $value) {
        if (!array_key_exists($key, $data)) {
            Settings::set($key, '');
        }
    }
    Settings::flush();
    echo 'restored ' . count($data) . " key(s) from {$file}\n";
    exit(0);
}

fwrite(STDERR, "usage: php settings_snapshot.php save|restore [file]\n");
exit(2);
