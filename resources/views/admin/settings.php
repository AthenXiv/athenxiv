<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Site settings: identity, branding (name/logo editable here), upload limits,
 * review workflow, OpenTimestamps, interface.
 *
 * @var array $values,$locales
 * @var string $calendars
 * @var int $phpLimit
 * @var string|null $logoUrl,$faviconUrl
 */
$v = static fn (string $key, mixed $default = '') => $values[$key] ?? $default;
?>
<header class="page-head">
  <h1><?= e(__('admin.settings')) ?></h1>
  <p class="muted small"><?= e(__('admin.limits_hint', [
      'pdf'     => human_size(\Athenaeum\Services\PaperService::limits()['pdf']),
      'archive' => human_size(\Athenaeum\Services\PaperService::limits()['attachment']),
      'count'   => (int) $v('upload.max_attachments', 5),
      'php'     => human_size($phpLimit),
  ])) ?></p>
</header>

<section class="card" id="branding">
  <h2 class="card__title"><?= e(__('admin.branding_saved')) ?></h2>
  <div class="branding-preview">
    <div>
      <?php if ($logoUrl !== null): ?>
        <img class="brand__logo brand__logo--preview" src="<?= e($logoUrl) ?>" alt="logo">
      <?php else: ?>
        <span class="brand__mark brand__mark--preview" aria-hidden="true">Α</span>
      <?php endif; ?>
      <p class="small muted"><?= e(\Athenaeum\Core\Settings::siteName()) ?></p>
    </div>
    <?php if ($faviconUrl !== null): ?>
      <div>
        <img class="favicon-preview" src="<?= e($faviconUrl) ?>" alt="favicon">
        <p class="small muted">favicon</p>
      </div>
    <?php endif; ?>
  </div>

  <form class="form" method="post" action="<?= e(url('admin.settings.branding')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-grid form-grid--2">
      <div class="form-row">
        <label for="logo">Logo</label>
        <input type="file" id="logo" name="logo" accept="image/*">
        <p class="help"><?= e(__('user.avatar_hint', ['limit' => (int) $v('upload.max_logo_kb', 1024) . ' KB'])) ?></p>
      </div>
      <div class="form-row">
        <label for="favicon">Favicon</label>
        <input type="file" id="favicon" name="favicon" accept="image/*,.ico">
      </div>
    </div>
    <button class="btn btn--primary btn--small" type="submit"><?= e(__('common.save_changes')) ?></button>
  </form>
</section>

<form class="form" method="post" action="<?= e(url('admin.settings.update')) ?>">
  <?= csrf_field() ?>

  <section class="card">
    <h2 class="card__title"><?= e(__('admin.settings_site')) ?></h2>
    <div class="form-grid form-grid--2">
      <div class="form-row">
        <label for="site_name"><?= e(__('admin.name')) ?> (primary)</label>
        <input type="text" id="site_name" name="site.name" maxlength="120" value="<?= e((string) $v('site.name')) ?>">
      </div>
      <div class="form-row">
        <label for="site_name_en"><?= e(__('admin.name')) ?> (English)</label>
        <input type="text" id="site_name_en" name="site.name_en" maxlength="120" value="<?= e((string) $v('site.name_en')) ?>">
      </div>
      <div class="form-row">
        <label for="site_tagline">Tagline (primary)</label>
        <input type="text" id="site_tagline" name="site.tagline" maxlength="300" value="<?= e((string) $v('site.tagline')) ?>">
      </div>
      <div class="form-row">
        <label for="site_tagline_en">Tagline (English)</label>
        <input type="text" id="site_tagline_en" name="site.tagline_en" maxlength="300" value="<?= e((string) $v('site.tagline_en')) ?>">
      </div>
      <div class="form-row">
        <label for="home_hero_title"><?= e(__('admin.hero_title')) ?></label>
        <input type="text" id="home_hero_title" name="home.hero_title" maxlength="200"
               value="<?= e((string) $v('home.hero_title')) ?>"
               placeholder="<?= e(__('home.hero_title')) ?>">
        <p class="help"><?= e(__('admin.hero_hint')) ?></p>
      </div>
      <div class="form-row">
        <label for="home_hero_cta_label"><?= e(__('admin.hero_cta')) ?></label>
        <input type="text" id="home_hero_cta_label" name="home.hero_cta_label" maxlength="80"
               value="<?= e((string) $v('home.hero_cta_label')) ?>"
               placeholder="<?= e(__('home.browse_cta')) ?>">
      </div>
    </div>
    <div class="form-row">
      <label for="home_hero_subtitle"><?= e(__('admin.hero_subtitle')) ?></label>
      <textarea id="home_hero_subtitle" name="home.hero_subtitle" rows="3"
                placeholder="<?= e(__('home.hero_subtitle')) ?>"><?= e((string) $v('home.hero_subtitle')) ?></textarea>
      <p class="help"><?= e(__('admin.hero_hint')) ?></p>
    </div>
      <div class="form-row">
        <label for="site_contact_email"><?= e(__('admin.notify_email')) ?></label>
        <input type="email" id="site_contact_email" name="site.contact_email" maxlength="190"
               value="<?= e((string) $v('site.contact_email')) ?>">
        <p class="help"><?= e(__('upload.error_too_large', ['limit' => human_size(\Athenaeum\Services\PaperService::limits()['pdf']), 'contact' => (string) $v('site.contact_email')])) ?></p>
      </div>
      <div class="form-row">
        <label for="site_icp"><?= e(__('common.footer_note')) ?> / ICP</label>
        <input type="text" id="site_icp" name="site.icp" maxlength="190" value="<?= e((string) $v('site.icp')) ?>">
      </div>
    </div>
    <div class="form-row">
      <label for="site_footer_text">Footer</label>
      <input type="text" id="site_footer_text" name="site.footer_text" maxlength="500" value="<?= e((string) $v('site.footer_text')) ?>">
    </div>
    <div class="form-row">
      <label for="home_notice"><?= e(__('home.notice_title')) ?> (Markdown)</label>
      <textarea id="home_notice" name="home.notice" rows="4"><?= e((string) $v('home.notice')) ?></textarea>
    </div>
    <div class="form-row">
      <label for="site_analytics">Analytics snippet (HTML)</label>
      <textarea id="site_analytics" name="site.analytics" rows="3"><?= e((string) $v('site.analytics')) ?></textarea>
    </div>
  </section>

  <section class="card" id="notice">
    <h2 class="card__title"><?= e(__('settings.notice')) ?></h2>
    <p class="help"><?= e(__('admin.notice_hint')) ?></p>

    <div class="form-row">
      <label for="notice_text"><?= e(__('home.notice_title')) ?> (Markdown)</label>
      <textarea id="notice_text" name="home.notice" rows="4"><?= e((string) $v('home.notice')) ?></textarea>
    </div>

    <?php
    $noticeColor = (string) $v('notice.color', 'info');
    $presets = [
        'info'    => 'settings.notice_color_info',
        'success' => 'settings.notice_color_success',
        'warning' => 'settings.notice_color_warning',
        'danger'  => 'settings.notice_color_danger',
        'neutral' => 'settings.notice_color_neutral',
    ];
    $isCustom = !isset($presets[$noticeColor]);
    ?>
    <div class="form-grid form-grid--2">
      <div class="form-row">
        <label for="notice_color"><?= e(__('settings.notice_color')) ?></label>
        <select id="notice_color" name="notice_color" data-notice-color>
          <?php foreach ($presets as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $noticeColor === $value ? 'selected' : '' ?>>
              <?= e(__($label)) ?>
            </option>
          <?php endforeach; ?>
          <option value="custom" <?= $isCustom ? 'selected' : '' ?>><?= e(__('settings.notice_color_custom')) ?></option>
        </select>
      </div>
      <div class="form-row" data-notice-custom <?= $isCustom ? '' : 'hidden' ?>>
        <label for="notice_color_custom">#RRGGBB</label>
        <input type="color" id="notice_color_custom" data-notice-color-input
               value="<?= e($isCustom && preg_match('/^#[0-9a-fA-F]{6}$/', $noticeColor) ? $noticeColor : '#1d4ed8') ?>">
        <p class="help">
          <?= e(__('settings.notice_color')) ?>:
          <code data-notice-color-value><?= e($isCustom ? $noticeColor : '') ?></code>
        </p>
      </div>
    </div>

    <div class="form-row">
      <label class="checkbox">
        <input type="checkbox" name="notice_dismissible" value="1" <?= !empty($v('notice.dismissible')) ? 'checked' : '' ?>>
        <?= e(__('settings.notice_dismissible')) ?>
      </label>
    </div>

    <div class="notice notice--<?= e(isset($presets[$noticeColor]) ? $noticeColor : 'custom') ?>">
      <div class="notice__inner">
        <div class="notice__body markdown"><?= \Athenaeum\Core\Markdown::render((string) $v('home.notice')) ?: '<p class="muted small">…</p>' ?></div>
        <span class="notice__close" aria-hidden="true">×</span>
      </div>
    </div>
  </section>

  <section class="card" id="versions">
    <h2 class="card__title"><?= e(__('settings.versions')) ?></h2>
    <div class="form-row">
      <label class="checkbox">
        <input type="checkbox" name="versions_enabled" value="1" <?= !empty($v('versions.enabled')) ? 'checked' : '' ?>>
        <?= e(__('settings.versions_enabled')) ?>
      </label>
    </div>
    <div class="form-row">
      <label class="checkbox">
        <input type="checkbox" name="versions_keep_files" value="1" <?= !empty($v('versions.keep_files')) ? 'checked' : '' ?>>
        <?= e(__('settings.versions_keep_files')) ?>
      </label>
      <p class="help"><?= e(__('version.keep_hint')) ?></p>
    </div>
    <div class="form-row">
      <label for="versions_max"><?= e(__('paper.version_count', ['count' => ''])) ?></label>
      <input type="number" id="versions_max" name="versions.max" min="1" max="500" value="<?= (int) $v('versions.max', 30) ?>">
    </div>
  </section>

  <section class="card">
    <h2 class="card__title"><?= e(__('admin.settings_upload')) ?></h2>
    <div class="form-grid form-grid--3">
      <div class="form-row">
        <label for="max_pdf">PDF (MB)</label>
        <input type="number" id="max_pdf" name="upload.max_pdf_mb" min="1" max="2048" value="<?= (int) $v('upload.max_pdf_mb', 10) ?>">
      </div>
      <div class="form-row">
        <label for="max_attachment"><?= e(__('paper.attachments')) ?> (MB)</label>
        <input type="number" id="max_attachment" name="upload.max_attachment_mb" min="1" max="4096" value="<?= (int) $v('upload.max_attachment_mb', 20) ?>">
      </div>
      <div class="form-row">
        <label for="max_attachments"><?= e(__('paper.attachment_count', ['count' => ''])) ?></label>
        <input type="number" id="max_attachments" name="upload.max_attachments" min="1" max="20" value="<?= (int) $v('upload.max_attachments', 5) ?>">
      </div>
      <div class="form-row">
        <label for="max_avatar"><?= e(__('user.avatar')) ?> (KB)</label>
        <input type="number" id="max_avatar" name="upload.max_avatar_kb" min="32" max="8192" value="<?= (int) $v('upload.max_avatar_kb', 512) ?>">
      </div>
      <div class="form-row">
        <label for="max_logo">Logo (KB)</label>
        <input type="number" id="max_logo" name="upload.max_logo_kb" min="32" max="8192" value="<?= (int) $v('upload.max_logo_kb', 1024) ?>">
      </div>
      <div class="form-row">
        <label for="allowed_ext"><?= e(__('upload.allowed_types')) ?></label>
        <input type="text" id="allowed_ext" name="upload.allowed_attachment_ext" maxlength="190"
               value="<?= e((string) $v('upload.allowed_attachment_ext')) ?>">
      </div>
    </div>
    <div class="form-row">
      <label for="pdf_message"><?= e(__('paper.pdf_limit')) ?> — <?= e(__('upload.pdf_limit_hint', ['limit' => human_size(\Athenaeum\Services\PaperService::limits()['pdf']), 'contact' => (string) $v('site.contact_email')])) ?></label>
      <textarea id="pdf_message" name="upload.pdf_message" rows="2"><?= e((string) $v('upload.pdf_message')) ?></textarea>
    </div>
    <p class="help">
      <?= e(__('upload.php_limit_warning', ['limit' => human_size($phpLimit)])) ?>
      <br>upload_max_filesize = <?= e((string) ini_get('upload_max_filesize')) ?>,
      post_max_size = <?= e((string) ini_get('post_max_size')) ?>
    </p>
  </section>

  <section class="card">
    <h2 class="card__title"><?= e(__('admin.settings_moderation')) ?></h2>
    <div class="form-row">
      <label class="checkbox">
        <input type="checkbox" name="registration.open" value="1" <?= !empty($v('registration.open')) ? 'checked' : '' ?>>
        <?= e(__('admin.registration_open')) ?>
      </label>
    </div>
    <div class="form-row">
      <label class="checkbox">
        <input type="checkbox" name="moderation.auto_approve" value="1" <?= !empty($v('moderation.auto_approve')) ? 'checked' : '' ?>>
        <?= e(__('admin.auto_approve')) ?>
      </label>
    </div>
    <div class="form-row">
      <label for="moderation_notify"><?= e(__('admin.notify_email')) ?></label>
      <input type="email" id="moderation_notify" name="moderation.notify_email" maxlength="190"
             value="<?= e((string) $v('moderation.notify_email')) ?>">
    </div>
  </section>

  <section class="card">
    <h2 class="card__title"><?= e(__('admin.settings_ots')) ?></h2>
    <div class="form-row">
      <label class="checkbox">
        <input type="checkbox" name="ots.enabled" value="1" <?= !empty($v('ots.enabled')) ? 'checked' : '' ?>>
        OpenTimestamps <?= e(__('admin.enabled')) ?>
      </label>
    </div>
    <div class="form-row">
      <label class="checkbox">
        <input type="checkbox" name="ots.auto_upgrade" value="1" <?= !empty($v('ots.auto_upgrade')) ? 'checked' : '' ?>>
        Automatic upgrade when a paper page is viewed
      </label>
    </div>
    <div class="form-row">
      <label class="checkbox">
        <input type="checkbox" name="ots.require_for_publish" value="1" <?= !empty($v('ots.require_for_publish')) ? 'checked' : '' ?>>
        <?= e(__('admin.require_ots')) ?>
      </label>
    </div>
    <div class="form-row">
      <label for="ots_calendars"><?= e(__('ots.calendars')) ?></label>
      <textarea id="ots_calendars" name="ots.calendars" rows="3"><?= e($calendars) ?></textarea>
      <p class="help"><?= e(__('page.timestamping_privacy')) ?></p>
    </div>
    <div class="form-row">
      <label for="ots_verify_url"><?= e(__('ots.verify_at')) ?></label>
      <input type="url" id="ots_verify_url" name="ots.verify_url" maxlength="300" value="<?= e((string) $v('ots.verify_url')) ?>">
      <p class="help"><?= e(__('ots.verify_tooltip')) ?></p>
    </div>
  </section>

  <section class="card">
    <h2 class="card__title"><?= e(__('admin.settings_ui')) ?></h2>
    <div class="form-grid form-grid--3">
      <div class="form-row">
        <label for="default_locale"><?= e(__('settings.locale')) ?></label>
        <select id="default_locale" name="ui.default_locale">
          <?php foreach ($locales as $code => $meta): ?>
            <option value="<?= e($code) ?>" <?= (string) $v('ui.default_locale') === $code ? 'selected' : '' ?>>
              <?= e($meta['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label for="locales"><?= e(__('common.language')) ?> (enabled)</label>
        <input type="text" id="locales" name="ui.locales" maxlength="120" value="<?= e((string) $v('ui.locales')) ?>">
        <p class="help">en,zh-CN,ja,ko,fr,de</p>
      </div>
      <div class="form-row">
        <label for="per_page"><?= e(__('paper.results_count', ['count' => ''])) ?></label>
        <input type="number" id="per_page" name="ui.papers_per_page" min="4" max="50" value="<?= (int) $v('ui.papers_per_page', 12) ?>">
      </div>
    </div>
    <div class="form-row">
      <label class="checkbox">
        <input type="checkbox" name="ui.allow_profile_markdown" value="1" <?= !empty($v('ui.allow_profile_markdown')) ? 'checked' : '' ?>>
        <?= e(__('user.homepage_md')) ?>
      </label>
    </div>
    <div class="form-row">
      <label class="checkbox">
        <input type="checkbox" name="ui.show_view_counts" value="1" <?= !empty($v('ui.show_view_counts')) ? 'checked' : '' ?>>
        <?= e(__('paper.views')) ?> / <?= e(__('paper.downloads')) ?>
      </label>
    </div>
  </section>

  <div class="form-actions">
    <button class="btn btn--primary" type="submit"><?= e(__('common.save_changes')) ?></button>
  </div>
</form>
