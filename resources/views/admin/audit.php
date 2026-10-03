<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Audit log.
 *
 * @var array $result,$actions
 * @var string $action,$basePath
 * @var array $query
 */

use Athenaeum\Models\AuditLog;
?>
<header class="page-head">
  <h1><?= e(__('admin.audit')) ?></h1>
  <p class="muted small"><?= (int) $result['total'] ?></p>
</header>

<form class="filters filters--inline" method="get" action="<?= e(url('admin.audit')) ?>">
  <div class="form-row">
    <label for="action"><?= e(__('admin.filter_action')) ?></label>
    <select id="action" name="action">
      <option value=""><?= e(__('common.all')) ?></option>
      <?php foreach ($actions as $row): ?>
        <option value="<?= e((string) $row['action']) ?>" <?= $action === (string) $row['action'] ? 'selected' : '' ?>>
          <?= e(AuditLog::label((string) $row['action'])) ?> (<?= (int) $row['total'] ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="btn btn--primary btn--small" type="submit"><?= e(__('common.search')) ?></button>
</form>

<?php if ($result['items'] === []): ?>
  <p class="muted"><?= e(__('admin.no_rows')) ?></p>
<?php else: ?>
  <table class="table table--compact">
    <thead>
      <tr>
        <th><?= e(__('admin.audit_when')) ?></th>
        <th><?= e(__('admin.audit_action')) ?></th>
        <th><?= e(__('admin.audit_actor')) ?></th>
        <th><?= e(__('admin.audit_target')) ?></th>
        <th><?= e(__('admin.audit_ip')) ?></th>
        <th>Meta</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($result['items'] as $row): ?>
        <tr>
          <td class="small nowrap"><?= e(format_date((string) $row['created_at'], true)) ?></td>
          <td class="small"><?= e(AuditLog::label((string) $row['action'])) ?><br>
            <span class="muted"><code><?= e((string) $row['action']) ?></code></span></td>
          <td class="small">
            <?php if (!empty($row['actor'])): ?>
              <a href="<?= e(url('admin.user', ['id' => $row['actor']['id']])) ?>"><?= e((string) $row['actor']['nickname']) ?></a>
              <br><code class="muted"><?= e((string) $row['actor_uid']) ?></code>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>
          <td class="small">
            <?= e((string) ($row['target_type'] ?? '')) ?>
            <?php if (!empty($row['target_id'])): ?>
              <code class="muted">#<?= e((string) $row['target_id']) ?></code>
            <?php endif; ?>
          </td>
          <td class="small muted"><?= e((string) ($row['ip'] ?? '')) ?></td>
          <td class="small muted"><?= e(excerpt((string) ($row['meta'] ?? ''), 60)) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php \Athenaeum\Core\View::partial('partials/pagination', [
      'result'   => $result,
      'basePath' => $basePath,
      'query'    => $query,
  ]); ?>
<?php endif; ?>
