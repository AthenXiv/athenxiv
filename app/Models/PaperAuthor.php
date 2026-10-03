<?php

declare(strict_types=1);

namespace Athenaeum\Models;

use Athenaeum\Core\Database;
use Athenaeum\Core\Model;

/**
 * Author entries on a paper. The submitting user is author #0; additional
 * authors are free text (they may or may not have an account here).
 */
final class PaperAuthor extends Model
{
    protected static string $table = 'paper_authors';

    protected static array $booleans = ['is_corresponding'];

    protected static bool $timestamps = false;

    /** @return array<int,array<string,mixed>> */
    public static function forPaper(int $paperId): array
    {
        return self::all(['paper_id' => $paperId], 'position ASC, id ASC');
    }

    /**
     * Replace the author list of a paper.
     *
     * @param array<int,array<string,mixed>> $authors
     */
    public static function sync(int $paperId, array $authors): void
    {
        $db = Database::instance();
        $db->delete('paper_authors', 'paper_id = :id', ['id' => $paperId]);
        $position = 0;
        foreach ($authors as $author) {
            $name = trim((string) ($author['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            self::create([
                'paper_id'         => $paperId,
                'position'         => $position++,
                'name'             => mb_substr($name, 0, 190),
                'affiliation'      => self::clean($author['affiliation'] ?? null, 190),
                'email'            => self::clean($author['email'] ?? null, 190),
                'orcid'            => self::clean($author['orcid'] ?? null, 32),
                'user_id'          => !empty($author['user_id']) ? (int) $author['user_id'] : null,
                'is_corresponding' => !empty($author['is_corresponding']) ? 1 : 0,
            ]);
        }
    }

    private static function clean(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        return mb_substr($value, 0, $max);
    }

    /** Papers where this user appears as a co-author (not the uploader). */
    public static function coAuthoredPaperIds(int $userId): array
    {
        $rows = Database::instance()->select(
            'SELECT paper_id FROM {{paper_authors}} WHERE user_id = :id',
            ['id' => $userId]
        );
        return array_map(static fn (array $row): int => (int) $row['paper_id'], $rows);
    }
}
