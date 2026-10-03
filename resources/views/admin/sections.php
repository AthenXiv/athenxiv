<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Sections (分区) management: create, rename per language, order, default,
 * visibility.
 *
 * @var array $sections,$locales
 */
?>
<header class="page-head">
  <h1><?= e(__('admin.sections')) ?></h1>
  <p class="muted small"><?= e(__('admin.section_default_saved')) ?></p>
</header>

<section class="card">
  <h2 class="card__title"><?= e(__('admin.sections')) ?></h2>
  <table class="table">
    <thead>
      <tr>
        <th><?= e(__('admin.slug')) ?></th>
        <th><?= e(__('admin.name')) ?></th>
        <th><?= e(__('admin.sort_order')) ?></th>
        <th><?= e(__('admin.is_public')) ?></th>
        <th><?= e(__('admin.papers')) ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($sections as $section): ?>
        <?php $names = json_decode((string) $section['names'], true) ?: []; ?>
        <?php $descriptions = json_decode((string) $section['descriptions'], true) ?: []; ?>
        <tr>
          <td>
            <code><?= e((string) $section['slug']) ?></code>
            <?php if ((int) $section['is_default'] === 1): ?>
              <br><span class="badge badge--ok"><?= e(__('admin.is_default')) ?></span>
            <?php endif; ?>
          </td>
          <td colspan="5">
            <form class="form form--inline-grid" method="post" action="<?= e(url('admin.sections.update', ['id' => $section['id']])) ?>">
              <?= csrf_field() ?>
              <div class="form-grid form-grid--3">
                <?php foreach ($locales as $code => $meta): ?>
                  <div class="form-row">
                    <label for="name_<?= e($code) ?>_<?= (int) $section['id'] ?>"><?= e($meta['name']) ?></label>
                    <input type="text" id="name_<?= e($code) ?>_<?= (int) $section['id'] ?>"
                           name="name_<?= e($code) ?>" maxlength="120"
                           value="<?= e((string) ($names[$code] ?? '')) ?>">
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="form-grid form-grid--3">
                <?php foreach ($locales as $code => $meta): ?>
                  <div class="form-row">
                    <label for="description_<?= e($code) ?>_<?= (int) $section['id'] ?>">
                      <?= e($meta['name']) ?> — <?= e(__('admin.description')) ?>
                    </label>
                    <input type="text" id="description_<?= e($code) ?>_<?= (int) $section['id'] ?>"
                           name="description_<?= e($code) ?>" maxlength="500"
                           value="<?= e((string) ($descriptions[$code] ?? '')) ?>">
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="form-grid form-grid--4">
                <div class="form-row">
                  <label for="sort_<?= (int) $section['id'] ?>"><?= e(__('admin.sort_order')) ?></label>
                  <input type="number" id="sort_<?= (int) $section['id'] ?>" name="sort_order"
                         value="<?= (int) $section['sort_order'] ?>">
                </div>
                <div class="form-row">
                  <label class="checkbox">
                    <input type="checkbox" name="is_public" value="1" <?= (int) $section['is_public'] === 1 ? 'checked' : '' ?>>
                    <?= e(__('admin.is_public')) ?>
                  </label>
                </div>
                <div class="form-row">
                  <label class="checkbox">
                    <input type="checkbox" name="is_default" value="1" <?= (int) $section['is_default'] === 1 ? 'checked' : '' ?>>
                    <?= e(__('admin.is_default')) ?>
                  </label>
                </div>
                <div class="form-row">
                  <label>&nbsp;</label>
                  <div class="row-actions">
                    <button class="btn btn--primary btn--small" type="submit"><?= e(__('common.save')) ?></button>
                  </div>
                </div>
              </div>
            </form>
            <div class="row-actions">
              <span class="muted small"><?= e(__('paper.results_count', ['count' => (int) ($section['paper_count'] ?? 0)])) ?></span>
              <form class="inline" method="post" action="<?= e(url('admin.sections.purge', ['id' => $section['id']])) ?>"
                    data-confirm="<?= e(__('common.confirm')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn--danger btn--tiny" type="submit"><?= e(__('common.delete')) ?></button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<section class="card">
  <h2 class="card__title"><?= e(__('admin.section_created')) ?></h2>
  <form class="form" method="post" action="<?= e(url('admin.sections.create')) ?>">
    <?= csrf_field() ?>
    <div class="form-row">
      <label for="new_slug"><?= e(__('admin.slug')) ?></label>
      <input type="text" id="new_slug" name="slug" maxlength="60" placeholder="preprints">
      <p class="help"><?= e(__('admin.name')) ?>: a–z, 0–9, dashes.</p>
    </div>
    <div class="form-grid form-grid--3">
      <?php foreach ($locales as $code => $meta): ?>
        <div class="form-row">
          <label for="new_name_<?= e($code) ?>"><?= e($meta['name']) ?></label>
          <input type="text" id="new_name_<?= e($code) ?>" name="name_<?= e($code) ?>" maxlength="120">
        </div>
      <?php endforeach; ?>
    </div>
    <div class="form-grid form-grid--3">
      <?php foreach ($locales as $code => $meta): ?>
        <div class="form-row">
          <label for="new_description_<?= e($code) ?>"><?= e($meta['name']) ?> — <?= e(__('admin.description')) ?></label>
          <input type="text" id="new_description_<?= e($code) ?>" name="description_<?= e($code) ?>" maxlength="500">
        </div>
      <?php endforeach; ?>
    </div>
    <div class="form-grid form-grid--3">
      <div class="form-row">
        <label for="new_sort"><?= e(__('admin.sort_order')) ?></label>
        <input type="number" id="new_sort" name="sort_order" value="0">
      </div>
      <div class="form-row">
        <label class="checkbox">
          <input type="checkbox" name="is_default" value="1">
          <?= e(__('admin.is_default')) ?>
        </label>
      </div>
    </div>
    <button class="btn btn--primary" type="submit"><?= e(__('admin.sections')) ?> +</button>
  </form>
</section>
