<?php

declare(strict_types=1);

namespace Athenaeum\Services;

use Athenaeum\Core\Logger;

/**
 * Best-effort PDF text extraction in pure PHP — no pdftotext, no extension.
 *
 * The AI reviewer works from the abstract alone if this fails, so the goal is
 * "usually right, never fatal": the extractor understands uncompressed and
 * FlateDecode content streams, PDF literal `(…)` strings, `TJ` arrays and
 * UTF-16BE strings. Scanned or exotic PDFs simply return less text.
 */
final class PdfText
{
    private const MAX_BYTES = 16 * 1024 * 1024;

    /**
     * @return array{ok:bool,text:string,pages:int,chars:int,streams:int,error:?string}
     */
    public static function extract(string $path, int $maxChars = 20000): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return self::failure('file not readable');
        }
        $size = (int) filesize($path);
        if ($size <= 0) {
            return self::failure('empty file');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return self::failure('cannot open file');
        }
        $raw = (string) fread($handle, min($size, self::MAX_BYTES));
        fclose($handle);

        if (!str_starts_with($raw, '%PDF-')) {
            return self::failure('not a PDF');
        }

        $pages = preg_match_all('#/Type\s*/Page[^s]#', $raw) ?: 0;
        $streams = 0;
        $texts = [];

        $offset = 0;
        while (($start = strpos($raw, 'stream', $offset)) !== false) {
            $headerStart = strrpos(substr($raw, 0, $start), '<<');
            $dictionary = $headerStart !== false ? substr($raw, $headerStart, $start - $headerStart) : '';
            // Skip `endstream`/`endobj` keywords that contain "stream" too.
            $after = substr($raw, $start + 6, 2);
            if ($after === "\r\n") {
                $contentStart = $start + 8;
            } elseif ($after[0] === "\n" || $after[0] === "\r") {
                $contentStart = $start + 7;
            } else {
                $offset = $start + 6;
                continue;
            }
            $end = strpos($raw, 'endstream', $contentStart);
            if ($end === false) {
                break;
            }
            $offset = $end + 9;

            $data = substr($raw, $contentStart, $end - $contentStart);
            $streams++;

            if (stripos($dictionary, 'FlateDecode') !== false) {
                $inflated = @gzuncompress($data);
                if ($inflated === false) {
                    $inflated = @gzinflate($data);
                }
                if ($inflated === false) {
                    continue;
                }
                $data = $inflated;
            } elseif (stripos($dictionary, 'Filter') !== false) {
                // DCTDecode (images), LZW, … — nothing textual to take.
                continue;
            }

            $text = self::textFromContentStream($data);
            if ($text !== '') {
                $texts[] = $text;
            }
            if (mb_strlen(implode("\n", $texts)) > $maxChars * 2) {
                break; // enough material already
            }
        }

        $text = self::tidy(implode("\n", $texts));
        if ($text === '') {
            return [
                'ok'      => false,
                'text'    => '',
                'pages'   => $pages,
                'chars'   => 0,
                'streams' => $streams,
                'error'   => 'no extractable text (scanned or image-only PDF?)',
            ];
        }
        if (mb_strlen($text) > $maxChars) {
            $text = mb_substr($text, 0, $maxChars);
        }

        return [
            'ok'      => true,
            'text'    => $text,
            'pages'   => $pages,
            'chars'   => mb_strlen($text),
            'streams' => $streams,
            'error'   => null,
        ];
    }

    /** Pull the visible strings out of one content stream. */
    private static function textFromContentStream(string $content): string
    {
        $out = [];
        $length = strlen($content);
        $index = 0;

        while ($index < $length) {
            $char = $content[$index];

            if ($char === '(') {
                [$literal, $index] = self::readLiteralString($content, $index);
                $out[] = $literal;
                continue;
            }
            if ($char === '<' && $index + 1 < $length && $content[$index + 1] !== '<') {
                $close = strpos($content, '>', $index);
                if ($close === false) {
                    break;
                }
                $hex = preg_replace('/[^0-9A-Fa-f]/', '', substr($content, $index + 1, $close - $index - 1)) ?? '';
                if (strlen($hex) >= 2 && strlen($hex) % 2 === 0) {
                    $out[] = self::decodeHexString((string) hex2bin($hex));
                }
                $index = $close + 1;
                continue;
            }
            // Text-positioning operators imply a break; cheap but effective.
            if ($char === 'T' && $index + 1 < $length && in_array($content[$index + 1], ['*', 'd', 'D', 'T'], true)) {
                $out[] = "\n";
            }
            $index++;
        }

        return self::decodeEscapes(implode('', $out));
    }

    /** @return array{0:string,1:int} */
    private static function readLiteralString(string $content, int $index): array
    {
        $depth = 0;
        $out = '';
        $length = strlen($content);
        $position = $index;

        while ($position < $length) {
            $char = $content[$position];
            if ($char === '\\') {
                $out .= $char . ($content[$position + 1] ?? '');
                $position += 2;
                continue;
            }
            if ($char === '(') {
                $depth++;
                if ($depth === 1) {
                    $position++;
                    continue;
                }
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $position++;
                    break;
                }
            }
            $out .= $char;
            $position++;
        }

        return [$out, $position];
    }

    private static function decodeEscapes(string $text): string
    {
        $text = preg_replace_callback(
            '/\\\\([nrtbf()\\\\]|[0-7]{1,3})/',
            static function (array $m): string {
                $escape = $m[1];
                return match ($escape) {
                    'n' => "\n",
                    'r' => "\n",
                    't' => "\t",
                    'b', 'f' => '',
                    '(', ')', '\\' => $escape,
                    default => chr((int) octdec($escape)),
                };
            },
            $text
        ) ?? $text;

        return self::decodeHexString($text);
    }

    /** PDF text is usually PDFDocEncoding/Latin-1, sometimes UTF-16BE. */
    private static function decodeHexString(string $text): string
    {
        if (str_starts_with($text, "\xFE\xFF")) {
            $converted = @mb_convert_encoding(substr($text, 2), 'UTF-8', 'UTF-16BE');
            return is_string($converted) ? $converted : '';
        }
        if (str_starts_with($text, "\xFF\xFE")) {
            $converted = @mb_convert_encoding(substr($text, 2), 'UTF-8', 'UTF-16LE');
            return is_string($converted) ? $converted : '';
        }
        foreach (['UTF-8', 'Windows-1252', 'ISO-8859-1'] as $encoding) {
            $converted = @mb_convert_encoding($text, 'UTF-8', $encoding);
            if (is_string($converted) && $converted !== '' && mb_check_encoding($converted, 'UTF-8')) {
                // Prefer the first encoding that produces no replacement chars.
                if (!str_contains($converted, "\u{FFFD}")) {
                    return $converted;
                }
            }
        }
        return $text;
    }

    private static function tidy(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        $text = preg_replace('/[^\P{C}\n\t]/u', '', $text) ?? $text;
        return trim($text);
    }

    /** @return array{ok:bool,text:string,pages:int,chars:int,streams:int,error:?string} */
    private static function failure(string $error): array
    {
        return ['ok' => false, 'text' => '', 'pages' => 0, 'chars' => 0, 'streams' => 0, 'error' => $error];
    }

    /** Convenience wrapper that logs failures once, for the AI pipeline. */
    public static function extractQuietly(string $path, int $maxChars = 20000): string
    {
        $result = self::extract($path, $maxChars);
        if (!$result['ok'] && $result['error'] !== null) {
            Logger::info('pdf text extraction returned nothing', [
                'file'  => basename($path),
                'error' => $result['error'],
            ]);
        }
        return $result['text'];
    }
}
