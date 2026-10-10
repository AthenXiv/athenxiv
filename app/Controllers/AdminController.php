<?php

declare(strict_types=1);

namespace Athenaeum\Controllers;

use Athenaeum\Core\Auth;
use Athenaeum\Core\Config;
use Athenaeum\Core\Database;
use Athenaeum\Core\I18n;
use Athenaeum\Core\Request;
use Athenaeum\Core\Response;
use Athenaeum\Core\Session;
use Athenaeum\Core\Settings;
use Athenaeum\Core\Str;
use Athenaeum\Core\Validator;
use Athenaeum\Core\View;
use Athenaeum\Models\Attachment;
use Athenaeum\Models\AuditLog;
use Athenaeum\Models\Category;
use Athenaeum\Models\Page;
use Athenaeum\Models\Paper;
use Athenaeum\Models\Section;
use Athenaeum\Models\Timestamp;
use Athenaeum\Models\User;
use Athenaeum\Services\AiReviewer;
use Athenaeum\Services\Mailer;
use Athenaeum\Services\OpenTimestamps;
use Athenaeum\Services\PageTimestamps;
use Athenaeum\Services\PaperService;
use Athenaeum\Services\Uploader;

/**
 * Administrator console: site branding, moderation, users, taxonomy,
 * upload limits (including "破例" exemptions) and OpenTimestamps operations.
 */
final class AdminController extends Controller
{
    // =====================================================================
    // Dashboard
    // =====================================================================

    public function dashboard(Request $request): Response
    {
        $db = Database::instance();
        $stats = [
            'users'        => (int) $db->scalar('SELECT COUNT(*) FROM {{users}}'),
            'banned'       => (int) $db->scalar("SELECT COUNT(*) FROM {{users}} WHERE status = 'banned'"),
            'papers'       => (int) $db->scalar('SELECT COUNT(*) FROM {{papers}}'),
            'published'    => Paper::approvedCount(),
            'pending'      => Paper::pendingCount(),
            'rejected'     => (int) $db->scalar("SELECT COUNT(*) FROM {{papers}} WHERE status = 'rejected'"),
            'taken_down'   => (int) $db->scalar("SELECT COUNT(*) FROM {{papers}} WHERE status = 'taken_down'"),
            'timestamps'   => (int) $db->scalar('SELECT COUNT(*) FROM {{timestamps}}'),
            'confirmed'    => (int) $db->scalar("SELECT COUNT(*) FROM {{timestamps}} WHERE status = 'confirmed'"),
            'storage'      => $this->storageUsage(),
        ];

        $queue = Paper::adminSearch(['status' => Paper::STATUS_PENDING], 1, 10);
        $recent = $db->select('SELECT * FROM {{audit_logs}} ORDER BY id DESC LIMIT 12');

        $calendarHealth = [];
        foreach (OpenTimestamps::calendars() as $calendar) {
            $probe = \Athenaeum\Core\Http::get(rtrim($calendar, '/') . '/timestamp/' . str_repeat('0', 64), [
                'Accept' => 'application/vnd.opentimestamps.v1',
            ], ['timeout' => 6, 'follow' => false]);
            $calendarHealth[] = [
                'url'    => $calendar,
                'online' => in_array($probe['status'], [200, 400, 404], true),
                'status' => $probe['status'],
                'error'  => $probe['error'],
            ];
        }

        return $this->view('admin/dashboard', [
            'title'          => __('admin.dashboard'),
            'stats'          => $stats,
            'queue'          => $queue['items'],
            'queueTotal'     => $queue['total'],
            'recent'         => $recent,
            'calendarHealth' => $calendarHealth,
        ], 'layouts/admin');
    }

    private function storageUsage(): array
    {
        $sum = static function (string $dir): int {
            $total = 0;
            if (!is_dir($dir)) {
                return 0;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile()) {
                    $total += (int) $file->getSize();
                }
            }
            return $total;
        };
        return [
            'papers'      => $sum(Config::path('uploads', 'papers')),
            'attachments' => $sum(Config::path('uploads', 'attachments')),
            'ots'         => $sum(Config::path('ots')),
        ];
    }

    // =====================================================================
    // Papers
    // =====================================================================

    /**
     * Search-engine visits, newest first, plus a per-crawler summary. On shared
     * hosting this is often the only way to know whether Googlebot ever came.
     */
    public function crawlers(Request $request): Response
    {
        return View::render('admin/crawlers', [
            'title'   => __('admin.crawlers_title'),
            'summary' => \Athenaeum\Core\CrawlerJournal::summary(),
            'hits'    => \Athenaeum\Core\CrawlerJournal::recent(200),
            'file'    => \Athenaeum\Core\CrawlerJournal::fileFor(),
        ], 'layouts/admin');
    }

    public function papers(Request $request): Response
    {
        $filters = [
            'status'     => $request->str('status'),
            'section_id' => $request->int('section_id'),
            'category_id' => $request->int('category_id'),
            'q'          => $request->str('q'),
            'sort'       => $request->str('sort', 'newest'),
        ];
        $filters = array_filter($filters, static fn ($value): bool => $value !== '' && $value !== 0);

        $result = Paper::adminSearch($filters, max(1, $request->int('page', 1)), 20);

        $rows = [];
        foreach ($result['items'] as $paper) {
            $paper['author_line'] = Paper::authorLine($paper);
            $paper['uploader'] = User::find((int) $paper['uploader_id']);
            $paper['section'] = Paper::sectionOf($paper);
            $paper['timestamp'] = Paper::timestamp((int) $paper['id'], 'pdf');
            $rows[] = $paper;
        }
        $result['items'] = $rows;

        return $this->view('admin/papers', [
            'title'      => __('admin.papers'),
            'result'     => $result,
            'filters'    => $filters,
            'sections'   => Section::ordered(false),
            'categories' => Category::ordered(),
            'counts'     => [
                'pending'  => Paper::pendingCount(),
                'approved' => Paper::approvedCount(),
                'all'      => (int) Database::instance()->scalar('SELECT COUNT(*) FROM {{papers}}'),
            ],
        ], 'layouts/admin');
    }

    public function paper(Request $request, string $id): Response
    {
        $paper = Paper::find((int) $id);
        if ($paper === null) {
            return View::error(404);
        }
        $paperId = (int) $paper['id'];
        $timestamps = [];
        foreach (Paper::timestamps($paperId) as $timestamp) {
            $timestamp['paper_uid'] = (string) $paper['uid'];
            $timestamp['proof_url'] = url('admin.timestamps.proof', ['id' => $timestamp['id']]);
            $timestamps[] = $timestamp;
        }

        return $this->view('admin/paper', [
            'title'       => __('admin.paper_detail') . ' · ' . $paper['uid'],
            'paper'       => $paper,
            'authors'     => Paper::authors($paperId),
            'attachments' => Paper::attachments($paperId),
            'links'       => Paper::links($paperId),
            'section'     => Paper::sectionOf($paper),
            'category'    => Paper::categoryOf($paper),
            'uploader'    => User::find((int) $paper['uploader_id']),
            'proxy'       => !empty($paper['proxy_uploader_id']) ? User::find((int) $paper['proxy_uploader_id']) : null,
            'timestamps'  => $timestamps,
            'categories'  => Category::flat(false),
            'versions'    => PaperService::versionsWithProofs($paperId),
            'sections'    => Section::ordered(false),
            'audit'       => Database::instance()->select(
                'SELECT * FROM {{audit_logs}} WHERE target_type = :t AND target_id = :id ORDER BY id DESC LIMIT 20',
                ['t' => 'paper', 'id' => (string) $paperId]
            ),
        ], 'layouts/admin');
    }

    public function paperFile(Request $request, string $id): Response
    {
        $paper = Paper::find((int) $id);
        if ($paper === null) {
            return View::error(404);
        }
        $path = Paper::pdfDiskPath($paper);
        if ($path === null) {
            return View::error(404);
        }
        return Response::file($path, (string) ($paper['pdf_name'] ?: 'paper.pdf'), 'application/pdf', true);
    }

    public function paperDownload(Request $request, string $id): Response
    {
        $paper = Paper::find((int) $id);
        if ($paper === null) {
            return View::error(404);
        }
        $path = Paper::pdfDiskPath($paper);
        if ($path === null) {
            return View::error(404);
        }
        return Response::file($path, (string) ($paper['pdf_name'] ?: $paper['uid'] . '.pdf'), 'application/pdf', false);
    }

    public function editPaper(Request $request, string $id): Response
    {
        $paper = Paper::find((int) $id);
        if ($paper === null) {
            return View::error(404);
        }
        $isAdmin = true;
        return $this->view('papers/form', [
            'title'       => __('admin.edit_paper') . ' · ' . $paper['uid'],
            'heading'     => __('admin.edit_paper') . ' · ' . $paper['uid'],
            'paper'       => $paper,
            'authors'     => Paper::authors((int) $paper['id']),
            'links'       => Paper::links((int) $paper['id']),
            'attachments' => Paper::attachments((int) $paper['id']),
            'sections'    => Section::ordered(false),
            'categories'  => Category::ordered(),
            'languages'   => \Athenaeum\Core\Languages::options(),
            'languageOther' => \Athenaeum\Core\Languages::OTHER,
            'linkKinds'   => Paper::LINK_KINDS,
            'siteUsers'   => array_map(
                static fn (array $user): array => [
                    'id'    => (int) $user['id'],
                    'uid'   => (string) $user['uid'],
                    'label' => trim((string) ($user['display_name'] ?? '') ?: (string) $user['nickname']) . ' (' . $user['uid'] . ')',
                ],
                User::all([], 'id ASC', 500)
            ),
            'limits'      => PaperService::limits(true),
            'phpLimit'    => Uploader::phpUploadLimit(),
            'maxPdfMessage' => PaperService::pdfLimitMessage(true),
            'contactEmail' => Settings::string('site.contact_email'),
            'allowedExt'  => Attachment::allowedExtensions(),
            'fieldErrors' => Session::errors(),
            'action'      => url('admin.paper.edit', ['id' => $paper['id']]),
            'isAdminForm' => true,
            'proxy'       => true,
        ], 'layouts/admin');
    }

    public function updatePaper(Request $request, string $id): Response
    {
        $paper = Paper::find((int) $id);
        if ($paper === null) {
            return View::error(404);
        }
        $validator = Validator::make($request->all(), [
            'title'    => 'required|string|min:3|max:300',
            'abstract' => 'required|string|min:40|max:20000',
            'language' => 'required|string|max:80',
        ]);
        if ($validator->fails()) {
            Session::flash('errors', $validator->errors());
            Session::flash('error', $validator->firstError());
            Session::flashInput($request->all());
            return $this->redirect(url('admin.paper.edit', ['id' => $paper['id']]));
        }

        $language = PaperService::normaliseLanguage(
            $request->str('language', 'en'),
            $request->str('language_custom')
        );

        $input = [
            'title'       => $request->str('title'),
            'subtitle'    => $request->str('subtitle') ?: null,
            'abstract'    => $request->str('abstract'),
            'language'    => $language['code'],
            'language_custom' => $language['custom'],
            'section_id'  => $request->int('section_id') ?: null,
            'category_id' => \Athenaeum\Controllers\PaperController::areaInput($request)['category_id'],
            'category_other' => \Athenaeum\Controllers\PaperController::areaInput($request)['category_other'],
            'keywords'    => $request->str('keywords') ?: null,
            'license'     => $request->str('license') ?: null,
            'doi'         => $request->str('doi') ?: null,
            'visibility'  => $request->str('visibility', 'public'),
            'author_name'         => $request->array('author_name'),
            'author_affiliation'  => $request->array('author_affiliation'),
            'author_email'        => $request->array('author_email'),
            'author_orcid'        => $request->array('author_orcid'),
            'author_user'         => $request->array('author_user'),
            'author_corresponding' => $request->array('author_corresponding'),
            'link_label'  => $request->array('link_label'),
            'link_url'    => $request->array('link_url'),
            'link_kind'   => $request->array('link_kind'),
            'size_exempt_note' => $request->str('size_exempt_note') ?: null,
        ];

        $result = PaperService::update(
            (int) $paper['id'],
            $input,
            $request->file('pdf'),
            $this->collectFiles('attachments'),
            $request->bool('size_exempt')
        );

        Session::flash($result['ok'] ? 'success' : 'error', $result['ok']
            ? __('admin.paper_updated')
            : (string) $result['error']);
        return $this->redirect(url('admin.paper.edit', ['id' => $paper['id']]));
    }

    public function approve(Request $request, string $id): Response
    {
        $paper = Paper::find((int) $id);
        if ($paper === null) {
            return View::error(404);
        }
        $result = PaperService::approve((int) $paper['id'], $request->str('note') ?: null, $request->str('section_id') ?: null);
        if (empty($result['ok'])) {
            // Most often the "Other / not listed" gate: the paper still needs a
            // real subject area before it may be published.
            Session::flash('error', (string) ($result['error'] ?? __('common.error_generic_title')));
            return $this->backAdmin($paper);
        }
        Session::flash('success', __('admin.approved', ['uid' => $paper['uid']]));
        return $this->backAdmin($paper);
    }

    /** Give a paper a real subject area (may create a new one). */
    public function reclassify(Request $request, string $id): Response
    {
        $paper = Paper::find((int) $id);
        if ($paper === null) {
            return View::error(404);
        }
        $result = PaperService::reclassify(
            (int) $paper['id'],
            $request->int('category_id') ?: null,
            $request->str('new_area') ?: null,
            null,
            'admin'
        );
        if (empty($result['ok'])) {
            Session::flash('error', (string) ($result['error'] ?? __('common.error_generic_title')));
        } else {
            Session::flash('success', __('admin.category_updated'));
        }
        return $this->redirect(url('admin.paper', ['id' => $paper['id']]));
    }

    public function reject(Request $request, string $id): Response
    {
        $paper = Paper::find((int) $id);
        if ($paper === null) {
            return View::error(404);
        }
        $reason = $request->str('reason');
        if ($reason === '') {
            Session::flash('error', __('admin.reason_required'));
            return $this->backAdmin($paper);
        }
        PaperService::reject((int) $paper['id'], $reason, $request->str('note') ?: null);
        Session::flash('success', __('admin.rejected', ['uid' => $paper['uid']]));
        return $this->backAdmin($paper);
    }

    public function takedown(Request $request, string $id): Response
    {
        $paper = Paper::find((int) $id);
        if ($paper === null) {
            return View::error(404);
        }
        $reason = $request->str('reason') ?: __('admin.takedown_default_reason');
        PaperService::takedown((int) $paper['id'], $reason);
        Session::flash('success', __('admin.taken_down', ['uid' => $paper['uid']]));
        return $this->backAdmin($paper);
    }

    public function restore(Request $request, string $id): Response
    {
        $paper = Paper::find((int) $id);
        if ($paper === null) {
            return View::error(404);
        }
        PaperService::approve((int) $paper['id'], $request->str('note') ?: __('admin.restored'));
        Session::flash('success', __('admin.restored_ok', ['uid' => $paper['uid']]));
        return $this->backAdmin($paper);
    }

    public function assignSection(Request $request, string $id): Response
    {
        $paper = Paper::find((int) $id);
        if ($paper === null) {
            return View::error(404);
        }
        $sectionId = $request->int('section_id');
        PaperService::assignSection((int) $paper['id'], $sectionId > 0 ? $sectionId : null);
        Session::flash('success', __('admin.section_assigned'));
        return $this->backAdmin($paper);
    }

    public function feature(Request $request, string $id): Response
    {
        $paper = Paper::find((int) $id);
        if ($paper === null) {
            return View::error(404);
        }
        PaperService::toggleFeatured((int) $paper['id'], $request->bool('featured'));
        Session::flash('success', __('admin.feature_updated'));
        return $this->backAdmin($paper);
    }

    public function purge(Request $request, string $id): Response
    {
        $paper = Paper::find((int) $id);
        if ($paper === null) {
            return View::error(404);
        }
        if ($request->str('confirm') !== $paper['uid']) {
            Session::flash('error', __('admin.confirm_uid_mismatch'));
            return $this->redirect(url('admin.paper', ['id' => $paper['id']]));
        }
        Paper::purge((int) $paper['id']);
        AuditLog::record('paper.purge', 'paper', (int) $paper['id'], ['uid' => $paper['uid']]);
        Session::flash('success', __('admin.paper_purged', ['uid' => $paper['uid']]));
        return $this->redirect(url('admin.papers'));
    }

    private function backAdmin(array $paper): Response
    {
        $from = isset($_GET['from']) && is_string($_GET['from']) ? $_GET['from'] : '';
        if ($from === 'list') {
            return $this->redirect(url('admin.papers'));
        }
        return $this->redirect(url('admin.paper', ['id' => $paper['id']]));
    }

    // =====================================================================
    // Proxy upload ("代理上传", with size exemption)
    // =====================================================================

    public function uploadForm(Request $request): Response
    {
        $targetUid = $request->str('user');
        $target = $targetUid !== '' ? User::findByUid($targetUid) : null;
        if ($target === null && $targetUid !== '') {
            $target = User::findByEmail($targetUid);
        }

        return $this->view('admin/upload', [
            'title'       => __('admin.proxy_upload'),
            'heading'     => __('admin.proxy_upload'),
            'action'      => url('admin.upload.store'),
            'target'      => $target,
            'paper'       => null,
            'authors'     => [['name' => '', 'affiliation' => '', 'email' => '', 'orcid' => '', 'is_corresponding' => 0]],
            'links'       => [],
            'attachments' => [],
            'isAdminForm' => true,
            'proxy'       => true,
            'sections'    => Section::ordered(false),
            'categories'  => Category::ordered(),
            'languages'   => \Athenaeum\Core\Languages::options(),
            'languageOther' => \Athenaeum\Core\Languages::OTHER,
            'linkKinds'   => Paper::LINK_KINDS,
            'siteUsers'   => array_map(
                static fn (array $user): array => [
                    'id'    => (int) $user['id'],
                    'uid'   => (string) $user['uid'],
                    'label' => trim((string) ($user['display_name'] ?? '') ?: (string) $user['nickname']) . ' (' . $user['uid'] . ')',
                ],
                User::all([], 'id ASC', 500)
            ),
            'limits'      => PaperService::limits(true),
            'phpLimit'    => Uploader::phpUploadLimit(),
            'maxPdfMessage' => PaperService::pdfLimitMessage(true),
            'allowedExt'  => Attachment::allowedExtensions(),
            'contactEmail' => Settings::string('site.contact_email'),
            'fieldErrors' => Session::errors(),
        ], 'layouts/admin');
    }

    public function uploadStore(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'title'      => 'required|string|min:3|max:300',
            'abstract'   => 'required|string|min:40|max:20000',
            'language'   => 'required|string|max:80',
            'uploader'   => 'required|string',
        ]);
        if ($validator->fails()) {
            Session::flash('errors', $validator->errors());
            Session::flash('error', $validator->firstError());
            Session::flashInput($request->all());
            return $this->redirect(url('admin.upload'));
        }

        $owner = User::findByUid($request->str('uploader')) ?? User::findByEmail($request->str('uploader'));
        if ($owner === null) {
            Session::flash('error', __('admin.user_not_found'));
            Session::flashInput($request->all());
            return $this->redirect(url('admin.upload'));
        }

        $language = PaperService::normaliseLanguage(
            $request->str('language', 'en'),
            $request->str('language_custom')
        );

        $input = [
            'title'       => $request->str('title'),
            'subtitle'    => $request->str('subtitle') ?: null,
            'abstract'    => $request->str('abstract'),
            'language'    => $language['code'],
            'language_custom' => $language['custom'],
            'section_id'  => $request->int('section_id') ?: null,
            'category_id' => \Athenaeum\Controllers\PaperController::areaInput($request)['category_id'],
            'category_other' => \Athenaeum\Controllers\PaperController::areaInput($request)['category_other'],
            'keywords'    => $request->str('keywords') ?: null,
            'license'     => $request->str('license') ?: null,
            'doi'         => $request->str('doi') ?: null,
            'visibility'  => $request->str('visibility', 'public'),
            'author_name'         => $request->array('author_name'),
            'author_affiliation'  => $request->array('author_affiliation'),
            'author_email'        => $request->array('author_email'),
            'author_orcid'        => $request->array('author_orcid'),
            'author_user'         => $request->array('author_user'),
            'author_corresponding' => $request->array('author_corresponding'),
            'link_label'  => $request->array('link_label'),
            'link_url'    => $request->array('link_url'),
            'link_kind'   => $request->array('link_kind'),
            'uploader_id' => (int) $owner['id'],
            'proxy_upload' => true,
            'size_exempt' => $request->bool('size_exempt'),
            'size_exempt_note' => $request->str('size_exempt_note') ?: null,
            'as_draft'    => false,
        ];

        $result = PaperService::create($input, $request->file('pdf'), $this->collectFiles('attachments'), Auth::user());
        if (!$result['ok']) {
            Session::flash('error', (string) $result['error']);
            Session::flashInput($request->all());
            return $this->redirect(url('admin.upload'));
        }

        $paper = $result['paper'] ?? [];
        AuditLog::record('paper.proxy_upload', 'paper', (int) ($paper['id'] ?? 0), [
            'uploader' => $owner['uid'],
            'exempt'   => $request->bool('size_exempt'),
        ]);
        Session::flash('success', __('admin.proxy_upload_done', ['uid' => (string) ($paper['uid'] ?? '')]));
        return $this->redirect(url('admin.paper', ['id' => (int) ($paper['id'] ?? 0)]));
    }

    // =====================================================================
    // Users
    // =====================================================================

    public function users(Request $request): Response
    {
        $db = Database::instance();
        $term = $request->str('q');
        $status = $request->str('status');

        $where = ['1 = 1'];
        $params = [];
        if ($term !== '') {
            // Distinct placeholders: MySQL rejects a repeated named parameter.
            $where[] = '(email LIKE :uq1 OR nickname LIKE :uq2 OR uid LIKE :uq3 OR display_name LIKE :uq4)';
            $like = '%' . $term . '%';
            $params['uq1'] = $like;
            $params['uq2'] = $like;
            $params['uq3'] = $like;
            $params['uq4'] = $like;
        }
        if ($status !== '' && in_array($status, User::STATUSES, true)) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }
        $clause = ' WHERE ' . implode(' AND ', $where);
        $perPage = 20;
        $page = max(1, $request->int('page', 1));
        $total = (int) $db->scalar('SELECT COUNT(*) FROM {{users}}' . $clause, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $users = $db->select(
            'SELECT * FROM {{users}}' . $clause . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        );

        $rows = [];
        foreach ($users as $user) {
            $user['paper_count'] = Paper::countForUser((int) $user['id']);
            $user['banned'] = $user['status'] === 'banned';
            $rows[] = $user;
        }

        return $this->view('admin/users', [
            'title'   => __('admin.users'),
            'users'   => $rows,
            'term'    => $term,
            'status'  => $status,
            'page'    => $page,
            'pages'   => $pages,
            'total'   => $total,
            'basePath' => '/admin/users',
            'query'   => array_filter(['q' => $term, 'status' => $status]),
        ], 'layouts/admin');
    }

    public function user(Request $request, string $id): Response
    {
        $user = User::find((int) $id);
        if ($user === null) {
            return View::error(404);
        }
        $papers = Paper::forUser((int) $user['id']);

        return $this->view('admin/user', [
            'title'  => __('admin.user_detail') . ' · ' . $user['uid'],
            'user'   => $user,
            'papers' => $papers,
            'links'  => \Athenaeum\Models\UserLink::forUser((int) $user['id']),
            'statuses' => User::STATUSES,
            'roles'  => User::ROLES,
            'summary' => Paper::statusSummary((int) $user['id']),
            'audit'  => Database::instance()->select(
                'SELECT * FROM {{audit_logs}} WHERE (target_type = :t AND target_id = :id) OR actor_id = :aid ORDER BY id DESC LIMIT 20',
                ['t' => 'user', 'id' => (string) $user['id'], 'aid' => (int) $user['id']]
            ),
        ], 'layouts/admin');
    }

    public function createUser(Request $request): Response
    {
        $min = (int) Config::get('security.password_min_length', 10);
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email|max:190|unique:users,email',
            'nickname' => 'required|string|min:2|max:80',
            'password' => 'required|string|min:' . $min . '|max:200',
            'role'     => 'required|in:' . implode(',', User::ROLES),
        ]);
        if ($validator->fails()) {
            Session::flash('errors', $validator->errors());
            Session::flash('error', $validator->firstError());
            Session::flashInput($request->all());
            return $this->redirect(url('admin.users'));
        }

        $id = User::createByAdmin([
            'email'        => $request->str('email'),
            'password'     => (string) $request->input('password', ''),
            'nickname'     => $request->str('nickname'),
            'display_name' => $request->str('display_name') ?: $request->str('nickname'),
            'affiliation'  => $request->str('affiliation') ?: null,
            'role'         => $request->str('role', 'user'),
            'status'       => 'active',
        ]);
        $created = User::find($id);
        AuditLog::record('user.create', 'user', $id, ['email' => $request->str('email')]);
        Session::flash('success', __('admin.user_created', ['uid' => (string) ($created['uid'] ?? '')]));
        return $this->redirect(url('admin.users'));
    }

    public function userStatus(Request $request, string $id): Response
    {
        $user = User::find((int) $id);
        if ($user === null) {
            return View::error(404);
        }
        if ((int) $user['id'] === (int) Auth::id() && $request->str('status') === 'banned') {
            Session::flash('error', __('admin.cannot_ban_self'));
            return $this->redirect(url('admin.user', ['id' => $user['id']]));
        }
        $status = $request->str('status');
        User::setStatus((int) $user['id'], $status);
        AuditLog::record('user.status', 'user', (int) $user['id'], [
            'status' => $status,
            'note'   => $request->str('note') ?: null,
        ]);
        Session::flash('success', __('admin.user_status_saved', ['uid' => $user['uid'], 'status' => $status]));
        return $this->redirect(url('admin.user', ['id' => $user['id']]));
    }

    public function userRole(Request $request, string $id): Response
    {
        $user = User::find((int) $id);
        if ($user === null) {
            return View::error(404);
        }
        $role = $request->str('role');
        if (!in_array($role, User::ROLES, true)) {
            Session::flash('error', __('admin.invalid_role'));
            return $this->redirect(url('admin.user', ['id' => $user['id']]));
        }
        if ((int) $user['id'] === (int) Auth::id() && $role !== 'admin') {
            Session::flash('error', __('admin.cannot_demote_self'));
            return $this->redirect(url('admin.user', ['id' => $user['id']]));
        }
        User::update((int) $user['id'], ['role' => $role]);
        AuditLog::record('user.role', 'user', (int) $user['id'], ['role' => $role]);
        Session::flash('success', __('admin.user_role_saved'));
        return $this->redirect(url('admin.user', ['id' => $user['id']]));
    }

    public function userPassword(Request $request, string $id): Response
    {
        $user = User::find((int) $id);
        if ($user === null) {
            return View::error(404);
        }
        $min = (int) Config::get('security.password_min_length', 10);
        $password = (string) $request->input('password', '');
        if (mb_strlen($password) < $min) {
            Session::flash('error', __('validation.min', ['field' => 'password', 'min' => (string) $min]));
            return $this->redirect(url('admin.user', ['id' => $user['id']]));
        }
        User::updatePassword((int) $user['id'], $password);
        AuditLog::record('user.password_reset', 'user', (int) $user['id']);
        Session::flash('success', __('admin.user_password_saved'));
        return $this->redirect(url('admin.user', ['id' => $user['id']]));
    }

    public function userProfile(Request $request, string $id): Response
    {
        $user = User::find((int) $id);
        if ($user === null) {
            return View::error(404);
        }
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email|max:190|unique:users,email,' . $user['id'],
            'nickname' => 'required|string|min:2|max:80',
        ]);
        if ($validator->fails()) {
            Session::flash('error', $validator->firstError());
            return $this->redirect(url('admin.user', ['id' => $user['id']]));
        }
        User::update((int) $user['id'], [
            'email'        => mb_strtolower($request->str('email')),
            'nickname'     => $request->str('nickname'),
            'display_name' => $request->str('display_name') ?: $request->str('nickname'),
            'affiliation'  => $request->str('affiliation') ?: null,
            'admin_note'   => $request->str('admin_note') ?: null,
        ]);
        AuditLog::record('user.profile', 'user', (int) $user['id']);
        Session::flash('success', __('admin.user_profile_saved'));
        return $this->redirect(url('admin.user', ['id' => $user['id']]));
    }

    public function userPurge(Request $request, string $id): Response
    {
        $user = User::find((int) $id);
        if ($user === null) {
            return View::error(404);
        }
        if ((int) $user['id'] === (int) Auth::id()) {
            Session::flash('error', __('admin.cannot_delete_self'));
            return $this->redirect(url('admin.user', ['id' => $user['id']]));
        }
        if ($request->str('confirm') !== $user['uid']) {
            Session::flash('error', __('admin.confirm_uid_mismatch'));
            return $this->redirect(url('admin.user', ['id' => $user['id']]));
        }
        AuditLog::record('user.purge', 'user', (int) $user['id'], ['uid' => $user['uid'], 'email' => $user['email']]);
        User::purge((int) $user['id']);
        Session::flash('success', __('admin.user_purged', ['uid' => $user['uid']]));
        return $this->redirect(url('admin.users'));
    }

    // =====================================================================
    // Sections (分区) and categories (分类)
    // =====================================================================

    public function sections(Request $request): Response
    {
        $sections = Section::withCounts(false);
        return $this->view('admin/sections', [
            'title'    => __('admin.sections'),
            'sections' => $sections,
            'locales'  => I18n::catalogue(),
        ], 'layouts/admin');
    }

    public function createSection(Request $request): Response
    {
        $slug = Str::slug($request->str('slug') ?: $request->str('name_en') ?: $request->str('name_zh-CN'), 60);
        if ($slug === '' || Section::findBySlug($slug) !== null) {
            Session::flash('error', __('admin.slug_taken'));
            return $this->redirect(url('admin.sections'));
        }
        $names = $this->collectLocalised($request, 'name');
        if ($names === []) {
            Session::flash('error', __('admin.name_required'));
            return $this->redirect(url('admin.sections'));
        }
        $descriptions = $this->collectLocalised($request, 'description');

        $id = Section::createSection(
            $slug,
            $names,
            $descriptions,
            $request->int('sort_order'),
            $request->bool('is_default')
        );
        AuditLog::record('section.create', 'section', $id, ['slug' => $slug]);
        Session::flash('success', __('admin.section_created'));
        return $this->redirect(url('admin.sections'));
    }

    public function updateSection(Request $request, string $id): Response
    {
        $section = Section::find((int) $id);
        if ($section === null) {
            return View::error(404);
        }
        $names = $this->collectLocalised($request, 'name');
        $descriptions = $this->collectLocalised($request, 'description');
        $updates = [
            'sort_order' => $request->int('sort_order', (int) $section['sort_order']),
            'is_public'  => $request->bool('is_public') ? 1 : 0,
        ];
        if ($names !== []) {
            $updates['names'] = json_encode($names, JSON_UNESCAPED_UNICODE);
        }
        if ($descriptions !== []) {
            $updates['descriptions'] = json_encode($descriptions, JSON_UNESCAPED_UNICODE);
        }
        Section::update((int) $section['id'], $updates);
        if ($request->bool('is_default')) {
            Section::setDefault((int) $section['id']);
        }
        AuditLog::record('section.update', 'section', (int) $section['id']);
        Session::flash('success', __('admin.section_updated'));
        return $this->redirect(url('admin.sections'));
    }

    public function defaultSection(Request $request, string $id): Response
    {
        Section::setDefault((int) $id);
        AuditLog::record('section.default', 'section', (int) $id);
        Session::flash('success', __('admin.section_default_saved'));
        return $this->redirect(url('admin.sections'));
    }

    public function purgeSection(Request $request, string $id): Response
    {
        if (!Section::safeDelete((int) $id)) {
            Session::flash('error', __('admin.section_in_use'));
            return $this->redirect(url('admin.sections'));
        }
        AuditLog::record('section.purge', 'section', (int) $id);
        Session::flash('success', __('admin.section_deleted'));
        return $this->redirect(url('admin.sections'));
    }

    public function categories(Request $request): Response
    {
        $tree = Category::tree(true);
        $total = 0;
        $walk = static function (array $nodes) use (&$walk, &$total): void {
            foreach ($nodes as $node) {
                $total++;
                if (!empty($node['children'])) {
                    $walk($node['children']);
                }
            }
        };
        $walk($tree);

        return $this->view('admin/categories', [
            'title'      => __('admin.categories'),
            'tree'       => $tree,
            'flat'       => Category::flat(false),
            'total'      => $total,
            'locales'    => I18n::catalogue(),
        ], 'layouts/admin');
    }

    public function createCategory(Request $request): Response
    {
        $slug = Str::slug($request->str('slug') ?: $request->str('name_en') ?: $request->str('name_zh-CN'), 60);
        if ($slug === '' || Category::findBySlug($slug) !== null) {
            Session::flash('error', __('admin.slug_taken'));
            return $this->redirect(url('admin.categories'));
        }
        $names = $this->collectLocalised($request, 'name');
        if ($names === []) {
            Session::flash('error', __('admin.name_required'));
            return $this->redirect(url('admin.categories'));
        }
        $id = Category::create([
            'slug'       => $slug,
            'names'      => json_encode($names, JSON_UNESCAPED_UNICODE),
            'parent_id'  => $request->int('parent_id') ?: null,
            'sort_order' => $request->int('sort_order'),
            'created_at' => Database::instance()->now(),
        ]);
        AuditLog::record('category.create', 'category', $id, ['slug' => $slug]);
        Session::flash('success', __('admin.category_created'));
        return $this->redirect(url('admin.categories'));
    }

    public function updateCategory(Request $request, string $id): Response
    {
        $category = Category::find((int) $id);
        if ($category === null) {
            return View::error(404);
        }
        $names = $this->collectLocalised($request, 'name');
        $updates = [
            'sort_order' => $request->int('sort_order', (int) $category['sort_order']),
            'parent_id'  => $request->int('parent_id') ?: null,
        ];
        if ($names !== []) {
            $updates['names'] = json_encode($names, JSON_UNESCAPED_UNICODE);
        }
        Category::update((int) $category['id'], $updates);
        AuditLog::record('category.update', 'category', (int) $category['id']);
        Session::flash('success', __('admin.category_updated'));
        return $this->redirect(url('admin.categories'));
    }

    public function purgeCategory(Request $request, string $id): Response
    {
        if (!Category::safeDelete((int) $id)) {
            Session::flash('error', __('admin.category_in_use'));
            return $this->redirect(url('admin.categories'));
        }
        AuditLog::record('category.purge', 'category', (int) $id);
        Session::flash('success', __('admin.category_deleted'));
        return $this->redirect(url('admin.categories'));
    }

    /** @return array<string,string> locale => value */
    private function collectLocalised(Request $request, string $prefix): array
    {
        $out = [];
        foreach (array_keys(I18n::CATALOGUE) as $locale) {
            $value = $request->str($prefix . '_' . $locale);
            if ($value !== '') {
                $out[$locale] = $value;
            }
        }
        return $out;
    }

    // =====================================================================
    // Settings & branding
    // =====================================================================

    public function settings(Request $request): Response
    {
        return $this->view('admin/settings', [
            'title'     => __('admin.settings'),
            'values'    => Settings::all(),
            'locales'   => I18n::catalogue(),
            'calendars' => implode(',', OpenTimestamps::calendars()),
            'phpLimit'  => Uploader::phpUploadLimit(),
            'logoUrl'   => Settings::string('site.logo_path') !== ''
                ? storage_url('branding', Settings::string('site.logo_path'))
                : null,
            'faviconUrl' => Settings::string('site.favicon_path') !== ''
                ? storage_url('branding', Settings::string('site.favicon_path'))
                : null,
        ], 'layouts/admin');
    }

    public function updateSettings(Request $request): Response
    {
        $text = [
            'site.name', 'site.name_en', 'site.tagline', 'site.tagline_en',
            'site.contact_email', 'site.footer_text', 'site.icp', 'site.analytics',
            'home.hero_title', 'home.hero_subtitle', 'home.hero_cta_label',
            'home.notice', 'notice.color', 'moderation.notify_email', 'upload.allowed_attachment_ext',
            'upload.pdf_message', 'ots.calendars', 'ots.verify_url',
            'ui.default_locale', 'ui.locales',
        ];
        $numbers = [
            'upload.max_pdf_mb', 'upload.max_attachment_mb', 'upload.max_attachments',
            'upload.max_avatar_kb', 'upload.max_logo_kb', 'ui.papers_per_page', 'versions.max',
        ];
        $flags = [
            'registration.open', 'registration.reset_password', 'moderation.auto_approve', 'ots.enabled',
            'ots.auto_upgrade', 'ots.require_for_publish', 'ui.allow_profile_markdown',
            'ui.show_view_counts', 'notice.dismissible', 'versions.enabled', 'versions.keep_files',
        ];

        // Only keys that the submitted form actually carried are written.
        // A partial post (an integration test, a hand-made request, a future
        // second settings screen) must not blank the settings it never
        // mentioned — that is how a live site silently loses its SMTP host and
        // its versioning switch. Checkboxes are still "absent means off".
        $present = static function (Request $request, string $key): bool {
            return array_key_exists(str_replace('.', '_', $key), $_POST)
                || array_key_exists($key, $_POST);
        };

        foreach ($text as $key) {
            if (!$present($request, $key)) {
                continue;
            }
            Settings::set($key, $request->dotted($key));
        }
        foreach ($numbers as $key) {
            if (!$present($request, $key)) {
                continue;
            }
            $value = max(0, $request->dottedInt($key, (int) Settings::get($key, 0)));
            Settings::set($key, $value);
        }
        foreach ($flags as $key) {
            Settings::set($key, $request->dottedBool($key));
        }

        // A changed announcement must be shown again to everyone who dismissed
        // the previous one — that is what the revision key is for.
        // "custom" in the picker means: take the hex value from the colour input.
        $noticeColor = $present($request, 'notice.color') ? $request->dotted('notice.color') : Settings::string('notice.color', 'info');
        if ($noticeColor === 'custom') {
            $custom = strtolower($request->str('notice_color_custom'));
            $noticeColor = preg_match('/^#[0-9a-f]{6}$/', $custom) ? $custom : 'info';
        }
        Settings::set('notice.color', $noticeColor);
        Settings::set('notice.revision', substr(md5(
            Settings::string('home.notice') . '|' . Settings::string('notice.color')
            . '|' . (Settings::bool('notice.dismissible') ? '1' : '0')
        ), 0, 12));

        AuditLog::record('settings.update', 'settings', null, ['keys' => count($text) + count($numbers) + count($flags)]);
        Session::flash('success', __('admin.settings_saved'));
        return $this->redirect(url('admin.settings'));
    }

    public function updateBranding(Request $request): Response
    {
        $messages = [];
        foreach (['logo', 'favicon'] as $field) {
            $file = $request->file($field);
            if ($file === null) {
                continue;
            }
            $limitKb = Settings::int('upload.max_logo_kb', 1024);
            $stored = Uploader::store($file, [
                'kind'      => Uploader::KIND_IMAGE,
                'max_bytes' => $limitKb * 1024,
                'subdir'    => 'branding',
            ]);
            if (!$stored['ok']) {
                Session::flash('error', (string) $stored['error']);
                return $this->redirect(url('admin.settings') . '#branding');
            }
            $flat = basename((string) $stored['data']['path']);
            $target = Config::path('uploads', 'branding/' . $flat);
            if ($stored['data']['absolute'] !== $target) {
                @rename($stored['data']['absolute'], $target);
            }
            if ($field === 'logo') {
                Uploader::shrinkImage($target, 1024);
            }
            $key = $field === 'logo' ? 'site.logo_path' : 'site.favicon_path';
            $old = Config::path('uploads', 'branding/' . Settings::string($key));
            Settings::set($key, $flat);
            if (Settings::string($key) !== '' && is_file($old)) {
                @unlink($old);
            }
            $messages[] = $field;
        }

        if ($messages === []) {
            Session::flash('error', __('upload.error_no_file'));
            return $this->redirect(url('admin.settings') . '#branding');
        }

        AuditLog::record('settings.branding', 'settings', null, ['fields' => $messages]);
        Session::flash('success', __('admin.branding_saved'));
        return $this->redirect(url('admin.settings') . '#branding');
    }

    // =====================================================================
    // OpenTimestamps operations
    // =====================================================================

    public function timestamps(Request $request): Response
    {
        $db = Database::instance();
        $status = $request->str('status');
        $where = ['1 = 1'];
        $params = [];
        if ($status !== '' && in_array($status, [Timestamp::STATUS_PENDING, Timestamp::STATUS_CONFIRMED, Timestamp::STATUS_FAILED], true)) {
            $where[] = 't.status = :status';
            $params['status'] = $status;
        }
        $clause = ' WHERE ' . implode(' AND ', $where);
        $perPage = 25;
        $page = max(1, $request->int('page', 1));
        $total = (int) $db->scalar('SELECT COUNT(*) FROM {{timestamps}} t' . $clause, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        $rows = $db->select(
            'SELECT t.*, p.uid AS paper_uid, p.title AS paper_title, p.status AS paper_status,'
            . ' pg.slug AS page_slug, pg.titles AS page_titles'
            . ' FROM {{timestamps}} t LEFT JOIN {{papers}} p ON p.id = t.paper_id'
            . ' LEFT JOIN {{pages}} pg ON pg.id = t.page_id'
            . $clause . ' ORDER BY t.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        );

        $decorated = [];
        foreach ($rows as $row) {
            $row['tips'] = $row['calendars'] ? (json_decode((string) $row['calendars'], true) ?: []) : [];
            $decorated[] = $row;
        }

        return $this->view('admin/timestamps', [
            'title'     => __('admin.timestamps'),
            'rows'      => $decorated,
            'status'    => $status,
            'page'      => $page,
            'pages'     => $pages,
            'total'     => $total,
            'basePath'  => '/admin/timestamps',
            'query'     => array_filter(['status' => $status]),
            'counts'    => [
                'pending'   => (int) $db->scalar("SELECT COUNT(*) FROM {{timestamps}} WHERE status = 'pending'"),
                'confirmed' => (int) $db->scalar("SELECT COUNT(*) FROM {{timestamps}} WHERE status = 'confirmed'"),
                'failed'    => (int) $db->scalar("SELECT COUNT(*) FROM {{timestamps}} WHERE status = 'failed'"),
            ],
            'calendars' => OpenTimestamps::calendars(),
            'verifyUrl' => OpenTimestamps::verifyUrl(),
        ], 'layouts/admin');
    }

    public function upgradeTimestamps(Request $request): Response
    {
        $limit = max(1, min(100, $request->int('limit', 25)));
        $stats = OpenTimestamps::upgradePending($limit, 0);
        AuditLog::record('ots.upgrade', 'timestamps', null, $stats);
        Session::flash('success', __('admin.ots_upgrade_done', [
            'checked'   => (string) $stats['checked'],
            'upgraded'  => (string) $stats['upgraded'],
            'confirmed' => (string) $stats['confirmed'],
        ]));
        return $this->redirect(url('admin.timestamps'));
    }

    public function downloadProof(Request $request, string $id): Response
    {
        $timestamp = Timestamp::find((int) $id);
        if ($timestamp === null || empty($timestamp['ots_path'])) {
            return View::error(404);
        }
        $path = Config::path('ots', (string) $timestamp['ots_path']);
        if (!is_file($path)) {
            return View::error(404);
        }
        return Response::file($path, (string) ($timestamp['ots_name'] ?: 'proof.ots'), 'application/octet-stream', false);
    }

    // =====================================================================
    // Audit log
    // =====================================================================

    public function audit(Request $request): Response
    {
        $action = $request->str('action');
        $result = AuditLog::paginateAll(max(1, $request->int('page', 1)), 50, $action !== '' ? $action : null);

        $actions = Database::instance()->select('SELECT action, COUNT(*) AS total FROM {{audit_logs}} GROUP BY action ORDER BY total DESC');
        $rows = [];
        foreach ($result['items'] as $row) {
            $row['actor'] = $row['actor_id'] ? User::find((int) $row['actor_id']) : null;
            $rows[] = $row;
        }
        $result['items'] = $rows;

        return $this->view('admin/audit', [
            'title'   => __('admin.audit'),
            'result'  => $result,
            'actions' => $actions,
            'action'  => $action,
            'basePath' => '/admin/audit',
            'query'   => array_filter(['action' => $action]),
        ], 'layouts/admin');
    }

    // =====================================================================
    // Shared helpers
    // =====================================================================

    /** @return array<int,array<string,mixed>> */
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

    // =====================================================================
    // Editable content pages
    // =====================================================================

    public function pages(Request $request): Response
    {
        $pages = Page::ordered();
        $rows = [];
        $stamping = PageTimestamps::enabled();
        foreach ($pages as $page) {
            $page['locale_count'] = count(Page::availableLocales($page));
            $page['size'] = Page::size($page);
            $page['editor'] = $page['updated_by'] ? User::find((int) $page['updated_by']) : null;
            $page['stamp'] = $stamping ? (PageTimestamps::overview($page)['current'] ?? null) : null;
            $rows[] = $page;
        }

        return $this->view('admin/pages', [
            'title'  => __('admin.pages'),
            'pages'  => $rows,
            'system' => Page::SYSTEM,
            'locales' => I18n::catalogue(),
            'stamping' => $stamping,
        ], 'layouts/admin');
    }

    public function page(Request $request, string $id): Response
    {
        $page = Page::find((int) $id);
        if ($page === null) {
            return View::error(404);
        }
        $locale = $request->str('locale');
        $available = I18n::availableLocales();
        if ($locale === '' || !in_array($locale, $available, true)) {
            $locale = I18n::locale();
            if (!in_array($locale, $available, true)) {
                $locale = $available[0] ?? 'en';
            }
        }
        $titles = Page::texts($page, 'titles');
        $contents = Page::texts($page, 'contents');

        return $this->view('admin/page', [
            'title'      => __('admin.edit_page') . ' · ' . Page::title($page, (string) $page['slug']),
            'page'       => $page,
            'locale'     => $locale,
            'locales'    => I18n::catalogue(),
            'titleText'  => (string) ($titles[$locale] ?? ''),
            'contentText' => (string) ($contents[$locale] ?? ''),
            'filled'     => array_keys($contents),
            'isSystem'   => array_key_exists((string) $page['slug'], Page::SYSTEM),
            'preview'    => \Athenaeum\Core\Markdown::render((string) ($contents[$locale] ?? '')),
        ], 'layouts/admin');
    }

    public function savePage(Request $request, string $id): Response
    {
        $page = Page::find((int) $id);
        if ($page === null) {
            return View::error(404);
        }
        $locale = $request->str('locale', 'en');
        if (!isset(I18n::CATALOGUE[$locale])) {
            Session::flash('error', __('admin.unknown_locale'));
            return $this->redirect(url('admin.page', ['id' => $page['id']]));
        }

        Page::saveLocale(
            (int) $page['id'],
            $locale,
            $request->str('title'),
            (string) $request->input('content', '')
        );
        AuditLog::record('page.save', 'page', (int) $page['id'], ['slug' => $page['slug'], 'locale' => $locale]);

        // Re-stamp the page so its OpenTimestamps proof matches the text a
        // visitor will now read. Best effort: a calendar outage must never stop
        // an administrator from saving.
        $fresh = Page::find((int) $page['id']);
        if ($fresh !== null && PageTimestamps::enabled()) {
            $stamped = PageTimestamps::ensure($fresh);
            if ($stamped !== null) {
                AuditLog::record('page.stamp', 'page', (int) $page['id'], [
                    'slug'         => $page['slug'],
                    'timestamp_id' => (int) $stamped['id'],
                    'status'       => (string) $stamped['status'],
                ]);
            }
        }

        Session::flash('success', __('admin.page_saved', ['locale' => $locale]));
        return $this->redirect(url('admin.page', ['id' => $page['id']]) . '?locale=' . rawurlencode($locale));
    }

    /** Create (or retry) the OpenTimestamps proof for a single content page. */
    public function restampPage(Request $request, string $id): Response
    {
        $page = Page::find((int) $id);
        if ($page === null) {
            return View::error(404);
        }
        if (!PageTimestamps::enabled()) {
            Session::flash('error', __('admin.page_stamp_disabled'));
            return $this->redirect(url('admin.page', ['id' => $page['id']]));
        }
        $row = PageTimestamps::ensure($page);
        AuditLog::record('page.stamp', 'page', (int) $page['id'], ['slug' => $page['slug']]);

        if ($row === null) {
            Session::flash('error', __('admin.page_stamp_empty'));
        } elseif ((string) $row['status'] === Timestamp::STATUS_FAILED) {
            Session::flash('error', __('admin.page_stamp_failed', ['error' => (string) ($row['last_error'] ?? '')]));
        } else {
            Session::flash('success', __('admin.page_stamped', ['status' => Timestamp::statusLabel((string) $row['status'])]));
        }
        return $this->redirect(url('admin.page', ['id' => $page['id']]));
    }

    /** Stamp every content page that has no proof for its current text yet. */
    public function stampAllPages(Request $request): Response
    {
        if (!PageTimestamps::enabled()) {
            Session::flash('error', __('admin.page_stamp_disabled'));
            return $this->redirect(url('admin.pages'));
        }
        $ok = 0;
        $failed = 0;
        foreach (Page::ordered() as $page) {
            $row = PageTimestamps::ensure($page);
            if ($row === null) {
                continue;
            }
            if ((string) $row['status'] === Timestamp::STATUS_FAILED) {
                $failed++;
            } else {
                $ok++;
            }
        }
        AuditLog::record('page.stamp_all', 'pages', null, ['ok' => $ok, 'failed' => $failed]);
        Session::flash($failed > 0 ? 'error' : 'success', __('admin.pages_stamped', [
            'ok'     => (string) $ok,
            'failed' => (string) $failed,
        ]));
        return $this->redirect(url('admin.pages'));
    }

    public function createPage(Request $request): Response
    {
        $slug = Str::slug($request->str('slug') ?: $request->str('title'), 60);
        if ($slug === '' || Page::findBySlug($slug) !== null) {
            Session::flash('error', __('admin.slug_taken'));
            return $this->redirect(url('admin.pages'));
        }
        $locale = $request->str('locale', 'en');
        $id = Page::create([
            'slug'       => $slug,
            'titles'     => json_encode([$locale => $request->str('title')], JSON_UNESCAPED_UNICODE),
            'contents'   => json_encode([$locale => (string) $request->input('content', '')], JSON_UNESCAPED_UNICODE),
            'is_system'  => 0,
            'sort_order' => 100,
            'updated_by' => Auth::id(),
            'created_at' => Database::instance()->now(),
        ]);
        AuditLog::record('page.create', 'page', $id, ['slug' => $slug]);
        Session::flash('success', __('admin.page_created'));
        return $this->redirect(url('admin.page', ['id' => $id]));
    }

    public function purgePage(Request $request, string $id): Response
    {
        $page = Page::find((int) $id);
        if ($page === null) {
            return View::error(404);
        }
        if (array_key_exists((string) $page['slug'], Page::SYSTEM)) {
            Session::flash('error', __('admin.system_page_kept'));
            return $this->redirect(url('admin.pages'));
        }
        Page::delete((int) $page['id']);
        AuditLog::record('page.purge', 'page', (int) $page['id'], ['slug' => $page['slug']]);
        Session::flash('success', __('admin.page_deleted'));
        return $this->redirect(url('admin.pages'));
    }

    // =====================================================================
    // AI review
    // =====================================================================

    public function ai(Request $request): Response
    {
        $status = AiReviewer::status();
        $probe = null;
        $queue = Paper::adminSearch(['status' => Paper::STATUS_PENDING], 1, 60);

        $rows = [];
        foreach ($queue['items'] as $paper) {
            $paper['author_line'] = Paper::authorLine($paper);
            $paper['ai'] = Paper::aiPayload($paper);
            $rows[] = $paper;
        }
        $queue['items'] = $rows;

        $recent = Database::instance()->select(
            'SELECT id, uid, title, status, ai_status, ai_decision, ai_confidence, ai_reviewed_at'
            . ' FROM {{papers}} WHERE ai_status <> :none ORDER BY ai_reviewed_at DESC, id DESC LIMIT 20',
            ['none' => 'none']
        );

        // Papers whose author wrote "my subject area is not in your list". The
        // AI reviewer is what gets them out of that bucket, so surface them here
        // with a one-click pass instead of leaving the editor to hunt for them.
        $unclassified = Database::instance()->select(
            'SELECT id, uid, title, status, category_other, ai_status FROM {{papers}}'
            . ' WHERE category_other IS NOT NULL AND category_other <> :empty AND status <> :takedown'
            . ' ORDER BY id ASC LIMIT 50',
            ['empty' => '', 'takedown' => Paper::STATUS_TAKEDOWN]
        );
        $unclassifiedTotal = (int) Database::instance()->scalar(
            'SELECT COUNT(*) FROM {{papers}} WHERE category_other IS NOT NULL AND category_other <> :empty'
            . ' AND status <> :takedown',
            ['empty' => '', 'takedown' => Paper::STATUS_TAKEDOWN]
        );

        return $this->view('admin/ai', [
            'title'     => __('admin.ai'),
            'status'    => $status,
            'probe'     => $request->str('probe') === '1' ? AiReviewer::probe() : null,
            'queue'     => $queue,
            'recent'    => $recent,
            'unclassified' => $unclassified,
            'unclassifiedTotal' => $unclassifiedTotal,
            'sections'  => Section::ordered(false),
            'categories' => Category::ordered(),
            'defaultPrompt' => AiReviewer::defaultSystemPrompt(),
        ], 'layouts/admin');
    }

    public function saveAi(Request $request): Response
    {
        $text = ['ai.base_url', 'ai.api_key', 'ai.model', 'ai.system_prompt', 'ai.rubric_extra', 'ai.mode'];
        // Same rule as the settings screen: only keys the submitted form
        // actually carried are written, so a partial post cannot blank the
        // provider URL or the model name. (A blank api_key field keeps the
        // stored secret, which is what lets the panel mask it.)
        $present = static fn (string $key): bool => array_key_exists(str_replace('.', '_', $key), $_POST)
            || array_key_exists($key, $_POST);
        foreach ($text as $key) {
            if (!$present($key)) {
                continue;
            }
            $value = $request->dotted($key);
            if ($key === 'ai.api_key' && $value === '') {
                continue;
            }
            Settings::set($key, $value);
        }
        foreach (['ai.temperature'] as $key) {
            Settings::set($key, (float) str_replace(',', '.', $request->dotted($key, '0')));
        }
        foreach (['ai.max_input_chars', 'ai.timeout', 'ai.min_confidence'] as $key) {
            Settings::set($key, max(0, $request->dottedInt($key, (int) Settings::get($key, 0))));
        }
        foreach (['ai.enabled', 'ai.read_pdf', 'ai.auto_publish', 'ai.assign_section', 'ai.assign_category', 'ai.create_categories'] as $key) {
            Settings::set($key, $request->dottedBool($key));
        }
        if (!in_array(Settings::string('ai.mode'), ['off', 'semi', 'auto'], true)) {
            Settings::set('ai.mode', 'off');
        }

        AuditLog::record('settings.ai', 'settings', null, ['mode' => Settings::string('ai.mode')]);
        Session::flash('success', __('admin.ai_saved'));
        return $this->redirect(url('admin.ai'));
    }

    public function testAi(Request $request): Response
    {
        $probe = AiReviewer::probe();
        if ($probe['ok']) {
            // Remember the model list so the form can offer it as a datalist:
            // a stale or misspelled model name is the most common cause of a
            // failing review, and this removes the guesswork.
            Settings::set('ai.models', json_encode($probe['models'] ?? [], JSON_UNESCAPED_UNICODE));
            Settings::flush();
            Session::flash('success', __('admin.ai_probe_ok', ['count' => (string) count($probe['models'] ?? [])]));
        } else {
            Session::flash('error', __('admin.ai_probe_failed', ['error' => (string) ($probe['error'] ?? '')]));
        }
        return $this->redirect(url('admin.ai'));
    }

    /** "AI 一键审核": review the selected papers (or all pending ones). */
    public function runAi(Request $request): Response
    {
        $ids = $request->array('paper_ids');
        if ($request->str('scope') === 'all_pending') {
            $ids = array_map(
                static fn (array $row): int => (int) $row['id'],
                Paper::all(['status' => Paper::STATUS_PENDING], 'id ASC', 50)
            );
        } elseif ($request->str('scope') === 'unclassified') {
            // Everything sitting in "Other / not listed" — the AI assigns a real
            // subject area and the author's free-text note is cleared.
            $ids = array_map(
                static fn (array $row): int => (int) $row['id'],
                Database::instance()->select(
                    'SELECT id FROM {{papers}} WHERE category_other IS NOT NULL AND category_other <> :empty'
                    . ' AND status <> :takedown ORDER BY id ASC LIMIT 50',
                    ['empty' => '', 'takedown' => Paper::STATUS_TAKEDOWN]
                )
            );
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            Session::flash('error', __('ai.nothing_selected'));
            return $this->redirect(url('admin.ai'));
        }
        if (!AiReviewer::enabled()) {
            Session::flash('error', __('ai.not_configured'));
            return $this->redirect(url('admin.ai'));
        }

        $stats = PaperService::runAiBatch($ids, $request->bool('auto_publish'));
        Session::flash('success', __('ai.batch_done', [
            'checked'     => (string) $stats['checked'],
            'published'   => (string) $stats['published'],
            'rejected'    => (string) $stats['rejected'],
            'recommended' => (string) $stats['recommended'],
            'failed'      => (string) $stats['failed'],
            'duration'    => (string) $stats['duration'],
        ]));
        return $this->redirect(url('admin.ai'));
    }

    public function runAiOne(Request $request, string $id): Response
    {
        $paper = Paper::find((int) $id);
        if ($paper === null) {
            return View::error(404);
        }
        if (!AiReviewer::enabled()) {
            Session::flash('error', __('ai.not_configured'));
            return $this->redirect(url('admin.paper', ['id' => $paper['id']]));
        }

        $result = PaperService::runAiReview((int) $paper['id'], $request->bool('auto_publish'));
        if ($result['ok']) {
            Session::flash('success', __('ai.single_done', [
                'decision'   => Paper::aiDecisionLabel($result['verdict']['decision']),
                'confidence' => (string) $result['verdict']['confidence'],
                'action'     => $result['action'],
            ]));
        } else {
            Session::flash('error', __('admin.ai_probe_failed', ['error' => (string) $result['error']]));
        }
        return $this->redirect(url('admin.paper', ['id' => $paper['id']]));
    }

    // =====================================================================
    // Mail
    // =====================================================================

    public function mail(Request $request): Response
    {
        $status = Mailer::configured();
        return $this->view('admin/mail', [
            'title'   => __('admin.mail'),
            'status'  => $status,
            'values'  => Settings::all(),
            'fromAddress' => Mailer::fromAddress(),
            'openssl' => extension_loaded('openssl'),
            'tlsAvailable' => \Athenaeum\Core\Http::tlsAvailable(),
        ], 'layouts/admin');
    }

    public function saveMail(Request $request): Response
    {
        foreach (['mail.transport', 'mail.host', 'mail.encryption', 'mail.username', 'mail.from_address', 'mail.from_name', 'mail.reply_to'] as $key) {
            Settings::set($key, $request->dotted($key));
        }
        $password = $request->dotted('mail.password');
        if ($password !== '' || $request->dottedBool('mail.clear_password')) {
            Settings::set('mail.password', $password);
        }
        Settings::set('mail.port', max(1, min(65535, $request->dottedInt('mail.port', 465))));
        foreach (['mail.enabled', 'mail.notify_admin', 'mail.notify_author'] as $key) {
            Settings::set($key, $request->dottedBool($key));
        }
        if (!in_array(Settings::string('mail.transport'), ['smtp', 'mail'], true)) {
            Settings::set('mail.transport', 'smtp');
        }
        if (!in_array(Settings::string('mail.encryption'), ['ssl', 'tls', 'none'], true)) {
            Settings::set('mail.encryption', 'ssl');
        }

        AuditLog::record('settings.mail', 'settings', null, [
            'host' => Settings::string('mail.host'),
            'encryption' => Settings::string('mail.encryption'),
        ]);
        Session::flash('success', __('admin.mail_saved'));
        return $this->redirect(url('admin.mail'));
    }

    public function testMail(Request $request): Response
    {
        $to = $request->str('to') ?: (string) Auth::user()['email'];
        $result = Mailer::sendTest($to);
        if ($result['ok']) {
            Session::flash('success', __('admin.mail_test_ok', ['to' => $to]));
        } else {
            $detail = (string) ($result['error'] ?? '');
            if (!empty($result['log'])) {
                $detail .= ' | ' . implode(' // ', array_slice($result['log'], -4));
            }
            Session::flash('error', __('admin.mail_test_failed', ['to' => $to, 'error' => mb_substr($detail, 0, 500)]));
        }
        return $this->redirect(url('admin.mail'));
    }

    // =====================================================================
    // Category tree extras
    // =====================================================================

    public function moveCategory(Request $request, string $id): Response
    {
        $category = Category::find((int) $id);
        if ($category === null) {
            return View::error(404);
        }
        $parentId = $request->int('parent_id') ?: null;
        if ($parentId !== null) {
            // Refuse to move a branch inside itself.
            if (in_array($parentId, Category::descendantIds((int) $category['id']), true)) {
                Session::flash('error', __('admin.category_cycle'));
                return $this->redirect(url('admin.categories'));
            }
        }
        Category::update((int) $category['id'], [
            'parent_id'  => $parentId,
            'sort_order' => $request->int('sort_order', (int) $category['sort_order']),
        ]);
        AuditLog::record('category.move', 'category', (int) $category['id'], ['parent_id' => $parentId]);
        Session::flash('success', __('admin.category_moved'));
        return $this->redirect(url('admin.categories'));
    }
}
