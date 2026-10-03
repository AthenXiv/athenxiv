<?php

declare(strict_types=1);

namespace Athenaeum\Core;

use RuntimeException;

/**
 * Plain-PHP template renderer: layouts, partials and automatic escaping.
 */
final class View
{
    /** @var array<string,mixed> */
    private static array $shared = [];

    private static string $viewPath = '';

    public static function init(string $viewPath): void
    {
        self::$viewPath = rtrim($viewPath, '/\\');
    }

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function exists(string $template): bool
    {
        return is_file(self::resolve($template));
    }

    public static function render(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::renderTemplate($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::renderTemplate($layout, array_merge($data, ['content' => $content]));
    }

    private static function renderTemplate(string $template, array $data): string
    {
        $file = self::resolve($template);
        if (!is_file($file)) {
            throw new RuntimeException('View not found: ' . $template);
        }
        $variables = array_merge(self::$shared, $data);
        extract($variables, EXTR_SKIP);
        ob_start();
        try {
            /** @psalm-suppress UnresolvedInclude */
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public static function partial(string $template, array $data = []): void
    {
        echo self::renderTemplate($template, $data);
    }

    private static function resolve(string $template): string
    {
        $path = self::$viewPath === '' ? ATHENAEUM_ROOT . '/resources/views' : self::$viewPath;
        return $path . '/' . str_replace('.', '/', $template) . '.php';
    }

    /** Full HTML error page (or JSON for API clients). */
    public static function error(int $status, string $message = ''): Response
    {
        $request = App::request();
        if ($request->wantsJson()) {
            return Response::json(['ok' => false, 'error' => $message ?: 'Error', 'status' => $status], $status);
        }
        $titles = [
            403 => __('common.error_403_title'),
            404 => __('common.error_404_title'),
            405 => __('common.error_405_title'),
            419 => __('common.error_419_title'),
            500 => __('common.error_500_title'),
        ];
        $html = self::render('errors/status', [
            'status'  => $status,
            'title'   => $titles[$status] ?? __('common.error_generic_title'),
            'message' => $message,
        ]);
        return Response::html($html, $status);
    }
}
