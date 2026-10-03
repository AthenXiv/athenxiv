<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * SMTP / notification settings.
 *
 * @var array $status,$values
 * @var string $fromAddress
 * @var bool $openssl,$tlsAvailable
 */
$v = static fn (string $key, mixed $default = '') => $values[$key] ?? $default;
$passwordSet = \Athenaeum\Core\Settings::string('mail.password') !== '';
?>
<header class="page-head">
  <h1><?= e(__('admin.mail')) ?></h1>
  <p class="small">
    <?php if ($status['ok']): ?>
      <span class="badge badge--ok"><?= e(__('mail.status_ok')) ?></span>
      <?php if ($fromAddress !== ''): ?>
        <span class="muted">· <?= e($fromAddress) ?></span>
      <?php endif; ?>
    <?php else: ?>
      <span class="badge badge--warn"><?= e(__('mail.status_problem', ['reason' => $status['reason']])) ?></span>
    <?php endif; ?>
  </p>
</header>

<?php if (!$openssl): ?>
  <div class="alert alert--error"><?= e(__('mail.openssl_missing')) ?></div>
<?php endif; ?>

<div class="admin-grid admin-grid--wide">
  <section class="card">
    <h2 class="card__title"><?= e(__('mail.title')) ?></h2>
    <form class="form" method="post" action="<?= e(url('admin.mail.save')) ?>">
      <?= csrf_field() ?>

      <div class="form-row">
        <label class="checkbox">
          <input type="checkbox" name="mail_enabled" value="1" <?= !empty($v('mail.enabled')) ? 'checked' : '' ?>>
          <?= e(__('mail.enabled')) ?>
        </label>
      </div>

      <div class="form-grid form-grid--2">
        <div class="form-row">
          <label for="mail_transport"><?= e(__('mail.transport')) ?></label>
          <select id="mail_transport" name="mail_transport">
            <option value="smtp" <?= (string) $v('mail.transport') === 'smtp' ? 'selected' : '' ?>><?= e(__('mail.transport_smtp')) ?></option>
            <option value="mail" <?= (string) $v('mail.transport') === 'mail' ? 'selected' : '' ?>><?= e(__('mail.transport_mail')) ?></option>
          </select>
        </div>
        <div class="form-row">
          <label for="mail_encryption"><?= e(__('mail.encryption')) ?></label>
          <select id="mail_encryption" name="mail_encryption">
            <?php foreach (['ssl' => 'mail.encryption_ssl', 'tls' => 'mail.encryption_tls', 'none' => 'mail.encryption_none'] as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= (string) $v('mail.encryption') === $value ? 'selected' : '' ?>>
                <?= e(__($label)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <label for="mail_host"><?= e(__('mail.host')) ?></label>
          <input type="text" id="mail_host" name="mail_host" maxlength="190" value="<?= e((string) $v('mail.host')) ?>">
        </div>
        <div class="form-row">
          <label for="mail_port"><?= e(__('mail.port')) ?></label>
          <input type="number" id="mail_port" name="mail_port" min="1" max="65535" value="<?= (int) $v('mail.port', 465) ?>">
        </div>
        <div class="form-row">
          <label for="mail_username"><?= e(__('mail.username')) ?></label>
          <input type="text" id="mail_username" name="mail_username" maxlength="190" value="<?= e((string) $v('mail.username')) ?>">
        </div>
        <div class="form-row">
          <label for="mail_password"><?= e(__('mail.password')) ?></label>
          <input type="password" id="mail_password" name="mail_password" autocomplete="new-password"
                 placeholder="<?= $passwordSet ? '••••••••' : '' ?>">
          <p class="help"><?= e(__('mail.password_hint')) ?></p>
          <label class="checkbox small">
            <input type="checkbox" name="mail_clear_password" value="1">
            <?= e(__('mail.clear_password')) ?>
          </label>
        </div>
        <div class="form-row">
          <label for="mail_from_address"><?= e(__('mail.from_address')) ?></label>
          <input type="email" id="mail_from_address" name="mail_from_address" maxlength="190"
                 value="<?= e((string) $v('mail.from_address')) ?>">
          <p class="help"><?= e(__('mail.from_address_hint')) ?></p>
        </div>
        <div class="form-row">
          <label for="mail_from_name"><?= e(__('mail.from_name')) ?></label>
          <input type="text" id="mail_from_name" name="mail_from_name" maxlength="120" value="<?= e((string) $v('mail.from_name')) ?>">
        </div>
        <div class="form-row">
          <label for="mail_reply_to"><?= e(__('mail.reply_to')) ?></label>
          <input type="email" id="mail_reply_to" name="mail_reply_to" maxlength="190" value="<?= e((string) $v('mail.reply_to')) ?>">
        </div>
      </div>

      <div class="form-row">
        <label class="checkbox">
          <input type="checkbox" name="mail_notify_admin" value="1" <?= !empty($v('mail.notify_admin')) ? 'checked' : '' ?>>
          <?= e(__('mail.notify_admin')) ?>
        </label>
      </div>
      <div class="form-row">
        <label class="checkbox">
          <input type="checkbox" name="mail_notify_author" value="1" <?= !empty($v('mail.notify_author')) ? 'checked' : '' ?>>
          <?= e(__('mail.notify_author')) ?>
        </label>
      </div>

      <div class="form-actions">
        <button class="btn btn--primary" type="submit"><?= e(__('common.save_changes')) ?></button>
      </div>
    </form>
  </section>

  <section class="card">
    <h2 class="card__title"><?= e(__('mail.test')) ?></h2>
    <form class="form form--tight" method="post" action="<?= e(url('admin.mail.test')) ?>">
      <?= csrf_field() ?>
      <div class="form-row">
        <label for="to"><?= e(__('mail.test_to')) ?></label>
        <input type="email" id="to" name="to" required
               value="<?= e((string) (\Athenaeum\Core\Auth::user()['email'] ?? '')) ?>">
      </div>
      <button class="btn btn--primary btn--small" type="submit"><?= e(__('mail.send_test')) ?></button>
    </form>

    <h3><?= e(__('mail.qq_hint')) ?></h3>
    <ul class="plain-list small muted">
      <li>QQ Mail — smtp.qq.com : 465 (SSL) · 587 (STARTTLS)</li>
      <li>163 — smtp.163.com : 465 (SSL)</li>
      <li>Gmail — smtp.gmail.com : 465 (SSL) · 587 (STARTTLS)</li>
      <li>Outlook — smtp-mail.outlook.com : 587 (STARTTLS)</li>
    </ul>
    <p class="small muted">
      <?= e(__('mail.password_hint')) ?>
    </p>
  </section>
</div>
