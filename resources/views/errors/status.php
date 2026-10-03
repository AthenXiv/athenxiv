<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * HTTP error page.
 *
 * @var int $status
 * @var string $title,$message
 */
?>
<div class="container">
  <div class="error-page">
    <p class="error-page__code"><?= (int) $status ?></p>
    <h1><?= e($title) ?></h1>
    <?php if ($message !== ''): ?>
      <p class="muted"><?= e($message) ?></p>
    <?php endif; ?>
    <p>
      <a class="btn btn--primary" href="<?= e(url('home')) ?>"><?= e(__('common.home')) ?></a>
      <a class="btn btn--ghost" href="<?= e(url('paper.index')) ?>"><?= e(__('nav.browse')) ?></a>
    </p>
  </div>
</div>
