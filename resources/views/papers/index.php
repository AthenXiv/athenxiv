<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Paper listing with filters — used by /papers, /sections/{slug} and
 * /categories/{slug}.
 *
 * @var array $result,$filters,$sections,$categories,$tree,$languageOptions
 * @var string $heading,$basePath
 * @var string|null $description
 * @var array $breadcrumb,$children
 */

use Athenaeum\Models\Category;
use Athenaeum\Models\Section;

$description = $description ?? '';
$breadcrumb = $breadcrumb ?? [];
$children = $children ?? [];
$tree = $tree ?? [];
$languageOptions = $languageOptions ?? [];
$query = array_filter($filters ?? [], static fn ($value): bool => $value !== '' && $value !== null);
unset($query['section'], $query['category']);

/** Render the subject tree as collapsible branches. */
$renderTree = function (array $nodes, int $depth = 0) use (&$renderTree, $filters): void {
    foreach ($nodes as $node) {
        $kids = $node['children'] ?? [];
        $isActive = ($filters['category'] ?? '') === $node['slug'];
        $label = Category::name($node);
        $count = (int) ($node['branch_count'] ?? 0);
        if ($kids === []) {
            ?>
            <li class="cat-li cat-li--d<?= $depth ?>">
              <a href="<?= e(url('paper.category', ['slug' => $node['slug']])) ?>"
                 class="<?= $isActive ? 'is-active' : '' ?>"><?= e($label) ?>
                <span class="muted"><?= $count ?></span></a>
            </li>
            <?php
            continue;
        }
        ?>
        <li class="cat-li cat-li--d<?= $depth ?>">
          <details class="cat-branch">
            <summary>
              <a href="<?= e(url('paper.category', ['slug' => $node['slug']])) ?>"
                 class="<?= $isActive ? 'is-active' : '' ?>"><?= e($label) ?></a>
              <span class="muted small"><?= $count ?></span>
            </summary>
            <ul class="cat-ul">
              <?php $renderTree($kids, $depth + 1); ?>
            </ul>
          </details>
        </li>
        <?php
    }
};
?>
<div class="container">
  <header class="page-head">
    <?php if ($breadcrumb !== []): ?>
      <nav class="breadcrumb small" aria-label="<?= e(__('category.browse_title')) ?>">
        <a href="<?= e(url('paper.categories')) ?>"><?= e(__('category.browse_title')) ?></a>
        <?php foreach ($breadcrumb as $crumb): ?>
          <span aria-hidden="true">/</span>
          <a href="<?= e(url('paper.category', ['slug' => $crumb['slug']])) ?>"><?= e(Category::name($crumb)) ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
    <h1><?= e($heading) ?></h1>
    <?php if ($description !== ''): ?>
      <p class="page-head__note"><?= e($description) ?></p>
    <?php endif; ?>

    <?php if ($children !== []): ?>
      <div class="child-chips">
        <span class="muted small"><?= e(__('category.subcategories')) ?>:</span>
        <?php foreach ($children as $child): ?>
          <a class="tag" href="<?= e(url('paper.category', ['slug' => $child['slug']])) ?>">
            <?= e(Category::name($child)) ?>
            <span class="tag__count"><?= (int) ($child['branch_count'] ?? 0) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </header>

  <div class="layout-with-side">
    <aside class="side">
      <form class="filters" id="filters" method="get" action="<?= e(path_url('/papers')) ?>" data-filters>
        <div class="form-row">
          <label for="q"><?= e(__('common.search')) ?></label>
          <input type="search" id="q" name="q" value="<?= e($filters['q'] ?? '') ?>"
                 placeholder="<?= e(__('common.search_placeholder')) ?>">
        </div>

        <div class="form-row">
          <label for="sort"><?= e(__('paper.sort')) ?></label>
          <select id="sort" name="sort">
            <?php foreach (['newest' => 'paper.sort_newest', 'oldest' => 'paper.sort_oldest', 'title' => 'paper.sort_title', 'downloads' => 'paper.sort_downloads', 'views' => 'paper.sort_views'] as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= ($filters['sort'] ?? 'newest') === $value ? 'selected' : '' ?>>
                <?= e(__($label)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row">
          <label for="language"><?= e(__('paper.filter_language')) ?></label>
          <select id="language" name="language">
            <option value=""><?= e(__('paper.filter_all')) ?></option>
            <?php foreach ($languageOptions as $option): ?>
              <?php
              // Languages the catalogue does not know (typed by an author via
              // "Other") appear here as soon as such a paper is published.
              $selected = ($filters['language'] ?? '') === $option['code']
                  && (string) ($filters['language_custom'] ?? '') === (string) ($option['custom'] ?? '');
              ?>
              <option value="<?= e($option['code']) ?>"
                      data-custom="<?= e((string) ($option['custom'] ?? '')) ?>"
                      <?= $selected ? 'selected' : '' ?>>
                <?= e($option['label']) ?> (<?= (int) $option['count'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <button class="btn btn--primary btn--small" type="submit"><?= e(__('common.search')) ?></button>
        <?php if ($query !== []): ?>
          <a class="btn btn--ghost btn--small" href="<?= e(path_url('/papers')) ?>"><?= e(__('common.all')) ?></a>
        <?php endif; ?>
      </form>

      <nav class="side__block">
        <h2 class="side__title"><?= e(__('common.sections')) ?></h2>
        <ul class="side__list">
          <?php foreach ($sections as $section): ?>
            <li>
              <a href="<?= e(url('paper.section', ['slug' => $section['slug']])) ?>"
                 class="<?= ($filters['section'] ?? '') === $section['slug'] ? 'is-active' : '' ?>">
                <?= e(Section::name($section)) ?>
                <span class="muted"><?= (int) ($section['paper_count'] ?? 0) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </nav>

      <nav class="side__block" id="areas">
        <h2 class="side__title">
          <a href="<?= e(url('paper.categories')) ?>"><?= e(__('category.browse_title')) ?></a>
        </h2>

        <?php
        $selectedAreas = array_values(array_filter((array) ($filters['categories'] ?? [])));
        $labelFor = static function (array $node): string {
            return \Athenaeum\Models\Category::name($node) . ' ' . (string) $node['slug'];
        };
        ?>
        <div class="area-filter" data-area-filter>
          <label class="area-filter__search">
            <span class="visually-hidden"><?= e(__('category.browse_title')) ?></span>
            <input type="search" data-area-search autocomplete="off"
                   placeholder="<?= e(__('category.search_placeholder')) ?>">
          </label>
          <p class="muted small" data-area-summary
             data-template="<?= e(__('category.selected_count', ['count' => ':count'])) ?>">
            <?= e(__('category.selected_count', ['count' => (string) count($selectedAreas)])) ?>
          </p>
          <div class="area-filter__list" data-area-list>
            <?php
            $renderAreas = function (array $nodes, int $depth = 0) use (&$renderAreas, $selectedAreas, $labelFor): void {
                foreach ($nodes as $node) {
                    $slug = (string) $node['slug'];
                    $kids = $node['children'] ?? [];
                    $checked = in_array($slug, $selectedAreas, true);
                    ?>
                    <label class="area-option" data-area-option
                           data-name="<?= e(mb_strtolower($labelFor($node))) ?>"
                           style="--depth:<?= $depth ?>">
                      <input type="checkbox" name="categories[]" value="<?= e($slug) ?>"
                             form="filters"
                             <?= $checked ? 'checked' : '' ?>>
                      <span class="area-option__name"><?= e(Category::name($node)) ?></span>
                      <span class="area-option__count muted"><?= (int) ($node['branch_count'] ?? 0) ?></span>
                    </label>
                    <?php
                    if ($kids !== []) {
                        $renderAreas($kids, $depth + 1);
                    }
                }
            };
            $renderAreas($tree);
            ?>
          </div>
          <p class="muted small" data-area-empty hidden><?= e(__('common.no_results')) ?></p>
        </div>
      </nav>
    </aside>

    <div class="main-col">
      <?php if ($result['items'] === []): ?>
        <p class="empty"><?= e(__('paper.no_papers')) ?></p>
        <p class="muted small"><?= e(__('paper.no_papers_hint')) ?></p>
      <?php else: ?>
        <p class="muted small"><?= e(__('paper.results_count', ['count' => (int) $result['total']])) ?></p>
        <div class="paper-grid">
          <?php foreach ($result['items'] as $paper): ?>
            <?php \Athenaeum\Core\View::partial('partials/paper_card', ['paper' => $paper]); ?>
          <?php endforeach; ?>
        </div>
        <?php \Athenaeum\Core\View::partial('partials/pagination', [
            'result'   => $result,
            'basePath' => $basePath,
            'query'    => $query,
        ]); ?>
      <?php endif; ?>
    </div>
  </div>
</div>
