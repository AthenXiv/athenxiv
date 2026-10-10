<?php

declare(strict_types=1);

namespace Athenaeum\Controllers;

use Athenaeum\Core\App;
use Athenaeum\Core\Config;
use Athenaeum\Core\I18n;
use Athenaeum\Core\Request;
use Athenaeum\Core\Response;
use Athenaeum\Core\Settings;
use Athenaeum\Models\Category;
use Athenaeum\Models\Paper;
use Athenaeum\Models\Section;
use Athenaeum\Models\Timestamp;
use Athenaeum\Models\User;
use Athenaeum\Services\PageTimestamps;

/**
 * Public landing pages, plus the machine readable files Google needs
 * (robots.txt and sitemap.xml) which are part of being indexed at all.
 */
final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = Settings::int('ui.papers_per_page', 12);

        $latest = Paper::search([], 1, $perPage);
        $featured = Paper::search(['featured' => true], 1, 6);
        $sections = Section::withCounts(true);
        // The subject tree has hundreds of nodes: the home page links to the
        // search interface instead of rendering them all as tags.
        $areaCount = count(Category::flat(false));

        return $this->view('home/index', [
            'latest'      => $latest,
            'featured'    => $featured['items'],
            'sections'    => $sections,
            'areaCount'   => $areaCount,
            'stats'       => [
                'papers'    => Paper::approvedCount(),
                'sections'  => count($sections),
                'authors'   => (int) \Athenaeum\Core\Database::instance()->scalar(
                    'SELECT COUNT(DISTINCT uploader_id) FROM {{papers}} WHERE status = :s',
                    ['s' => Paper::STATUS_APPROVED]
                ),
                'timestamped' => (int) \Athenaeum\Core\Database::instance()->scalar(
                    'SELECT COUNT(*) FROM {{timestamps}}'
                ),
            ],
            'notice'      => Settings::string('home.notice'),
        ]);
    }

    // =====================================================================
    // Editable content pages
    // =====================================================================

    /** 关于本站 — the main "about" page linked from the navigation. */
    public function about(Request $request): Response
    {
        return $this->renderPage('about');
    }

    /** 关于本站（AthenXiv）*/
    public function athenaeum(Request $request): Response
    {
        return $this->renderPage('athenaeum');
    }

    /** 用户政策 — the registration form asks the author to accept this page. */
    public function policy(Request $request): Response
    {
        return $this->renderPage('policy');
    }

    /** 时间戳存证如何运作 — linked from the footer. */
    public function timestamping(Request $request): Response
    {
        return $this->renderPage('timestamping', [
            'calendars' => \Athenaeum\Services\OpenTimestamps::calendars(),
            'verifyUrl' => \Athenaeum\Services\OpenTimestamps::verifyUrl(),
        ]);
    }

    /** 投稿指南 */
    public function guidelines(Request $request): Response
    {
        return $this->renderPage('guidelines', [
            'limits' => [
                'pdf'      => human_size(\Athenaeum\Services\PaperService::limits()['pdf']),
                'archive'  => human_size(\Athenaeum\Services\PaperService::limits()['attachment']),
                'count'    => Settings::int('upload.max_attachments', 5),
                'extensions' => implode(', ', \Athenaeum\Models\Attachment::allowedExtensions()),
                'contact'  => Settings::string('site.contact_email'),
            ],
        ]);
    }

    /** Any additional page an administrator creates (/p/{slug}). */
    public function customPage(Request $request, string $slug): Response
    {
        if (isset(\Athenaeum\Models\Page::SYSTEM[$slug])) {
            // System slugs have their own routes; avoid duplicate URLs.
            return \Athenaeum\Core\Response::redirect(url('page.about'));
        }
        $page = \Athenaeum\Models\Page::findBySlug($slug);
        if ($page === null) {
            return \Athenaeum\Core\View::error(404);
        }
        return $this->renderPage($slug);
    }

    /**
     * Render a database-backed page, falling back to the shipped translations
     * when the administrators have not written anything yet.
     *
     * @param array<string,mixed> $extra
     */
    private function renderPage(string $slug, array $extra = []): Response
    {
        $page = \Athenaeum\Models\Page::findBySlug($slug);
        $title = \Athenaeum\Models\Page::title($page, $slug);
        $markdown = \Athenaeum\Models\Page::content($page);
        $fromDatabase = trim($markdown) !== '';
        if (!$fromDatabase) {
            $markdown = $this->fallbackBody($slug);
        }

        // The page's own OpenTimestamps proof: a visitor can download the exact
        // text we committed to and re-hash it, without trusting this server.
        $pageProofs = ['current' => null, 'history' => [], 'earliest' => null];
        if ($page !== null && PageTimestamps::enabled()) {
            $pageProofs = PageTimestamps::overview($page);
        }

        return $this->view('pages/show', array_merge([
            'title'       => $title,
            'pageTitle'   => $title,
            'bodyHtml'    => \Athenaeum\Core\Markdown::render($markdown),
            'page'        => $page,
            'pageSlug'    => $slug,
            'fromDatabase' => $fromDatabase,
            'updatedAt'   => $page['updated_at'] ?? null,
            'locales'     => $page !== null ? \Athenaeum\Models\Page::availableLocales($page) : [],
            'pageProofs'  => $pageProofs,
        ], $extra));
    }

    /** Download the .ots proof that covers a content page. */
    public function pageProof(Request $request, string $timestamp): Response
    {
        $row = $this->findPageProof($timestamp);
        if ($row === null || empty($row['ots_path'])) {
            return \Athenaeum\Core\View::error(404);
        }
        $path = Config::path('ots', (string) $row['ots_path']);
        if (!is_file($path)) {
            return \Athenaeum\Core\View::error(404);
        }
        return Response::file($path, (string) ($row['ots_name'] ?: 'page-proof.ots'), 'application/octet-stream', false);
    }

    /** Download the exact bytes a page proof commits to, so it can be re-hashed. */
    public function pageSnapshot(Request $request, string $timestamp): Response
    {
        $row = $this->findPageProof($timestamp);
        if ($row === null) {
            return \Athenaeum\Core\View::error(404);
        }
        $relative = \Athenaeum\Services\OpenTimestamps::snapshotPathFor($row);
        $path = $relative === '' ? '' : Config::path('ots', $relative);
        if ($path === '' || !is_file($path)) {
            return \Athenaeum\Core\View::error(404);
        }
        $slug = pathinfo((string) ($row['file_name'] ?? 'page'), PATHINFO_FILENAME) ?: 'page';
        return Response::file($path, $slug . '.txt', 'text/plain; charset=UTF-8', false);
    }

    /** A timestamp row, but only when it really belongs to a content page. */
    private function findPageProof(string $id): ?array
    {
        $row = Timestamp::find((int) $id);
        if ($row === null || (string) ($row['target_type'] ?? '') !== Timestamp::TARGET_PAGE) {
            return null;
        }
        return $row;
    }

    /** Built-in text used before an administrator publishes the page. */
    private function fallbackBody(string $slug): string
    {
        $paragraphs = match ($slug) {
            'about'        => ['page.about_p1', 'page.about_p2', 'page.about_p3'],
            'athenaeum'    => ['page.about_p1', 'page.about_p2'],
            'timestamping' => ['page.timestamping_p1', 'page.timestamping_p2', 'page.timestamping_p3', 'page.timestamping_p4', 'page.timestamping_privacy'],
            'guidelines'   => ['page.guidelines_p1', 'page.guidelines_rights', 'page.guidelines_withdraw'],
            default        => [],
        };
        $lines = [];
        foreach ($paragraphs as $key) {
            $text = __($key);
            if ($text !== $key) {
                $lines[] = $text;
            }
        }
        return implode("\n\n", $lines);
    }

    /** Switch the interface language and return to where the user was. */
    public function locale(Request $request, string $locale): Response
    {
        I18n::setLocale($locale);
        if (PHP_SAPI !== 'cli') {
            setcookie('athenaeum_locale', I18n::locale(), [
                'expires'  => time() + 31536000,
                'path'     => '/',
                'samesite' => 'Lax',
            ]);
        }
        $next = (string) ($_GET['next'] ?? '');
        if ($next === '' || !str_starts_with($next, '/')) {
            $next = '/';
        }
        return Response::redirect($next);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /settings',
            'Disallow: /dashboard',
            'Disallow: /api/',
            '',
            'Sitemap: ' . Config::baseUrl() . '/sitemap.xml',
        ];
        return Response::text(implode("\n", $lines) . "\n");
    }

    /**
     * Sitemap: only publicly visible papers, so Google Scholar and crawlers
     * can discover every article page and its PDF.
     */
    /**
     * W3C datetime for <lastmod>; null when there is nothing to report (the
     * element is then omitted, which is better than a wrong date).
     */
    private static function sitemapDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $time = strtotime($value);
        return $time === false ? null : gmdate('Y-m-d\\TH:i:s+00:00', $time);
    }

    public function sitemap(): Response
    {
        $items = [];

        // Per-subject-area last modification, taken from the newest paper in
        // each area: crawlers use <lastmod> to decide what to re-read.
        $areaLast = static function (string $column): array {
            $map = [];
            $rows = \Athenaeum\Core\Database::instance()->select(
                "SELECT {$column} AS area_id, MAX(updated_at) AS last FROM {{papers}}"
                . " WHERE status = 'approved' AND visibility = 'public' AND {$column} IS NOT NULL"
                . " GROUP BY {$column}"
            );
            foreach ($rows as $row) {
                $map[(int) $row['area_id']] = (string) $row['last'];
            }
            return $map;
        };
        $sectionLast = $areaLast('section_id');
        $categoryLast = $areaLast('category_id');

        foreach (['', '/papers', '/about', '/about/timestamping', '/guidelines'] as $path) {
            $items[] = ['loc' => path_url($path), 'changefreq' => 'weekly', 'priority' => '0.6'];
        }
        foreach (Section::ordered(true) as $section) {
            $items[] = [
                'loc'        => path_url('/sections/' . rawurlencode((string) $section['slug'])),
                'lastmod'    => self::sitemapDate($sectionLast[(int) $section['id']] ?? null),
                'changefreq' => 'daily',
                'priority'   => '0.7',
            ];
        }
        foreach (Category::ordered() as $category) {
            $items[] = [
                'loc'        => path_url('/categories/' . rawurlencode((string) $category['slug'])),
                'lastmod'    => self::sitemapDate($categoryLast[(int) $category['id']] ?? null),
                'changefreq' => 'weekly',
                'priority'   => '0.5',
            ];
        }

        $page = 1;
        do {
            $result = Paper::search([], $page, 200);
            foreach ($result['items'] as $paper) {
                $items[] = [
                    'loc'        => path_url('/paper/' . rawurlencode((string) $paper['uid'])),
                    'lastmod'    => self::sitemapDate((string) ($paper['updated_at'] ?? $paper['created_at'])),
                    'changefreq' => 'monthly',
                    'priority'   => '0.9',
                ];
            }
            $page++;
        } while ($page <= $result['pages'] && $page <= 50);

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($items as $item) {
            $xml .= "  <url>\n    <loc>" . e($item['loc']) . "</loc>\n";
            if (!empty($item['lastmod'])) {
                $xml .= '    <lastmod>' . e($item['lastmod']) . "</lastmod>\n";
            }
            if (!empty($item['changefreq'])) {
                $xml .= '    <changefreq>' . e($item['changefreq']) . "</changefreq>\n";
            }
            if (!empty($item['priority'])) {
                $xml .= '    <priority>' . e($item['priority']) . "</priority>\n";
            }
            $xml .= "  </url>\n";
        }
        $xml .= "</urlset>\n";

        return (new Response($xml, 200, [
            'Content-Type'  => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]));
    }
}
