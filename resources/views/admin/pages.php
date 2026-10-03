<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * List of editable content pages.
 *
 * @var array $pages,$system,$locales
 */

use Athenaeum\Models\Page;
?>
<header class="page-head">
  <h1><?= e(__('admin.pages')) ?></h1>
  <p class="muted small"><?= e(__('admin.pages_hint')) ?></p>
</header>

<?php if ($pages === []): ?>
  <p class="muted"><?= e(__('admin.no_rows')) ?></p>
<?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th><?= e(__('admin.page_slug')) ?></th>
        <th><?= e(__('admin.name')) ?></th>
        <th><?= e(__('admin.filled_locales')) ?></th>
        <th><?= e(__('common.updated')) ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pages as $page): ?>
        <?php $slug = (string) $page['slug']; ?>
        <tr>
          <td>
            <code><?= e($slug) ?></code>
            <?php if (array_key_exists($slug, $system)): ?>
              <br><span class="badge badge--ok"><?= e(__('admin.system_page_kept')) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <a href="<?= e(url('admin.page', ['id' => $page['id']])) ?>">
              <?= e(Page::title($page, $slug, 'zh-CN')) ?>
            </a>
            <br><span class="muted small"><?= e(implode(' · ', array_slice(array_keys(Page::texts($page, 'contents')), 0, 8))) ?></span>
          </td>
          <td class="small"><?= (int) $page['locale_count'] ?> / <?= count($locales) ?>
            <br><span class="muted"><?= number_format((int) $page['size']) ?> chars</span></td>
          <td class="small">
            <?= e(format_date((string) ($page['updated_at'] ?? $page['created_at']), true)) ?>
            <?php if (!empty($page['editor'])): ?>
              <br><span class="muted"><?= e((string) $page['editor']['nickname']) ?></span>
            <?php endif; ?>
          </td>
          <td class="right nowrap">
            <a class="btn btn--primary btn--small" href="<?= e(url('admin.page', ['id' => $page['id']])) ?>"><?= e(__('common.edit')) ?></a>
            <?php if (!array_key_exists($slug, $system)): ?>
              <a class="btn btn--ghost btn--small" href="<?= e(url('page.custom', ['slug' => $slug])) ?>" target="_blank" rel="noopener"><?= e(__('common.view')) ?></a>
              <form class="inline" method="post" action="<?= e(url('admin.page.purge', ['id' => $page['id']])) ?>"
                    data-confirm="<?= e(__('common.confirm')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn--danger btn--tiny" type="submit"><?= e(__('common.delete')) ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<section class="card">
  <h2 class="card__title"><?= e(__('admin.new_page')) ?></h2>
  <form class="form" method="post" action="<?= e(url('admin.pages.create')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid form-grid--3">
      <div class="form-row">
        <label for="new_page_slug"><?= e(__('admin.page_slug')) ?></label>
        <input type="text" id="new_page_slug" name="slug" maxlength="60" placeholder="privacy">
      </div>
      <div class="form-row">
        <label for="new_page_title"><?= e(__('admin.page_title')) ?></label>
        <input type="text" id="new_page_title" name="title" maxlength="190" required>
      </div>
      <div class="form-row">
        <label for="new_page_locale"><?= e(__('admin.page_locale')) ?></label>
        <select id="new_page_locale" name="locale">
          <?php foreach ($locales as $code => $meta): ?>
            <option value="<?= e($code) ?>" <?= $code === 'zh-CN' ? 'selected' : '' ?>><?= e($meta['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <p class="help"><code>/p/{slug}</code></p>
    <button class="btn btn--primary btn--small" type="submit"><?= e(__('admin.new_page')) ?></button>
  </form>
</section>
