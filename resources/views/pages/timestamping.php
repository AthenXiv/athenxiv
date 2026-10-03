<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Explains the OpenTimestamps pipeline, lists the calendars actually in use and
 * states plainly what a pending proof does and does not prove.
 *
 * @var string[] $calendars
 * @var string $verifyUrl
 */
?>
<div class="container">
  <article class="prose-page">
    <h1><?= e(__('page.timestamping_title')) ?></h1>
    <p><?= e(__('page.timestamping_p1')) ?></p>
    <p><?= e(__('page.timestamping_p2')) ?></p>

    <h2><?= e(__('ots.calendars')) ?></h2>
    <ul>
      <?php foreach ($calendars as $calendar): ?>
        <li><a href="<?= e($calendar) ?>" rel="noopener nofollow" target="_blank"><?= e($calendar) ?></a></li>
      <?php endforeach; ?>
    </ul>

    <h2><?= e(__('ots.verify_button')) ?></h2>
    <p><?= e(__('page.timestamping_p3')) ?></p>
    <p>
      <a class="btn btn--primary" href="<?= e($verifyUrl) ?>" target="_blank" rel="noopener noreferrer">
        <?= e(__('ots.verify_at')) ?>
      </a>
    </p>

    <h2><?= e(__('common.info')) ?></h2>
    <p><?= e(__('page.timestamping_p4')) ?></p>
    <p class="muted"><?= e(__('page.timestamping_privacy')) ?></p>
  </article>
</div>
