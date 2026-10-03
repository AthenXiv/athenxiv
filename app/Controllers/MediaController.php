<?php

declare(strict_types=1);

namespace Athenaeum\Controllers;

use Athenaeum\Core\Config;
use Athenaeum\Core\Request;
use Athenaeum\Core\Response;
use Athenaeum\Core\View;
use Athenaeum\Models\User;

/**
 * Streams files that live outside the web root: avatars and site branding.
 * Only whitelisted folders are reachable and paths are resolved, never
 * concatenated blindly.
 */
final class MediaController extends Controller
{
    private const KINDS = [
        'avatars'  => ['avatars', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']],
        'branding' => ['branding', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico']],
        'logo'     => ['branding', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']],
    ];

    public function show(Request $request, string $kind, string $file): Response
    {
        if (!isset(self::KINDS[$kind])) {
            return View::error(404);
        }
        [$folder, $allowedExtensions] = self::KINDS[$kind];

        $file = rawurldecode($file);
        if ($file === '' || str_contains($file, '..') || str_contains($file, "\0")) {
            return View::error(404);
        }
        // Only a single, flat filename is accepted here (avatars/branding are
        // stored flat); anything with a directory separator is refused.
        if (basename($file) !== $file) {
            return View::error(404);
        }
        $extension = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            return View::error(404);
        }

        $base = realpath(Config::path('uploads', $folder));
        $path = realpath(Config::path('uploads', $folder . '/' . $file));
        if ($base === false || $path === false || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
            return View::error(404);
        }

        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'svg'         => 'image/svg+xml',
            'ico'         => 'image/x-icon',
            default       => 'application/octet-stream',
        };

        $response = Response::file($path, basename($path), $mime, true);
        return $response->withHeader('Cache-Control', 'public, max-age=86400');
    }

    /**
     * Serve the bundled third-party assets (PDF.js) through PHP.
     *
     * Why: nginx has no MIME entry for `.mjs`, so it sends PDF.js's ES modules
     * as application/octet-stream and the browser refuses to execute them —
     * the viewer then renders a black page. Apache users were fine because
     * public/.htaccess adds the type; here the type has to come from us.
     */
    public function vendor(Request $request, string $path): Response
    {
        $path = rawurldecode($path);
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            return View::error(404);
        }

        $relative = 'vendor/' . ltrim($path, '/');
        $absolute = public_path($relative);          // handles both layouts
        if ($absolute === null) {
            return View::error(404);
        }

        $extension = strtolower((string) pathinfo($absolute, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'html', 'htm'  => 'text/html; charset=UTF-8',
            'js', 'mjs'    => 'text/javascript; charset=UTF-8',
            'css'          => 'text/css; charset=UTF-8',
            'json', 'map'  => 'application/json; charset=UTF-8',
            'wasm'         => 'application/wasm',
            'svg'          => 'image/svg+xml',
            'png'          => 'image/png',
            'jpg', 'jpeg'  => 'image/jpeg',
            'gif'          => 'image/gif',
            'ico'          => 'image/x-icon',
            'woff2'        => 'font/woff2',
            'woff'         => 'font/woff',
            'ttf'          => 'font/ttf',
            'otf'          => 'font/otf',
            'txt', 'md'    => 'text/plain; charset=UTF-8',
            default        => 'application/octet-stream',
        };

        // The viewer's own files change only when the bundle is upgraded.
        return Response::file($absolute, basename($absolute), $mime, true)
            ->withHeader('Cache-Control', 'public, max-age=604800');
    }
}
