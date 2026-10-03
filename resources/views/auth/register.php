<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/** @var string $heading @var int $minPassword */
?>
<section class="card card--auth">
  <h1 class="card__title"><?= e($heading) ?></h1>
  <p class="muted small"><?= e(__('auth.register_subtitle')) ?></p>

  <form method="post" action="<?= e(url('register')) ?>" class="form">
    <?= csrf_field() ?>

    <div class="form-row">
      <label for="nickname"><?= e(__('auth.nickname')) ?> <span class="req">*</span></label>
      <input type="text" id="nickname" name="nickname" required minlength="2" maxlength="80"
             value="<?= e((string) old('nickname')) ?>">
      <p class="help"><?= e(__('auth.nickname_hint')) ?></p>
      <?php \Athenaeum\Core\View::partial('partials/field_error', ['field' => 'nickname']); ?>
    </div>

    <div class="form-row">
      <label for="email"><?= e(__('auth.email')) ?> <span class="req">*</span></label>
      <input type="email" id="email" name="email" required autocomplete="username"
             value="<?= e((string) old('email')) ?>">
      <?php \Athenaeum\Core\View::partial('partials/field_error', ['field' => 'email']); ?>
    </div>

    <?php if (!empty($verifyEmail)): ?>
      <div class="form-row" data-email-code-form
           data-endpoint="<?= e(url('register.code')) ?>"
           data-csrf="<?= e(csrf_token()) ?>"
           data-locale="<?= e(locale()) ?>">
        <label for="email_code"><?= e(__('auth.email_code')) ?> <span class="req">*</span></label>
        <div class="code-row">
          <input type="text" id="email_code" name="email_code" required inputmode="numeric"
                 autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6"
                 value="<?= e((string) old('email_code')) ?>">
          <button type="button" class="btn btn--ghost" data-send-code><?= e(__('auth.send_code')) ?></button>
        </div>
        <p class="help"><?= e(__('auth.code_hint')) ?></p>
        <p class="small" data-code-status hidden></p>
        <?php \Athenaeum\Core\View::partial('partials/field_error', ['field' => 'email_code']); ?>
      </div>
    <?php else: ?>
      <p class="alert alert--info small"><?= e(__('auth.mail_unavailable')) ?></p>
    <?php endif; ?>

    <div class="form-row">
      <label for="affiliation"><?= e(__('auth.affiliation')) ?> <span class="muted">(<?= e(__('common.optional')) ?>)</span></label>
      <input type="text" id="affiliation" name="affiliation" maxlength="190"
             value="<?= e((string) old('affiliation')) ?>">
    </div>

    <div class="form-row">
      <label for="password"><?= e(__('auth.password')) ?> <span class="req">*</span></label>
      <input type="password" id="password" name="password" required minlength="<?= (int) $minPassword ?>"
             autocomplete="new-password">
      <p class="help"><?= e(__('validation.min', ['field' => __('field.password'), 'min' => (string) $minPassword])) ?></p>
      <?php \Athenaeum\Core\View::partial('partials/field_error', ['field' => 'password']); ?>
    </div>

    <div class="form-row">
      <label for="password_confirmation"><?= e(__('auth.password_confirm')) ?> <span class="req">*</span></label>
      <input type="password" id="password_confirmation" name="password_confirmation" required
             autocomplete="new-password">
    </div>

    <div class="form-row">
      <label class="checkbox">
        <input type="checkbox" name="terms" value="1" required>
        <?php
        // The sentence carries a :policy placeholder that has to become a real
        // link: escape the sentence first, then substitute the link markup.
        ?>
        <?= str_replace(
            ':policy',
            '<a href="' . e(url('page.policy')) . '" target="_blank" rel="noopener">' . e(__('auth.terms_policy')) . '</a>',
            e(__('auth.terms'))
        ) ?>
      </label>
      <?php \Athenaeum\Core\View::partial('partials/field_error', ['field' => 'terms']); ?>
    </div>

    <button class="btn btn--primary btn--block" type="submit"><?= e(__('auth.register_cta')) ?></button>
  </form>

  <p class="card__foot small">
    <?= e(__('auth.have_account')) ?>
    <a href="<?= e(url('login')) ?>"><?= e(__('nav.login')) ?></a>
  </p>
</section>
