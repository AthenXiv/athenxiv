<?php

declare(strict_types=1);

namespace Athenaeum\Controllers;

use Athenaeum\Core\Auth;
use Athenaeum\Core\Config;
use Athenaeum\Core\I18n;
use Athenaeum\Core\Markdown;
use Athenaeum\Core\Request;
use Athenaeum\Core\Response;
use Athenaeum\Core\Session;
use Athenaeum\Core\Settings;
use Athenaeum\Core\Str;
use Athenaeum\Core\Validator;
use Athenaeum\Core\View;
use Athenaeum\Models\AuditLog;
use Athenaeum\Models\Paper;
use Athenaeum\Models\Timestamp;
use Athenaeum\Models\User;
use Athenaeum\Models\UserLink;
use Athenaeum\Services\Uploader;

final class UserController extends Controller
{
    // =====================================================================
    // Author area
    // =====================================================================

    public function dashboard(Request $request): Response
    {
        $user = $this->requireUser();
        $userId = (int) $user['id'];

        $papers = Paper::forUser($userId);
        $summary = Paper::statusSummary($userId);

        $timestamps = \Athenaeum\Core\Database::instance()->select(
            'SELECT t.*, p.uid AS paper_uid, p.title AS paper_title, p.status AS paper_status'
            . ' FROM {{timestamps}} t JOIN {{papers}} p ON p.id = t.paper_id'
            . ' WHERE p.uploader_id = :id ORDER BY t.id DESC LIMIT 10',
            ['id' => $userId]
        );

        return $this->view('dashboard/index', [
            'title'      => __('dashboard.title'),
            'user'       => $user,
            'papers'     => array_slice($papers, 0, 5),
            'summary'    => $summary,
            'total'      => count($papers),
            'timestamps' => $timestamps,
            'profileUrl' => User::profileUrl($user),
            'avatar'     => User::avatarUrl($user),
        ]);
    }

    /** The author's own submission list (statuses, actions, OTS state). */
    public function managePapers(Request $request): Response
    {
        $user = $this->requireUser();
        $status = $request->str('status');
        $where = ['uploader_id' => (int) $user['id']];
        if ($status !== '' && in_array($status, Paper::STATUSES, true)) {
            $where['status'] = $status;
        }
        $result = Paper::paginate($where, max(1, $request->int('page', 1)), 20, 'id DESC');

        $decorated = [];
        foreach ($result['items'] as $paper) {
            $paper['timestamp'] = Paper::timestamp((int) $paper['id'], 'pdf');
            $paper['author_line'] = Paper::authorLine($paper);
            $decorated[] = $paper;
        }
        $result['items'] = $decorated;

        return $this->view('dashboard/papers', [
            'title'   => __('dashboard.my_papers'),
            'result'  => $result,
            'status'  => $status,
            'summary' => Paper::statusSummary((int) $user['id']),
        ]);
    }

    public function settings(Request $request): Response
    {
        $user = $this->requireUser();
        $links = [];
        foreach (UserLink::forUser((int) $user['id']) as $link) {
            $links[(string) $link['platform']] = UserLink::rawValue((string) $link['platform'], (string) $link['url']);
        }

        return $this->view('dashboard/settings', [
            'title'     => __('settings.title'),
            'user'      => $user,
            'links'     => $links,
            'platforms' => UserLink::platforms(),
            'avatar'    => User::avatarUrl($user),
            'locales'   => I18n::catalogue(),
            'maxAvatar' => Settings::int('upload.max_avatar_kb', 512),
            'minPassword' => (int) Config::get('security.password_min_length', 10),
        ]);
    }

    public function updateProfile(Request $request): Response
    {
        $user = $this->requireUser();
        $validator = Validator::make($request->all(), [
            'nickname'     => 'required|string|min:2|max:80',
            'display_name' => 'nullable|string|max:120',
            'affiliation'  => 'nullable|string|max:190',
            'bio'          => 'nullable|string|max:500',
            'homepage_md'  => 'nullable|string|max:20000',
        ]);
        if ($validator->fails()) {
            Session::flash('errors', $validator->errors());
            Session::flash('error', $validator->firstError());
            Session::flashInput($request->all());
            return $this->redirect(url('settings') . '#profile');
        }

        User::update((int) $user['id'], [
            'nickname'     => $request->str('nickname'),
            'display_name' => $request->str('display_name') ?: $request->str('nickname'),
            'affiliation'  => $request->str('affiliation') ?: null,
            'bio'          => $request->str('bio') ?: null,
            'homepage_md'  => Settings::bool('ui.allow_profile_markdown')
                ? ($request->str('homepage_md') ?: null)
                : null,
        ]);

        Session::flash('success', __('settings.profile_saved'));
        return $this->redirect(url('settings') . '#profile');
    }

    public function updateLinks(Request $request): Response
    {
        $user = $this->requireUser();
        $submitted = [];
        $invalid = [];

        foreach (UserLink::platforms() as $platform => $meta) {
            $value = $request->str('link_' . $platform);
            if ($value === '') {
                continue;
            }
            if (!UserLink::validate($platform, $value)) {
                $invalid[] = $meta['label'];
                continue;
            }
            $submitted[$platform] = $value;
        }

        if ($invalid !== []) {
            Session::flash('error', __('settings.link_invalid', ['fields' => implode(', ', $invalid)]));
            return $this->redirect(url('settings') . '#links');
        }

        UserLink::syncForUser((int) $user['id'], $submitted);
        Session::flash('success', __('settings.links_saved'));
        return $this->redirect(url('settings') . '#links');
    }

    public function updateAvatar(Request $request): Response
    {
        $user = $this->requireUser();
        $file = $request->file('avatar');
        if ($file === null) {
            Session::flash('error', __('upload.error_no_file'));
            return $this->redirect(url('settings') . '#avatar');
        }

        $limitKb = Settings::int('upload.max_avatar_kb', 512);
        $stored = Uploader::store($file, [
            'kind'      => Uploader::KIND_IMAGE,
            'max_bytes' => $limitKb * 1024,
            'subdir'    => 'avatars',
        ]);
        if (!$stored['ok']) {
            Session::flash('error', (string) $stored['error']);
            return $this->redirect(url('settings') . '#avatar');
        }

        $absolute = $stored['data']['absolute'];
        Uploader::shrinkImage($absolute, 512);

        // Flat filename so the /media/avatars/{file} route stays trivial.
        $flatName = basename((string) $stored['data']['path']);
        $flatPath = Config::path('uploads', 'avatars/' . $flatName);
        if ($absolute !== $flatPath) {
            @rename($absolute, $flatPath);
            if (is_file($absolute)) {
                @unlink($absolute);
            }
        }

        $old = Config::path('uploads', 'avatars/' . (string) ($user['avatar_path'] ?? ''));
        User::update((int) $user['id'], ['avatar_path' => $flatName]);
        if (($user['avatar_path'] ?? '') !== '' && is_file($old)) {
            @unlink($old);
        }

        Session::flash('success', __('settings.avatar_saved'));
        return $this->redirect(url('settings') . '#avatar');
    }

    public function updatePassword(Request $request): Response
    {
        $user = $this->requireUser();
        $min = (int) Config::get('security.password_min_length', 10);

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password'         => 'required|string|min:' . $min . '|same:password_confirmation',
        ]);
        if ($validator->fails()) {
            Session::flash('error', $validator->firstError());
            return $this->redirect(url('settings') . '#password');
        }
        if (!password_verify((string) $request->input('current_password', ''), (string) $user['password_hash'])) {
            Session::flash('error', __('settings.current_password_wrong'));
            return $this->redirect(url('settings') . '#password');
        }

        User::updatePassword((int) $user['id'], (string) $request->input('password', ''));
        AuditLog::record('user.password', 'user', (int) $user['id']);
        Session::flash('success', __('settings.password_saved'));
        return $this->redirect(url('settings') . '#password');
    }

    public function updatePreferences(Request $request): Response
    {
        $user = $this->requireUser();
        $locale = $request->str('locale');
        if ($locale !== '' && isset(I18n::CATALOGUE[$locale])) {
            User::update((int) $user['id'], ['locale' => $locale]);
            I18n::setLocale($locale);
        }
        Session::flash('success', __('settings.preferences_saved'));
        return $this->redirect(url('settings'));
    }

    // =====================================================================
    // Public profile
    // =====================================================================

    public function show(Request $request, string $uid): Response
    {
        $user = User::findByUid($uid);
        if ($user === null) {
            return View::error(404);
        }
        if (($user['status'] ?? 'active') === 'banned' && !Auth::isAdmin()) {
            return View::error(404);
        }

        $userId = (int) $user['id'];
        $result = Paper::paginate(
            ['uploader_id' => $userId, 'status' => Paper::STATUS_APPROVED, 'visibility' => 'public'],
            max(1, $request->int('page', 1)),
            10,
            'published_at DESC, id DESC'
        );

        $links = [];
        foreach (UserLink::forUser($userId) as $link) {
            $platform = (string) $link['platform'];
            $meta = UserLink::platforms()[$platform] ?? ['label' => ucfirst($platform), 'icon' => 'link'];
            $links[] = [
                'platform' => $platform,
                'label'    => $meta['label'],
                'icon'     => $meta['icon'],
                'url'      => (string) $link['url'],
            ];
        }

        $homepageHtml = '';
        if (Settings::bool('ui.allow_profile_markdown') && !empty($user['homepage_md'])) {
            $homepageHtml = Markdown::render((string) $user['homepage_md']);
        }

        return $this->view('users/show', [
            'title'        => (string) $user['display_name'] ?: (string) $user['nickname'],
            'profile'      => $user,
            'avatar'       => User::avatarUrl($user),
            'links'        => $links,
            'homepageHtml' => $homepageHtml,
            'result'       => $result,
            'isSelf'       => Auth::id() === $userId,
            'orcid'        => $this->orcidOf($links),
        ]);
    }

    public function papers(Request $request, string $uid): Response
    {
        $user = User::findByUid($uid);
        if ($user === null) {
            return View::error(404);
        }
        $result = Paper::paginate(
            ['uploader_id' => (int) $user['id'], 'status' => Paper::STATUS_APPROVED, 'visibility' => 'public'],
            max(1, $request->int('page', 1)),
            20,
            'published_at DESC, id DESC'
        );

        return $this->view('users/papers', [
            'title'  => __('user.papers_of', ['name' => (string) ($user['display_name'] ?: $user['nickname'])]),
            'profile' => $user,
            'result' => $result,
        ]);
    }

    /** @param array<int,array<string,mixed>> $links */
    private function orcidOf(array $links): ?string
    {
        foreach ($links as $link) {
            if ($link['platform'] === 'orcid') {
                return (string) $link['url'];
            }
        }
        return null;
    }
}
