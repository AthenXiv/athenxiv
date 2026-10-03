<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Landing page.
 *
 * @var array $latest,$featured,$sections,$categories,$stats
 * @var string $notice
 */

use Athenaeum\Core\Settings;
use Athenaeum\Models\Category;
use Athenaeum\Models\Section;

$siteName = Settings::siteName();
?>
<section class="hero">
  <div class="container hero__inner">
    <div class="hero__text">
      <h1><?= e(site_text('home.hero_title')) ?></h1>
      <p class="hero__subtitle"><?= e(site_text('home.hero_subtitle')) ?></p>
      <p class="hero__cta">
        <a class="btn btn--primary" href="<?= e(url('paper.index')) ?>"><?= e(site_text('home.hero_cta_label', [], 'home.browse_cta')) ?></a>
        <?php if (Settings::bool('registration.open')): ?>
          <a class="btn btn--ghost" href="<?= e(url('register')) ?>"><?= e(__('home.submit_cta')) ?></a>
        <?php endif; ?>
      </p>
    </div>
    <?php
    // The paper/author/timestamp counters used to sit here. An archive that
    // announces "2 authors" reads as abandoned rather than open, so the hero
    // now carries the claim and the call to action only. HomeController still
    // computes $stats, so restoring the block is a copy-paste away.
    ?>
  </div>
</section>

<div class="container">
  <?php if ($notice !== ''): ?>
    <section class="notice markdown">
      <h2 class="notice__title"><?= e(__('home.notice_title')) ?></h2>
      <?= markdown($notice) ?>
    </section>
  <?php endif; ?>

  <?php if ($featured !== []): ?>
    <section class="section-block">
      <h2><?= e(__('home.featured')) ?></h2>
      <div class="paper-grid">
        <?php foreach ($featured as $paper): ?>
          <?php \Athenaeum\Core\View::partial('partials/paper_card', ['paper' => $paper]); ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="section-block">
    <div class="section-block__head">
      <h2><?= e(__('home.latest')) ?></h2>
      <a class="small" href="<?= e(url('paper.index')) ?>"><?= e(__('home.view_all')) ?> →</a>
    </div>
    <?php if ($latest['items'] === []): ?>
      <p class="empty"><?= e(__('paper.no_papers')) ?></p>
      <p class="muted small"><?= e(__('paper.no_papers_hint')) ?></p>
    <?php else: ?>
      <div class="paper-grid">
        <?php foreach ($latest['items'] as $paper): ?>
          <?php \Athenaeum\Core\View::partial('partials/paper_card', ['paper' => $paper]); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($sections !== []): ?>
    <section class="section-block">
      <h2><?= e(__('home.sections_title')) ?></h2>
      <ul class="tile-list">
        <?php foreach ($sections as $section): ?>
          <li class="tile">
            <a href="<?= e(url('paper.section', ['slug' => $section['slug']])) ?>">
              <span class="tile__name"><?= e(Section::name($section)) ?></span>
              <span class="tile__count"><?= (int) ($section['paper_count'] ?? 0) ?></span>
            </a>
            <?php $description = Section::description($section); ?>
            <?php if ($description !== ''): ?>
              <p class="tile__note small muted"><?= e(excerpt($description, 120)) ?></p>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <section class="section-block section-block--areas">
    <h2><?= e(__('home.categories_title')) ?></h2>
    <p class="muted small"><?= e(__('category.tree_hint')) ?></p>
    <p class="cta-row">
      <a class="btn btn--primary" href="<?= e(url('paper.index') . '#areas') ?>">
        <?= e(__('home.categories_title')) ?>
      </a>
      <a class="btn btn--ghost" href="<?= e(url('paper.categories')) ?>">
        <?= e(__('category.browse_title')) ?>
      </a>
    </p>
  </section>

  <section class="section-block how">
    <h2><?= e(__('home.how_title')) ?></h2>
    <ol class="how__list">
      <li>
        <h3><?= e(__('home.how_step1_title')) ?></h3>
        <p><?= e(__('home.how_step1_body')) ?></p>
      </li>
      <li>
        <h3><?= e(__('home.how_step2_title')) ?></h3>
        <p><?= e(__('home.how_step2_body')) ?></p>
      </li>
      <li>
        <h3><?= e(__('home.how_step3_title')) ?></h3>
        <p><?= e(__('home.how_step3_body')) ?></p>
      </li>
    </ol>
    <p><a class="btn btn--ghost btn--small" href="<?= e(url('page.timestamping')) ?>"><?= e(__('page.timestamping_title')) ?> →</a></p>
  </section>
</div>
