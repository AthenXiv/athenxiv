<?php

declare(strict_types=1);

namespace Athenaeum\Core;

/**
 * A journal of search-engine visits.
 *
 * Shared hosting usually keeps the access log somewhere the customer cannot
 * reach, which makes "did Googlebot ever come?" unanswerable. This writes a
 * line per crawler request into storage/logs/crawlers-YYYY-MM.log, one file per
 * month, and the admin console lists the tail of it.
 *
 * Only recognised crawler user agents are recorded, so the file stays small
 * however busy the site gets.
 */
final class CrawlerJournal
{
    /** Recognised crawlers: pattern => label. */
    private const BOTS = [
        'googlebot-news'          => 'Googlebot-News',
        'googlebot-image'         => 'Googlebot-Image',
        'googlebot-video'         => 'Googlebot-Video',
        'storebot-google'         => 'Storebot-Google',
        'google-inspectiontool'   => 'Google-InspectionTool',
        'googleother'             => 'GoogleOther',
        'apis-google'             => 'APIs-Google',
        'mediapartners-google'    => 'Mediapartners-Google',
        'adsbot-google'           => 'AdsBot-Google',
        'googlebot'               => 'Googlebot',
        'google'                  => 'Google (other)',
        'bingbot'                 => 'Bingbot',
        'bingpreview'             => 'BingPreview',
        'msnbot'                  => 'MSNBot',
        'baiduspider'             => 'Baiduspider',
        'sogou'                   => 'Sogou',
        'yandex'                  => 'YandexBot',
        'duckduckbot'             => 'DuckDuckBot',
        'applebot'                => 'Applebot',
        'ahrefsbot'               => 'AhrefsBot',
        'semrushbot'              => 'SemrushBot',
        'petalbot'                => 'PetalBot',
        'facebookexternalhit'     => 'Facebook',
        'twitterbot'              => 'Twitterbot',
    ];

    public static function directory(?string $base = null): string
    {
        // An explicit base wins: the bootstrap hook runs before Config::load(),
        // where Config::path('logs') is still empty.
        if ($base !== null && $base !== '') {
            $directory = rtrim($base, '/\\') . '/logs';
        } else {
            $directory = Config::path('logs');
            if ($directory === '' || !is_dir(dirname($directory))) {
                $directory = (defined('ATHENAEUM_ROOT') ? ATHENAEUM_ROOT : dirname(__DIR__, 2)) . '/storage/logs';
            }
        }
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
        return $directory;
    }

    public static function fileFor(?string $month = null, ?string $base = null): string
    {
        return self::directory($base) . '/crawlers-' . ($month ?? date('Y-m')) . '.log';
    }

    /** The crawler name behind a user agent, or null when it is a normal visitor. */
    public static function identify(string $userAgent): ?string
    {
        $lower = strtolower($userAgent);
        if ($lower === '') {
            return null;
        }
        foreach (self::BOTS as $needle => $label) {
            if (str_contains($lower, $needle)) {
                return $label;
            }
        }
        return null;
    }

    /** Called once per request from the bootstrap; cheap for normal visitors. */
    public static function observe(string $userAgent, string $uri, string $ip, ?string $base = null): void
    {
        $bot = self::identify($userAgent);
        if ($bot === null) {
            return;
        }
        $directory = self::directory($base);
        if ($directory === '' || !is_dir($directory) || !is_writable($directory)) {
            return;
        }
        $line = sprintf(
            "%s\t%s\t%s\t%s\t%s\n",
            date('Y-m-d H:i:s'),
            $bot,
            $ip,
            mb_substr(str_replace(["\t", "\n", "\r"], ' ', $uri), 0, 300),
            mb_substr(str_replace(["\t", "\n", "\r"], ' ', $userAgent), 0, 220)
        );
        @file_put_contents(self::fileFor(null, $base), $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * The most recent crawler requests, newest first.
     *
     * @return array<int,array{time:string,bot:string,ip:string,path:string,ua:string}>
     */
    public static function recent(int $limit = 200): array
    {
        $files = array_filter([
            self::fileFor(),
            self::fileFor(date('Y-m', strtotime('-1 month'))),
        ], 'is_file');
        if ($files === []) {
            return [];
        }
        $lines = [];
        foreach ($files as $file) {
            $handle = @fopen($file, 'rb');
            if ($handle === false) {
                continue;
            }
            while (($line = fgets($handle)) !== false) {
                $lines[] = $line;
            }
            fclose($handle);
        }
        $lines = array_slice($lines, -max(1, $limit * 3));
        $rows = [];
        foreach (array_reverse($lines) as $line) {
            $parts = explode("\t", rtrim((string) $line, "\n"));
            if (count($parts) < 4) {
                continue;
            }
            $rows[] = [
                'time' => $parts[0],
                'bot'  => $parts[1],
                'ip'   => $parts[2],
                'path' => $parts[3],
                'ua'   => (string) ($parts[4] ?? ''),
            ];
            if (count($rows) >= $limit) {
                break;
            }
        }
        return $rows;
    }

    /**
     * Per-crawler totals and the last time each one was seen.
     *
     * @return array<int,array{bot:string,hits:int,last:string,last_path:string}>
     */
    public static function summary(): array
    {
        $byBot = [];
        foreach (self::recent(500) as $row) {
            $bot = $row['bot'];
            if (!isset($byBot[$bot])) {
                $byBot[$bot] = ['bot' => $bot, 'hits' => 0, 'last' => $row['time'], 'last_path' => $row['path']];
            }
            $byBot[$bot]['hits']++;
        }
        usort($byBot, static fn (array $a, array $b): int => strcmp($b['last'], $a['last']));
        return array_values($byBot);
    }

    public static function enabled(): bool
    {
        // A file per request would be noise; the journal is only useful when an
        // operator is watching for crawlers, which is the default.
        return Settings::bool('seo.crawler_journal', true);
    }
}
