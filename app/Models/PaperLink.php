<?php

declare(strict_types=1);

namespace Athenaeum\Models;

use Athenaeum\Core\Database;
use Athenaeum\Core\Model;

/**
 * External links attached to a paper (DOI, arXiv, data set, slides, video …).
 */
final class PaperLink extends Model
{
    protected static string $table = 'paper_links';

    protected static bool $timestamps = false;

    /** @return array<int,array<string,mixed>> */
    public static function forPaper(int $paperId): array
    {
        return self::all(['paper_id' => $paperId], 'sort_order ASC, id ASC');
    }

    /**
     * @param array<int,array{label?:string,url?:string,kind?:string}> $links
     */
    public static function sync(int $paperId, array $links): void
    {
        $db = Database::instance();
        $db->delete('paper_links', 'paper_id = :id', ['id' => $paperId]);
        $position = 0;
        foreach ($links as $link) {
            $url = trim((string) ($link['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            if (!preg_match('#^https?://#i', $url)) {
                $url = 'https://' . ltrim($url, '/');
            }
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            $kind = (string) ($link['kind'] ?? 'other');
            if (!in_array($kind, Paper::LINK_KINDS, true)) {
                $kind = 'other';
            }
            $label = trim((string) ($link['label'] ?? ''));
            self::create([
                'paper_id'   => $paperId,
                'kind'       => $kind,
                'label'      => mb_substr($label !== '' ? $label : ucfirst($kind), 0, 120),
                'url'        => mb_substr($url, 0, 500),
                'sort_order' => $position++,
                'created_at' => $db->now(),
            ]);
        }
    }

    public static function kindLabel(string $kind): string
    {
        $key = 'link_kind.' . $kind;
        $translated = __($key);
        return $translated === $key ? ucfirst($kind) : $translated;
    }
}
