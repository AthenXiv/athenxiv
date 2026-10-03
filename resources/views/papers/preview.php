<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Full-page PDF.js viewer.
 *
 * @var array $paper
 * @var string $fileUrl
 */
$viewerAvailable = public_path('vendor/pdfjs/web/viewer.html') !== null;
// Served through the application (route "vendor.file"), not straight off the
// disk: nginx does not know .mjs and would send the viewer's ES modules as
// application/octet-stream, which browsers refuse to execute (black page).
// asset() is not usable here because it appends a cache-buster query string
// that would collide with the viewer's own `file=` parameter.
$viewerUrl = url('vendor.file', ['path' => 'pdfjs/web/viewer.html'])
    . '?file=' . rawurlencode($fileUrl);
?>
<div class="viewer">
  <div class="viewer__bar">
    <a class="btn btn--ghost btn--small" href="<?= e(url('paper.show', ['uid' => $paper['uid']])) ?>">
      ← <?= e(__('common.back')) ?>
    </a>
    <span class="viewer__title"><?= e($paper['title']) ?></span>
    <a class="btn btn--small" href="<?= e(url('paper.download', ['uid' => $paper['uid']])) ?>" download>
      <?= e(__('paper.download_pdf')) ?>
    </a>
  </div>

  <div class="viewer__frame">
    <?php if ($viewerAvailable): ?>
      <iframe src="<?= e($viewerUrl) ?>" title="<?= e($paper['title']) ?>"></iframe>
      <p class="muted small viewer__hint">
        <?= e(__('paper.pdf_open_new')) ?>:
        <a href="<?= e($fileUrl) ?>" target="_blank" rel="noopener"><?= e(__('paper.download_pdf')) ?></a>
      </p>
    <?php else: ?>
      <?php
      // <object>/<embed> make Firefox offer its Flash emulator ("click to start
      // flash emulator") and fall back to plain text elsewhere. An iframe hands
      // the file to the browser's built-in PDF viewer on every platform.
      ?>
      <iframe src="<?= e($fileUrl) ?>" title="<?= e($paper['title']) ?>"></iframe>
      <p class="muted small viewer__hint">
        <?= e(__('paper.pdf_open_new')) ?>:
        <a href="<?= e($fileUrl) ?>" target="_blank" rel="noopener"><?= e(__('paper.download_pdf')) ?></a>
      </p>
    <?php endif; ?>
  </div>
</div>
