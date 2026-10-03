<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Pagination control.
 *
 * @var array $result  {items,total,page,pages,perPage}
 * @var string $basePath
 * @var array $query   extra query parameters to preserve
 */
$query = $query ?? [];
$basePath = path_url($basePath ?? current_path());
$link = static function (int $page) use ($basePath, $query): string {
    $params = array_merge($query, ['page' => $page]);
    return $basePath . '?' . http_build_query($params);
};
$page = (int) ($result['page'] ?? 1);
$pages = (int) ($result['pages'] ?? 1);
if ($pages <= 1) {
    return;
}
$window = 2;
$from = max(1, $page - $window);
$to = min($pages, $page + $window);
?>
<nav class="pagination" aria-label="<?= e(__('common.page')) ?>">
  <?php if ($page > 1): ?>
    <a class="pagination__step" href="<?= e($link($page - 1)) ?>" rel="prev">← <?= e(__('common.prev')) ?></a>
  <?php endif; ?>

  <?php if ($from > 1): ?>
    <a href="<?= e($link(1)) ?>">1</a>
    <?php if ($from > 2): ?><span class="pagination__gap">…</span><?php endif; ?>
  <?php endif; ?>

  <?php for ($i = $from; $i <= $to; $i++): ?>
    <?php if ($i === $page): ?>
      <span class="pagination__current" aria-current="page"><?= $i ?></span>
    <?php else: ?>
      <a href="<?= e($link($i)) ?>"><?= $i ?></a>
    <?php endif; ?>
  <?php endfor; ?>

  <?php if ($to < $pages): ?>
    <?php if ($to < $pages - 1): ?><span class="pagination__gap">…</span><?php endif; ?>
    <a href="<?= e($link($pages)) ?>"><?= $pages ?></a>
  <?php endif; ?>

  <?php if ($page < $pages): ?>
    <a class="pagination__step" href="<?= e($link($page + 1)) ?>" rel="next"><?= e(__('common.next')) ?> →</a>
  <?php endif; ?>

  <span class="pagination__info muted small">
    <?= e(__('common.page')) ?> <?= $page ?> <?= e(__('common.of')) ?> <?= $pages ?>
    · <?= e(__('paper.results_count', ['count' => (int) ($result['total'] ?? 0)])) ?>
  </span>
</nav>
