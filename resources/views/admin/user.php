<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Single user administration.
 *
 * @var array $user,$papers,$links,$statuses,$roles,$summary,$audit
 */

use Athenaeum\Models\Paper;
?>
<header class="page-head">
  <h1><?= e(__('admin.user_detail')) ?> <code><?= e($user['uid']) ?></code></h1>
  <p class="small">
    <?= e((string) $user['nickname']) ?> · <?= e((string) $user['email']) ?> ·
    <a href="<?= e(url('user.show', ['uid' => $user['uid']])) ?>" target="_blank" rel="noopener"><?= e(__('user.public_profile')) ?> ↗</a> ·
    <a href="<?= e(url('admin.users')) ?>">← <?= e(__('admin.users')) ?></a>
  </p>
</header>

<div class="admin-grid admin-grid--wide">
  <section class="card">
    <h2 class="card__title"><?= e(__('settings.profile')) ?></h2>
    <form class="form form--tight" method="post" action="<?= e(url('admin.user.profile', ['id' => $user['id']])) ?>">
      <?= csrf_field() ?>
      <div class="form-grid form-grid--2">
        <div class="form-row">
          <label for="email"><?= e(__('admin.user_email')) ?></label>
          <input type="email" id="email" name="email" required value="<?= e((string) $user['email']) ?>">
        </div>
        <div class="form-row">
          <label for="nickname"><?= e(__('user.nickname')) ?></label>
          <input type="text" id="nickname" name="nickname" required maxlength="80" value="<?= e((string) $user['nickname']) ?>">
        </div>
        <div class="form-row">
          <label for="display_name"><?= e(__('user.display_name')) ?></label>
          <input type="text" id="display_name" name="display_name" maxlength="120" value="<?= e((string) $user['display_name']) ?>">
        </div>
        <div class="form-row">
          <label for="affiliation"><?= e(__('user.affiliation')) ?></label>
          <input type="text" id="affiliation" name="affiliation" maxlength="190" value="<?= e((string) $user['affiliation']) ?>">
        </div>
      </div>
      <div class="form-row">
        <label for="admin_note"><?= e(__('admin.note')) ?></label>
        <input type="text" id="admin_note" name="admin_note" maxlength="500" value="<?= e((string) $user['admin_note']) ?>">
      </div>
      <button class="btn btn--primary btn--small" type="submit"><?= e(__('common.save_changes')) ?></button>
    </form>
  </section>

  <section class="card">
    <h2 class="card__title"><?= e(__('admin.status_filter')) ?> / <?= e(__('admin.role')) ?></h2>

    <form class="form form--tight" method="post" action="<?= e(url('admin.user.status', ['id' => $user['id']])) ?>">
      <?= csrf_field() ?>
      <div class="form-row">
        <label for="status"><?= e(__('admin.status_filter')) ?></label>
        <select id="status" name="status">
          <?php foreach ($statuses as $status): ?>
            <option value="<?= e($status) ?>" <?= $user['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label for="note"><?= e(__('admin.note')) ?></label>
        <input type="text" id="note" name="note" maxlength="500">
      </div>
      <button class="btn btn--small" type="submit"><?= e(__('common.save')) ?></button>
    </form>

    <hr>

    <form class="form form--tight" method="post" action="<?= e(url('admin.user.role', ['id' => $user['id']])) ?>">
      <?= csrf_field() ?>
      <div class="form-row">
        <label for="role"><?= e(__('admin.role')) ?></label>
        <select id="role" name="role">
          <?php foreach ($roles as $role): ?>
            <option value="<?= e($role) ?>" <?= $user['role'] === $role ? 'selected' : '' ?>><?= e($role) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn--small" type="submit"><?= e(__('common.save')) ?></button>
    </form>

    <hr>

    <form class="form form--tight" method="post" action="<?= e(url('admin.user.password', ['id' => $user['id']])) ?>">
      <?= csrf_field() ?>
      <div class="form-row">
        <label for="password"><?= e(__('admin.reset_password')) ?></label>
        <input type="text" id="password" name="password" required value="<?= e(\Athenaeum\Core\Str::token(6)) ?>">
      </div>
      <button class="btn btn--danger btn--small" type="submit"><?= e(__('admin.reset_password')) ?></button>
    </form>

    <hr>

    <details class="danger-zone">
      <summary><?= e(__('admin.delete_user')) ?></summary>
      <p class="small muted"><?= e(__('admin.confirm_delete_hint')) ?></p>
      <form class="form form--tight" method="post" action="<?= e(url('admin.user.purge', ['id' => $user['id']])) ?>">
        <?= csrf_field() ?>
        <div class="form-row">
          <label for="confirm"><?= e(__('admin.confirm_uid', ['uid' => (string) $user['uid']])) ?></label>
          <input type="text" id="confirm" name="confirm" required placeholder="<?= e((string) $user['uid']) ?>">
        </div>
        <button class="btn btn--danger btn--small" type="submit"><?= e(__('admin.delete_user')) ?></button>
      </form>
    </details>
  </section>

  <section class="card">
    <h2 class="card__title"><?= e(__('dashboard.summary')) ?></h2>
    <ul class="plain-list">
      <?php foreach ($summary as $status => $count): ?>
        <li><?= e(Paper::statusLabel((string) $status)) ?>: <strong><?= (int) $count ?></strong></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($links !== []): ?>
      <h3><?= e(__('user.links')) ?></h3>
      <ul class="plain-list">
        <?php foreach ($links as $link): ?>
          <li><a href="<?= e($link['url']) ?>" target="_blank" rel="noopener nofollow"><?= e((string) $link['platform']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2 class="card__title"><?= e(__('admin.papers')) ?></h2>
    <?php if ($papers === []): ?>
      <p class="muted small"><?= e(__('admin.no_rows')) ?></p>
    <?php else: ?>
      <table class="table table--compact">
        <tbody>
          <?php foreach ($papers as $paper): ?>
            <tr>
              <td class="small">
                <a href="<?= e(url('admin.paper', ['id' => $paper['id']])) ?>"><?= e(excerpt((string) $paper['title'], 60)) ?></a>
                <br><code class="muted"><?= e($paper['uid']) ?></code>
              </td>
              <td>
                <span class="<?= e(Paper::statusBadgeClass((string) $paper['status'])) ?>">
                  <?= e(Paper::statusLabel((string) $paper['status'])) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2 class="card__title"><?= e(__('admin.audit')) ?></h2>
    <table class="table table--compact">
      <tbody>
        <?php foreach ($audit as $row): ?>
          <tr>
            <td class="small"><?= e(\Athenaeum\Models\AuditLog::label((string) $row['action'])) ?></td>
            <td class="small muted right"><?= e(time_ago((string) $row['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</div>
