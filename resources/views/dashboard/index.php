<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Author dashboard.
 *
 * @var array $user,$papers,$summary,$timestamps
 * @var int $total
 * @var string|null $avatar,$profileUrl
 */

use Athenaeum\Models\Paper;
use Athenaeum\Models\Timestamp;

$statusCards = [
    Paper::STATUS_PENDING   => 'badge--warn',
    Paper::STATUS_APPROVED  => 'badge--ok',
    Paper::STATUS_REJECTED  => 'badge--danger',
    Paper::STATUS_WITHDRAWN => 'badge--muted',
];
?>
<div class="container">
  <header class="page-head">
    <h1><?= e(__('dashboard.title')) ?></h1>
    <p class="muted"><?= e(__('dashboard.welcome', ['name' => (string) $user['nickname']])) ?>
      · <code><?= e($user['uid']) ?></code></p>
    <p>
      <a class="btn btn--primary" href="<?= e(url('paper.create')) ?>">+ <?= e(__('dashboard.new_submission')) ?></a>
      <a class="btn btn--ghost" href="<?= e((string) $profileUrl) ?>"><?= e(__('dashboard.public_page')) ?></a>
      <a class="btn btn--ghost" href="<?= e(url('settings')) ?>"><?= e(__('dashboard.edit_profile')) ?></a>
    </p>
  </header>

  <section class="section-block">
    <h2><?= e(__('dashboard.summary')) ?></h2>
    <ul class="tile-list tile-list--stats">
      <?php foreach ($statusCards as $status => $class): ?>
        <li class="tile tile--stat">
          <a href="<?= e(url('dashboard.papers') . '?status=' . $status) ?>">
            <span class="tile__count"><?= (int) ($summary[$status] ?? 0) ?></span>
            <span class="tile__name"><?= e(Paper::statusLabel($status)) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
      <li class="tile tile--stat">
        <a href="<?= e(url('dashboard.papers')) ?>">
          <span class="tile__count"><?= (int) $total ?></span>
          <span class="tile__name"><?= e(__('dashboard.papers_total')) ?></span>
        </a>
      </li>
    </ul>
  </section>

  <section class="section-block">
    <div class="section-block__head">
      <h2><?= e(__('dashboard.my_papers')) ?></h2>
      <a class="small" href="<?= e(url('dashboard.papers')) ?>"><?= e(__('home.view_all')) ?> →</a>
    </div>

    <?php if ($papers === []): ?>
      <p class="empty"><?= e(__('dashboard.no_papers_yet')) ?></p>
      <p><a class="btn btn--primary" href="<?= e(url('paper.create')) ?>"><?= e(__('dashboard.start_submitting')) ?></a></p>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th><?= e(__('dashboard.table_title')) ?></th>
            <th><?= e(__('dashboard.table_status')) ?></th>
            <th><?= e(__('ots.heading')) ?></th>
            <th><?= e(__('dashboard.table_updated')) ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($papers as $paper): ?>
            <tr>
              <td>
                <a href="<?= e(Paper::publicUrl($paper)) ?>"><?= e($paper['title']) ?></a>
                <br><span class="muted small"><code><?= e($paper['uid']) ?></code></span>
              </td>
              <td><span class="<?= e(Paper::statusBadgeClass((string) $paper['status'])) ?>"><?= e(Paper::statusLabel((string) $paper['status'])) ?></span></td>
              <td>
                <?php $ts = Paper::timestamp((int) $paper['id'], 'pdf'); ?>
                <?php if ($ts !== null): ?>
                  <span class="ots-chip ots-chip--<?= $ts['status'] === 'confirmed' ? 'ok' : ($ts['status'] === 'failed' ? 'bad' : 'warn') ?>">
                    <?= e(Timestamp::statusLabel((string) $ts['status'])) ?>
                  </span>
                <?php else: ?>
                  <span class="muted small">—</span>
                <?php endif; ?>
              </td>
              <td class="small"><?= e(time_ago((string) ($paper['updated_at'] ?? $paper['created_at']))) ?></td>
              <td class="right">
                <a class="btn btn--ghost btn--small" href="<?= e(url('paper.edit', ['uid' => $paper['uid']])) ?>"><?= e(__('common.edit')) ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section class="section-block">
    <h2><?= e(__('dashboard.recent_timestamps')) ?></h2>
    <?php if ($timestamps === []): ?>
      <p class="muted small"><?= e(__('dashboard.no_timestamps')) ?></p>
    <?php else: ?>
      <table class="table table--compact">
        <thead>
          <tr>
            <th><?= e(__('ots.file')) ?></th>
            <th><?= e(__('common.status')) ?></th>
            <th>SHA-256</th>
            <th><?= e(__('dashboard.table_updated')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($timestamps as $timestamp): ?>
            <tr>
              <td>
                <a href="<?= e(url('paper.show', ['uid' => $timestamp['paper_uid']])) ?>">
                  <?= e(excerpt((string) $timestamp['paper_title'], 60)) ?>
                </a>
                <br><span class="muted small"><?= e((string) ($timestamp['file_name'] ?? '')) ?></span>
              </td>
              <td>
                <span class="ots-chip ots-chip--<?= $timestamp['status'] === 'confirmed' ? 'ok' : ($timestamp['status'] === 'failed' ? 'bad' : 'warn') ?>">
                  <?= e(Timestamp::statusLabel((string) $timestamp['status'])) ?>
                </span>
              </td>
              <td><code class="hash small"><?= e(substr((string) $timestamp['file_sha256'], 0, 24)) ?>…</code></td>
              <td class="small"><?= e(time_ago((string) ($timestamp['upgraded_at'] ?? $timestamp['submitted_at']))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>
