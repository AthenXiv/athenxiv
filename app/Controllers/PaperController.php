<?php

declare(strict_types=1);

namespace Athenaeum\Controllers;

use Athenaeum\Core\App;
use Athenaeum\Core\Auth;
use Athenaeum\Core\Database;
use Athenaeum\Core\Languages;
use Athenaeum\Core\Logger;
use Athenaeum\Core\Request;
use Athenaeum\Core\Response;
use Athenaeum\Core\Session;
use Athenaeum\Core\Settings;
use Athenaeum\Core\Validator;
use Athenaeum\Core\View;
use Athenaeum\Models\Attachment;
use Athenaeum\Models\AuditLog;
use Athenaeum\Models\Category;
use Athenaeum\Models\Paper;
use Athenaeum\Models\PaperAuthor;
use Athenaeum\Models\PaperLink;
use Athenaeum\Models\PaperVersion;
use Athenaeum\Models\Section;
use Athenaeum\Models\User;
use Athenaeum\Models\Timestamp;
use Athenaeum\Services\OpenTimestamps;
use Athenaeum\Services\PaperService;
use Athenaeum\Services\Uploader;
use Athenaeum\Core\Str;

final class PaperController extends Controller
{
    /**
     * Sentinel submitted by the area picker when the author's field is not in
     * the taxonomy. Such a paper carries a free-text name instead of an id and
     * cannot be approved until a moderator (or the AI) classifies it.
     */
    public const AREA_OTHER = '__other__';

    /**
     * Accounts referenced by a paper's authors, keyed by user id, so the view
     * can show an avatar and link without a query per author.
     *
     * @param array<int,array<string,mixed>> $authors
     * @return array<int,array<string,mixed>>
     */
    /**
     * Highwire/citation_* metadata for a paper page, in the shape Google
     * Scholar expects: title, one author line each, a real publication date
     * and an absolute link to the PDF that ends in .pdf.
     *
     * @return array<string,array<int,string>>
     */
    private function citationMeta(array $paper): array
    {
        $authors = [];
        foreach (Paper::authors((int) $paper['id']) as $author) {
            $name = trim((string) $author['name']);
            if ($name !== '') {
                $authors[] = Str::citationAuthor($name);
            }
        }

        // Archived works were published elsewhere: the original date is the
        // honest publication date for a citation.
        $published = (string) ($paper['origin_published_at'] ?? '') !== ''
            ? (string) $paper['origin_published_at']
            : (string) ($paper['published_at'] ?: $paper['created_at']);
        $timestamp = strtotime($published) ?: time();

        $meta = [
            'citation_title'            => [(string) $paper['title']],
            'citation_author'           => $authors,
            'citation_publication_date' => [date('Y/m/d', $timestamp)],
            'citation_pdf_url'          => [url('paper.pdf', ['uid' => $paper['uid']])],
            'citation_abstract_html_url' => [url('paper.show', ['uid' => $paper['uid']])],
        ];
        if (!empty($paper['language'])) {
            $meta['citation_language'] = [(string) $paper['language']];
        }
        if (!empty($paper['doi'])) {
            $meta['citation_doi'] = [(string) $paper['doi']];
        }
        if (!empty($paper['origin_source'])) {
            // Where an archived paper first appeared — Scholar accepts a
            // journal (or conference) title and shows it with the citation.
            $meta['citation_journal_title'] = [mb_substr((string) $paper['origin_source'], 0, 190)];
        }
        return $meta;
    }

    private static function linkedAuthorUsers(array $authors): array
    {
        $users = [];
        foreach ($authors as $author) {
            $id = (int) ($author['user_id'] ?? 0);
            if ($id > 0 && !isset($users[$id])) {
                $user = User::find($id);
                if ($user !== null) {
                    $users[$id] = $user;
                }
            }
        }
        return $users;
    }

    // =====================================================================
    // Browsing
    // =====================================================================

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $perPage = Settings::int('ui.papers_per_page', 12);
        $result = Paper::search($filters, max(1, $request->int('page', 1)), $perPage);

        return $this->view('papers/index', [
            'title'      => __('paper.browse_title'),
            'result'     => $result,
            'filters'    => $filters,
            'sections'   => Section::withCounts(true),
            'categories'      => Category::flat(true),
            'tree'            => Category::tree(true),
            'languageOptions' => Languages::filterOptions(),
            'heading'    => __('paper.browse_title'),
            'basePath'   => '/papers',
        ]);
    }

    public function section(Request $request, string $slug): Response
    {
        $section = Section::findBySlug($slug);
        if ($section === null || (int) $section['is_public'] !== 1) {
            return View::error(404);
        }
        $filters = $this->filters($request);
        $filters['section'] = $slug;
        $perPage = Settings::int('ui.papers_per_page', 12);
        $result = Paper::search($filters, max(1, $request->int('page', 1)), $perPage);

        return $this->view('papers/index', [
            'title'       => Section::name($section),
            'result'      => $result,
            'filters'     => $filters,
            'sections'    => Section::withCounts(true),
            'categories'  => Category::flat(true),
            'tree'        => Category::tree(true),
            'languageOptions' => Languages::filterOptions(),
            'heading'     => Section::name($section),
            'description' => Section::description($section),
            'basePath'    => '/sections/' . rawurlencode($slug),
        ]);
    }

    public function category(Request $request, string $slug): Response
    {
        $category = Category::findBySlug($slug);
        if ($category === null) {
            return View::error(404);
        }
        $filters = $this->filters($request);
        $filters['category'] = $slug;
        $perPage = Settings::int('ui.papers_per_page', 12);
        $result = Paper::search($filters, max(1, $request->int('page', 1)), $perPage);

        // Children of this branch, so a visitor can narrow down one level.
        $children = [];
        foreach (Category::tree(false) as $root) {
            if ((int) $root['id'] === (int) $category['id']) {
                $children = $root['children'] ?? [];
                break;
            }
            $found = $this->findInChildren($root['children'] ?? [], (int) $category['id']);
            if ($found !== null) {
                $children = $found;
                break;
            }
        }
        foreach ($children as $index => $child) {
            $children[$index]['paper_count'] = (int) ($child['branch_count'] ?? 0);
        }

        return $this->view('papers/index', [
            'title'      => Category::name($category),
            'result'     => $result,
            'filters'    => $filters,
            'sections'   => Section::withCounts(true),
            'categories'      => Category::flat(true),
            'tree'            => Category::tree(true),
            'languageOptions' => Languages::filterOptions(),
            'heading'    => Category::name($category),
            'breadcrumb' => Category::pathOf((int) $category['id']),
            'children'   => $children,
            'basePath'   => '/categories/' . rawurlencode($slug),
        ]);
    }

    /** @param array<int,array<string,mixed>> $nodes */
    private function findInChildren(array $nodes, int $id): ?array
    {
        foreach ($nodes as $node) {
            if ((int) $node['id'] === $id) {
                return $node['children'] ?? [];
            }
            $deeper = $this->findInChildren($node['children'] ?? [], $id);
            if ($deeper !== null) {
                return $deeper;
            }
        }
        return null;
    }

    /** Full subject-area tree (/categories). */
    public function categories(Request $request): Response
    {
        return $this->view('papers/categories', [
            'title'  => __('category.browse_title'),
            'tree'   => Category::tree(true),
            'counts' => Category::withCounts(),
        ]);
    }

    /** @return array<string,string> */
    private function filters(Request $request): array
    {
        $filters = [
            'q'                => trim($request->str('q')),
            'language'         => $request->str('language'),
            'language_custom'  => $request->str('language_custom'),
            'sort'             => $request->str('sort', 'newest'),
            'category'         => $request->str('category'),
            'section'          => $request->str('section'),
            'categories'       => [],
        ];

        // Multi-area filter: ?categories[]=mathematics&categories[]=physics.
        // A comma separated value is accepted too, so links stay shareable.
        $raw = $request->input('categories', []);
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        if (is_array($raw)) {
            foreach ($raw as $value) {
                $value = trim((string) $value);
                if ($value !== '' && !in_array($value, $filters['categories'], true)) {
                    $filters['categories'][] = $value;
                }
            }
        }

        return array_filter($filters, static fn ($value): bool => $value !== '' && $value !== []);
    }

    public function show(Request $request, string $uid): Response
    {
        $paper = Paper::findByUid($uid);
        if ($paper === null) {
            return View::error(404);
        }
        $user = Auth::user();
        if (!$this->canView($paper, $user)) {
            return View::error(404);
        }

        $paperId = (int) $paper['id'];
        if (Paper::isPublic($paper)) {
            Paper::incrementViews($paperId);
        }

        $timestamps = Paper::timestamps($paperId);
        $this->scheduleTimestampUpgrade($timestamps);

        return $this->view('papers/show', [
            'title'       => (string) $paper['title'],
            'paper'       => $paper,
            'authors'     => Paper::authors($paperId),
            'authorUsers' => self::linkedAuthorUsers(Paper::authors($paperId)),
            'attachments' => Paper::attachments($paperId),
            'links'       => Paper::links($paperId),
            'section'     => Paper::sectionOf($paper),
            'category'    => Paper::categoryOf($paper),
            'uploader'    => Paper::uploader($paper),
            'timestamps'  => $this->decorateTimestamps($timestamps, (string) $paper['uid']),
            'versions'    => \Athenaeum\Services\PaperService::versionsWithProofs($paperId),
            'languageLabel' => Paper::languageLabel($paper),
            'canEdit'     => $this->canManage($paper, $user),
            'isOwner'     => $user !== null && (int) $paper['uploader_id'] === (int) $user['id'],
            'metaDescription' => \Athenaeum\Core\Str::excerpt((string) $paper['abstract'], 300),
            // Highwire Press tags, the format Google Scholar parses.
            'citationMeta' => $this->citationMeta($paper),
        ]);
    }

    /** Inline PDF stream (used by the PDF.js viewer and <embed> fallback). */
    /**
     * The same bytes as paper.file, but at a URL ending in .pdf — the signal
     * Google Scholar looks for when it decides whether a landing page has a
     * full text it may index.
     */
    public function pdf(Request $request, string $uid): Response
    {
        $paper = Paper::findByUid($uid);
        if ($paper === null) {
            return View::error(404);
        }
        if (!$this->canView($paper, Auth::user())) {
            return View::error(403);
        }
        $path = Paper::pdfDiskPath($paper);
        if ($path === null) {
            return View::error(404);
        }
        $name = Str::slug((string) $paper['title'], 80) . '.pdf';
        return Response::file($path, $name, 'application/pdf', true)
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }

    public function file(Request $request, string $uid): Response
    {
        $paper = Paper::findByUid($uid);
        if ($paper === null) {
            return View::error(404);
        }
        if (!$this->canView($paper, Auth::user())) {
            return View::error(403);
        }
        $path = Paper::pdfDiskPath($paper);
        if ($path === null) {
            return View::error(404);
        }
        return Response::file($path, (string) ($paper['pdf_name'] ?: 'paper.pdf'), 'application/pdf', true);
    }

    /** Attachment download: counts and forces a save dialog. */
    public function download(Request $request, string $uid): Response
    {
        $paper = Paper::findByUid($uid);
        if ($paper === null) {
            return View::error(404);
        }
        if (!$this->canView($paper, Auth::user())) {
            return View::error(403);
        }
        $path = Paper::pdfDiskPath($paper);
        if ($path === null) {
            return View::error(404);
        }
        Paper::incrementDownloads((int) $paper['id']);
        return Response::file($path, (string) ($paper['pdf_name'] ?: $paper['uid'] . '.pdf'), 'application/pdf', false);
    }

    /** Full-screen PDF.js viewer page. */
    public function preview(Request $request, string $uid): Response
    {
        $paper = Paper::findByUid($uid);
        if ($paper === null) {
            return View::error(404);
        }
        if (!$this->canView($paper, Auth::user())) {
            return View::error(403);
        }
        return $this->view('papers/preview', [
            'title'   => (string) $paper['title'],
            'paper'   => $paper,
            // Same-origin *path* on purpose: PDF.js validates the `file=`
            // parameter against the viewer's own origin.
            // The viewer fetches this itself; it is the same canonical .pdf URL
            // the page advertises to search engines.
            'fileUrl' => url('paper.pdf', ['uid' => $paper['uid']]),
            // The viewer is a reading tool, not a landing page: keep it out of
            // search results so the citation page is the one that gets indexed.
            'metaRobots' => 'noindex, follow',
        ], 'layouts/blank');
    }

    public function attachment(Request $request, string $uid, string $attachment): Response
    {
        $paper = Paper::findByUid($uid);
        if ($paper === null) {
            return View::error(404);
        }
        if (!$this->canView($paper, Auth::user())) {
            return View::error(403);
        }
        $row = Attachment::find((int) $attachment);
        if ($row === null || (int) $row['paper_id'] !== (int) $paper['id']) {
            return View::error(404);
        }
        $path = Attachment::diskPath($row);
        if ($path === null) {
            return View::error(404);
        }
        Attachment::incrementDownloads((int) $row['id']);
        return Response::file(
            $path,
            (string) $row['original_name'],
            (string) ($row['mime'] ?: 'application/octet-stream'),
            false
        );
    }

    /** Download the .ots proof so it can be verified at opentimestamps.org. */
    public function proof(Request $request, string $uid, string $timestamp): Response
    {
        $paper = Paper::findByUid($uid);
        if ($paper === null) {
            return View::error(404);
        }
        if (!$this->canView($paper, Auth::user())) {
            return View::error(403);
        }
        $row = Timestamp::find((int) $timestamp);
        if ($row === null || (int) $row['paper_id'] !== (int) $paper['id'] || empty($row['ots_path'])) {
            return View::error(404);
        }
        $path = \Athenaeum\Core\Config::path('ots', (string) $row['ots_path']);
        if (!is_file($path)) {
            return View::error(404);
        }
        return Response::file($path, (string) ($row['ots_name'] ?: 'proof.ots'), 'application/octet-stream', false);
    }

    /** Citation export: ?format=bibtex|ris|text|json */
    public function cite(Request $request, string $uid): Response
    {
        $paper = Paper::findByUid($uid);
        if ($paper === null) {
            return View::error(404);
        }
        // Public papers are exported by anyone; a submission that is not yet
        // public may still be cited by its own author (or an administrator).
        if (!Paper::isPublic($paper) && !$this->canView($paper, Auth::user())) {
            return View::error(404);
        }
        $authors = Paper::authors((int) $paper['id']);
        $format = strtolower($request->str('format', 'bibtex'));
        // Archived works were published elsewhere: cite the original year, the
        // same date the landing page reports.
        $year = substr((string) ($paper['origin_published_at'] ?: ($paper['published_at'] ?: $paper['created_at'])), 0, 4);
        $authorNames = $authors === []
            ? [Paper::authorLine($paper)]
            : array_map(static fn (array $a): string => (string) $a['name'], $authors);

        switch ($format) {
            case 'ris':
                $lines = ['TY  - GEN'];
                foreach ($authorNames as $name) {
                    $lines[] = 'AU  - ' . $name;
                }
                $lines[] = 'TI  - ' . $paper['title'];
                $lines[] = 'PY  - ' . $year;
                $lines[] = 'AB  - ' . preg_replace('/\s+/', ' ', (string) $paper['abstract']);
                $lines[] = 'UR  - ' . Paper::publicUrl($paper);
                $lines[] = 'DO  - ' . (string) ($paper['doi'] ?? '');
                $lines[] = 'ER  - ';
                $body = implode("\n", $lines) . "\n";
                $mime = 'application/x-research-info-systems';
                $extension = 'ris';
                break;

            case 'text':
                $body = implode(', ', $authorNames) . ' (' . $year . '). ' . $paper['title']
                    . '. ' . Settings::siteName() . '. ' . Paper::publicUrl($paper) . "\n";
                $mime = 'text/plain; charset=UTF-8';
                $extension = 'txt';
                break;

            case 'json':
                return $this->json([
                    'uid'     => $paper['uid'],
                    'title'   => $paper['title'],
                    'authors' => $authorNames,
                    'year'    => $year,
                    'abstract' => $paper['abstract'],
                    'url'     => Paper::publicUrl($paper),
                    // Same canonical PDF URL the page and Google Scholar see.
            'pdf'     => url('paper.pdf', ['uid' => $paper['uid']]),
                    'doi'     => $paper['doi'],
                    'license' => $paper['license'],
                    'keywords' => $paper['keywords'],
                ]);

            default:
                $key = preg_replace('/[^A-Za-z0-9]/', '', (string) $paper['uid']);
                $body = "@misc{" . $key . ",\n"
                    . '  title        = {' . $paper['title'] . "},\n"
                    . '  author       = {' . implode(' and ', $authorNames) . "},\n"
                    . '  year         = {' . $year . "},\n"
                    . '  howpublished = {' . Settings::siteName() . "},\n"
                    . '  url          = {' . Paper::publicUrl($paper) . "},\n"
                    . ($paper['doi'] ? '  doi          = {' . $paper['doi'] . "},\n" : '')
                    . '  note         = {OpenTimestamps proof: ' . url('paper.timestamp.download', [
                        'uid' => $paper['uid'],
                        'timestamp' => (int) ($this->primaryTimestampId((int) $paper['id']) ?? 0),
                    ]) . "}\n}\n";
                $mime = 'application/x-bibtex';
                $extension = 'bib';
                break;
        }

        return (new Response($body, 200, [
            'Content-Type'        => $mime . (str_contains($mime, 'charset') ? '' : '; charset=UTF-8'),
            'Content-Disposition' => 'attachment; filename="' . $paper['uid'] . '.' . $extension . '"',
        ]));
    }

    // =====================================================================
    // Authoring
    // =====================================================================

    public function createForm(Request $request): Response
    {
        return $this->view('papers/form', array_merge($this->formContext(), [
            'title'      => __('paper.submit_title'),
            'heading'    => __('paper.submit_title'),
            'paper'      => null,
            'authors'    => [['name' => (string) (Auth::user()['display_name'] ?? Auth::user()['nickname'] ?? '')]],
            'links'      => [],
            'attachments' => [],
            'action'     => url('paper.create'),
            'isAdminForm' => Auth::isAdmin(),
            'proxy'      => false,
        ]));
    }

    public function store(Request $request): Response
    {
        $validator = Validator::make($request->all(), $this->paperRules());
        $languageError = $this->languageError($request);
        $areaError = $this->areaError($request);
        if ($validator->fails() || $languageError !== null || $areaError !== null) {
            if ($validator->fails()) {
                Session::flash('errors', $validator->errors());
                Session::flash('error', $validator->firstError());
            } else {
                Session::flash('error', (string) ($languageError ?? $areaError));
            }
            Session::flashInput($request->all());
            return $this->redirect(url('paper.create'));
        }

        $input = $this->paperInput($request);
        $result = PaperService::create(
            $input,
            $request->file('pdf'),
            $this->collectFiles('attachments'),
            Auth::user()
        );

        if (!$result['ok']) {
            Session::flash('error', (string) $result['error']);
            Session::flashInput($request->all());
            return $this->redirect(url('paper.create'));
        }

        $paper = $result['paper'] ?? [];
        $timestampNote = __('paper.timestamp_stamped');
        if (!empty($paper['status']) && $paper['status'] === Paper::STATUS_APPROVED) {
            Session::flash('success', __('paper.created_approved') . ' ' . $timestampNote);
            return $this->redirect(Paper::publicUrl($paper));
        }

        Session::flash('success', __('paper.created_pending') . ' ' . $timestampNote);
        return $this->redirect(url('dashboard.papers'));
    }

    public function editForm(Request $request, string $uid): Response
    {
        $paper = Paper::findByUid($uid);
        if ($paper === null) {
            return View::error(404);
        }
        if (!$this->canManage($paper, Auth::user())) {
            return View::error(403);
        }
        return $this->view('papers/form', array_merge($this->formContext(), [
            'title'       => __('paper.edit_title'),
            'heading'     => __('paper.edit_title'),
            'paper'       => $paper,
            'authors'     => Paper::authors((int) $paper['id']),
            'links'       => Paper::links((int) $paper['id']),
            'attachments' => Paper::attachments((int) $paper['id']),
            'action'      => url('paper.edit', ['uid' => $paper['uid']]),
            'isAdminForm' => Auth::isAdmin(),
            'proxy'       => false,
        ]));
    }

    public function update(Request $request, string $uid): Response
    {
        $paper = Paper::findByUid($uid);
        if ($paper === null) {
            return View::error(404);
        }
        if (!$this->canManage($paper, Auth::user())) {
            return View::error(403);
        }

        $validator = Validator::make($request->all(), $this->paperRules(false));
        $languageError = $this->languageError($request);
        $areaError = $this->areaError($request);
        if ($validator->fails() || $languageError !== null || $areaError !== null) {
            if ($validator->fails()) {
                Session::flash('errors', $validator->errors());
                Session::flash('error', $validator->firstError());
            } else {
                Session::flash('error', (string) ($languageError ?? $areaError));
            }
            Session::flashInput($request->all());
            return $this->redirect(url('paper.edit', ['uid' => $paper['uid']]));
        }

        $exempt = Auth::isAdmin() && $request->bool('size_exempt');
        $result = PaperService::update(
            (int) $paper['id'],
            $this->paperInput($request),
            $request->file('pdf'),
            $this->collectFiles('attachments'),
            $exempt
        );

        if (!$result['ok']) {
            Session::flash('error', (string) $result['error']);
            Session::flashInput($request->all());
            return $this->redirect(url('paper.edit', ['uid' => $paper['uid']]));
        }

        Session::flash('success', __('paper.updated'));
        return $this->redirect(url('paper.edit', ['uid' => $paper['uid']]));
    }

    public function submit(Request $request, string $uid): Response
    {
        $paper = Paper::findByUid($uid);
        if ($paper === null) {
            return View::error(404);
        }
        if (!$this->canManage($paper, Auth::user())) {
            return View::error(403);
        }
        $result = PaperService::submit((int) $paper['id']);
        Session::flash($result['ok'] ? 'success' : 'error', $result['ok']
            ? __('paper.submitted')
            : (string) $result['error']);
        return $this->redirect(url('dashboard.papers'));
    }

    public function withdraw(Request $request, string $uid): Response
    {
        $paper = Paper::findByUid($uid);
        if ($paper === null) {
            return View::error(404);
        }
        $user = Auth::user();
        $isOwner = $user !== null && (int) $paper['uploader_id'] === (int) $user['id'];
        if (!Auth::isAdmin() && !$isOwner) {
            return View::error(403);
        }
        PaperService::withdraw((int) $paper['id'], $request->str('reason') ?: null);
        Session::flash('success', __('paper.withdrawn'));
        return $this->redirect(Auth::isAdmin() ? url('admin.paper', ['id' => $paper['id']]) : url('dashboard.papers'));
    }

    /** Owners may delete their own draft/rejected/withdrawn submissions. */
    public function destroy(Request $request, string $uid): Response
    {
        $paper = Paper::findByUid($uid);
        if ($paper === null) {
            return View::error(404);
        }
        $user = Auth::user();
        $isOwner = $user !== null && (int) $paper['uploader_id'] === (int) $user['id'];
        if (!$isOwner && !Auth::isAdmin()) {
            return View::error(403);
        }
        if ($isOwner && !Auth::isAdmin() && !in_array($paper['status'], [
            Paper::STATUS_DRAFT,
            Paper::STATUS_REJECTED,
            Paper::STATUS_WITHDRAWN,
        ], true)) {
            Session::flash('error', __('paper.delete_not_allowed'));
            return $this->redirect(url('dashboard.papers'));
        }
        Paper::purge((int) $paper['id']);
        AuditLog::record('paper.purge', 'paper', (int) $paper['id'], ['uid' => $paper['uid'], 'by' => 'owner']);
        Session::flash('success', __('paper.deleted'));
        return $this->redirect(Auth::isAdmin() ? url('admin.papers') : url('dashboard.papers'));
    }

    // =====================================================================
    // Version management
    // =====================================================================

    /**
     * Upload a new revision of an existing paper. The previous PDF keeps its
     * file, its version row and its own timestamp proof.
     */
    public function storeVersion(Request $request, string $uid): Response
    {
        $paper = Paper::findByUid($uid);
        if ($paper === null) {
            return View::error(404);
        }
        if (!$this->canManage($paper, Auth::user())) {
            return View::error(403);
        }
        if (!Settings::bool('versions.enabled')) {
            Session::flash('error', __('version.disabled'));
            return $this->redirect(url('paper.edit', ['uid' => $paper['uid']]));
        }

        $exempt = Auth::isAdmin() && $request->bool('size_exempt');
        $result = PaperService::newVersion(
            (int) $paper['id'],
            $request->file('version_pdf'),
            $request->str('version_note') ?: null,
            $exempt
        );

        if (!$result['ok']) {
            Session::flash('error', (string) $result['error']);
            Session::flashInput($request->all());
            return $this->redirect(url('paper.edit', ['uid' => $paper['uid']]) . '#versions');
        }

        Session::flash('success', __('version.created', ['version' => 'v' . (int) $result['version']]));
        return $this->redirect(url('paper.show', ['uid' => $paper['uid']]) . '#versions');
    }

    /** Download a specific revision. */
    public function downloadVersion(Request $request, string $uid, string $version): Response
    {
        $paper = Paper::findByUid($uid);
        if ($paper === null) {
            return View::error(404);
        }
        if (!$this->canView($paper, Auth::user())) {
            return View::error(403);
        }
        $row = PaperVersion::findByNumber((int) $paper['id'], (int) $version);
        if ($row === null) {
            return View::error(404);
        }
        $path = PaperVersion::diskPath($row);
        if ($path === null) {
            return View::error(404);
        }
        if ((int) $version === (int) $paper['version_no']) {
            Paper::incrementDownloads((int) $paper['id']);
        }
        return Response::file($path, (string) $row['pdf_name'], 'application/pdf', false);
    }

    /** Stream a specific revision inline (used by the viewer for old versions). */
    public function versionFile(Request $request, string $uid, string $version): Response
    {
        $paper = Paper::findByUid($uid);
        if ($paper === null) {
            return View::error(404);
        }
        if (!$this->canView($paper, Auth::user())) {
            return View::error(403);
        }
        $row = PaperVersion::findByNumber((int) $paper['id'], (int) $version);
        if ($row === null) {
            return View::error(404);
        }
        $path = PaperVersion::diskPath($row);
        if ($path === null) {
            return View::error(404);
        }
        return Response::file($path, (string) $row['pdf_name'], 'application/pdf', true);
    }

    public function deleteAttachment(Request $request, string $uid, string $attachment): Response
    {
        $paper = Paper::findByUid($uid);
        if ($paper === null) {
            return View::error(404);
        }
        if (!$this->canManage($paper, Auth::user())) {
            return View::error(403);
        }
        $row = Attachment::find((int) $attachment);
        if ($row === null || (int) $row['paper_id'] !== (int) $paper['id']) {
            return View::error(404);
        }
        $path = Attachment::diskPath($row);
        if ($path !== null) {
            @unlink($path);
        }
        Attachment::delete((int) $row['id']);
        Session::flash('success', __('paper.attachment_deleted'));
        return $this->redirect(url('paper.edit', ['uid' => $paper['uid']]));
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /** @return array<string,string> */
    private function paperRules(bool $requirePdf = true): array
    {
        $rules = [
            'title'    => 'required|string|min:3|max:300',
            'subtitle' => 'nullable|string|max:300',
            'abstract' => 'required|string|min:40|max:20000',
            'language'     => 'required|string|max:80',
            'keywords' => 'nullable|string|max:500',
            'license'  => 'nullable|string|max:80',
            'doi'      => 'nullable|string|max:190',
            'visibility' => 'nullable|in:public,unlisted',
            'section_id' => 'nullable|int',
            'category_id' => 'nullable|string|max:40',
            'category_other' => 'nullable|string|max:190',
        ];
        return $rules;
    }

    /**
     * Resolve the subject-area picker.
     *
     * "Other / not listed" in the area picker means the author names a field the
     * taxonomy does not contain: no category_id is stored and the free text goes
     * to category_other, which blocks publication until a moderator (or the AI)
     * classifies the paper. Shared with the administrator's proxy upload/edit
     * forms so all three paths behave identically.
     *
     * @return array{category_id:?int,category_other:?string}
     */
    public static function areaInput(Request $request): array
    {
        $categoryId = $request->int('category_id') ?: null;
        $categoryOther = null;
        if ($request->str('category_id') === self::AREA_OTHER) {
            $categoryId = null;
            $typed = trim($request->str('category_other'));
            $categoryOther = $typed !== '' ? mb_substr($typed, 0, 190) : null;
        }
        return ['category_id' => $categoryId, 'category_other' => $categoryOther];
    }

    /** @return array<string,mixed> */
    private function paperInput(Request $request): array
    {
        // "Other" in the language picker: derive a stable code from the typed
        // name so the language filter can group those papers together.
        $languageCode = $request->str('language', 'en');
        $languageCustom = null;
        if ($languageCode === Languages::OTHER) {
            $typed = $request->str('language_custom');
            $languageCustom = $typed !== '' ? $typed : null;
            $languageCode = Languages::customCode($typed);
        }

        // "Other / not listed" in the area picker: the author names a field the
        // taxonomy does not contain. No category_id is stored; a moderator (or
        // the AI) has to classify the paper before it can be published.
        $area = self::areaInput($request);
        $categoryId = $area['category_id'];
        $categoryOther = $area['category_other'];

        return [
            'title'        => $request->str('title'),
            'subtitle'     => $request->str('subtitle') ?: null,
            'abstract'     => $request->str('abstract'),
            'language'     => $languageCode,
            'language_custom' => $languageCustom,
            'section_id'   => $request->int('section_id') ?: null,
            'category_id'  => $categoryId,
            'category_other' => $categoryOther,
            'keywords'     => $request->str('keywords') ?: null,
            'license'      => $request->str('license') ?: null,
            'doi'          => $request->str('doi') ?: null,
            'visibility'   => $request->str('visibility', 'public'),
            'author_name'         => $request->array('author_name'),
            'author_affiliation'  => $request->array('author_affiliation'),
            'author_email'        => $request->array('author_email'),
            'author_orcid'        => $request->array('author_orcid'),
            'author_user'         => $request->array('author_user'),
            'author_corresponding' => $request->array('author_corresponding'),
            'link_label'   => $request->array('link_label'),
            'link_url'     => $request->array('link_url'),
            'link_kind'    => $request->array('link_kind'),
            'as_draft'     => $request->bool('as_draft'),
            'size_exempt'  => $request->bool('size_exempt'),
            'size_exempt_note' => $request->str('size_exempt_note') ?: null,
            'proxy_upload' => $request->bool('proxy_upload'),
            'uploader_id'  => $request->int('uploader_id') ?: null,
            'version_note' => $request->str('version_note') ?: null,
        ];
    }

    /** Reject a free-text language that is too short to be meaningful. */
    private function languageError(Request $request): ?string
    {
        $code = $request->str('language', 'en');
        if ($code !== Languages::OTHER) {
            return Languages::exists($code) || Languages::isCustom($code) ? null : __('validation.in', ['field' => __('paper.language')]);
        }
        return Languages::validateSubmission(Languages::OTHER, $request->str('language_custom'))
            ? null
            : __('paper.language_custom_invalid');
    }

    /**
     * "Other / not listed" must come with a name, and a real area must be
     * picked otherwise.
     */
    private function areaError(Request $request): ?string
    {
        if ($request->str('category_id') !== self::AREA_OTHER) {
            return null;
        }
        return trim($request->str('category_other')) === '' ? __('category.other_required') : null;
    }

    /** @return array<string,mixed> */
    private function formContext(): array
    {
        $isAdmin = Auth::isAdmin();
        $limits = PaperService::limits($isAdmin);
        return [
            'sections'        => Section::ordered(false),
            'categories'      => Category::flat(false),
            'languages'       => Languages::options(),
            'languageOther'   => Languages::OTHER,
            'languageFilter'  => Languages::filterOptions(),
            'linkKinds'       => Paper::LINK_KINDS,
            'siteUsers'       => array_map(
                static fn (array $user): array => [
                    'id'    => (int) $user['id'],
                    'uid'   => (string) $user['uid'],
                    'label' => trim((string) ($user['display_name'] ?? '') ?: (string) $user['nickname']) . ' (' . $user['uid'] . ')',
                ],
                User::all([], 'id ASC', 500)
            ),
            'limits'          => $limits,
            'phpLimit'        => Uploader::phpUploadLimit(),
            'maxPdfMessage'   => PaperService::pdfLimitMessage($isAdmin),
            'contactEmail'    => Settings::string('site.contact_email'),
            'allowedExt'      => Attachment::allowedExtensions(),
            'fieldErrors'     => Session::errors(),
        ];
    }

    /**
     * Normalise a multi-file field (`name="attachments[]"`) into a list of
     * classic single-file arrays.
     *
     * @return array<int,array<string,mixed>>
     */
    private function collectFiles(string $field): array
    {
        $files = $_FILES[$field] ?? null;
        if (!is_array($files) || !isset($files['name'])) {
            return [];
        }
        if (!is_array($files['name'])) {
            return $files['error'] === UPLOAD_ERR_NO_FILE ? [] : [$files];
        }

        $out = [];
        $count = count($files['name']);
        $max = Settings::int('upload.max_attachments', 5);
        for ($i = 0; $i < $count && count($out) < $max; $i++) {
            if ((int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];
        }
        return $out;
    }

    private function canView(array $paper, ?array $user): bool
    {
        if (Paper::isPublic($paper)) {
            return true;
        }
        if ($user === null) {
            return false;
        }
        if (($user['role'] ?? '') === 'admin') {
            return true;
        }
        return (int) $paper['uploader_id'] === (int) $user['id'];
    }

    private function canManage(array $paper, ?array $user): bool
    {
        if ($user === null) {
            return false;
        }
        if (($user['role'] ?? '') === 'admin') {
            return true;
        }
        if ((int) $paper['uploader_id'] !== (int) $user['id']) {
            return false;
        }
        return in_array($paper['status'], Paper::EDITABLE_BY_OWNER, true);
    }

    private function primaryTimestampId(int $paperId): ?int
    {
        $row = Paper::timestamp($paperId, 'pdf');
        return $row === null ? null : (int) $row['id'];
    }

    /**
     * @param array<int,array<string,mixed>> $timestamps
     * @return array<int,array<string,mixed>>
     */
    private function decorateTimestamps(array $timestamps, string $paperUid): array
    {
        $verifyUrl = OpenTimestamps::verifyUrl();
        $out = [];
        foreach ($timestamps as $timestamp) {
            $timestamp['paper_uid'] = $paperUid;
            $timestamp['verify_url'] = $verifyUrl;
            $timestamp['proof_url'] = !empty($timestamp['ots_path'])
                ? Timestamp::proofDownloadUrl($timestamp, $paperUid)
                : null;
            $timestamp['short_hash'] = Timestamp::shortHash((string) $timestamp['file_sha256']);
            $timestamp['is_confirmed'] = $timestamp['status'] === Timestamp::STATUS_CONFIRMED;
            $out[] = $timestamp;
        }
        return $out;
    }

    /**
     * Opportunistically upgrade pending proofs *after* the page has been sent,
     * so a slow calendar never delays a reader.
     *
     * @param array<int,array<string,mixed>> $timestamps
     */
    private function scheduleTimestampUpgrade(array $timestamps): void
    {
        if (!Settings::bool('ots.auto_upgrade') || !Settings::bool('ots.enabled')) {
            return;
        }
        $stale = [];
        foreach ($timestamps as $timestamp) {
            if (($timestamp['status'] ?? '') !== Timestamp::STATUS_PENDING) {
                continue;
            }
            $lastAttempt = $timestamp['last_attempt_at'] ?? null;
            if ($lastAttempt !== null && strtotime($lastAttempt . ' UTC') > time() - 900) {
                continue;
            }
            if ((int) ($timestamp['attempts'] ?? 0) > 150) {
                continue;
            }
            $stale[] = $timestamp;
        }
        if ($stale === []) {
            return;
        }

        register_shutdown_function(static function () use ($stale): void {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            foreach ($stale as $timestamp) {
                try {
                    OpenTimestamps::upgradeRow($timestamp);
                } catch (\Throwable $e) {
                    Logger::warning('opportunistic OTS upgrade failed: ' . $e->getMessage());
                }
            }
        });
    }
}
