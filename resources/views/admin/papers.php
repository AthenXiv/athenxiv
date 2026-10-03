<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Admin paper list with moderation filters.
 *
 * @var array $result,$filters,$sections,$categories,$counts
 */

use Athenaeum\Models\Paper;
use Athenaeum\Models\Section;
use Athenaeum\Models\Timestamp;

$query = array_filter($filters, static fn ($value): bool => $value !== '' && $value !== 0);
?>
<header class="page-head">
  <h1><?= e(__('admin.papers')) ?></h1>
  <p class="small">
    <a href="<?= e(url('admin.papers')) ?>" class="<?= !isset($filters['status']) ? 'is-active' : '' ?>">
      <?= e(__('common.all')) ?> (<?= (int) $counts['all'] ?>)
    </a> ·
    <a href="<?= e(url('admin.papers') . '?status=pending') ?>">
      <?= e(__('status.pending')) ?> (<?= (int) $counts['pending'] ?>)
    </a> ·
    <a href="<?= e(url('admin.papers') . '?status=approved') ?>">
      <?= e(__('status.approved')) ?> (<?= (int) $counts['approved'] ?>)
    </a>
  </p>
</header>

<form class="filters filters--inline" method="get" action="<?= e(url('admin.papers')) ?>">
  <div class="form-row">
    <label for="q"><?= e(__('common.search')) ?></label>
    <input type="search" id="q" name="q" value="<?= e($filters['q'] ?? '') ?>">
  </div>
  <div class="form-row">
    <label for="status"><?= e(__('admin.status_filter')) ?></label>
    <select id="status" name="status">
      <option value=""><?= e(__('common.all')) ?></option>
      <?php foreach (Paper::STATUSES as $status): ?>
        <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>>
          <?= e(Paper::statusLabel($status)) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-row">
    <label for="section_id"><?= e(__('paper.section')) ?></label>
    <select id="section_id" name="section_id">
      <option value=""><?= e(__('common.all')) ?></option>
      <?php foreach ($sections as $section): ?>
        <option value="<?= (int) $section['id'] ?>" <?= (string) ($filters['section_id'] ?? '') === (string) $section['id'] ? 'selected' : '' ?>>
          <?= e(Section::name($section)) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-row">
    <label for="category_id"><?= e(__('paper.category')) ?></label>
    <select id="category_id" name="category_id">
      <option value=""><?= e(__('common.all')) ?></option>
      <?php foreach ($categories as $category): ?>
        <option value="<?= (int) $category['id'] ?>" <?= (string) ($filters['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>>
          <?= e(\Athenaeum\Models\Category::name($category)) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="btn btn--primary btn--small" type="submit"><?= e(__('common.search')) ?></button>
  <?php if ($query !== []): ?>
    <a class="btn btn--ghost btn--small" href="<?= e(url('admin.papers')) ?>"><?= e(__('common.all')) ?></a>
  <?php endif; ?>
</form>

<?php if ($result['items'] === []): ?>
  <p class="muted"><?= e(__('admin.no_rows')) ?></p>
<?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th><?= e(__('dashboard.table_title')) ?></th>
        <th><?= e(__('admin.paper_owner')) ?></th>
        <th><?= e(__('admin.status_filter')) ?></th>
        <th><?= e(__('paper.section')) ?></th>
        <th><?= e(__('ots.heading')) ?></th>
        <th><?= e(__('dashboard.table_actions')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($result['items'] as $paper): ?>
        <tr>
          <td>
            <a href="<?= e(url('admin.paper', ['id' => $paper['id']])) ?>"><?= e(excerpt((string) $paper['title'], 70)) ?></a>
            <br><span class="muted small"><code><?= e($paper['uid']) ?></code> · <?= e(excerpt((string) $paper['author_line'], 50)) ?></span>
          </td>
          <td class="small">
            <?php if ($paper['uploader'] !== null): ?>
              <a href="<?= e(url('admin.user', ['id' => $paper['uploader']['id']])) ?>">
                <?= e((string) $paper['uploader']['nickname']) ?>
              </a>
              <br><code class="muted"><?= e($paper['uploader']['uid']) ?></code>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="<?= e(Paper::statusBadgeClass((string) $paper['status'])) ?>">
              <?= e(Paper::statusLabel((string) $paper['status'])) ?>
            </span>
            <?php if ((int) $paper['size_exempt'] === 1): ?>
              <br><span class="badge" title="<?= e((string) $paper['size_exempt_note']) ?>"><?= e(__('paper.size_exempt')) ?></span>
            <?php endif; ?>
            <?php if (($paper['ai_status'] ?? 'none') !== 'none'): ?>
              <br><span class="badge <?= e(Paper::aiBadgeClass($paper)) ?>"
                        title="<?= e(excerpt((string) ($paper['ai_reason'] ?? ''), 200)) ?>">
                <?= e(__('ai.badge')) ?>: <?= e(Paper::aiDecisionLabel($paper['ai_decision']) ?: Paper::aiStatusLabel($paper)) ?>
                <?php if (!empty($paper['ai_confidence'])): ?>(<?= (int) $paper['ai_confidence'] ?>%)<?php endif; ?>
              </span>
            <?php endif; ?>
            <?php if ((int) ($paper['version_no'] ?? 1) > 1): ?>
              <br><span class="badge badge--muted">v<?= (int) $paper['version_no'] ?></span>
            <?php endif; ?>
          </td>
          <td class="small">
            <?php if ($paper['section'] !== null): ?>
              <?= e(Section::name($paper['section'])) ?>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>
          <td class="small">
            <?php if ($paper['timestamp'] !== null): ?>
              <span class="ots-chip ots-chip--<?= $paper['timestamp']['status'] === 'confirmed' ? 'ok' : ($paper['timestamp']['status'] === 'failed' ? 'bad' : 'warn') ?>">
                <?= e(Timestamp::statusLabel((string) $paper['timestamp']['status'])) ?>
              </span>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>
          <td class="right nowrap">
            <a class="btn btn--primary btn--small" href="<?= e(url('admin.paper', ['id' => $paper['id']])) ?>"><?= e(__('admin.review')) ?></a>
            <a class="btn btn--ghost btn--small" href="<?= e(url('admin.paper.edit', ['id' => $paper['id']])) ?>"><?= e(__('common.edit')) ?></a>
            <?php if (Paper::isPublic($paper)): ?>
              <a class="btn btn--ghost btn--small" href="<?= e(Paper::publicUrl($paper)) ?>" target="_blank" rel="noopener"><?= e(__('common.view')) ?></a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php \Athenaeum\Core\View::partial('partials/pagination', [
      'result'   => $result,
      'basePath' => '/admin/papers',
      'query'    => $query,
  ]); ?>
<?php endif; ?>
