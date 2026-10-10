<?php
/**
 * Athenaeum route table.
 *
 * Read this file top-to-bottom to see the whole surface of the platform.
 * Middleware names: auth, admin, guest, csrf.
 */

declare(strict_types=1);

// Direct access to this file must not execute anything (the document root may
// be the project root on hosts that cannot rewrite).
if (!defined('ATHENAEUM_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

use Athenaeum\Controllers\AdminController;
use Athenaeum\Controllers\ApiController;
use Athenaeum\Controllers\AuthController;
use Athenaeum\Controllers\HomeController;
use Athenaeum\Controllers\MediaController;
use Athenaeum\Controllers\PaperController;
use Athenaeum\Controllers\UserController;
use Athenaeum\Core\App;
use Athenaeum\Core\View;

$router = App::router();

// ---------------------------------------------------------------------------
// Public site
// ---------------------------------------------------------------------------
$router->get('/', [HomeController::class, 'index'])->name('home');
$router->get('/papers', [PaperController::class, 'index'])->name('paper.index');
$router->get('/sections/{slug}', [PaperController::class, 'section'])->name('paper.section');
$router->get('/categories', [PaperController::class, 'categories'])->name('paper.categories');
$router->get('/categories/{slug}', [PaperController::class, 'category'])->name('paper.category');
$router->get('/paper/{uid}.pdf', [PaperController::class, 'pdf'])->name('paper.pdf');
$router->get('/paper/{uid:[A-Za-z0-9\-]+}', [PaperController::class, 'show'])->name('paper.show');
$router->get('/paper/{uid}/file', [PaperController::class, 'file'])->name('paper.file');
// Google Scholar wants a direct link that ends in .pdf and answers with
// Content-Type: application/pdf. /paper/ATH-XXXX.pdf does exactly that.
$router->get('/paper/{uid}/download', [PaperController::class, 'download'])->name('paper.download');
$router->get('/paper/{uid}/preview', [PaperController::class, 'preview'])->name('paper.preview');
$router->get('/paper/{uid}/version/{version}', [PaperController::class, 'downloadVersion'])->name('paper.version.download');
$router->get('/paper/{uid}/version/{version}/file', [PaperController::class, 'versionFile'])->name('paper.version.file');
$router->get('/paper/{uid}/attachment/{attachment}', [PaperController::class, 'attachment'])->name('paper.attachment');
$router->get('/paper/{uid}/proof/{timestamp}', [PaperController::class, 'proof'])->name('paper.timestamp.download');
$router->get('/paper/{uid}/cite', [PaperController::class, 'cite'])->name('paper.cite');
$router->get('/u/{uid}', [UserController::class, 'show'])->name('user.show');
$router->get('/u/{uid}/papers', [UserController::class, 'papers'])->name('user.papers');
// Editable pages (content lives in the database, slug per route).
$router->get('/about', [HomeController::class, 'about'])->name('page.about');
$router->get('/about/athenaeum', [HomeController::class, 'athenaeum'])->name('page.athenaeum');
$router->get('/about/timestamping', [HomeController::class, 'timestamping'])->name('page.timestamping');
$router->get('/guidelines', [HomeController::class, 'guidelines'])->name('page.guidelines');
$router->get('/policy', [HomeController::class, 'policy'])->name('page.policy');
$router->get('/p/{slug}', [HomeController::class, 'customPage'])->name('page.custom');
// A content page's own OpenTimestamps proof, plus the exact text it commits to.
// No uid and no account: verification must never depend on trusting this site.
$router->get('/page-proof/{timestamp}', [HomeController::class, 'pageProof'])->name('page.timestamp.proof');
$router->get('/page-snapshot/{timestamp}', [HomeController::class, 'pageSnapshot'])->name('page.timestamp.snapshot');
$router->get('/media/{kind}/{file}', [MediaController::class, 'show'])->name('media');
// Bundled assets (PDF.js) with correct MIME types — nginx would serve .mjs as
// application/octet-stream and the viewer would never run. Matches nested paths.
$router->get('/vendor/{path:.+}', [MediaController::class, 'vendor'])->name('vendor.file');
$router->get('/locale/{locale}', [HomeController::class, 'locale'])->name('locale.switch');
$router->get('/robots.txt', [HomeController::class, 'robots']);
$router->get('/sitemap.xml', [HomeController::class, 'sitemap']);

// ---------------------------------------------------------------------------
// Authentication
// ---------------------------------------------------------------------------
$router->get('/login', [AuthController::class, 'loginForm'])->name('login')->middleware('guest');
$router->post('/login', [AuthController::class, 'login'])->middleware(['guest', 'csrf']);
$router->get('/register', [AuthController::class, 'registerForm'])->name('register')->middleware('guest');
$router->post('/register', [AuthController::class, 'register'])->middleware(['guest', 'csrf']);
$router->post('/register/code', [AuthController::class, 'sendCode'])->name('register.code')->middleware(['guest', 'csrf']);
// Password recovery: the code arrives by e-mail, the new password is chosen on
// the same page (POST /password/reset). Both routes are for guests only — a
// signed-in visitor changes the password from /settings instead.
$router->get('/password/forgot', [AuthController::class, 'forgotForm'])->name('password.request')->middleware('guest');
$router->post('/password/forgot', [AuthController::class, 'sendResetCode'])->name('password.email')->middleware(['guest', 'csrf']);
$router->post('/password/reset', [AuthController::class, 'reset'])->name('password.update')->middleware(['guest', 'csrf']);
$router->post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware(['auth', 'csrf']);
$router->get('/logout', static function () {
    return \Athenaeum\Core\Response::redirect(url('home'));
});

// ---------------------------------------------------------------------------
// Author area
// ---------------------------------------------------------------------------
$router->group(['auth'], function ($router): void {
    $router->get('/dashboard', [UserController::class, 'dashboard'])->name('dashboard');
    $router->get('/dashboard/papers', [UserController::class, 'managePapers'])->name('dashboard.papers');

    $router->get('/submit', [PaperController::class, 'createForm'])->name('paper.create');
    $router->post('/submit', [PaperController::class, 'store'])->name('paper.store')->middleware('csrf');
    $router->get('/paper/{uid}/edit', [PaperController::class, 'editForm'])->name('paper.edit');
    $router->post('/paper/{uid}/edit', [PaperController::class, 'update'])->name('paper.update')->middleware('csrf');
    $router->post('/paper/{uid}/submit', [PaperController::class, 'submit'])->name('paper.submit')->middleware('csrf');
    $router->post('/paper/{uid}/version', [PaperController::class, 'storeVersion'])->name('paper.version.store')->middleware('csrf');
    $router->post('/paper/{uid}/withdraw', [PaperController::class, 'withdraw'])->name('paper.withdraw')->middleware('csrf');
    $router->post('/paper/{uid}/delete', [PaperController::class, 'destroy'])->name('paper.destroy')->middleware('csrf');
    $router->post('/paper/{uid}/attachment/{attachment}/delete', [PaperController::class, 'deleteAttachment'])->name('paper.attachment.delete')->middleware('csrf');

    $router->get('/settings', [UserController::class, 'settings'])->name('settings');
    $router->post('/settings/profile', [UserController::class, 'updateProfile'])->name('settings.profile')->middleware('csrf');
    $router->post('/settings/links', [UserController::class, 'updateLinks'])->name('settings.links')->middleware('csrf');
    $router->post('/settings/avatar', [UserController::class, 'updateAvatar'])->name('settings.avatar')->middleware('csrf');
    $router->post('/settings/password', [UserController::class, 'updatePassword'])->name('settings.password')->middleware('csrf');
    $router->post('/settings/preferences', [UserController::class, 'updatePreferences'])->name('settings.preferences')->middleware('csrf');
});

// ---------------------------------------------------------------------------
// Administration
// ---------------------------------------------------------------------------
$router->group(['admin'], function ($router): void {
    $router->get('/admin', [AdminController::class, 'dashboard'])->name('admin.dashboard');

    // Who crawled us — the poor man's access log for shared hosting.
$router->get('/admin/crawlers', [AdminController::class, 'crawlers'])->name('admin.crawlers');
$router->get('/admin/papers', [AdminController::class, 'papers'])->name('admin.papers');
    $router->get('/admin/paper/{id}', [AdminController::class, 'paper'])->name('admin.paper');
    $router->get('/admin/paper/{id}/edit', [AdminController::class, 'editPaper'])->name('admin.paper.edit');
    $router->post('/admin/paper/{id}/edit', [AdminController::class, 'updatePaper'])->middleware('csrf');
    $router->get('/admin/paper/{id}/file', [AdminController::class, 'paperFile'])->name('admin.paper.file');
    $router->get('/admin/paper/{id}/download', [AdminController::class, 'paperDownload'])->name('admin.paper.download');
    $router->post('/admin/paper/{id}/approve', [AdminController::class, 'approve'])->name('admin.paper.approve')->middleware('csrf');
    $router->post('/admin/paper/{id}/reject', [AdminController::class, 'reject'])->name('admin.paper.reject')->middleware('csrf');
    $router->post('/admin/paper/{id}/takedown', [AdminController::class, 'takedown'])->name('admin.paper.takedown')->middleware('csrf');
    $router->post('/admin/paper/{id}/restore', [AdminController::class, 'restore'])->name('admin.paper.restore')->middleware('csrf');
    $router->post('/admin/paper/{id}/section', [AdminController::class, 'assignSection'])->name('admin.paper.section')->middleware('csrf');
    $router->post('/admin/paper/{id}/reclassify', [AdminController::class, 'reclassify'])->name('admin.paper.reclassify')->middleware('csrf');
    $router->post('/admin/paper/{id}/feature', [AdminController::class, 'feature'])->name('admin.paper.feature')->middleware('csrf');
    $router->post('/admin/paper/{id}/purge', [AdminController::class, 'purge'])->name('admin.paper.purge')->middleware('csrf');

    $router->get('/admin/upload', [AdminController::class, 'uploadForm'])->name('admin.upload');
    $router->post('/admin/upload', [AdminController::class, 'uploadStore'])->name('admin.upload.store')->middleware('csrf');

    $router->get('/admin/users', [AdminController::class, 'users'])->name('admin.users');
    $router->get('/admin/user/{id}', [AdminController::class, 'user'])->name('admin.user');
    $router->post('/admin/users', [AdminController::class, 'createUser'])->name('admin.users.create')->middleware('csrf');
    $router->post('/admin/user/{id}/status', [AdminController::class, 'userStatus'])->name('admin.user.status')->middleware('csrf');
    $router->post('/admin/user/{id}/role', [AdminController::class, 'userRole'])->name('admin.user.role')->middleware('csrf');
    $router->post('/admin/user/{id}/password', [AdminController::class, 'userPassword'])->name('admin.user.password')->middleware('csrf');
    $router->post('/admin/user/{id}/profile', [AdminController::class, 'userProfile'])->name('admin.user.profile')->middleware('csrf');
    $router->post('/admin/user/{id}/purge', [AdminController::class, 'userPurge'])->name('admin.user.purge')->middleware('csrf');

    $router->get('/admin/sections', [AdminController::class, 'sections'])->name('admin.sections');
    $router->post('/admin/sections', [AdminController::class, 'createSection'])->name('admin.sections.create')->middleware('csrf');
    $router->post('/admin/sections/{id}', [AdminController::class, 'updateSection'])->name('admin.sections.update')->middleware('csrf');
    $router->post('/admin/sections/{id}/default', [AdminController::class, 'defaultSection'])->name('admin.sections.default')->middleware('csrf');
    $router->post('/admin/sections/{id}/purge', [AdminController::class, 'purgeSection'])->name('admin.sections.purge')->middleware('csrf');

    $router->get('/admin/categories', [AdminController::class, 'categories'])->name('admin.categories');
    $router->post('/admin/categories', [AdminController::class, 'createCategory'])->name('admin.categories.create')->middleware('csrf');
    $router->post('/admin/categories/{id}', [AdminController::class, 'updateCategory'])->name('admin.categories.update')->middleware('csrf');
    $router->post('/admin/categories/{id}/purge', [AdminController::class, 'purgeCategory'])->name('admin.categories.purge')->middleware('csrf');
    $router->post('/admin/categories/{id}/move', [AdminController::class, 'moveCategory'])->name('admin.categories.move')->middleware('csrf');

    // Editable content pages
    $router->get('/admin/pages', [AdminController::class, 'pages'])->name('admin.pages');
    $router->get('/admin/page/{id}', [AdminController::class, 'page'])->name('admin.page');
    $router->post('/admin/page/{id}', [AdminController::class, 'savePage'])->name('admin.page.save')->middleware('csrf');
    $router->post('/admin/pages', [AdminController::class, 'createPage'])->name('admin.pages.create')->middleware('csrf');
    $router->post('/admin/pages/stamp', [AdminController::class, 'stampAllPages'])->name('admin.pages.stamp')->middleware('csrf');
    $router->post('/admin/page/{id}/stamp', [AdminController::class, 'restampPage'])->name('admin.page.stamp')->middleware('csrf');
    $router->post('/admin/page/{id}/purge', [AdminController::class, 'purgePage'])->name('admin.page.purge')->middleware('csrf');

    // AI review
    $router->get('/admin/ai', [AdminController::class, 'ai'])->name('admin.ai');
    $router->post('/admin/ai', [AdminController::class, 'saveAi'])->name('admin.ai.save')->middleware('csrf');
    $router->post('/admin/ai/test', [AdminController::class, 'testAi'])->name('admin.ai.test')->middleware('csrf');
    $router->post('/admin/ai/review', [AdminController::class, 'runAi'])->name('admin.ai.review')->middleware('csrf');
    $router->post('/admin/ai/paper/{id}', [AdminController::class, 'runAiOne'])->name('admin.ai.paper')->middleware('csrf');

    // Mail
    $router->get('/admin/mail', [AdminController::class, 'mail'])->name('admin.mail');
    $router->post('/admin/mail', [AdminController::class, 'saveMail'])->name('admin.mail.save')->middleware('csrf');
    $router->post('/admin/mail/test', [AdminController::class, 'testMail'])->name('admin.mail.test')->middleware('csrf');

    $router->get('/admin/settings', [AdminController::class, 'settings'])->name('admin.settings');
    $router->post('/admin/settings', [AdminController::class, 'updateSettings'])->name('admin.settings.update')->middleware('csrf');
    $router->post('/admin/settings/branding', [AdminController::class, 'updateBranding'])->name('admin.settings.branding')->middleware('csrf');

    $router->get('/admin/timestamps', [AdminController::class, 'timestamps'])->name('admin.timestamps');
    $router->post('/admin/timestamps/upgrade', [AdminController::class, 'upgradeTimestamps'])->name('admin.timestamps.upgrade')->middleware('csrf');
    $router->get('/admin/timestamps/{id}/proof', [AdminController::class, 'downloadProof'])->name('admin.timestamps.proof');

    $router->get('/admin/audit', [AdminController::class, 'audit'])->name('admin.audit');
});

// ---------------------------------------------------------------------------
// JSON endpoints
// ---------------------------------------------------------------------------
$router->get('/api/papers', [ApiController::class, 'papers'])->name('api.papers');
$router->get('/api/search', [ApiController::class, 'search'])->name('api.search');
$router->get('/api/stats', [ApiController::class, 'stats'])->name('api.stats');
$router->post('/api/ots/upgrade', [ApiController::class, 'upgradeTimestamps'])->middleware(['admin', 'csrf']);

// ---------------------------------------------------------------------------
// Fallback
// ---------------------------------------------------------------------------
$router->any('/{any}', static function () {
    return View::error(404, __('common.error_404_message'));
});
