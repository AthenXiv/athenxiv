<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Password recovery.
 *
 * One page, three steps: type the account address, ask for a six-digit code
 * (sent by the same e-mail machinery as registration), then type that code with
 * the new password. The "send" button lives in its own form and is submitted
 * through the `form` attribute, so the page never nests two <form> elements.
 *
 * @var string $heading
 * @var bool   $available
 * @var int    $minPassword
 * @var int    $expiresMinutes
 */
?>
<section class="card card--auth">
  <h1 class="card__title"><?= e($heading) ?></h1>
  <p class="muted small"><?= e(__('auth.reset_subtitle')) ?></p>

  <?php if (!$available): ?>
    <p class="alert alert--info small"><?= e(__('auth.reset_unavailable')) ?></p>
  <?php else: ?>
    <?php
    // One form, one POST target: the codes are requested over AJAX from the
    // block below (its data-endpoint), while the form itself carries the code
    // and the new password to /password/reset. The block marker has to sit on
    // the element that *contains* the button, the status line and the address,
    // because app.js scopes its lookups to it.
    ?>
    <form method="post" action="<?= e(url('password.update')) ?>" class="form">
      <?= csrf_field() ?>

      <div class="form-row" data-email-code-block
           data-endpoint="<?= e(url('password.email')) ?>"
           data-csrf="<?= e(csrf_token()) ?>">
        <label for="email"><?= e(__('auth.reset_email')) ?> <span class="req">*</span></label>
        <div class="code-row">
          <input type="email" id="email" name="email" required autocomplete="username"
                 value="<?= e((string) old('email')) ?>">
          <button type="button" class="btn btn--ghost" data-send-code>
            <?= e(__('auth.reset_send_cta')) ?>
          </button>
        </div>
        <p class="help"><?= e(__('auth.reset_hint', ['minutes' => (string) $expiresMinutes])) ?></p>
        <p class="small" data-code-status hidden></p>
        <?php \Athenaeum\Core\View::partial('partials/field_error', ['field' => 'email']); ?>
      </div>

      <div class="form-row">
        <label for="code"><?= e(__('auth.reset_code_label')) ?> <span class="req">*</span></label>
        <input type="text" id="code" name="code" required inputmode="numeric"
               autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6"
               value="<?= e((string) old('code')) ?>">
        <?php \Athenaeum\Core\View::partial('partials/field_error', ['field' => 'code']); ?>
      </div>

      <div class="form-row">
        <label for="password"><?= e(__('auth.reset_new_password')) ?> <span class="req">*</span></label>
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

      <button class="btn btn--primary btn--block" type="submit"><?= e(__('auth.reset_submit')) ?></button>
    </form>
  <?php endif; ?>

  <p class="card__foot small">
    <a href="<?= e(url('login')) ?>"><?= e(__('nav.login')) ?></a>
    <?php if (\Athenaeum\Core\Settings::bool('registration.open')): ?>
      · <?= e(__('auth.no_account')) ?>
      <a href="<?= e(url('register')) ?>"><?= e(__('nav.register')) ?></a>
    <?php endif; ?>
  </p>
</section>
