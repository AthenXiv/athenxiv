<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Subject-area tree (/categories) — the multi-level replacement for the old
 * flat list. Each branch shows its own paper count and expands visually by
 * indentation, which stays readable at three levels.
 *
 * @var array $tree
 */
$render = function (array $nodes, int $depth = 0) use (&$render): void {
    foreach ($nodes as $node) {
        $children = $node['children'] ?? [];
        ?>
        <li class="cat-node cat-node--d<?= $depth ?>" style="--depth:<?= $depth ?>">
          <div class="cat-node__head">
            <a class="cat-node__name" href="<?= e(url('paper.category', ['slug' => $node['slug']])) ?>">
              <?= e(\Athenaeum\Models\Category::name($node)) ?>
            </a>
            <span class="cat-node__count muted small">
              <?= e(__('category.branch_total', ['count' => (int) ($node['branch_count'] ?? 0)])) ?>
            </span>
          </div>
          <?php if ($children !== []): ?>
            <ul class="cat-list">
              <?php $render($children, $depth + 1); ?>
            </ul>
          <?php endif; ?>
        </li>
        <?php
    }
};
?>
<div class="container">
  <header class="page-head">
    <h1><?= e(__('category.browse_title')) ?></h1>
    <p class="muted small"><?= e(__('category.tree_hint')) ?></p>
  </header>

  <div class="category-toolbar">
    <a class="btn btn--small" href="<?= e(url('paper.index')) ?>"><?= e(__('category.all')) ?></a>
  </div>

  <ul class="cat-list cat-list--root">
    <?php $render($tree, 0); ?>
  </ul>
</div>
