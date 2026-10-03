<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * User administration: search, account creation, status.
 *
 * @var array $users
 * @var string $term,$status,$basePath
 * @var array $query
 * @var int $page,$pages,$total
 */
?>
<header class="page-head">
  <h1><?= e(__('admin.users')) ?></h1>
  <p class="muted small"><?= (int) $total ?></p>
</header>

<div class="admin-grid admin-grid--wide">
  <section class="card">
    <h2 class="card__title"><?= e(__('admin.create_user')) ?></h2>
    <form class="form form--tight" method="post" action="<?= e(url('admin.users.create')) ?>">
      <?= csrf_field() ?>
      <div class="form-grid form-grid--2">
        <div class="form-row">
          <label for="email"><?= e(__('admin.user_email')) ?> <span class="req">*</span></label>
          <input type="email" id="email" name="email" required value="<?= e((string) old('email')) ?>">
          <?php \Athenaeum\Core\View::partial('partials/field_error', ['field' => 'email']); ?>
        </div>
        <div class="form-row">
          <label for="nickname"><?= e(__('user.nickname')) ?> <span class="req">*</span></label>
          <input type="text" id="nickname" name="nickname" required maxlength="80" value="<?= e((string) old('nickname')) ?>">
        </div>
        <div class="form-row">
          <label for="password"><?= e(__('auth.password')) ?> <span class="req">*</span></label>
          <input type="text" id="password" name="password" required
                 value="<?= e(\Athenaeum\Core\Str::token(6)) ?>">
          <p class="help"><?= e(__('admin.reset_password')) ?></p>
        </div>
        <div class="form-row">
          <label for="role"><?= e(__('admin.user_role')) ?></label>
          <select id="role" name="role">
            <option value="user">user</option>
            <option value="editor">editor</option>
            <option value="admin">admin</option>
          </select>
        </div>
      </div>
      <button class="btn btn--primary btn--small" type="submit"><?= e(__('admin.create_user')) ?></button>
    </form>
  </section>

  <section class="card">
    <h2 class="card__title"><?= e(__('common.search')) ?></h2>
    <form class="filters filters--inline" method="get" action="<?= e(url('admin.users')) ?>">
      <div class="form-row">
        <label for="q"><?= e(__('common.search')) ?></label>
        <input type="search" id="q" name="q" value="<?= e($term) ?>" placeholder="uid / e-mail / nickname">
      </div>
      <div class="form-row">
        <label for="status"><?= e(__('admin.status_filter')) ?></label>
        <select id="status" name="status">
          <option value=""><?= e(__('common.all')) ?></option>
          <?php foreach (\Athenaeum\Models\User::STATUSES as $option): ?>
            <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn--primary btn--small" type="submit"><?= e(__('common.search')) ?></button>
    </form>
  </section>
</div>

<?php if ($users === []): ?>
  <p class="muted"><?= e(__('admin.no_rows')) ?></p>
<?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th><?= e(__('user.uid')) ?></th>
        <th><?= e(__('user.nickname')) ?></th>
        <th><?= e(__('admin.user_email')) ?></th>
        <th><?= e(__('admin.role')) ?></th>
        <th><?= e(__('admin.status_filter')) ?></th>
        <th><?= e(__('admin.papers')) ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $user): ?>
        <tr>
          <td><code><?= e($user['uid']) ?></code></td>
          <td>
            <?= e((string) $user['nickname']) ?>
            <?php if (!empty($user['affiliation'])): ?>
              <br><span class="muted small"><?= e((string) $user['affiliation']) ?></span>
            <?php endif; ?>
          </td>
          <td class="small"><?= e((string) $user['email']) ?></td>
          <td class="small"><?= e((string) $user['role']) ?></td>
          <td>
            <?php $class = match ($user['status']) { 'active' => 'badge--ok', 'banned' => 'badge--danger', default => 'badge--warn' }; ?>
            <span class="badge <?= e($class) ?>"><?= e((string) $user['status']) ?></span>
          </td>
          <td class="small"><?= (int) $user['paper_count'] ?></td>
          <td class="right nowrap">
            <a class="btn btn--ghost btn--small" href="<?= e(url('admin.user', ['id' => $user['id']])) ?>"><?= e(__('dashboard.manage')) ?></a>
            <a class="btn btn--ghost btn--small" href="<?= e(url('user.show', ['uid' => $user['uid']])) ?>" target="_blank" rel="noopener"><?= e(__('nav.profile')) ?></a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php \Athenaeum\Core\View::partial('partials/pagination', [
      'result'   => ['page' => $page, 'pages' => $pages, 'total' => $total, 'perPage' => 20, 'items' => $users],
      'basePath' => $basePath,
      'query'    => $query,
  ]); ?>
<?php endif; ?>
