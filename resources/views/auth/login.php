<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
 /** @var string $heading */ ?>
<section class="card card--auth">
  <h1 class="card__title"><?= e($heading) ?></h1>
  <p class="muted small"><?= e(__('auth.login_subtitle', ['site' => \Athenaeum\Core\Settings::siteName()])) ?></p>

  <form method="post" action="<?= e(url('login')) ?>" class="form">
    <?= csrf_field() ?>
    <div class="form-row">
      <label for="email"><?= e(__('auth.email')) ?></label>
      <input type="email" id="email" name="email" required autocomplete="username"
             value="<?= e((string) old('email')) ?>">
      <?php \Athenaeum\Core\View::partial('partials/field_error', ['field' => 'email']); ?>
    </div>
    <div class="form-row">
      <label for="password"><?= e(__('auth.password')) ?></label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
      <?php \Athenaeum\Core\View::partial('partials/field_error', ['field' => 'password']); ?>
    </div>
    <button class="btn btn--primary btn--block" type="submit"><?= e(__('auth.login_cta')) ?></button>
  </form>

  <?php if (\Athenaeum\Core\Settings::bool('registration.open')): ?>
    <p class="card__foot small">
      <?= e(__('auth.no_account')) ?>
      <a href="<?= e(url('register')) ?>"><?= e(__('nav.register')) ?></a>
    </p>
  <?php endif; ?>
</section>
