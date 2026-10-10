<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/** Centred card layout for sign-in / sign-up. @var string $content */

use Athenaeum\Core\Settings;
use Athenaeum\Core\Session;

$siteName = Settings::siteName();
$logoPath = Settings::string('site.logo_path');
$success = Session::getFlash('success');
$error = Session::getFlash('error');
?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? $siteName) ?> · <?= e($siteName) ?></title>
<meta name="robots" content="noindex, follow">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body class="auth">
<main class="auth__shell">
  <a class="brand brand--center" href="<?= e(url('home')) ?>">
    <?php if ($logoPath !== ''): ?>
      <img class="brand__logo" src="<?= e(storage_url('branding', $logoPath)) ?>" alt="<?= e($siteName) ?>">
    <?php else: ?>
      <span class="brand__mark" aria-hidden="true">Α</span>
    <?php endif; ?>
    <span class="brand__name"><?= e($siteName) ?></span>
  </a>

  <?php if ($success): ?><div class="alert alert--success"><?= e((string) $success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert--error" role="alert"><?= e((string) $error) ?></div><?php endif; ?>

  <?= $content ?>

  <p class="auth__foot small muted">
    <a href="<?= e(url('home')) ?>"><?= e(__('common.home')) ?></a> ·
    <a href="<?= e(url('page.about')) ?>"><?= e(__('common.about')) ?></a> ·
    <a href="<?= e(url('page.timestamping')) ?>"><?= e(__('nav.timestamping')) ?></a>
  </p>

  <?php
  // The language has to be choosable *before* signing in: the verification and
  // password-reset mails are written in the language the visitor reads here.
  $authLocale = locale();
  $authLocales = \Athenaeum\Core\I18n::enabledLocales();
  $authCatalogue = \Athenaeum\Core\I18n::catalogue();
  ?>
  <details class="lang-switch lang-switch--auth">
    <summary title="<?= e(__('common.language')) ?>">
      <?= e($authCatalogue[$authLocale]['name'] ?? strtoupper($authLocale)) ?>
    </summary>
    <ul class="lang-switch__list">
      <?php foreach ($authLocales as $authCode): ?>
        <li>
          <a href="<?= e(url('locale.switch', ['locale' => $authCode]) . '?next=' . rawurlencode(current_path())) ?>"
             class="<?= $authCode === $authLocale ? 'is-active' : '' ?>" hreflang="<?= e($authCode) ?>">
            <?= e($authCatalogue[$authCode]['name'] ?? $authCode) ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </details>
</main>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
