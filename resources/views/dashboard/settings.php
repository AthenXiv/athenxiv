<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Account settings: profile + Markdown page, linked accounts, avatar,
 * password, interface language.
 *
 * @var array $user,$links,$platforms,$locales
 * @var string|null $avatar
 * @var int $maxAvatar,$minPassword
 */
?>
<div class="container">
  <header class="page-head">
    <h1><?= e(__('settings.title')) ?></h1>
    <p class="muted"><?= e(__('user.uid')) ?>: <code><?= e($user['uid']) ?></code> ·
      <a href="<?= e(url('user.show', ['uid' => $user['uid']])) ?>"><?= e(__('user.public_profile')) ?></a></p>
  </header>

  <nav class="tab-nav">
    <a href="#profile"><?= e(__('settings.profile')) ?></a>
    <a href="#avatar"><?= e(__('settings.avatar')) ?></a>
    <a href="#links"><?= e(__('settings.links')) ?></a>
    <a href="#password"><?= e(__('settings.password')) ?></a>
    <a href="#preferences"><?= e(__('settings.preferences')) ?></a>
  </nav>

  <section class="card" id="profile">
    <h2 class="card__title"><?= e(__('settings.profile')) ?></h2>
    <form class="form" method="post" action="<?= e(url('settings.profile')) ?>">
      <?= csrf_field() ?>
      <div class="form-grid form-grid--2">
        <div class="form-row">
          <label for="nickname"><?= e(__('user.nickname')) ?> <span class="req">*</span></label>
          <input type="text" id="nickname" name="nickname" required maxlength="80"
                 value="<?= e((string) old('nickname', $user['nickname'])) ?>">
          <p class="help"><?= e(__('auth.nickname_hint')) ?></p>
          <?php \Athenaeum\Core\View::partial('partials/field_error', ['field' => 'nickname']); ?>
        </div>
        <div class="form-row">
          <label for="display_name"><?= e(__('user.display_name')) ?></label>
          <input type="text" id="display_name" name="display_name" maxlength="120"
                 value="<?= e((string) old('display_name', $user['display_name'])) ?>">
        </div>
      </div>

      <div class="form-row">
        <label for="affiliation"><?= e(__('user.affiliation')) ?></label>
        <input type="text" id="affiliation" name="affiliation" maxlength="190"
               value="<?= e((string) old('affiliation', $user['affiliation'])) ?>">
      </div>

      <div class="form-row">
        <label for="bio"><?= e(__('user.bio')) ?></label>
        <textarea id="bio" name="bio" rows="3" maxlength="500"><?= e((string) old('bio', $user['bio'])) ?></textarea>
      </div>

      <?php if (\Athenaeum\Core\Settings::bool('ui.allow_profile_markdown')): ?>
        <div class="form-row">
          <label for="homepage_md"><?= e(__('user.homepage_md')) ?></label>
          <textarea id="homepage_md" name="homepage_md" rows="10" maxlength="20000"
                    data-markdown-source><?= e((string) old('homepage_md', $user['homepage_md'])) ?></textarea>
          <p class="help"><?= e(__('user.homepage_md_hint')) ?></p>
          <div class="md-preview" data-markdown-preview hidden>
            <p class="muted small"><?= e(__('settings.markdown_preview')) ?></p>
            <div class="markdown" data-markdown-target></div>
          </div>
        </div>
      <?php endif; ?>

      <button class="btn btn--primary" type="submit"><?= e(__('common.save_changes')) ?></button>
    </form>
  </section>

  <section class="card" id="avatar">
    <h2 class="card__title"><?= e(__('settings.avatar')) ?></h2>
    <div class="avatar-editor">
      <?php if ($avatar !== null): ?>
        <img class="avatar avatar--large" src="<?= e($avatar) ?>" alt="">
      <?php else: ?>
        <span class="avatar avatar--large avatar--initial"><?= e(mb_substr((string) $user['nickname'], 0, 1)) ?></span>
      <?php endif; ?>
      <form class="form" method="post" action="<?= e(url('settings.avatar')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-row">
          <label for="avatar"><?= e(__('user.change_avatar')) ?></label>
          <input type="file" id="avatar" name="avatar" accept="image/*" required>
          <p class="help"><?= e(__('user.avatar_hint', ['limit' => $maxAvatar . ' KB'])) ?></p>
        </div>
        <button class="btn btn--primary btn--small" type="submit"><?= e(__('common.save')) ?></button>
      </form>
    </div>
  </section>

  <section class="card" id="links">
    <h2 class="card__title"><?= e(__('settings.links')) ?></h2>
    <form class="form" method="post" action="<?= e(url('settings.links')) ?>" data-link-form>
      <?= csrf_field() ?>
      <p class="help"><?= e(__('settings.links_picker_hint')) ?></p>

      <?php
      // Existing values (and anything typed in a previous attempt) are rendered
      // as rows. The picker below appends new rows; without JavaScript the
      // rows remain ordinary labelled inputs, so the form still works.
      $rows = [];
      foreach (array_keys($platforms) as $key) {
          $value = (string) old('link_' . $key, $links[$key] ?? '');
          if ($value !== '') {
              $rows[$key] = $value;
          }
      }
      ?>

      <div class="linked-list" data-link-rows>
        <?php foreach ($rows as $key => $value): ?>
          <?php $meta = $platforms[$key] ?? ['label' => $key]; ?>
          <div class="linked-row" data-link-row data-platform="<?= e($key) ?>">
            <span class="linked-row__platform"><?= e($meta['label']) ?></span>
            <input type="text" name="link_<?= e($key) ?>" value="<?= e($value) ?>"
                   aria-label="<?= e($meta['label']) ?>">
            <button type="button" class="btn btn--ghost btn--tiny" data-link-remove
                    title="<?= e(__('settings.remove_link')) ?>">×</button>
          </div>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
          <p class="muted small" data-link-empty><?= e(__('settings.no_links')) ?></p>
        <?php endif; ?>
      </div>

      <div class="link-picker">
        <button type="button" class="btn btn--ghost btn--small" data-link-open>
          + <?= e(__('settings.add_link')) ?>
        </button>
      </div>

      <div class="link-picker__panel" data-link-panel hidden>
        <div class="link-picker__head">
          <strong><?= e(__('settings.add_link')) ?></strong>
          <button type="button" class="btn btn--ghost btn--tiny" data-link-close
                  aria-label="<?= e(__('common.close')) ?>">×</button>
        </div>
        <div class="form-row">
          <label for="link-platform"><?= e(__('settings.choose_platform')) ?></label>
          <select id="link-platform" data-link-platform>
            <?php foreach ($platforms as $key => $meta): ?>
              <?php
              // Show the shape of the expected value without hard-coding a
              // placeholder per platform: the template says it all.
              $template = (string) ($meta['template'] ?? '{value}');
              $isUrl = str_starts_with($template, 'http') || str_starts_with($template, 'mailto:');
              ?>
              <option value="<?= e($key) ?>"
                      data-placeholder="<?= e($isUrl && $template === '{value}' ? 'https://…' : ($key === 'orcid' ? '0000-0002-1825-0097' : 'username / iD')) ?>"
                      data-hint="<?= e(str_replace('{value}', '…', $template)) ?>">
                <?= e($meta['label']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <label for="link-value"><?= e(__('paper.link_url')) ?></label>
          <input type="text" id="link-value" data-link-value
                 placeholder="<?= e(__('settings.link_value_placeholder')) ?>">
        </div>
        <p class="help" data-link-hint></p>
        <button type="button" class="btn btn--primary btn--small" data-link-add>
          <?= e(__('settings.link_added')) ?>
        </button>

        <template data-link-template>
          <div class="linked-row" data-link-row>
            <span class="linked-row__platform" data-link-label></span>
            <input type="text" data-link-input>
            <button type="button" class="btn btn--ghost btn--tiny" data-link-remove
                    title="<?= e(__('settings.remove_link')) ?>">×</button>
          </div>
        </template>
      </div>

      <button class="btn btn--primary" type="submit"><?= e(__('common.save_changes')) ?></button>
    </form>
  </section>

  <section class="card" id="password">
    <h2 class="card__title"><?= e(__('settings.password')) ?></h2>
    <form class="form" method="post" action="<?= e(url('settings.password')) ?>">
      <?= csrf_field() ?>
      <div class="form-row">
        <label for="current_password"><?= e(__('settings.current_password')) ?></label>
        <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
      </div>
      <div class="form-grid form-grid--2">
        <div class="form-row">
          <label for="password"><?= e(__('settings.new_password')) ?></label>
          <input type="password" id="password" name="password" required minlength="<?= (int) $minPassword ?>"
                 autocomplete="new-password">
        </div>
        <div class="form-row">
          <label for="password_confirmation"><?= e(__('settings.confirm_password')) ?></label>
          <input type="password" id="password_confirmation" name="password_confirmation" required
                 autocomplete="new-password">
        </div>
      </div>
      <button class="btn btn--primary" type="submit"><?= e(__('common.save_changes')) ?></button>
    </form>
  </section>

  <section class="card" id="preferences">
    <h2 class="card__title"><?= e(__('settings.preferences')) ?></h2>
    <form class="form" method="post" action="<?= e(url('settings.preferences')) ?>">
      <?= csrf_field() ?>
      <div class="form-row">
        <label for="locale"><?= e(__('settings.locale')) ?></label>
        <select id="locale" name="locale">
          <?php foreach ($locales as $code => $meta): ?>
            <option value="<?= e($code) ?>" <?= (string) $user['locale'] === $code ? 'selected' : '' ?>>
              <?= e($meta['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn--primary" type="submit"><?= e(__('common.save_changes')) ?></button>
    </form>
  </section>
</div>
