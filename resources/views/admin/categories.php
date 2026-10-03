<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Subject-area tree editor: nested display, inline rename/move, create child,
 * delete empty leaves.
 *
 * @var array $tree,$flat,$locales
 * @var int $total
 */

use Athenaeum\Models\Category;

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editing = $editId > 0 ? Category::find($editId) : null;
$editNames = $editing !== null ? (json_decode((string) $editing['names'], true) ?: []) : [];
$editPath = $editing !== null ? Category::pathOf($editId) : [];

$renderTree = function (array $nodes) use (&$renderTree): void {
    ?>
    <ul class="cat-list cat-list--admin">
      <?php foreach ($nodes as $node): ?>
        <?php $kids = $node['children'] ?? []; ?>
        <li class="cat-node cat-node--d<?= (int) ($node['depth'] ?? 0) ?>">
          <div class="cat-node__head">
            <span class="cat-node__name"><?= e(Category::name($node)) ?></span>
            <code class="muted small"><?= e((string) $node['slug']) ?></code>
            <span class="badge badge--muted"><?= e(__('category.branch_total', ['count' => (int) ($node['branch_count'] ?? 0)])) ?></span>
            <span class="row-actions small">
              <a class="btn btn--ghost btn--tiny"
                 href="<?= e(url('admin.categories') . '?edit=' . (int) $node['id']) ?>"><?= e(__('common.edit')) ?></a>
              <a class="btn btn--ghost btn--tiny" title="<?= e(__('admin.category_add_child')) ?>"
                 href="<?= e(url('admin.categories') . '?parent=' . (int) $node['id'] . '#new-area') ?>">+</a>
              <form class="inline" method="post" action="<?= e(url('admin.categories.purge', ['id' => $node['id']])) ?>"
                    data-confirm="<?= e(__('common.confirm')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn--danger btn--tiny" type="submit">×</button>
              </form>
            </span>
          </div>
          <?php if ($kids !== []): ?>
            <?php $renderTree($kids); ?>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php
};
?>
<header class="page-head">
  <h1><?= e(__('admin.categories')) ?></h1>
  <p class="muted small">
    <?= (int) $total ?> · <?= e(__('category.tree_hint')) ?>
  </p>
</header>

<?php if ($editing !== null): ?>
  <section class="card">
    <h2 class="card__title">
      <?= e(__('common.edit')) ?>:
      <?= e(implode(' / ', array_map(static fn (array $n): string => Category::name($n), $editPath))) ?>
      <span class="muted small">(<?= e((string) $editing['slug']) ?>)</span>
    </h2>

    <form class="form" method="post" action="<?= e(url('admin.categories.update', ['id' => $editing['id']])) ?>">
      <?= csrf_field() ?>
      <div class="form-grid form-grid--3">
        <?php foreach ($locales as $code => $meta): ?>
          <div class="form-row">
            <label for="edit_name_<?= e($code) ?>"><?= e($meta['name']) ?></label>
            <input type="text" id="edit_name_<?= e($code) ?>" name="name_<?= e($code) ?>" maxlength="120"
                   value="<?= e((string) ($editNames[$code] ?? '')) ?>"
                   placeholder="<?= e((string) (Category::name($editing, $code === 'en' ? 'zh-CN' : 'en'))) ?>">
          </div>
        <?php endforeach; ?>
      </div>

      <div class="form-grid form-grid--3">
        <div class="form-row">
          <label for="edit_parent"><?= e(__('admin.category_parent')) ?></label>
          <select id="edit_parent" name="parent_id">
            <option value="">—</option>
            <?php foreach ($flat as $option): ?>
              <?php if ((int) $option['id'] === (int) $editing['id']) { continue; } ?>
              <option value="<?= (int) $option['id'] ?>"
                <?= (int) $editing['parent_id'] === (int) $option['id'] ? 'selected' : '' ?>>
                <?= e($option['indent'] . Category::name($option)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <label for="edit_sort"><?= e(__('admin.sort_order')) ?></label>
          <input type="number" id="edit_sort" name="sort_order" value="<?= (int) $editing['sort_order'] ?>">
        </div>
        <div class="form-row">
          <label>&nbsp;</label>
          <div class="row-actions">
            <button class="btn btn--primary btn--small" type="submit"><?= e(__('common.save')) ?></button>
            <a class="btn btn--ghost btn--small" href="<?= e(url('admin.categories')) ?>"><?= e(__('common.cancel')) ?></a>
          </div>
        </div>
      </div>
    </form>
  </section>
<?php endif; ?>

<section class="card" id="new-area">
  <h2 class="card__title"><?= e(__('admin.category_add_child')) ?></h2>
  <form class="form" method="post" action="<?= e(url('admin.categories.create')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid form-grid--3">
      <div class="form-row">
        <label for="new_slug"><?= e(__('admin.slug')) ?></label>
        <input type="text" id="new_slug" name="slug" maxlength="60" placeholder="real-analysis">
      </div>
      <div class="form-row">
        <label for="new_parent"><?= e(__('admin.category_parent')) ?></label>
        <select id="new_parent" name="parent_id">
          <option value="">—</option>
          <?php
          $preselected = isset($_GET['parent']) ? (int) $_GET['parent'] : 0;
          foreach ($flat as $option): ?>
            <option value="<?= (int) $option['id'] ?>" <?= $preselected === (int) $option['id'] ? 'selected' : '' ?>>
              <?= e($option['indent'] . Category::name($option)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label for="new_sort"><?= e(__('admin.sort_order')) ?></label>
        <input type="number" id="new_sort" name="sort_order" value="0">
      </div>
    </div>

    <div class="form-grid form-grid--3">
      <?php foreach ($locales as $code => $meta): ?>
        <div class="form-row">
          <label for="new_name_<?= e($code) ?>"><?= e($meta['name']) ?></label>
          <input type="text" id="new_name_<?= e($code) ?>" name="name_<?= e($code) ?>" maxlength="120">
        </div>
      <?php endforeach; ?>
    </div>

    <button class="btn btn--primary btn--small" type="submit"><?= e(__('admin.category_add_child')) ?></button>
  </form>
</section>

<section class="card">
  <h2 class="card__title"><?= e(__('category.browse_title')) ?></h2>
  <?php $renderTree($tree); ?>
</section>
