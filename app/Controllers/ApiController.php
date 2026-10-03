<?php

declare(strict_types=1);

namespace Athenaeum\Controllers;

use Athenaeum\Core\Auth;
use Athenaeum\Core\Request;
use Athenaeum\Core\Response;
use Athenaeum\Core\Settings;
use Athenaeum\Models\Paper;
use Athenaeum\Models\Section;
use Athenaeum\Models\Timestamp;
use Athenaeum\Services\OpenTimestamps;

/**
 * Read-only JSON endpoints. Useful for the search box and for admins who want
 * to script the OpenTimestamps upgrade cycle from cron over HTTP.
 */
final class ApiController extends Controller
{
    public function papers(Request $request): Response
    {
        $filters = [
            'section'  => $request->str('section'),
            'category' => $request->str('category'),
            'language' => $request->str('language'),
            'q'        => $request->str('q'),
            'sort'     => $request->str('sort', 'newest'),
        ];
        $filters = array_filter($filters, static fn ($value): bool => $value !== '');

        $result = Paper::search(
            $filters,
            max(1, $request->int('page', 1)),
            min(50, max(1, $request->int('per_page', 12)))
        );

        $items = array_map(static function (array $paper): array {
            return [
                'uid'          => $paper['uid'],
                'title'        => $paper['title'],
                'abstract'     => $paper['abstract'],
                'language'     => $paper['language'],
                'authors'      => Paper::authorLine($paper),
                'published_at' => $paper['published_at'],
                'url'          => Paper::publicUrl($paper),
                'pdf_url'      => url('paper.file', ['uid' => $paper['uid']]),
                'downloads'    => (int) $paper['downloads'],
                'views'        => (int) $paper['views'],
                'doi'          => $paper['doi'],
            ];
        }, $result['items']);

        return $this->json([
            'ok'    => true,
            'total' => $result['total'],
            'page'  => $result['page'],
            'pages' => $result['pages'],
            'items' => $items,
        ]);
    }

    public function search(Request $request): Response
    {
        $term = $request->str('q');
        if (mb_strlen($term) < 2) {
            return $this->json(['ok' => true, 'items' => []]);
        }
        $result = Paper::search(['q' => $term], 1, 8);
        return $this->json([
            'ok'    => true,
            'items' => array_map(static fn (array $paper): array => [
                'uid'    => $paper['uid'],
                'title'  => $paper['title'],
                'author' => Paper::authorLine($paper),
                'url'    => Paper::publicUrl($paper),
            ], $result['items']),
        ]);
    }

    public function stats(): Response
    {
        return $this->json([
            'ok'      => true,
            'papers'  => Paper::approvedCount(),
            'pending' => Paper::pendingCount(),
            'stamped' => (int) \Athenaeum\Core\Database::instance()->scalar('SELECT COUNT(*) FROM {{timestamps}}'),
        ]);
    }

    /** POST /api/ots/upgrade — run the OpenTimestamps upgrade cycle. */
    public function upgradeTimestamps(Request $request): Response
    {
        $limit = max(1, min(200, $request->int('limit', 25)));
        $stats = OpenTimestamps::upgradePending($limit, 0);
        return $this->json(['ok' => true, 'stats' => $stats]);
    }
}
