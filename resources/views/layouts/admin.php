<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Administration layout: sidebar + work area.
 *
 * @var string $content
 */

use Athenaeum\Core\Auth;
use Athenaeum\Core\Settings;
use Athenaeum\Core\Session;

$siteName = Settings::siteName();
$user = Auth::user();
$success = Session::getFlash('success');
$error = Session::getFlash('error');
$title = $title ?? __('admin.dashboard');
$pendingCount = \Athenaeum\Models\Paper::pendingCount();

$nav = [
    ['route' => 'admin.dashboard',  'label' => __('admin.dashboard'),  'match' => '/admin',           'exact' => true],
    ['route' => 'admin.papers',     'label' => __('admin.papers'),     'match' => '/admin/paper',     'badge' => $pendingCount],
    ['route' => 'admin.ai',         'label' => __('admin.ai'),         'match' => '/admin/ai'],
    ['route' => 'admin.upload',     'label' => __('admin.proxy_upload'), 'match' => '/admin/upload'],
    ['route' => 'admin.users',      'label' => __('admin.users'),      'match' => '/admin/user'],
    ['route' => 'admin.sections',   'label' => __('admin.sections'),   'match' => '/admin/sections'],
    ['route' => 'admin.categories', 'label' => __('admin.categories'), 'match' => '/admin/categories'],
    ['route' => 'admin.pages',      'label' => __('admin.pages'),      'match' => '/admin/page'],
    ['route' => 'admin.timestamps', 'label' => __('admin.timestamps'), 'match' => '/admin/timestamps'],
    ['route' => 'admin.mail',       'label' => __('admin.mail'),       'match' => '/admin/mail'],
    ['route' => 'admin.settings',   'label' => __('admin.settings'),   'match' => '/admin/settings'],
    ['route' => 'admin.audit',      'label' => __('admin.audit'),      'match' => '/admin/audit'],
];
?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>" dir="<?= e(\Athenaeum\Core\I18n::direction()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($siteName) ?> admin</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<script>
window.ATHENAEUM = {
  base: <?= json_encode(\Athenaeum\Core\Config::baseUrl(), JSON_UNESCAPED_SLASHES) ?>,
  locale: <?= json_encode(locale()) ?>,
  csrf: <?= json_encode(csrf_token()) ?>
};
</script>
</head>
<body class="admin">

<header class="admin-header">
  <div class="admin-header__inner">
    <a class="brand brand--small" href="<?= e(url('admin.dashboard')) ?>">
      <span class="brand__mark" aria-hidden="true">Α</span>
      <span class="brand__name"><?= e($siteName) ?></span>
      <span class="brand__suffix"><?= e(__('nav.admin')) ?></span>
    </a>
    <div class="admin-header__actions">
      <a class="btn btn--ghost btn--small" href="<?= e(url('home')) ?>"><?= e(__('common.home')) ?></a>
      <a class="btn btn--ghost btn--small" href="<?= e(url('dashboard')) ?>"><?= e(__('nav.dashboard')) ?></a>
      <form method="post" action="<?= e(url('logout')) ?>" class="inline">
        <?= csrf_field() ?>
        <button class="btn btn--ghost btn--small" type="submit"><?= e(__('nav.logout')) ?></button>
      </form>
    </div>
  </div>
</header>

<div class="admin-shell">
  <aside class="admin-side">
    <nav>
      <?php foreach ($nav as $item): ?>
        <?php
        $active = is_active($item['match'], !($item['exact'] ?? false))
            && (!($item['exact'] ?? false) || current_path() === $item['match']);
        ?>
        <a href="<?= e(url($item['route'])) ?>" class="admin-side__link <?= $active ? 'is-active' : '' ?>">
          <?= e($item['label']) ?>
          <?php if (!empty($item['badge'])): ?>
            <span class="badge badge--warn"><?= (int) $item['badge'] ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="admin-side__foot small muted">
      <p><?= e($user['nickname'] ?? '') ?> · <code><?= e($user['uid'] ?? '') ?></code></p>
    </div>
  </aside>

  <main class="admin-main">
    <?php if ($success): ?><div class="alert alert--success" role="status"><?= e((string) $success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert--error" role="alert"><?= e((string) $error) ?></div><?php endif; ?>
    <?= $content ?>
  </main>
</div>

<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
