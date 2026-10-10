<?php
/**
 * AthenXiv — configuration template.
 *
 * Copy this file to `config/config.php` and edit it, or copy it to
 * `config/config.local.php`, which overrides `config/config.php` when present
 * (useful for keeping production credentials out of version control).
 *
 * Every value can also be overridden with an environment variable:
 *   ATHENAEUM_APP_URL, ATHENAEUM_DB_DRIVER, ATHENAEUM_DB_HOST, ...
 * See app/Core/Config.php for the mapping.
 */

return [
    'app' => [
        // Human readable name. The *displayed* name is editable in the admin
        // panel (Settings → Site); this one is only a boot-time fallback.
        'name'        => 'AthenXiv',
        'env'         => 'production',            // production | development
        'debug'       => false,                   // never true on a public host
        // Public base URL, no trailing slash. Leave empty to auto-detect.
        'url'         => '',
        'timezone'    => 'UTC',
        // 32+ random bytes. Used to sign cookies / one-time tokens.
        // Generate with: php -r "echo bin2hex(random_bytes(32));"
        'key'         => 'change-me-please-change-me-please',
        'trusted_proxies' => [],                  // e.g. ['127.0.0.1'] behind nginx
    ],

    'db' => [
        // 'mysql' for production, 'sqlite' for local verification.
        'driver'      => 'mysql',
        // Placeholders only. Put real credentials in config/config.local.php,
        // which is not versioned — never in this file or in config.php.
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
        // Files uploaded by users live OUTSIDE the web root and are streamed
        // through PHP. Never expose storage/ directly to the internet.
        'storage'  => __DIR__ . '/../storage',
        'uploads'  => __DIR__ . '/../storage/uploads',
        'ots'      => __DIR__ . '/../storage/ots',
        'logs'     => __DIR__ . '/../storage/logs',
        'cache'    => __DIR__ . '/../storage/cache',
    ],

    'http' => [
        // CA bundle used to verify TLS peers (OpenTimestamps calendars).
        // '' = auto-detect from openssl.cafile / common system locations.
        'ca_bundle'      => '',
        'timeout'        => 20,
        'connect_timeout' => 8,
        'user_agent'     => 'AthenXiv/1.0 (+https://opentimestamps.org/)',
        // Set to false only on a host with a broken TLS stack; the app then
        // refuses to talk to calendars instead of silently downgrading.
        'verify_tls'     => true,
    ],

    'security' => [
        'session_name'        => 'athenaeum_session',
        'session_lifetime'    => 60 * 60 * 24 * 14,
        'password_min_length' => 10,
        'login_max_attempts'  => 8,      // per IP+email within the window
        'login_window'        => 900,    // seconds
        'force_https'         => false,  // true when served over TLS
    ],
];
