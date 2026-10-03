<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Markdown editor for one content page, one locale at a time.
 *
 * @var array $page,$locales,$filled
 * @var string $locale,$titleText,$contentText,$preview
 * @var bool $isSystem
 */

use Athenaeum\Models\Page;

$slug = (string) $page['slug'];
?>
<header class="page-head">
  <h1><?= e(__('admin.edit_page')) ?></h1>
  <p class="small">
    <code><?= e($slug) ?></code> ·
    <?php if (array_key_exists($slug, Page::SYSTEM)): ?>
      <?php
      $route = match ($slug) {
          'about' => 'page.about',
          'guidelines' => 'page.guidelines',
          'athenaeum' => 'page.athenaeum',
          'timestamping' => 'page.timestamping',
          default => null,
      };
      ?>
      <?php if ($route !== null): ?>
        <a href="<?= e(url($route)) ?>" target="_blank" rel="noopener"><?= e(__('common.view')) ?> ↗</a> ·
      <?php endif; ?>
    <?php else: ?>
      <a href="<?= e(url('page.custom', ['slug' => $slug])) ?>" target="_blank" rel="noopener"><?= e(__('common.view')) ?> ↗</a> ·
    <?php endif; ?>
    <a href="<?= e(url('admin.pages')) ?>">← <?= e(__('admin.pages')) ?></a>
  </p>
</header>

<div class="admin-grid admin-grid--wide">
  <section class="card">
    <h2 class="card__title">
      <?= e(__('admin.page_locale')) ?>:
      <?= e($locales[$locale]['name'] ?? $locale) ?>
      <span class="muted small">(<?= e($locale) ?>)</span>
    </h2>

    <nav class="tab-nav">
      <?php foreach ($locales as $code => $meta): ?>
        <a href="<?= e(url('admin.page', ['id' => $page['id']]) . '?locale=' . rawurlencode($code)) ?>"
           class="<?= $code === $locale ? 'is-active' : '' ?>">
          <?= e($meta['name']) ?>
          <?php if (in_array($code, $filled, true)): ?>
            <span class="badge badge--ok">✓</span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <form class="form" method="post" action="<?= e(url('admin.page.save', ['id' => $page['id']])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="locale" value="<?= e($locale) ?>">

      <?php
      // An empty locale used to render two blank boxes with no explanation.
      // Show what the site would fall back to, and offer to start from it.
      $hasThisLocale = in_array($locale, $filled, true);
      $fallbackTitle = $locale === 'en' ? '' : Page::title($page, $slug, 'en');
      $fallbackContent = $locale === 'en' ? '' : Page::content($page, 'en');
      ?>
      <?php if (!$hasThisLocale): ?>
        <div class="alert alert--info small">
          <strong><?= e($locales[$locale]['name'] ?? $locale) ?></strong> —
          <?= e(__('page.not_edited_yet')) ?>
          <?php if ($fallbackContent !== ''): ?>
            <br>
            <button type="button" class="btn btn--ghost btn--tiny" data-fill-from-fallback>
              <?= e(__('common.copy')) ?> English
            </button>
          <?php endif; ?>
        </div>
        <input type="hidden" data-fallback-title value="<?= e($fallbackTitle) ?>">
        <input type="hidden" data-fallback-content value="<?= e($fallbackContent) ?>">
      <?php endif; ?>

      <div class="form-row">
        <label for="title"><?= e(__('admin.page_title')) ?></label>
        <input type="text" id="title" name="title" maxlength="190" value="<?= e($titleText) ?>"
               placeholder="<?= e($fallbackTitle) ?>">
      </div>

      <div class="form-row">
        <label for="content"><?= e(__('admin.page_content')) ?></label>
        <textarea id="content" name="content" rows="22" data-markdown-source
                  placeholder="<?= e(mb_substr($fallbackContent, 0, 400)) ?>"><?= e($contentText) ?></textarea>
        <p class="help"><?= e(__('settings.markdown_hint')) ?></p>
      </div>

      <div class="form-actions">
        <button class="btn btn--primary" type="submit"><?= e(__('common.save_changes')) ?></button>
        <a class="btn btn--ghost" href="<?= e(url('admin.pages')) ?>"><?= e(__('common.back')) ?></a>
      </div>
    </form>
  </section>

  <section class="card">
    <h2 class="card__title"><?= e(__('admin.page_preview')) ?></h2>
    <div class="markdown" data-markdown-preview>
      <div data-markdown-target><?= $preview ?></div>
    </div>
    <p class="small muted"><?= e(__('admin.pages_hint')) ?></p>
  </section>
</div>
