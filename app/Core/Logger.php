<?php

declare(strict_types=1);

namespace Athenaeum\Core;

/**
 * File logger (storage/logs/app-YYYY-MM-DD.log). Never throws.
 */
final class Logger
{
    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function write(string $level, string $message, array $context = []): void
    {
        try {
            $dir = Config::path('logs');
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $line = sprintf(
                "[%s] %s: %s%s\n",
                gmdate('Y-m-d H:i:s'),
                $level,
                $message,
                $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            @file_put_contents($dir . '/app-' . gmdate('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // Logging must never break a request.
        }
    }
}
