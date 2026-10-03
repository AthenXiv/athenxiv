<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * OpenTimestamps operations: every proof, its state and the upgrade trigger.
 *
 * @var array $rows,$counts,$calendars
 * @var string $status,$basePath,$verifyUrl
 * @var array $query
 * @var int $page,$pages,$total
 */

use Athenaeum\Models\Timestamp;

$stateClass = static fn (string $state): string => match ($state) {
    'confirmed' => 'ok',
    'failed'    => 'bad',
    default     => 'warn',
};
?>
<header class="page-head">
  <h1><?= e(__('admin.timestamps')) ?></h1>
  <p class="small">
    <a href="<?= e(url('admin.timestamps')) ?>"><?= e(__('common.all')) ?> (<?= (int) $total ?>)</a> ·
    <a href="<?= e(url('admin.timestamps') . '?status=pending') ?>"><?= e(__('admin.timestamps_pending')) ?> (<?= (int) $counts['pending'] ?>)</a> ·
    <a href="<?= e(url('admin.timestamps') . '?status=confirmed') ?>"><?= e(__('admin.timestamps_confirmed')) ?> (<?= (int) $counts['confirmed'] ?>)</a> ·
    <a href="<?= e(url('admin.timestamps') . '?status=failed') ?>"><?= e(__('admin.timestamps_failed')) ?> (<?= (int) $counts['failed'] ?>)</a>
  </p>
</header>

<div class="admin-grid admin-grid--wide">
  <section class="card">
    <h2 class="card__title"><?= e(__('admin.ots_upgrade')) ?></h2>
    <form class="form form--tight" method="post" action="<?= e(url('admin.timestamps.upgrade')) ?>">
      <?= csrf_field() ?>
      <div class="form-row">
        <label for="limit">Batch size</label>
        <input type="number" id="limit" name="limit" min="1" max="100" value="25">
      </div>
      <button class="btn btn--primary btn--small" type="submit"><?= e(__('admin.ots_upgrade')) ?></button>
    </form>
    <p class="small muted">
      <?= e(__('ots.pending_no_date')) ?>
    </p>
    <p class="small muted">
      Cron: <code>php bin/ots-upgrade.php --limit=50</code>
    </p>
  </section>

  <section class="card">
    <h2 class="card__title"><?= e(__('ots.calendars')) ?></h2>
    <ul class="plain-list small">
      <?php foreach ($calendars as $calendar): ?>
        <li><a href="<?= e($calendar) ?>" target="_blank" rel="noopener nofollow"><?= e($calendar) ?></a></li>
      <?php endforeach; ?>
    </ul>
    <p class="small">
      <a class="btn btn--ghost btn--tiny" href="<?= e($verifyUrl) ?>" target="_blank" rel="noopener noreferrer"
         title="<?= e(__('ots.verify_tooltip')) ?>"><?= e(__('ots.verify_at')) ?></a>
    </p>
  </section>
</div>

<?php if ($rows === []): ?>
  <p class="muted"><?= e(__('admin.no_rows')) ?></p>
<?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th><?= e(__('admin.papers')) ?></th>
        <th><?= e(__('ots.file')) ?></th>
        <th>SHA-256</th>
        <th><?= e(__('admin.status_filter')) ?></th>
        <th><?= e(__('ots.submitted_at')) ?></th>
        <th><?= e(__('ots.confirmed_at')) ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td class="small">
            <?php if (!empty($row['paper_uid'])): ?>
              <a href="<?= e(url('admin.paper', ['id' => $row['paper_id']])) ?>"><?= e(excerpt((string) $row['paper_title'], 50)) ?></a>
              <br><code class="muted"><?= e((string) $row['paper_uid']) ?></code>
            <?php else: ?>
              <span class="muted">#<?= (int) $row['paper_id'] ?></span>
            <?php endif; ?>
          </td>
          <td class="small">
            <?= e((string) ($row['file_name'] ?? '')) ?>
            <br><span class="muted"><?= e((string) $row['target_type']) ?></span>
          </td>
          <td><code class="hash small"><?= e(substr((string) $row['file_sha256'], 0, 20)) ?>…</code></td>
          <td>
            <span class="ots-chip ots-chip--<?= e($stateClass((string) $row['status'])) ?>">
              <?= e(Timestamp::statusLabel((string) $row['status'])) ?>
            </span>
            <br><span class="muted small"><?= (int) $row['attempts'] ?> attempts</span>
            <?php if (!empty($row['last_error'])): ?>
              <br><span class="small danger" title="<?= e((string) $row['last_error']) ?>"><?= e(excerpt((string) $row['last_error'], 40)) ?></span>
            <?php endif; ?>
          </td>
          <td class="small"><?= e(format_date((string) $row['submitted_at'], true)) ?></td>
          <td class="small">
            <?php if (!empty($row['bitcoin_time'])): ?>
              <?= e(format_date((string) $row['bitcoin_time'], true)) ?>
              <br><span class="muted">#<?= (int) $row['bitcoin_height'] ?></span>
            <?php elseif (!empty($row['upgraded_at'])): ?>
              <?= e(format_date((string) $row['upgraded_at'], true)) ?>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>
          <td class="right nowrap">
            <?php if (!empty($row['ots_path'])): ?>
              <a class="btn btn--ghost btn--tiny" href="<?= e(url('admin.timestamps.proof', ['id' => $row['id']])) ?>" download>.ots</a>
            <?php endif; ?>
            <?php if (!empty($row['paper_uid'])): ?>
              <a class="btn btn--ghost btn--tiny" href="<?= e(url('paper.show', ['uid' => $row['paper_uid']])) ?>" target="_blank" rel="noopener"><?= e(__('common.view')) ?></a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php \Athenaeum\Core\View::partial('partials/pagination', [
      'result'   => ['page' => $page, 'pages' => $pages, 'total' => $total, 'perPage' => 25, 'items' => $rows],
      'basePath' => $basePath,
      'query'    => $query,
  ]); ?>
<?php endif; ?>
