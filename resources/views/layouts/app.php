<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Main site layout.
 *
 * @var string $content
 */

use Athenaeum\Core\Auth;
use Athenaeum\Core\I18n;
use Athenaeum\Core\Settings;
use Athenaeum\Core\Session;

$siteName = Settings::siteName();
$logoPath = Settings::string('site.logo_path');
$favicon = Settings::string('site.favicon_path');
$user = Auth::user();
$success = Session::getFlash('success');
$error = Session::getFlash('error');
$info = Session::getFlash('info');
$sections = \Athenaeum\Models\Section::ordered(true);
$locales = I18n::catalogue();
$currentLocale = I18n::locale();
$title = $title ?? $siteName;
$metaDescription = $metaDescription ?? __('meta.description');

// Announcement: colour, closability, and a revision key so a *new* notice is
// shown again to visitors who dismissed the previous one.
$noticeText = Settings::string('home.notice');
$noticeColor = Settings::string('notice.color', 'info');
$noticeDismissible = Settings::bool('notice.dismissible');
$noticeRevision = Settings::string('notice.revision');
if ($noticeRevision === '') {
    $noticeRevision = substr(md5($noticeText . '|' . $noticeColor), 0, 12);
}
$noticeDismissed = ($_COOKIE['athenaeum_notice'] ?? '') === $noticeRevision;
?>
<!DOCTYPE html>
<html lang="<?= e($currentLocale) ?>" dir="<?= e(I18n::direction()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($siteName) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<meta name="keywords" content="<?= e(__('meta.keywords')) ?>">
<?php
// Highwire Press tags for Google Scholar (title, authors, date, PDF link).
foreach (($citationMeta ?? []) as $citationName => $citationValues) {
    foreach ((array) $citationValues as $citationValue) {
        if ((string) $citationValue === '') {
            continue;
        }
        echo '<meta name="' . e($citationName) . '" content="' . e((string) $citationValue) . '">' . "\n";
    }
}
?>
<meta name="generator" content="AthenXiv">
<link rel="canonical" href="<?= e(path_url(current_path())) ?>">
<?php if ($favicon !== ''): ?>
<link rel="icon" href="<?= e(storage_url('branding', $favicon)) ?>">
<?php else: ?>
<link rel="icon" href="<?= e(\Athenaeum\Core\Config::baseUrl()) ?>/favicon.ico" sizes="any">
<link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(asset('assets/img/apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= e(\Athenaeum\Core\Config::baseUrl()) ?>/site.webmanifest">
<meta name="theme-color" content="#1d4ed8">
<?php endif; ?>
<link rel="alternate" hreflang="x-default" href="<?= e(path_url(current_path())) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<script>
window.ATHENAEUM = {
  base: <?= json_encode(\Athenaeum\Core\Config::baseUrl(), JSON_UNESCAPED_SLASHES) ?>,
  locale: <?= json_encode($currentLocale) ?>,
  csrf: <?= json_encode(csrf_token()) ?>,
  i18n: <?= json_encode([
      'copied' => __('common.copy'),
      'loading' => __('common.loading'),
      'error' => __('common.error_generic_title'),
      'no_results' => __('common.no_results'),
      'email_required' => __('validation.required', ['field' => __('auth.email')]),
  ], JSON_UNESCAPED_UNICODE) ?>
};
</script>
</head>
<body class="site">

<a class="skip-link" href="#main"><?= e(__('common.papers')) ?></a>

<header class="site-header">
  <div class="container site-header__inner">
    <a class="brand" href="<?= e(url('home')) ?>">
      <?php if ($logoPath !== ''): ?>
        <img class="brand__logo" src="<?= e(storage_url('branding', $logoPath)) ?>" alt="<?= e($siteName) ?>">
      <?php else: ?>
        <img class="brand__mark" src="<?= e(asset('assets/img/logo-mark.svg')) ?>"
             alt="" width="28" height="28" aria-hidden="true">
      <?php endif; ?>
      <span class="brand__name"><?= e($siteName) ?></span>
    </a>

    <nav class="site-nav" aria-label="<?= e(__('common.papers')) ?>">
      <a href="<?= e(url('paper.index')) ?>" class="<?= is_active('/papers', true) ? 'is-active' : '' ?>"><?= e(__('nav.browse')) ?></a>
      <?php foreach (array_slice($sections, 0, 4) as $section): ?>
        <a href="<?= e(url('paper.section', ['slug' => $section['slug']])) ?>"
           class="<?= is_active('/sections/' . $section['slug']) ? 'is-active' : '' ?>"><?= e(\Athenaeum\Models\Section::name($section)) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('paper.categories')) ?>" class="<?= is_active('/categories', true) ? 'is-active' : '' ?>"><?= e(__('common.categories')) ?></a>
      <a href="<?= e(url('page.about')) ?>" class="<?= is_active('/about') ? 'is-active' : '' ?>"><?= e(__('nav.about_site')) ?></a>
    </nav>

    <div class="site-header__actions">
      <form class="search-mini" action="<?= e(url('paper.index')) ?>" method="get" role="search">
        <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>"
               placeholder="<?= e(__('common.search')) ?>" aria-label="<?= e(__('common.search')) ?>">
      </form>

      <details class="lang-switch">
        <summary title="<?= e(__('common.language')) ?>"><?= e($locales[$currentLocale]['flag'] ?? 'EN') ?></summary>
        <ul class="lang-switch__list">
          <?php foreach ($locales as $code => $meta): ?>
            <li>
              <a href="<?= e(url('locale.switch', ['locale' => $code]) . '?next=' . rawurlencode(current_path())) ?>"
                 class="<?= $code === $currentLocale ? 'is-active' : '' ?>" hreflang="<?= e($code) ?>">
                <?= e($meta['name']) ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </details>

      <?php if ($user !== null): ?>
        <details class="user-menu">
          <summary>
            <?php $avatar = \Athenaeum\Models\User::avatarUrl($user); ?>
            <?php if ($avatar !== null): ?>
              <img class="avatar avatar--tiny" src="<?= e($avatar) ?>" alt="">
            <?php else: ?>
              <span class="avatar avatar--tiny avatar--initial"><?= e(mb_substr((string) $user['nickname'], 0, 1)) ?></span>
            <?php endif; ?>
            <span class="user-menu__name"><?= e($user['nickname']) ?></span>
          </summary>
          <ul class="user-menu__list">
            <li class="user-menu__id"><?= e(__('user.uid')) ?>: <code><?= e($user['uid']) ?></code></li>
            <li><a href="<?= e(url('dashboard')) ?>"><?= e(__('nav.dashboard')) ?></a></li>
            <li><a href="<?= e(url('dashboard.papers')) ?>"><?= e(__('nav.my_papers')) ?></a></li>
            <li><a href="<?= e(url('paper.create')) ?>"><?= e(__('nav.submit')) ?></a></li>
            <li><a href="<?= e(url('user.show', ['uid' => $user['uid']])) ?>"><?= e(__('nav.profile')) ?></a></li>
            <li><a href="<?= e(url('settings')) ?>"><?= e(__('nav.settings')) ?></a></li>
            <?php if (Auth::isAdmin()): ?>
              <li><a href="<?= e(url('admin.dashboard')) ?>"><?= e(__('nav.admin')) ?></a></li>
            <?php endif; ?>
            <li>
              <form method="post" action="<?= e(url('logout')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="link-button"><?= e(__('nav.logout')) ?></button>
              </form>
            </li>
          </ul>
        </details>
      <?php else: ?>
        <a class="btn btn--ghost btn--small" href="<?= e(url('login')) ?>"><?= e(__('nav.login')) ?></a>
        <?php if (Settings::bool('registration.open')): ?>
          <a class="btn btn--primary btn--small" href="<?= e(url('register')) ?>"><?= e(__('nav.register')) ?></a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</header>

<main id="main" class="site-main">
  <?php if (!$noticeDismissed) {
      \Athenaeum\Core\View::partial('partials/notice', [
          'notice'      => $noticeText,
          'color'       => $noticeColor,
          'dismissible' => $noticeDismissible,
          'revision'    => $noticeRevision,
      ]);
  } ?>
  <div class="container">
    <?php if ($success): ?><div class="alert alert--success" role="status"><?= e((string) $success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert--error" role="alert"><?= e((string) $error) ?></div><?php endif; ?>
    <?php if ($info): ?><div class="alert alert--info"><?= e((string) $info) ?></div><?php endif; ?>
  </div>
  <?= $content ?>
</main>

<footer class="site-footer">
  <div class="container site-footer__inner">
    <div class="site-footer__about">
      <strong><?= e($siteName) ?></strong>
      <p><?= e(Settings::siteTagline()) ?></p>
      <p class="muted small"><?= e(__('common.footer_note')) ?></p>
      <?php if (Settings::string('site.footer_text') !== ''): ?>
        <p class="small"><?= e(Settings::string('site.footer_text')) ?></p>
      <?php endif; ?>
    </div>
    <div class="site-footer__links">
      <a href="<?= e(url('page.about')) ?>"><?= e(__('nav.about_site')) ?></a>
      <a href="<?= e(url('page.athenaeum')) ?>"><?= e(__('page.athenaeum_title')) ?></a>
      <a href="<?= e(url('page.timestamping')) ?>"><?= e(__('page.timestamping_how_title')) ?></a>
      <a href="<?= e(url('page.guidelines')) ?>"><?= e(__('page.guidelines_title')) ?></a>
      <a href="<?= e(url('paper.index')) ?>"><?= e(__('nav.browse')) ?></a>
      <a href="<?= e(url('paper.categories')) ?>"><?= e(__('category.browse_title')) ?></a>
      <a href="<?= e(url('sitemap.xml')) ?>">sitemap.xml</a>
    </div>
    <div class="site-footer__meta">
      <?php $contact = Settings::string('site.contact_email'); ?>
      <?php if ($contact !== ''): ?>
        <p class="small"><?= e(__('common.contact')) ?? 'Contact' ?>:
          <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a></p>
      <?php endif; ?>
      <?php if (Settings::string('site.icp') !== ''): ?>
        <p class="small"><?= e(Settings::string('site.icp')) ?></p>
      <?php endif; ?>
      <p class="small muted">AthenXiv v<?= e(\ATHENAEUM_VERSION) ?></p>
      <?php
      // Small print at the very bottom: the source is open. The :site
      // placeholder becomes the link, so the sentence stays translatable
      // without hard-coding a URL or the word GitHub into 30 language files.
      ?>
      <p class="small muted">
        <?= str_replace(
            ':site',
            '<a href="https://github.com/AthenXiv/athenxiv" target="_blank" rel="noopener">GitHub</a>',
            e(__('footer.open_source'))
        ) ?>
      </p>
    </div>
  </div>
</footer>

<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
<?php if (Settings::string('site.analytics') !== ''): ?>
<?= Settings::string('site.analytics') ?>
<?php endif; ?>
</body>
</html>
