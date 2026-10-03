<?php

declare(strict_types=1);

namespace Athenaeum\Core;

/**
 * Safe Markdown rendering for user profile pages and admin notices.
 *
 * Uses Parsedown when it has been vendored into app/Support/Parsedown.php,
 * otherwise falls back to a compact built-in subset renderer. Either way raw
 * HTML is escaped and links are hardened.
 */
final class Markdown
{
    private static mixed $parsedown = null;

    private static bool $parsedownChecked = false;

    public static function render(string $markdown, int $maxLength = 20000): string
    {
        $markdown = trim($markdown);
        if ($markdown === '') {
            return '';
        }
        if (mb_strlen($markdown) > $maxLength) {
            $markdown = mb_substr($markdown, 0, $maxLength);
        }

        $parser = self::parsedown();
        if ($parser !== null) {
            $parser->setSafeMode(true);
            $parser->setBreaksEnabled(true);
            $html = $parser->text($markdown);
        } else {
            $html = self::fallback($markdown);
        }

        return self::harden($html);
    }

    public static function toPlainText(string $markdown, int $length = 240): string
    {
        $text = preg_replace('/[`*_>#\[\]()!~-]+/u', ' ', $markdown) ?? $markdown;
        return Str::excerpt($text, $length);
    }

    private static function parsedown(): ?object
    {
        if (self::$parsedownChecked) {
            return is_object(self::$parsedown) ? self::$parsedown : null;
        }
        self::$parsedownChecked = true;

        $file = ATHENAEUM_ROOT . '/app/Support/Parsedown.php';
        if (is_file($file)) {
            require_once $file;
            if (class_exists('Parsedown')) {
                self::$parsedown = new \Parsedown();
            }
        }
        return is_object(self::$parsedown) ? self::$parsedown : null;
    }

    /**
     * Best-effort hardening: external links get target/rel, scripts and event
     * handlers are stripped even if a parser let them through.
     */
    public static function harden(string $html): string
    {
        $html = preg_replace('#<\s*(script|iframe|object|embed|form|input|link|meta|base)\b[^>]*>#i', '', $html) ?? $html;
        $html = preg_replace('#</\s*(script|iframe|object|embed|form)\s*>#i', '', $html) ?? $html;
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/\s(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*\2/i', ' $1="#"', $html) ?? $html;

        $html = preg_replace_callback(
            '/<a\s+([^>]*)>/i',
            static function (array $m): string {
                $attributes = $m[1];
                $href = '';
                if (preg_match('/href\s*=\s*"([^"]*)"/i', $attributes, $hm)) {
                    $href = $hm[1];
                } elseif (preg_match("/href\s*=\s*'([^']*)'/i", $attributes, $hm)) {
                    $href = $hm[1];
                }
                $isExternal = preg_match('#^https?://#i', $href) === 1
                    && !str_starts_with($href, Config::baseUrl());
                if ($isExternal) {
                    $attributes = preg_replace('/\s(rel|target)\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $attributes) ?? $attributes;
                    return '<a ' . trim($attributes) . ' rel="nofollow ugc noopener noreferrer" target="_blank">';
                }
                return '<a ' . $attributes . '>';
            },
            $html
        ) ?? $html;

        return $html;
    }

    /** Compact renderer used when Parsedown is not vendored. */
    private static function fallback(string $markdown): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];
        $html = [];
        $inList = false;
        $inCode = false;
        $paragraph = [];

        $flushParagraph = static function () use (&$paragraph, &$html): void {
            if ($paragraph !== []) {
                $html[] = '<p>' . implode('<br>', $paragraph) . '</p>';
                $paragraph = [];
            }
        };
        $closeList = static function () use (&$inList, &$html): void {
            if ($inList) {
                $html[] = '</ul>';
                $inList = false;
            }
        };

        foreach ($lines as $line) {
            $raw = $line;
            $escaped = htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            if (preg_match('/^```/', $raw)) {
                $flushParagraph();
                $closeList();
                $html[] = $inCode ? '</code></pre>' : '<pre><code>';
                $inCode = !$inCode;
                continue;
            }
            if ($inCode) {
                $html[] = $escaped;
                continue;
            }
            if (trim($raw) === '') {
                $flushParagraph();
                $closeList();
                continue;
            }
            if (preg_match('/^(#{1,6})\s+(.*)$/u', $raw, $m)) {
                $flushParagraph();
                $closeList();
                $level = min(6, strlen($m[1]));
                $html[] = sprintf('<h%d>%s</h%d>', $level, self::inline($m[2]), $level);
                continue;
            }
            if (preg_match('/^\s*([-*+])\s+(.*)$/u', $raw, $m)) {
                $flushParagraph();
                if (!$inList) {
                    $html[] = '<ul>';
                    $inList = true;
                }
                $html[] = '<li>' . self::inline($m[2]) . '</li>';
                continue;
            }
            if (preg_match('/^\s*>\s?(.*)$/u', $raw, $m)) {
                $flushParagraph();
                $closeList();
                $html[] = '<blockquote>' . self::inline($m[1]) . '</blockquote>';
                continue;
            }
            if (preg_match('/^\s*(?:---|\*\*\*|___)\s*$/u', $raw)) {
                $flushParagraph();
                $closeList();
                $html[] = '<hr>';
                continue;
            }
            $paragraph[] = self::inline($raw);
        }

        $flushParagraph();
        $closeList();
        if ($inCode) {
            $html[] = '</code></pre>';
        }
        return implode("\n", $html);
    }

    private static function inline(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/u', '<em>$1</em>', $escaped) ?? $escaped;
        $escaped = preg_replace('/~~([^~]+)~~/u', '<del>$1</del>', $escaped) ?? $escaped;
        $escaped = preg_replace(
            '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/u',
            '<a href="$2">$1</a>',
            $escaped
        ) ?? $escaped;
        $escaped = preg_replace(
            '/(?<!["\'>])(https?:\/\/[^\s<]+)/u',
            '<a href="$1">$1</a>',
            $escaped
        ) ?? $escaped;
        return $escaped;
    }
}
