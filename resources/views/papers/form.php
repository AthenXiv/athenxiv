<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Shared submission form: /submit, /paper/{uid}/edit and the admin
 * "upload on behalf of an author" screen.
 *
 * @var array|null $paper
 * @var array $authors,$links,$attachments,$sections,$categories,$languages,$linkKinds,$limits
 * @var string $action
 * @var bool $isAdminForm,$proxy
 */

use Athenaeum\Core\Settings;
use Athenaeum\Models\Category;
use Athenaeum\Models\Section;

$isEdit = $paper !== null;
$field = static function (string $name, mixed $default = '') use ($paper) {
    $old = old($name, null);
    if ($old !== null && $old !== '') {
        return $old;
    }
    if ($paper !== null && isset($paper[$name])) {
        return $paper[$name];
    }
    return $default;
};
$selection = static function (string $name, $value) use ($paper) {
    $old = old($name, null);
    return (string) ($old !== null && $old !== '' ? $old : ($paper[$name] ?? $value));
};

// Pre-render a few blank rows so the form still works without JavaScript.
$authorRows = $authors;
$authorRows = array_merge($authorRows, array_fill(0, max(0, 3 - count($authorRows)), [
    'name' => '', 'affiliation' => '', 'email' => '', 'orcid' => '', 'is_corresponding' => 0,
]));
$linkRows = $links;
$linkRows = array_merge($linkRows, array_fill(0, max(0, 2 - count($linkRows)), [
    'label' => '', 'url' => '', 'kind' => 'other',
]));
?>
<div class="<?= $isAdminForm ? '' : 'container' ?>">
  <header class="page-head">
    <h1><?= e($heading) ?></h1>
    <?php if (!$isEdit): ?>
      <p class="page-head__note"><?= e(__('page.guidelines_p1')) ?></p>
    <?php endif; ?>
  </header>

  <?php if (($paper['status'] ?? '') === \Athenaeum\Models\Paper::STATUS_APPROVED): ?>
    <p class="alert alert--info"><?= e(__('paper.edit_returns_to_review')) ?></p>
  <?php endif; ?>

  <?php
    // Always show the *site* limits. An administrator performing a proxy upload
    // may waive them, and `$limits` then carries PHP's ceiling instead — which
    // used to be printed as if it were the site limit ("PDF 1,000 MB").
    $siteLimits = \Athenaeum\Services\PaperService::limits();
  ?>
  <?php if (($limits['exempt'] ?? false)): ?>
    <div class="alert alert--info">
      <?= e(__('admin.limits_hint', [
          'pdf'     => human_size($siteLimits['pdf']),
          'archive' => human_size($siteLimits['attachment']),
          'count'   => $siteLimits['attachments'],
          'php'     => human_size((int) $phpLimit),
      ])) ?>
      <br><?= e(__('admin.exempt_upload')) ?> — <?= e(__('upload.exempt_allowed')) ?>
    </div>
  <?php elseif ($phpLimit > 0 && $phpLimit < $limits['pdf']): ?>
    <div class="alert alert--info"><?= e(__('upload.php_limit_warning', ['limit' => human_size((int) $phpLimit)])) ?></div>
  <?php endif; ?>

  <form class="form" method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php if ($proxy): ?>
      <input type="hidden" name="proxy_upload" value="1">
    <?php endif; ?>
    <?php if ($isAdminForm && $isEdit): ?>
      <input type="hidden" name="size_exempt_note" value="">
    <?php endif; ?>

    <fieldset class="fieldset">
      <legend><?= e(__('paper.title')) ?></legend>

      <?php if ($proxy && !$isEdit): ?>
        <div class="form-row">
          <label for="uploader"><?= e(__('admin.paper_owner')) ?> <span class="req">*</span></label>
          <input type="text" id="uploader" name="uploader" required
                 value="<?= e((string) old('uploader', $target['uid'] ?? '')) ?>"
                 placeholder="UXXXXXXXX / name@example.org">
          <p class="help"><?= e(__('admin.user_not_found')) ?></p>
          <?php \Athenaeum\Core\View::partial('partials/field_error', ['field' => 'uploader']); ?>
        </div>
      <?php endif; ?>

      <div class="form-row">
        <label for="title"><?= e(__('paper.title')) ?> <span class="req">*</span></label>
        <input type="text" id="title" name="title" required maxlength="300"
               value="<?= e((string) $field('title')) ?>">
        <?php \Athenaeum\Core\View::partial('partials/field_error', ['field' => 'title']); ?>
      </div>

      <div class="form-row">
        <label for="subtitle"><?= e(__('paper.subtitle')) ?> <span class="muted">(<?= e(__('common.optional')) ?>)</span></label>
        <input type="text" id="subtitle" name="subtitle" maxlength="300"
               value="<?= e((string) $field('subtitle')) ?>">
      </div>

      <div class="form-row">
        <label for="abstract"><?= e(__('paper.abstract')) ?> <span class="req">*</span></label>
        <textarea id="abstract" name="abstract" rows="10" required minlength="40"><?= e((string) $field('abstract')) ?></textarea>
        <p class="help"><?= e(__('paper.abstract_hint')) ?></p>
        <?php \Athenaeum\Core\View::partial('partials/field_error', ['field' => 'abstract']); ?>
      </div>

      <div class="form-grid form-grid--3">
        <div class="form-row">
          <label for="language"><?= e(__('paper.language')) ?> <span class="req">*</span></label>
          <?php
          // The stored value may be a custom code (x-…) from a previous save.
          $storedLanguage = (string) $selection('language', 'en');
          $languageIsCustom = \Athenaeum\Core\Languages::isCustom($storedLanguage);
          if ($languageIsCustom && (string) $selection('language_custom', '') === '') {
              $languageIsCustom = false;
              $storedLanguage = $languageOther;
          }
          ?>
          <div class="picker" data-picker>
            <input type="search" class="picker__search" data-picker-search autocomplete="off"
                   placeholder="<?= e(__('paper.language_search')) ?>"
                   aria-label="<?= e(__('paper.language_search')) ?>"
                   aria-controls="language">
            <div class="picker__list" data-picker-list hidden></div>
          </div>
          <select id="language" name="language" required data-language-select data-picker-select
                  data-other-value="<?= e($languageOther) ?>">
            <?php foreach ($languages as $code => $nativeName): ?>
              <option value="<?= e($code) ?>"
                      data-search="<?= e(\Athenaeum\Core\Languages::searchTerms((string) $code)) ?>"
                      <?= $storedLanguage === $code ? 'selected' : '' ?>>
                <?= e($nativeName) ?> · <?= e(\Athenaeum\Core\Languages::englishName((string) $code)) ?>
              </option>
            <?php endforeach; ?>
            <option value="<?= e($languageOther) ?>" data-search="other not listed"
                    <?= $languageIsCustom ? 'selected' : '' ?>>
              <?= e(__('paper.language_other')) ?>
            </option>
          </select>
          <p class="help"><?= e(__('paper.language_search')) ?></p>
        </div>

        <div class="form-row" data-language-custom <?= $languageIsCustom ? '' : 'hidden' ?>>
          <label for="language_custom"><?= e(__('paper.language_custom')) ?> <span class="req">*</span></label>
          <input type="text" id="language_custom" name="language_custom" maxlength="60"
                 value="<?= e((string) $field('language_custom')) ?>"
                 placeholder="<?= e(__('paper.language_custom_placeholder')) ?>">
          <p class="help"><?= e(__('paper.language_custom_hint')) ?></p>
          <?php \Athenaeum\Core\View::partial('partials/field_error', ['field' => 'language_custom']); ?>
        </div>

        <div class="form-row">
          <label for="section_id"><?= e(__('paper.section')) ?> <span class="muted">(<?= e(__('common.optional')) ?>)</span></label>
          <select id="section_id" name="section_id">
            <option value=""><?= e(__('common.select_placeholder')) ?></option>
            <?php foreach ($sections as $section): ?>
              <option value="<?= (int) $section['id'] ?>"
                <?= (string) $selection('section_id', '') === (string) $section['id'] ? 'selected' : '' ?>>
                <?= e(Section::name($section)) ?><?= (int) $section['is_public'] === 1 ? '' : ' (hidden)' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="help"><?= e(__('paper.section_hint')) ?></p>
        </div>
      </div>

      <div class="form-row">
        <label for="category_id"><?= e(__('paper.category')) ?> <span class="muted">(<?= e(__('common.optional')) ?>)</span></label>
        <?php
        $storedCategory = (string) $selection('category_id', '');
        $storedAreaOther = (string) $field('category_other');
        if ($storedCategory === '' && $storedAreaOther !== '') {
            // An existing paper whose author picked "Other": keep that choice.
            $storedCategory = \Athenaeum\Controllers\PaperController::AREA_OTHER;
        }
        ?>
        <div class="picker" data-picker>
          <input type="search" class="picker__search" data-picker-search autocomplete="off"
                 placeholder="<?= e(__('category.search_placeholder')) ?>"
                 aria-label="<?= e(__('category.search_placeholder')) ?>"
                 aria-controls="category_id">
          <div class="picker__list" data-picker-list hidden></div>
        </div>
        <select id="category_id" name="category_id" data-picker-select data-area-select
                data-other-value="<?= e(\Athenaeum\Controllers\PaperController::AREA_OTHER) ?>">
          <option value=""><?= e(__('common.select_placeholder')) ?></option>
          <option value="<?= e(\Athenaeum\Controllers\PaperController::AREA_OTHER) ?>"
                  data-search="other not listed 其它 未被列出"
                  <?= $storedCategory === \Athenaeum\Controllers\PaperController::AREA_OTHER ? 'selected' : '' ?>>
            <?= e(__('category.other')) ?>
          </option>
          <?php
          // flat(false) is ordered depth-first, so the ancestor stack can be
          // rebuilt in one pass — no per-row queries on a 600-node taxonomy.
          $ancestors = [];
          foreach ($categories as $category):
              $depth = (int) ($category['depth'] ?? 0);
              $areaName = \Athenaeum\Models\Category::name($category);
              $ancestors = array_slice($ancestors, 0, $depth);
              $areaPath = implode(' ', array_merge($ancestors, [$areaName]));
              $ancestors[$depth] = $areaName;
              ?>
            <option value="<?= (int) $category['id'] ?>"
                    data-search="<?= e(mb_strtolower($areaPath . ' ' . $category['slug'])) ?>"
              <?= $storedCategory === (string) $category['id'] ? 'selected' : '' ?>>
              <?= e(str_repeat('— ', $depth) . $areaName) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="help"><?= e(__('category.other_hint')) ?></p>
      </div>

      <div class="form-row" data-area-other <?= $storedCategory === \Athenaeum\Controllers\PaperController::AREA_OTHER ? '' : 'hidden' ?>>
        <label for="category_other"><?= e(__('category.other_label')) ?> <span class="req">*</span></label>
        <input type="text" id="category_other" name="category_other" maxlength="190"
               value="<?= e($storedAreaOther) ?>"
               placeholder="<?= e(__('category.other_placeholder')) ?>">
        <?php \Athenaeum\Core\View::partial('partials/field_error', ['field' => 'category_other']); ?>
      </div>

      <div class="form-grid form-grid--3">
        <div class="form-row">
          <label for="keywords"><?= e(__('paper.keywords')) ?> <span class="muted">(<?= e(__('common.optional')) ?>)</span></label>
          <input type="text" id="keywords" name="keywords" maxlength="500" value="<?= e((string) $field('keywords')) ?>">
          <p class="help"><?= e(__('paper.keywords_hint')) ?></p>
        </div>
        <div class="form-row">
          <label for="doi"><?= e(__('paper.doi')) ?> <span class="muted">(<?= e(__('common.optional')) ?>)</span></label>
          <input type="text" id="doi" name="doi" maxlength="190" value="<?= e((string) $field('doi')) ?>">
        </div>
        <div class="form-row">
          <label for="license"><?= e(__('paper.license')) ?> <span class="muted">(<?= e(__('common.optional')) ?>)</span></label>
          <?php $licenseOptions = [
              '' => __('common.select_placeholder'),
              'CC BY 4.0' => __('paper.license_cc_by'),
              'CC BY-SA 4.0' => __('paper.license_cc_by_sa'),
              'CC BY-NC 4.0' => __('paper.license_cc_by_nc'),
              'CC0 1.0' => __('paper.license_cc0'),
              'All rights reserved' => __('paper.license_all_rights'),
          ]; ?>
          <select id="license" name="license">
            <?php foreach ($licenseOptions as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= (string) $field('license') === (string) $value ? 'selected' : '' ?>>
                <?= e($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <fieldset class="radios">
          <legend><?= e(__('paper.visibility')) ?> <span class="muted">(<?= e(__('common.optional')) ?>)</span></legend>
          <label>
            <input type="radio" name="visibility" value="public"
              <?= $selection('visibility', 'public') === 'public' ? 'checked' : '' ?>>
            <?= e(__('paper.visibility_public')) ?>
          </label>
          <label>
            <input type="radio" name="visibility" value="unlisted"
              <?= $selection('visibility', 'public') === 'unlisted' ? 'checked' : '' ?>>
            <?= e(__('paper.visibility_unlisted')) ?>
          </label>
        </fieldset>
      </div>
    </fieldset>

    <fieldset class="fieldset">
      <legend><?= e(__('paper.authors')) ?> <span class="muted">(<?= e(__('common.optional')) ?>)</span></legend>
      <p class="help"><?= e(__('paper.authors_hint')) ?></p>
      <div data-repeat="authors">
                <?php if (!empty($siteUsers)): ?>
          <datalist id="site-users">
            <?php foreach ($siteUsers as $siteUser): ?>
              <option value="<?= e((string) $siteUser['uid']) ?>"><?= e((string) $siteUser['label']) ?></option>
            <?php endforeach; ?>
          </datalist>
        <?php endif; ?>
<?php foreach ($authorRows as $index => $author): ?>
          <div class="repeat-row" data-repeat-row>
            <div class="form-grid form-grid--4">
              <div class="form-row">
                <label><?= e(__('paper.author_name')) ?> <span class="req">*</span></label>
                <input type="text" name="author_name[]" maxlength="190" value="<?= e((string) ($author['name'] ?? '')) ?>">
              </div>
              <div class="form-row">
                <label><?= e(__('paper.author_affiliation')) ?> <span class="muted">(<?= e(__('common.optional')) ?>)</span></label>
                <input type="text" name="author_affiliation[]" maxlength="190" value="<?= e((string) ($author['affiliation'] ?? '')) ?>">
              </div>
              <div class="form-row">
                <label><?= e(__('paper.author_email')) ?> <span class="muted">(<?= e(__('common.optional')) ?>)</span></label>
                <input type="email" name="author_email[]" maxlength="190" value="<?= e((string) ($author['email'] ?? '')) ?>">
              </div>
              <div class="form-row">
                <label><?= e(__('paper.author_orcid')) ?> <span class="muted">(<?= e(__('common.optional')) ?>)</span></label>
                <input type="text" name="author_orcid[]" maxlength="19" placeholder="0000-0000-0000-0000"
                       value="<?= e((string) ($author['orcid'] ?? '')) ?>">
              </div>
            </div>
            <div class="form-row">
              <label for="author_user_<?= (int) $index ?>"><?= e(__('paper.author_user')) ?></label>
              <?php
              // Optional: attach this author to an AthenXiv account so their
              // avatar and profile link appear on the paper page.
              $linkedUid = '';
              foreach (($siteUsers ?? []) as $candidate) {
                  if ((int) ($candidate['id'] ?? 0) === (int) ($author['user_id'] ?? 0)) {
                      $linkedUid = (string) $candidate['uid'];
                      break;
                  }
              }
              ?>
              <input type="text" id="author_user_<?= (int) $index ?>" name="author_user[]"
                     list="site-users" maxlength="40" value="<?= e($linkedUid) ?>"
                     placeholder="<?= e(__('paper.author_user_placeholder')) ?>">
              <p class="help"><?= e(__('paper.author_user_hint')) ?></p>
            </div>
            <div class="repeat-row__foot">
              <label class="checkbox">
                <input type="checkbox" name="author_corresponding[<?= (int) $index ?>]" value="1"
                  <?= !empty($author['is_corresponding']) ? 'checked' : '' ?>>
                <?= e(__('paper.author_corresponding')) ?>
              </label>
              <button type="button" class="btn btn--ghost btn--small" data-repeat-remove><?= e(__('paper.remove_author')) ?></button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <p><button type="button" class="btn btn--ghost btn--small" data-repeat-add="authors">+ <?= e(__('paper.add_author')) ?></button></p>
    </fieldset>

    <fieldset class="fieldset">
      <legend><?= e(__('paper.links')) ?> <span class="muted">(<?= e(__('common.optional')) ?>)</span></legend>
      <p class="help"><?= e(__('paper.links_hint')) ?></p>
      <div data-repeat="links">
        <?php foreach ($linkRows as $link): ?>
          <div class="repeat-row" data-repeat-row>
            <div class="form-grid form-grid--3">
              <div class="form-row">
                <label><?= e(__('paper.link_kind')) ?></label>
                <select name="link_kind[]">
                  <?php foreach ($linkKinds as $kind): ?>
                    <option value="<?= e($kind) ?>" <?= (string) ($link['kind'] ?? 'other') === $kind ? 'selected' : '' ?>>
                      <?= e(\Athenaeum\Models\PaperLink::kindLabel($kind)) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-row">
                <label><?= e(__('paper.link_label')) ?></label>
                <input type="text" name="link_label[]" maxlength="120" value="<?= e((string) ($link['label'] ?? '')) ?>">
              </div>
              <div class="form-row">
                <label><?= e(__('paper.link_url')) ?></label>
                <input type="url" name="link_url[]" maxlength="500" placeholder="https://"
                       value="<?= e((string) ($link['url'] ?? '')) ?>">
              </div>
            </div>
            <div class="repeat-row__foot">
              <button type="button" class="btn btn--ghost btn--small" data-repeat-remove><?= e(__('paper.remove_author')) ?></button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <p><button type="button" class="btn btn--ghost btn--small" data-repeat-add="links">+ <?= e(__('paper.add_link')) ?></button></p>
    </fieldset>

    <fieldset class="fieldset">
      <legend><?= e(__('paper.pdf_file')) ?></legend>

      <?php if ($isEdit && !empty($paper['pdf_name'])): ?>
        <p class="current-file">
          <?= e(__('paper.pdf_current')) ?>:
          <a href="<?= e(url('paper.file', ['uid' => $paper['uid']])) ?>" target="_blank" rel="noopener">
            <?= e((string) $paper['pdf_name']) ?>
          </a>
          <span class="muted small">(<?= e(human_size((int) $paper['pdf_size'])) ?>)</span>
          <?php if (!empty($paper['pdf_sha256'])): ?>
            <br><span class="muted small">SHA-256: <code class="hash"><?= e((string) $paper['pdf_sha256']) ?></code></span>
          <?php endif; ?>
        </p>
      <?php endif; ?>

      <div class="form-row">
        <label for="pdf">
          <?= e($isEdit ? __('paper.version_upload') : __('paper.pdf_file')) ?>
          <?php if (!$isEdit): ?><span class="req">*</span><?php else: ?>
            <span class="muted">(<?= e(__('common.optional')) ?>)</span>
          <?php endif; ?>
        </label>
        <input type="file" id="pdf" name="pdf" accept="application/pdf,.pdf" <?= $isEdit ? '' : 'required' ?>>
        <p class="help"><?= e($maxPdfMessage) ?></p>
        <?php if ($isEdit): ?>
          <p class="help"><?= e(__('paper.version_upload_hint')) ?></p>
        <?php endif; ?>
        <?php \Athenaeum\Core\View::partial('partials/field_error', ['field' => 'pdf']); ?>
      </div>

      <?php if ($isEdit): ?>
        <div class="form-row">
          <label for="version_note"><?= e(__('paper.version_note')) ?> <span class="muted">(<?= e(__('common.optional')) ?>)</span></label>
          <input type="text" id="version_note" name="version_note" maxlength="500"
                 value="<?= e((string) old('version_note')) ?>">
          <p class="help"><?= e(__('paper.version_note_hint')) ?></p>
        </div>
        <p class="small">
          <a href="<?= e(url('paper.show', ['uid' => $paper['uid']]) . '#versions') ?>">
            <?= e(__('paper.version_history')) ?> →
          </a>
          <span class="muted">· <?= e(__('version.keep_hint')) ?></span>
        </p>
      <?php endif; ?>

      <?php if (!empty($contactEmail)): ?>
        <p class="help"><?= e(__('upload.error_pdf_required')) ?>
          <?= e(__('page.guidelines_limits', [
              'pdf' => human_size($limits['pdf']),
              'contact' => $contactEmail,
          ])) ?>
        </p>
      <?php endif; ?>
    </fieldset>

    <fieldset class="fieldset">
      <legend><?= e(__('paper.attachments')) ?> <span class="muted">(<?= e(__('common.optional')) ?>)</span></legend>
      <p class="help">
        <?= e(__('paper.attachments_hint', [
            'extensions' => implode(', ', $allowedExt),
            'limit'      => human_size($limits['attachment']),
            'count'      => $limits['attachments'],
        ])) ?>
      </p>

      <?php if ($attachments !== []): ?>
        <table class="table table--compact">
          <tbody>
            <?php foreach ($attachments as $attachment): ?>
              <tr>
                <td><?= e($attachment['original_name']) ?></td>
                <td><?= e(human_size((int) $attachment['size'])) ?></td>
                <td class="right">
                  <a class="btn btn--ghost btn--small"
                     href="<?= e(url('paper.attachment', ['uid' => $paper['uid'], 'attachment' => $attachment['id']])) ?>" download>
                    <?= e(__('common.download')) ?>
                  </a>
                  <?php if ($isOwner ?? true): ?>
                    <form class="inline" method="post"
                          action="<?= e(url('paper.attachment.delete', ['uid' => $paper['uid'], 'attachment' => $attachment['id']])) ?>"
                          data-confirm="<?= e(__('common.confirm')) ?>">
                      <?= csrf_field() ?>
                      <button class="btn btn--danger btn--small" type="submit"><?= e(__('common.delete')) ?></button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <div class="form-row">
        <label for="attachments"><?= e(__('paper.attachments')) ?></label>
        <input type="file" id="attachments" name="attachments[]" multiple
               accept="<?= e('.' . implode(',.', $allowedExt)) ?>">
      </div>
    </fieldset>

    <?php if ($isAdminForm): ?>
      <fieldset class="fieldset">
        <legend><?= e(__('admin.exempt_upload')) ?></legend>
        <div class="form-row">
          <label class="checkbox">
            <input type="checkbox" name="size_exempt" value="1">
            <?= e(__('upload.exempt_allowed')) ?>
          </label>
        </div>
        <div class="form-row">
          <label for="size_exempt_note"><?= e(__('upload.exempt_note')) ?></label>
          <input type="text" id="size_exempt_note" name="size_exempt_note" maxlength="500"
                 value="<?= e((string) $field('size_exempt_note')) ?>">
        </div>
      </fieldset>
    <?php endif; ?>

    <div class="form-actions">
      <button class="btn btn--primary" type="submit">
        <?= e($isEdit ? __('common.save_changes') : __('paper.submit_cta')) ?>
      </button>
      <?php if (!$isEdit): ?>
        <button class="btn btn--ghost" type="submit" name="as_draft" value="1"><?= e(__('paper.save_draft')) ?></button>
      <?php else: ?>
        <a class="btn btn--ghost" href="<?= e(url('paper.show', ['uid' => $paper['uid']])) ?>"><?= e(__('common.cancel')) ?></a>
      <?php endif; ?>
    </div>
  </form>
</div>
