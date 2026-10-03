<?php

declare(strict_types=1);

namespace Athenaeum\Core;

/**
 * Small utility belt: identifiers, formatting and text helpers.
 */
final class Str
{
    /**
     * "Last, First" — the author format Google Scholar asks for.
     *
     * Only plain Western-looking names are reordered; anything with CJK
     * characters, a comma or a single word is returned untouched, because a
     * wrong reordering is worse than a name Scholar accepts as-is.
     */
    public static function citationAuthor(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        if ($name === '' || str_contains($name, ',') || preg_match('/[^\p{Latin}\s.\-\'’]/u', $name) === 1) {
            return $name;
        }
        $parts = explode(' ', $name);
        if (count($parts) < 2) {
            return $name;
        }
        // "LHD Experiment Group", "ATLAS Collaboration": a collective, not a
        // person — reordering would turn it into nonsense.
        foreach (['group', 'collaboration', 'collaborators', 'consortium', 'team', 'committee', 'network'] as $collective) {
            if (in_array($collective, array_map('mb_strtolower', $parts), true)) {
                return $name;
            }
        }
        $last = array_pop($parts);
        return $last . ', ' . implode(' ', $parts);
    }

    private const UID_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // no 0/O/1/I

    /** Public user id, e.g. "U7K4M2QF". Nicknames may collide, uids may not. */
    public static function uid(string $prefix = 'U', int $length = 8): string
    {
        $out = $prefix;
        $max = strlen(self::UID_ALPHABET) - 1;
        for ($i = 0; $i < $length; $i++) {
            $out .= self::UID_ALPHABET[random_int(0, $max)];
        }
        return $out;
    }

    /** Public paper id, e.g. "ATH-8F3K2Q". */
    public static function paperUid(): string
    {
        return 'ATH-' . self::uid('', 6);
    }

    public static function token(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /** Filesystem-safe, still readable slug. Keeps CJK characters. */
    public static function slug(string $text, int $maxLength = 80): string
    {
        $text = trim($text);
        $text = preg_replace('/[\p{Z}\s]+/u', '-', $text) ?? $text;
        $text = preg_replace('/[^\p{L}\p{N}\-_.]+/u', '', $text) ?? $text;
        $text = preg_replace('/-+/', '-', $text) ?? $text;
        $text = trim($text, '-_.');
        if ($text === '') {
            $text = 'item';
        }
        if (mb_strlen($text) > $maxLength) {
            $text = mb_substr($text, 0, $maxLength);
            $text = rtrim($text, '-_.');
        }
        return mb_strtolower($text);
    }

    public static function excerpt(?string $text, int $length = 240): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text) ?? '');
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $length)) . '…';
    }

    /** Safe-ish HTML fragment from an uploaded filename. */
    public static function filename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? $name;
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'file';
        }
        return mb_substr($name, 0, 180);
    }

    public static function humanSize(int|float|null $bytes): string
    {
        $bytes = (float) ($bytes ?? 0);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;
        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }
        $precision = $bytes >= 100 || $index === 0 ? 0 : 1;
        return number_format($bytes, $precision) . ' ' . $units[$index];
    }

    public static function maskIp(string $ip): string
    {
        if (str_contains($ip, ':')) {
            $parts = explode(':', $ip);
            return implode(':', array_slice($parts, 0, 3)) . '::/48';
        }
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.x';
        }
        return $ip;
    }

    /** Absolute URL for a storage-relative path handled by the media route. */
    public static function url(string $path): string
    {
        return Config::baseUrl() . '/' . ltrim($path, '/');
    }

    public static function randomHexColor(string $seed): string
    {
        $hash = substr(md5($seed), 0, 6);
        return '#' . $hash;
    }

    /** "3 min ago" style label, localised through the lang files. */
    public static function timeAgo(?string $datetime): string
    {
        if ($datetime === null || $datetime === '') {
            return '—';
        }
        $timestamp = strtotime($datetime . ' UTC');
        if ($timestamp === false) {
            return $datetime;
        }
        $diff = time() - $timestamp;
        if ($diff < 0) {
            $diff = 0;
        }
        $units = [
            ['year', 31536000],
            ['month', 2592000],
            ['day', 86400],
            ['hour', 3600],
            ['minute', 60],
        ];
        foreach ($units as [$unit, $seconds]) {
            if ($diff >= $seconds) {
                $value = (int) floor($diff / $seconds);
                return __('time.' . $unit, ['count' => $value]);
            }
        }
        return __('time.just_now');
    }
}
