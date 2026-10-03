<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/** About page. */
?>
<div class="container">
  <article class="prose-page">
    <h1><?= e(__('page.about_title')) ?></h1>
    <p><?= e(__('page.about_p1')) ?></p>
    <p><?= e(__('page.about_p2')) ?></p>
    <p><?= e(__('page.about_p3')) ?></p>

    <h2><?= e(__('home.how_title')) ?></h2>
    <ol>
      <li><strong><?= e(__('home.how_step1_title')) ?></strong> — <?= e(__('home.how_step1_body')) ?></li>
      <li><strong><?= e(__('home.how_step2_title')) ?></strong> — <?= e(__('home.how_step2_body')) ?></li>
      <li><strong><?= e(__('home.how_step3_title')) ?></strong> — <?= e(__('home.how_step3_body')) ?></li>
    </ol>

    <?php $contact = \Athenaeum\Core\Settings::string('site.contact_email'); ?>
    <?php if ($contact !== ''): ?>
      <h2><?= e(__('user.contact')) ?></h2>
      <p><a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a></p>
    <?php endif; ?>

    <p>
      <a class="btn btn--ghost btn--small" href="<?= e(url('page.timestamping')) ?>"><?= e(__('page.timestamping_title')) ?> →</a>
      <a class="btn btn--ghost btn--small" href="<?= e(url('page.guidelines')) ?>"><?= e(__('page.guidelines_title')) ?> →</a>
    </p>
  </article>
</div>
