<?php
/**
 * Upgrade pending OpenTimestamps proofs.
 *
 *   php bin/ots-upgrade.php                     # one pass, 25 proofs
 *   php bin/ots-upgrade.php --limit=100
 *   php bin/ots-upgrade.php --min-age=60        # retry anything older than 60s
 *   php bin/ots-upgrade.php --loop --interval=300
 *
 * Run it from cron every few minutes; the public calendars answer 404 until the
 * aggregation transaction is confirmed in a Bitcoin block (usually 1–3 hours).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$config = require $root . '/app/bootstrap.php';

use Athenaeum\Core\Config;
use Athenaeum\Core\Logger;
use Athenaeum\Core\Settings;
use Athenaeum\Services\OpenTimestamps;

Config::load($config);

$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--([a-z0-9\-]+)(?:=(.*))?$/i', $argument, $matches)) {
        $options[$matches[1]] = $matches[2] ?? true;
    }
}

$limit = isset($options['limit']) ? max(1, (int) $options['limit']) : 25;
$minAge = isset($options['min-age']) ? max(0, (int) $options['min-age']) : 900;
$loop = !empty($options['loop']);
$interval = isset($options['interval']) ? max(30, (int) $options['interval']) : 300;

if (!Settings::bool('ots.enabled')) {
    echo "OpenTimestamps is disabled in the settings; nothing to do.\n";
    exit(0);
}

$run = static function () use ($limit, $minAge): void {
    $started = microtime(true);
    $stats = OpenTimestamps::upgradePending($limit, $minAge);
    printf(
        "[%s] checked %d, upgraded %d, confirmed %d, failed %d (%.1fs)\n",
        gmdate('Y-m-d H:i:s'),
        $stats['checked'],
        $stats['upgraded'],
        $stats['confirmed'],
        $stats['failed'],
        microtime(true) - $started
    );
};

if (!$loop) {
    $run();
    exit(0);
}

echo "Looping every {$interval}s (Ctrl+C to stop).\n";
while (true) {
    $run();
    Logger::info('ots-upgrade loop pass');
    sleep($interval);
}
