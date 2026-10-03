<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Site-wide announcement (首页公告 / 站内公告).
 *
 * Fully admin controlled: Markdown body, colour (info|success|warning|danger|
 * neutral or a #rrggbb value) and whether visitors may close it. Closing stores
 * a cookie keyed to the notice revision, so a *new* announcement still shows up
 * for people who dismissed the previous one.
 *
 * @var string $notice,$color
 * @var bool $dismissible
 * @var string $revision
 */
if (trim($notice) === '') {
    return;
}

$palette = ['info', 'success', 'warning', 'danger', 'neutral'];
$class = in_array($color, $palette, true) ? $color : 'custom';
$inline = '';
if (!in_array($color, $palette, true) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
    // Derive a tint and a border from the single colour the admin picked.
    $inline = sprintf(
        ' style="--notice-accent:%1$s;background:color-mix(in srgb, %1$s 12%%, #ffffff);border-color:color-mix(in srgb, %1$s 35%%, #ffffff)"',
        $color
    );
}
?>
<aside class="notice notice--<?= e($class) ?>" data-notice data-notice-revision="<?= e($revision) ?>"<?= $inline ?>>
  <div class="container notice__inner">
    <div class="notice__body markdown"><?= \Athenaeum\Core\Markdown::render($notice) ?></div>
    <?php if ($dismissible): ?>
      <button type="button" class="notice__close" data-notice-close
              title="<?= e(__('notice.dismiss')) ?>" aria-label="<?= e(__('notice.dismiss')) ?>">×</button>
    <?php else: ?>
      <span class="notice__pin" title="<?= e(__('notice.sticky')) ?>" aria-hidden="true">📌</span>
    <?php endif; ?>
  </div>
</aside>
