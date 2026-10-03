<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * An admin-editable content page.
 *
 * @var string $pageTitle,$bodyHtml,$pageSlug
 * @var bool $fromDatabase
 * @var array|null $page
 * @var string|null $updatedAt
 * @var array $locales  locales in which this page has content
 * @var array $calendars,$verifyUrl  (timestamping page)
 * @var array $limits                (guidelines page)
 */

use Athenaeum\Core\Auth;
use Athenaeum\Models\Page;

$calendars = $calendars ?? [];
$verifyUrl = $verifyUrl ?? \Athenaeum\Services\OpenTimestamps::verifyUrl();
$limits = $limits ?? null;
?>
<div class="container">
  <article class="prose-page" data-page-slug="<?= e($pageSlug) ?>">
    <?php if (!$fromDatabase): ?>
      <div class="alert alert--info"><?= e(__('page.not_edited_yet')) ?></div>
    <?php endif; ?>

    <?= $bodyHtml ?>

    <?php if ($pageSlug === 'timestamping' && $calendars !== []): ?>
      <h2><?= e(__('ots.calendars')) ?></h2>
      <ul>
        <?php foreach ($calendars as $calendar): ?>
          <li><a href="<?= e($calendar) ?>" target="_blank" rel="noopener nofollow"><?= e($calendar) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <p>
        <a class="btn btn--primary" href="<?= e($verifyUrl) ?>" target="_blank" rel="noopener noreferrer">
          <?= e(__('ots.verify_at')) ?>
        </a>
      </p>
    <?php endif; ?>

    <?php if ($pageSlug === 'guidelines' && is_array($limits)): ?>
      <h2><?= e(__('paper.pdf_limit')) ?></h2>
      <ul>
        <li>PDF — <?= e($limits['pdf']) ?></li>
        <li><?= e(__('paper.attachments')) ?> — <?= e($limits['archive']) ?> × <?= (int) $limits['count'] ?></li>
        <li><?= e(__('upload.allowed_types')) ?> — <?= e($limits['extensions']) ?></li>
        <li><?= e(__('common.contact')) ?> — <a href="mailto:<?= e($limits['contact']) ?>"><?= e($limits['contact']) ?></a></li>
      </ul>
      <p><a class="btn btn--primary" href="<?= e(url('paper.create')) ?>"><?= e(__('paper.submit_title')) ?></a></p>
    <?php endif; ?>

    <?php if ($pageSlug === 'about'): ?>
      <p class="page-cta">
        <a class="btn btn--primary" href="<?= e(url('register')) ?>"><?= e(__('nav.register')) ?></a>
        <a class="btn" href="<?= e(url('paper.create')) ?>"><?= e(__('nav.submit')) ?></a>
        <a class="btn btn--ghost" href="<?= e(url('page.guidelines')) ?>"><?= e(__('page.guidelines_title')) ?></a>
        <a class="btn btn--ghost" href="<?= e(url('page.timestamping')) ?>"><?= e(__('page.timestamping_how_title')) ?></a>
      </p>
    <?php endif; ?>

    <?php if ($pageSlug === 'athenaeum'): ?>
      <p class="page-cta">
        <a class="btn btn--ghost" href="<?= e(url('page.about')) ?>"><?= e(__('page.about_title')) ?></a>
        <a class="btn btn--ghost" href="<?= e(url('paper.categories')) ?>"><?= e(__('category.browse_title')) ?></a>
        <a class="btn btn--ghost" href="<?= e(url('page.timestamping')) ?>"><?= e(__('page.timestamping_how_title')) ?></a>
      </p>
    <?php endif; ?>

    <footer class="prose-page__foot small muted">
      <?php if (!empty($locales)): ?>
        <span><?= e(__('page.translated_into', ['count' => count($locales)])) ?></span>
      <?php endif; ?>
      <?php if (!empty($updatedAt)): ?>
        <span>· <?= e(__('common.updated')) ?> <?= e(format_date((string) $updatedAt, true)) ?></span>
      <?php endif; ?>
      <?php if (Auth::isAdmin() && $page !== null): ?>
        <span>· <a href="<?= e(url('admin.page', ['id' => $page['id']])) ?>"><?= e(__('admin.edit_page')) ?></a></span>
      <?php endif; ?>
    </footer>
  </article>
</div>
