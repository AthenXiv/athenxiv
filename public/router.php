<?php
/**
 * Local development server front controller.
 *
 *   php -S 127.0.0.1:8080 -t public public/router.php
 *
 * Serves real files from public/ untouched and routes everything else through
 * the application, which is what Apache/nginx do with the rewrite rules in
 * public/.htaccess on a real host.
 */

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$candidate = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $path);

if ($path !== '/' && is_file($candidate)) {
    return false; // let the built-in server stream the static file
}

require __DIR__ . '/index.php';
