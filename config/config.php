<?php
/**
 * AthenXiv — active configuration.
 *
 * The values below are placeholders so a fresh clone boots. Put real
 * credentials in `config/config.local.php`, which is not versioned and is
 * merged on top of this file key by key (recursively) when it exists.
 */

$config = [
    'app' => [
        'name'        => 'AthenXiv',
        'env'         => 'production',
        'debug'       => false,
        'url'         => '',
        'timezone'    => 'UTC',
        'key'         => 'change-me-please-change-me-please',
        'trusted_proxies' => [],
    ],

    'db' => [
        // SQLite by default so a fresh clone runs with the commands in the
        // README. Switch to 'mysql' for a public deployment.
        'driver'      => 'sqlite',
        'host'        => '127.0.0.1',
        'port'        => 3306,
        'database'    => 'athenxiv',
        'username'    => 'athenxiv',
        'password'    => '',
        'charset'     => 'utf8mb4',
        'collation'   => 'utf8mb4_unicode_ci',
        'prefix'      => '',
        'sqlite_path' => __DIR__ . '/../storage/database/athenaeum.sqlite',
        'persistent'  => false,
    ],

    'paths' => [
        'storage' => __DIR__ . '/../storage',
        'uploads' => __DIR__ . '/../storage/uploads',
        'ots'     => __DIR__ . '/../storage/ots',
        'logs'    => __DIR__ . '/../storage/logs',
        'cache'   => __DIR__ . '/../storage/cache',
    ],

    'http' => [
        'ca_bundle'       => '',
        'timeout'         => 20,
        'connect_timeout' => 8,
        'user_agent'      => 'AthenXiv/1.0 (+https://opentimestamps.org/)',
        'verify_tls'      => true,
    ],

    'security' => [
        'session_name'        => 'athenaeum_session',
        'session_lifetime'    => 60 * 60 * 24 * 14,
        'password_min_length' => 10,
        'login_max_attempts'  => 8,
        'login_window'        => 900,
        'force_https'         => false,
    ],
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $override = require $local;
    if (is_array($override)) {
        $merge = static function (array $base, array $over) use (&$merge): array {
            foreach ($over as $key => $value) {
                if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                    $base[$key] = $merge($base[$key], $value);
                } else {
                    $base[$key] = $value;
                }
            }
            return $base;
        };
        $config = $merge($config, $override);
    }
}

return $config;
