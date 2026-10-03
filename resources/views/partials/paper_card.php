<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * One paper in a list. Expects `$paper`; optionally `$showStatus`.
 *
 * @var array $paper
 */

use Athenaeum\Models\Paper;
use Athenaeum\Models\Section;

$showStatus = $showStatus ?? false;
$authors = Paper::authorLine($paper);
$section = Paper::sectionOf($paper);
$timestamp = $paper['timestamp'] ?? null;
$pdfUrl = url('paper.file', ['uid' => $paper['uid']]);
?>
<article class="paper-card">
  <h3 class="paper-card__title">
    <a href="<?= e(Paper::publicUrl($paper)) ?>"><?= e($paper['title']) ?></a>
    <?php if (Paper::isArchived($paper)): ?>
      <span class="<?= e(Paper::rightsBadgeClass($paper)) ?>"
            title="<?= e(__('paper.rights_hint')) ?>"><?= e(Paper::rightsLabel($paper)) ?></span>
    <?php endif; ?>
  </h3>

  <?php if ($authors !== ''): ?>
    <p class="paper-card__authors"><?= e($authors) ?></p>
  <?php endif; ?>

  <p class="paper-card__abstract"><?= e(excerpt((string) $paper['abstract'], 280)) ?></p>

  <p class="paper-card__meta small muted">
    <?php if ($section !== null): ?>
      <a class="badge" href="<?= e(url('paper.section', ['slug' => $section['slug']])) ?>"><?= e(Section::name($section)) ?></a>
    <?php endif; ?>
    <?php if (Paper::isArchived($paper) && !empty($paper['origin_published_at'])): ?>
      <span><?= e(__('paper.origin_published_short', ['date' => format_date((string) $paper['origin_published_at'])])) ?></span>
    <?php else: ?>
      <span><?= e(format_date((string) ($paper['published_at'] ?: $paper['created_at']))) ?></span>
    <?php endif; ?>
    <?php if (\Athenaeum\Core\Settings::bool('ui.show_view_counts')): ?>
      <span>· <?= (int) $paper['views'] ?> <?= e(__('paper.views')) ?></span>
      <span>· <?= (int) $paper['downloads'] ?> <?= e(__('paper.downloads')) ?></span>
    <?php endif; ?>
    <?php if ($showStatus): ?>
      <span class="<?= e(Paper::statusBadgeClass((string) $paper['status'])) ?>"><?= e(Paper::statusLabel((string) $paper['status'])) ?></span>
    <?php endif; ?>
  </p>

  <p class="paper-card__actions">
    <a class="btn btn--small" href="<?= e(Paper::publicUrl($paper)) ?>"><?= e(__('paper.pdf_preview')) ?></a>
    <a class="btn btn--ghost btn--small" href="<?= e(url('paper.download', ['uid' => $paper['uid']])) ?>" download>
      <?= e(__('paper.download_pdf')) ?>
    </a>
    <?php if ($timestamp !== null): ?>
      <span class="ots-chip ots-chip--<?= $timestamp['status'] === 'confirmed' ? 'ok' : ($timestamp['status'] === 'failed' ? 'bad' : 'warn') ?>"
            title="<?= e($timestamp['status'] === 'confirmed' ? __('ots.status_confirmed') : __('ots.status_pending')) ?>">
        <span aria-hidden="true">⧗</span> OpenTimestamps
      </span>
    <?php endif; ?>
  </p>
</article>
