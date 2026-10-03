<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Administration overview: counters, review queue, calendar health, activity.
 *
 * @var array $stats,$queue,$recent,$calendarHealth
 * @var int $queueTotal
 */

use Athenaeum\Models\AuditLog;
use Athenaeum\Models\Paper;
?>
<header class="page-head">
  <h1><?= e(__('admin.dashboard')) ?></h1>
  <p class="muted"><?= e(\Athenaeum\Core\Settings::siteName()) ?> ·
    <?= e(format_date(gmdate('Y-m-d H:i:s'), true)) ?></p>
</header>

<ul class="tile-list tile-list--stats">
  <li class="tile tile--stat"><a href="<?= e(url('admin.papers') . '?status=pending') ?>">
    <span class="tile__count"><?= (int) $stats['pending'] ?></span>
    <span class="tile__name"><?= e(Paper::statusLabel('pending')) ?></span></a></li>
  <li class="tile tile--stat"><a href="<?= e(url('admin.papers') . '?status=approved') ?>">
    <span class="tile__count"><?= (int) $stats['published'] ?></span>
    <span class="tile__name"><?= e(Paper::statusLabel('approved')) ?></span></a></li>
  <li class="tile tile--stat"><a href="<?= e(url('admin.papers')) ?>">
    <span class="tile__count"><?= (int) $stats['papers'] ?></span>
    <span class="tile__name"><?= e(__('admin.papers')) ?></span></a></li>
  <li class="tile tile--stat"><a href="<?= e(url('admin.users')) ?>">
    <span class="tile__count"><?= (int) $stats['users'] ?></span>
    <span class="tile__name"><?= e(__('admin.users')) ?></span></a></li>
  <li class="tile tile--stat"><a href="<?= e(url('admin.timestamps')) ?>">
    <span class="tile__count"><?= (int) $stats['timestamps'] ?></span>
    <span class="tile__name"><?= e(__('admin.timestamps')) ?></span></a></li>
  <li class="tile tile--stat"><a href="<?= e(url('admin.timestamps') . '?status=confirmed') ?>">
    <span class="tile__count"><?= (int) $stats['confirmed'] ?></span>
    <span class="tile__name"><?= e(__('admin.timestamps_confirmed')) ?></span></a></li>
</ul>

<div class="admin-grid">
  <section class="card">
    <h2 class="card__title"><?= e(__('admin.queue')) ?>
      <?php if ($queueTotal > 0): ?><span class="badge badge--warn"><?= (int) $queueTotal ?></span><?php endif; ?>
    </h2>
    <?php if ($queue === []): ?>
      <p class="muted small"><?= e(__('admin.no_rows')) ?></p>
    <?php else: ?>
      <table class="table table--compact">
        <tbody>
          <?php foreach ($queue as $paper): ?>
            <tr>
              <td>
                <a href="<?= e(url('admin.paper', ['id' => $paper['id']])) ?>"><?= e(excerpt((string) $paper['title'], 70)) ?></a>
                <br><span class="muted small"><code><?= e($paper['uid']) ?></code> ·
                  <?= e(time_ago((string) $paper['submitted_at'])) ?></span>
              </td>
              <td class="right">
                <a class="btn btn--primary btn--small" href="<?= e(url('admin.paper', ['id' => $paper['id']])) ?>">
                  <?= e(__('admin.review')) ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p><a class="small" href="<?= e(url('admin.papers') . '?status=pending') ?>"><?= e(__('home.view_all')) ?> →</a></p>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2 class="card__title"><?= e(__('admin.calendar_health')) ?></h2>
    <table class="table table--compact">
      <tbody>
        <?php foreach ($calendarHealth as $calendar): ?>
          <tr>
            <td class="small"><?= e($calendar['url']) ?></td>
            <td class="right">
              <?php if ($calendar['online']): ?>
                <span class="badge badge--ok"><?= e(__('admin.online')) ?></span>
              <?php else: ?>
                <span class="badge badge--danger" title="<?= e((string) $calendar['error']) ?>"><?= e(__('admin.offline')) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="small">
      <a href="<?= e(url('admin.timestamps')) ?>"><?= e(__('admin.timestamps')) ?> →</a>
    </p>
  </section>

  <section class="card">
    <h2 class="card__title"><?= e(__('admin.storage_usage')) ?></h2>
    <table class="table table--compact">
      <tbody>
        <tr><td><?= e(__('admin.papers')) ?></td><td class="right"><?= e(human_size((int) $stats['storage']['papers'])) ?></td></tr>
        <tr><td><?= e(__('paper.attachments')) ?></td><td class="right"><?= e(human_size((int) $stats['storage']['attachments'])) ?></td></tr>
        <tr><td>OpenTimestamps (.ots)</td><td class="right"><?= e(human_size((int) $stats['storage']['ots'])) ?></td></tr>
      </tbody>
    </table>
    <p class="small muted"><?= e(__('admin.limits_hint', [
        'pdf'     => human_size(\Athenaeum\Services\PaperService::limits()['pdf']),
        'archive' => human_size(\Athenaeum\Services\PaperService::limits()['attachment']),
        'count'   => \Athenaeum\Core\Settings::int('upload.max_attachments', 5),
        'php'     => human_size(\Athenaeum\Services\Uploader::phpUploadLimit()),
    ])) ?></p>
  </section>

  <section class="card">
    <h2 class="card__title"><?= e(__('admin.recent_activity')) ?></h2>
    <?php if ($recent === []): ?>
      <p class="muted small"><?= e(__('admin.no_rows')) ?></p>
    <?php else: ?>
      <table class="table table--compact">
        <tbody>
          <?php foreach ($recent as $row): ?>
            <tr>
              <td class="small"><?= e(AuditLog::label((string) $row['action'])) ?></td>
              <td class="small muted"><?= e((string) ($row['actor_uid'] ?? '—')) ?></td>
              <td class="small muted right"><?= e(time_ago((string) $row['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p><a class="small" href="<?= e(url('admin.audit')) ?>"><?= e(__('admin.audit')) ?> →</a></p>
    <?php endif; ?>
  </section>
</div>
