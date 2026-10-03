<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * "My papers" management list.
 *
 * @var array $result,$summary
 * @var string $status
 */

use Athenaeum\Models\Paper;
use Athenaeum\Models\Timestamp;
use Athenaeum\Services\PaperService;

$query = $status !== '' ? ['status' => $status] : [];
?>
<div class="container">
  <header class="page-head">
    <h1><?= e(__('dashboard.my_papers')) ?></h1>
    <p><a class="btn btn--primary btn--small" href="<?= e(url('paper.create')) ?>">+ <?= e(__('dashboard.new_submission')) ?></a></p>
    <nav class="tab-nav">
      <a href="<?= e(url('dashboard.papers')) ?>" class="<?= $status === '' ? 'is-active' : '' ?>">
        <?= e(__('common.all')) ?> (<?= array_sum($summary) ?>)
      </a>
      <?php foreach (Paper::STATUSES as $state): ?>
        <a href="<?= e(url('dashboard.papers') . '?status=' . $state) ?>" class="<?= $status === $state ? 'is-active' : '' ?>">
          <?= e(Paper::statusLabel($state)) ?> (<?= (int) ($summary[$state] ?? 0) ?>)
        </a>
      <?php endforeach; ?>
    </nav>
  </header>

  <?php if ($result['items'] === []): ?>
    <p class="empty"><?= e(__('dashboard.no_papers_yet')) ?></p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th><?= e(__('dashboard.table_title')) ?></th>
          <th><?= e(__('dashboard.table_status')) ?></th>
          <th><?= e(__('ots.heading')) ?></th>
          <th><?= e(__('paper.views')) ?> / <?= e(__('paper.downloads')) ?></th>
          <th><?= e(__('dashboard.table_actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($result['items'] as $paper): ?>
          <?php $timestamp = $paper['timestamp'] ?? null; ?>
          <tr>
            <td>
              <a href="<?= e(Paper::publicUrl($paper)) ?>"><?= e($paper['title']) ?></a>
              <br><span class="muted small"><code><?= e($paper['uid']) ?></code> · <?= e((string) $paper['author_line']) ?></span>
              <?php if (!empty($paper['reject_reason'])): ?>
                <br><span class="small danger"><?= e(__('paper.reject_reason')) ?>: <?= e((string) $paper['reject_reason']) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <span class="<?= e(Paper::statusBadgeClass((string) $paper['status'])) ?>">
                <?= e(Paper::statusLabel((string) $paper['status'])) ?>
              </span>
            </td>
            <td>
              <?php if ($timestamp !== null): ?>
                <span class="ots-chip ots-chip--<?= $timestamp['status'] === 'confirmed' ? 'ok' : ($timestamp['status'] === 'failed' ? 'bad' : 'warn') ?>"
                      title="<?= e(Timestamp::statusLabel((string) $timestamp['status'])) ?> · <?= e((string) $timestamp['file_sha256']) ?>">
                  <?= e(Timestamp::shortStatusLabel((string) $timestamp['status'])) ?>
                </span>
              <?php else: ?>
                <span class="muted small">—</span>
              <?php endif; ?>
            </td>
            <td class="small"><?= (int) $paper['views'] ?> / <?= (int) $paper['downloads'] ?>
              <?php if ((int) ($paper['version_no'] ?? 1) > 1): ?>
                <br><span class="badge badge--muted">v<?= (int) $paper['version_no'] ?></span>
              <?php endif; ?>
            </td>
            <td class="right nowrap">
              <?php if (in_array($paper['status'], Paper::EDITABLE_BY_OWNER, true)): ?>
                <a class="btn btn--ghost btn--small" href="<?= e(url('paper.edit', ['uid' => $paper['uid']])) ?>"><?= e(__('common.edit')) ?></a>
              <?php else: ?>
                <?php // Taken down by a moderator: only an administrator may edit it. ?>
                <span class="muted small" title="<?= e(__('paper.not_editable')) ?>"><?= e(__('paper.not_editable')) ?></span>
              <?php endif; ?>
              <a class="btn btn--ghost btn--small" href="<?= e(url('paper.show', ['uid' => $paper['uid']])) ?>"><?= e(__('common.view')) ?></a>

              <?php if (in_array($paper['status'], [Paper::STATUS_DRAFT, Paper::STATUS_REJECTED, Paper::STATUS_WITHDRAWN], true)): ?>
                <form class="inline" method="post" action="<?= e(url('paper.submit', ['uid' => $paper['uid']])) ?>">
                  <?= csrf_field() ?>
                  <button class="btn btn--primary btn--small" type="submit"><?= e(__('paper.submit_cta')) ?></button>
                </form>
              <?php endif; ?>

              <?php if ($paper['status'] === Paper::STATUS_APPROVED): ?>
                <form class="inline" method="post" action="<?= e(url('paper.withdraw', ['uid' => $paper['uid']])) ?>"
                      data-confirm="<?= e(__('paper.withdraw_confirm')) ?>">
                  <?= csrf_field() ?>
                  <button class="btn btn--ghost btn--small" type="submit"><?= e(__('paper.withdraw')) ?></button>
                </form>
              <?php endif; ?>

              <?php if (in_array($paper['status'], [Paper::STATUS_DRAFT, Paper::STATUS_REJECTED, Paper::STATUS_WITHDRAWN], true)): ?>
                <form class="inline" method="post" action="<?= e(url('paper.destroy', ['uid' => $paper['uid']])) ?>"
                      data-confirm="<?= e(__('paper.delete_confirm')) ?>">
                  <?= csrf_field() ?>
                  <button class="btn btn--danger btn--small" type="submit"><?= e(__('common.delete')) ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php \Athenaeum\Core\View::partial('partials/pagination', [
        'result'   => $result,
        'basePath' => '/dashboard/papers',
        'query'    => $query,
    ]); ?>
  <?php endif; ?>
</div>
