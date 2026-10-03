<?php
/**
 * Set one setting from the command line. Used by the end-to-end scripts to put
 * the site into a known state (and to put it back) without an admin session.
 *
 *   php tests/fixtures/set_setting.php registration.verify_email 0
 *   php tests/fixtures/set_setting.php registration.verify_email 1
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/bootstrap.php';

use Athenaeum\Core\Config;
use Athenaeum\Core\Settings;

Config::load(require $root . '/config/config.php');

$key = $argv[1] ?? '';
$value = $argv[2] ?? '';
if ($key === '') {
    fwrite(STDERR, "usage: php set_setting.php <key> <value>\n");
    exit(2);
}

$before = Settings::get($key);
if (in_array(strtolower($value), ['0', 'false', 'no', 'off'], true)) {
    $value = false;
} elseif (in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true)) {
    $value = true;
} elseif (is_numeric($value)) {
    $value = str_contains($value, '.') ? (float) $value : (int) $value;
}

Settings::set($key, $value);
Settings::flush();

printf("%s: %s → %s\n", $key, var_export($before, true), var_export(Settings::get($key), true));
