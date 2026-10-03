<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Moderation screen for a single paper: full metadata, both timestamps,
 * every available administrative action.
 *
 * @var array $paper,$authors,$attachments,$links,$timestamps,$sections,$audit
 * @var array|null $section,$category,$uploader,$proxy
 */

use Athenaeum\Models\Paper;
use Athenaeum\Models\PaperLink;
use Athenaeum\Models\Section;
use Athenaeum\Models\Timestamp;
?>
<header class="page-head">
  <h1><?= e(__('admin.paper_detail')) ?> <code><?= e($paper['uid']) ?></code></h1>
  <p class="small">
    <span class="<?= e(Paper::statusBadgeClass((string) $paper['status'])) ?>"><?= e(Paper::statusLabel((string) $paper['status'])) ?></span>
    <?php if (Paper::isArchived($paper)): ?>
      <span class="<?= e(Paper::rightsBadgeClass($paper)) ?>"><?= e(Paper::rightsLabel($paper)) ?></span>
      <span class="badge badge--muted"><?= e(__('admin.origin_archive')) ?></span>
      <?php if (!empty($paper['origin_published_at'])): ?>
        <span class="muted"><?= e(__('paper.origin_published_short', ['date' => format_date((string) $paper['origin_published_at'])])) ?></span>
      <?php endif; ?>
    <?php else: ?>
      <span class="badge badge--muted"><?= e(__('admin.origin_submission')) ?></span>
    <?php endif; ?>
    <?php if (Paper::isPublic($paper)): ?>
      · <a href="<?= e(Paper::publicUrl($paper)) ?>" target="_blank" rel="noopener"><?= e(__('common.view')) ?> ↗</a>
    <?php endif; ?>
    · <a href="<?= e(url('admin.papers')) ?>">← <?= e(__('admin.papers')) ?></a>
  </p>
</header>

<div class="admin-grid admin-grid--wide">
  <section class="card">
    <h2 class="card__title"><?= e($paper['title']) ?></h2>
    <?php if (!empty($paper['subtitle'])): ?>
      <p class="muted"><?= e((string) $paper['subtitle']) ?></p>
    <?php endif; ?>

    <dl class="kv">
      <dt><?= e(__('paper.authors')) ?></dt>
      <dd>
        <?php foreach ($authors as $author): ?>
          <div>
            <?= e($author['name']) ?>
            <?php if (!empty($author['affiliation'])): ?>· <span class="muted"><?= e($author['affiliation']) ?></span><?php endif; ?>
            <?php if (!empty($author['orcid'])): ?>· ORCID <?= e($author['orcid']) ?><?php endif; ?>
            <?php if (!empty($author['email'])): ?>· <?= e($author['email']) ?><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </dd>

      <dt><?= e(__('admin.paper_owner')) ?></dt>
      <dd>
        <?php if ($uploader !== null): ?>
          <a href="<?= e(url('admin.user', ['id' => $uploader['id']])) ?>"><?= e((string) $uploader['nickname']) ?></a>
          <code class="muted"><?= e($uploader['uid']) ?></code> · <?= e((string) $uploader['email']) ?>
        <?php else: ?>—<?php endif; ?>
        <?php if ($proxy !== null): ?>
          <div class="small muted"><?= e(__('paper.proxy_uploaded_by')) ?>
            <?= e((string) $proxy['nickname']) ?> (<code><?= e($proxy['uid']) ?></code>)</div>
        <?php endif; ?>
      </dd>

      <dt><?= e(__('paper.submitted_on')) ?></dt>
      <dd><?= e(format_date((string) ($paper['submitted_at'] ?? $paper['created_at']), true)) ?></dd>

      <dt><?= e(__('paper.language')) ?></dt>
      <dd><?= e(Paper::languageLabel($paper)) ?> · <?= e(__('paper.visibility')) ?>: <?= e((string) $paper['visibility']) ?>
        · <?= e(__('paper.version')) ?> v<?= (int) ($paper['version_no'] ?? 1) ?></dd>

      <dt><?= e(__('paper.section')) ?></dt>
      <dd><?= $section !== null ? e(Section::name($section)) : '<span class="muted">—</span>' ?></dd>

      <dt><?= e(__('paper.category')) ?></dt>
      <dd><?= $category !== null ? e(\Athenaeum\Models\Category::name($category)) : '<span class="muted">—</span>' ?></dd>

      <dt><?= e(__('paper.views')) ?> / <?= e(__('paper.downloads')) ?></dt>
      <dd><?= (int) $paper['views'] ?> / <?= (int) $paper['downloads'] ?></dd>

      <?php if ((int) $paper['size_exempt'] === 1): ?>
        <dt><?= e(__('paper.size_exempt')) ?></dt>
        <dd>
          <span class="badge badge--warn"><?= e(__('paper.size_exempt')) ?></span>
          <?php if (!empty($paper['size_exempt_note'])): ?>
            <div class="small"><?= e((string) $paper['size_exempt_note']) ?></div>
          <?php endif; ?>
        </dd>
      <?php endif; ?>

      <dt>PDF</dt>
      <dd>
        <?php if (!empty($paper['pdf_name'])): ?>
          <?= e((string) $paper['pdf_name']) ?> (<?= e(human_size((int) $paper['pdf_size'])) ?>)
          <br><code class="hash small"><?= e((string) $paper['pdf_sha256']) ?></code>
        <?php else: ?><span class="muted">—</span><?php endif; ?>
      </dd>
    </dl>

    <p class="row-actions">
      <a class="btn btn--small" href="<?= e(url('admin.paper.file', ['id' => $paper['id']])) ?>" target="_blank" rel="noopener"><?= e(__('admin.open_pdf')) ?></a>
      <a class="btn btn--ghost btn--small" href="<?= e(url('admin.paper.download', ['id' => $paper['id']])) ?>" download><?= e(__('admin.download_pdf')) ?></a>
      <a class="btn btn--ghost btn--small" href="<?= e(url('admin.paper.edit', ['id' => $paper['id']])) ?>"><?= e(__('common.edit')) ?></a>
    </p>

    <h3><?= e(__('paper.abstract')) ?></h3>
    <div class="review-abstract"><?= e((string) $paper['abstract']) ?></div>

    <?php if ($attachments !== []): ?>
      <h3><?= e(__('paper.attachments_section')) ?></h3>
      <ul class="plain-list">
        <?php foreach ($attachments as $attachment): ?>
          <li>
            <?= e($attachment['original_name']) ?>
            <span class="muted small">(<?= e(human_size((int) $attachment['size'])) ?>)</span>
            <a class="small" href="<?= e(url('paper.attachment', ['uid' => $paper['uid'], 'attachment' => $attachment['id']])) ?>" download>
              <?= e(__('common.download')) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($links !== []): ?>
      <h3><?= e(__('paper.external_links')) ?></h3>
      <ul class="plain-list">
        <?php foreach ($links as $link): ?>
          <li>
            <span class="muted small"><?= e(PaperLink::kindLabel((string) $link['kind'])) ?></span>
            <a href="<?= e($link['url']) ?>" target="_blank" rel="noopener nofollow"><?= e($link['label']) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2 class="card__title"><?= e(__('admin.review')) ?></h2>

    <?php $classified = \Athenaeum\Services\PaperService::isClassified($paper); ?>
    <?php if (!$classified): ?>
      <div class="alert alert--warn-strong">
        <strong><?= e(__('category.approve_blocked')) ?></strong>
        <?php if (!empty($paper['category_other'])): ?>
          <br><span class="muted small"><?= e(__('category.other_label')) ?>:
            „<?= e((string) $paper['category_other']) ?>"</span>
        <?php endif; ?>
      </div>
      <form class="form form--tight" method="post"
            action="<?= e(url('admin.paper.reclassify', ['id' => $paper['id']])) ?>">
        <?= csrf_field() ?>
        <div class="form-row">
          <label for="reclassify_area"><?= e(__('category.assign')) ?></label>
          <select id="reclassify_area" name="category_id">
            <?php foreach ($categories as $option): ?>
              <option value="<?= (int) $option['id'] ?>">
                <?= e(str_repeat('— ', (int) ($option['depth'] ?? 0)) . \Athenaeum\Models\Category::name($option)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <label class="muted small" for="reclassify_new"><?= e(__('admin.category_add_child')) ?></label>
          <input type="text" id="reclassify_new" name="new_area" maxlength="120"
                 placeholder="<?= e(__('category.other_placeholder')) ?>">
        </div>
        <button class="btn btn--primary btn--small" type="submit"><?= e(__('category.assign')) ?></button>
      </form>
      <hr>
    <?php endif; ?>

    <?php if (in_array($paper['status'], [Paper::STATUS_PENDING, Paper::STATUS_REJECTED, Paper::STATUS_TAKEDOWN, Paper::STATUS_WITHDRAWN], true)): ?>
      <form class="form form--tight" method="post" action="<?= e(url('admin.paper.approve', ['id' => $paper['id']])) ?>">
        <?= csrf_field() ?>
        <div class="form-row">
          <label for="approve_section"><?= e(__('admin.assign_section')) ?></label>
          <select id="approve_section" name="section_id">
            <option value=""><?= e(__('admin.is_default')) ?></option>
            <?php foreach ($sections as $option): ?>
              <option value="<?= (int) $option['id'] ?>" <?= (int) $paper['section_id'] === (int) $option['id'] ? 'selected' : '' ?>>
                <?= e(Section::name($option)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <label for="approve_note"><?= e(__('admin.note')) ?></label>
          <input type="text" id="approve_note" name="note" maxlength="1000">
        </div>
        <button class="btn btn--primary btn--block" type="submit" <?= $classified ? '' : 'disabled' ?>>
          <?= e(__('admin.approve')) ?>
        </button>
      </form>
      <hr>
    <?php endif; ?>

    <form class="form form--tight" method="post" action="<?= e(url('admin.paper.reject', ['id' => $paper['id']])) ?>">
      <?= csrf_field() ?>
      <div class="form-row">
        <label for="reject_reason"><?= e(__('admin.reason')) ?> <span class="req">*</span></label>
        <input type="text" id="reject_reason" name="reason" maxlength="1000" required
               value="<?= e((string) $paper['reject_reason']) ?>">
      </div>
      <div class="form-row">
        <label for="reject_note"><?= e(__('admin.note')) ?></label>
        <input type="text" id="reject_note" name="note" maxlength="1000">
      </div>
      <button class="btn btn--danger btn--block" type="submit"><?= e(__('admin.reject')) ?></button>
    </form>

    <hr>

    <form class="form form--tight" method="post" action="<?= e(url('admin.paper.takedown', ['id' => $paper['id']])) ?>">
      <?= csrf_field() ?>
      <div class="form-row">
        <label for="takedown_reason"><?= e(__('admin.reason')) ?></label>
        <input type="text" id="takedown_reason" name="reason" maxlength="1000"
               value="<?= e((string) $paper['takedown_reason']) ?>">
      </div>
      <button class="btn btn--danger btn--block" type="submit"><?= e(__('admin.takedown')) ?></button>
    </form>

    <hr>

    <form class="form form--tight" method="post" action="<?= e(url('admin.paper.section', ['id' => $paper['id']])) ?>">
      <?= csrf_field() ?>
      <div class="form-row">
        <label for="section_id"><?= e(__('admin.assign_section')) ?></label>
        <select id="section_id" name="section_id">
          <?php foreach ($sections as $option): ?>
            <option value="<?= (int) $option['id'] ?>" <?= (int) $paper['section_id'] === (int) $option['id'] ? 'selected' : '' ?>>
              <?= e(Section::name($option)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn--small" type="submit"><?= e(__('common.save')) ?></button>
    </form>

    <form class="form form--tight" method="post" action="<?= e(url('admin.paper.feature', ['id' => $paper['id']])) ?>">
      <?= csrf_field() ?>
      <label class="checkbox">
        <input type="checkbox" name="featured" value="1" <?= (int) $paper['is_featured'] === 1 ? 'checked' : '' ?>>
        <?= e(__('admin.feature')) ?>
      </label>
      <button class="btn btn--small" type="submit"><?= e(__('common.save')) ?></button>
    </form>

    <hr>

    <details class="danger-zone">
      <summary><?= e(__('admin.purge')) ?></summary>
      <p class="small muted"><?= e(__('paper.delete_confirm')) ?></p>
      <form class="form form--tight" method="post" action="<?= e(url('admin.paper.purge', ['id' => $paper['id']])) ?>">
        <?= csrf_field() ?>
        <div class="form-row">
          <label for="confirm"><?= e(__('admin.confirm_uid', ['uid' => (string) $paper['uid']])) ?></label>
          <input type="text" id="confirm" name="confirm" required placeholder="<?= e((string) $paper['uid']) ?>">
        </div>
        <button class="btn btn--danger btn--small" type="submit"><?= e(__('admin.purge')) ?></button>
      </form>
    </details>
  </section>

  <section class="card">
    <h2 class="card__title"><?= e(__('admin.timestamps')) ?></h2>
    <?php if ($timestamps === []): ?>
      <p class="muted small"><?= e(__('dashboard.no_timestamps')) ?></p>
    <?php else: ?>
      <table class="table table--compact">
        <tbody>
          <?php foreach ($timestamps as $timestamp): ?>
            <tr>
              <td class="small">
                <?= e((string) ($timestamp['file_name'] ?? '')) ?>
                <br><code class="hash small"><?= e((string) $timestamp['file_sha256']) ?></code>
              </td>
              <td class="small">
                <span class="ots-chip ots-chip--<?= $timestamp['status'] === 'confirmed' ? 'ok' : ($timestamp['status'] === 'failed' ? 'bad' : 'warn') ?>">
                  <?= e(Timestamp::statusLabel((string) $timestamp['status'])) ?>
                </span>
                <?php if (!empty($timestamp['bitcoin_height'])): ?>
                  <br><span class="muted">#<?= (int) $timestamp['bitcoin_height'] ?></span>
                <?php endif; ?>
              </td>
              <td class="right small nowrap">
                <a class="btn btn--ghost btn--tiny" href="<?= e((string) $timestamp['proof_url']) ?>" download>.ots</a>
                <a class="btn btn--ghost btn--tiny" href="<?= e(\Athenaeum\Services\OpenTimestamps::verifyUrl()) ?>"
                   target="_blank" rel="noopener noreferrer"
                   title="<?= e(__('ots.verify_tooltip')) ?>"><?= e(__('ots.verify_button')) ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p><a class="small" href="<?= e(url('admin.timestamps')) ?>"><?= e(__('admin.timestamps')) ?> →</a></p>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2 class="card__title"><?= e(__('admin.ai_panel')) ?></h2>
    <?php $ai = Paper::aiPayload($paper); ?>
    <?php $aiFailed = ($paper['ai_status'] ?? '') === 'failed'; ?>
    <dl class="kv">
      <dt><?= e(__('ai.status')) ?></dt>
      <dd><span class="badge <?= e(Paper::aiBadgeClass($paper)) ?>"><?= e(Paper::aiStatusLabel($paper)) ?></span>
        <?php if (!empty($paper['ai_reviewed_at'])): ?>
          <span class="muted small">· <?= e(format_date((string) $paper['ai_reviewed_at'], true)) ?></span>
        <?php endif; ?>
      </dd>
    </dl>

    <?php if ($aiFailed): ?>
      <?php // The reason column holds the error text of the failed attempt, so
            // the moderator can see *why* nothing changed instead of guessing. ?>
      <div class="ai-error">
        <strong><?= e(__('ai.failed_reason')) ?></strong>
        <div><?= e((string) ($paper['ai_reason'] ?? '')) ?></div>
        <?php if (!empty($paper['ai_model'])): ?>
          <div class="muted small"><?= e(__('ai.model')) ?>: <code><?= e((string) $paper['ai_model']) ?></code></div>
        <?php endif; ?>
        <p class="small" style="margin:.4rem 0 0">
          <a href="<?= e(url('admin.ai')) ?>"><?= e(__('admin.ai')) ?></a>
        </p>
      </div>
    <?php endif; ?>

    <dl class="kv">
      <?php if (!empty($paper['ai_decision'])): ?>
        <dt><?= e($aiFailed ? __('ai.last_verdict') : __('ai.decision')) ?></dt>
        <dd>
          <?= e(Paper::aiDecisionLabel((string) $paper['ai_decision'])) ?>
          <span class="muted">· <?= e(__('ai.confidence')) ?> <?= (int) $paper['ai_confidence'] ?>%</span>
        </dd>
        <dt><?= e(__('ai.reason')) ?></dt>
        <dd><?= nl2br(e((string) $paper['ai_reason'])) ?></dd>
      <?php endif; ?>

      <?php if (!empty($paper['ai_model'])): ?>
        <dt><?= e(__('ai.model')) ?></dt>
        <dd><code><?= e((string) $paper['ai_model']) ?></code>
          <span class="muted small">· <?= e(format_date((string) $paper['ai_reviewed_at'], true)) ?></span></dd>
      <?php endif; ?>

      <?php if (!empty($ai['tags'])): ?>
        <dt><?= e(__('ai.tags')) ?></dt>
        <dd><?= e(implode(', ', (array) $ai['tags'])) ?></dd>
      <?php endif; ?>

      <?php if (!empty($ai['category_slug'])): ?>
        <?php $suggested = \Athenaeum\Models\Category::findBySlug((string) $ai['category_slug']); ?>
        <dt><?= e(__('ai.suggested_category')) ?></dt>
        <dd><?= $suggested !== null ? e(\Athenaeum\Models\Category::pathLabel((int) $suggested['id'])) : e((string) $ai['category_slug']) ?></dd>
      <?php endif; ?>

      <?php if (!empty($ai['section_slug'])): ?>
        <?php $suggestedSection = \Athenaeum\Models\Section::findBySlug((string) $ai['section_slug']); ?>
        <dt><?= e(__('ai.suggested_section')) ?></dt>
        <dd><?= $suggestedSection !== null ? e(\Athenaeum\Models\Section::name($suggestedSection)) : e((string) $ai['section_slug']) ?></dd>
      <?php endif; ?>

      <?php if (array_key_exists('pdf_text', $ai)): ?>
        <dt><?= e(__('ai.read_pdf')) ?></dt>
        <dd class="small muted">
          <?= e(!empty($ai['pdf_text']) ? __('ai.pdf_text_used') : __('ai.pdf_text_missing')) ?>
          <?php if (!empty($ai['input_chars'])): ?>
            · <?= (int) $ai['input_chars'] ?> chars
          <?php endif; ?>
        </dd>
      <?php endif; ?>
    </dl>

    <?php if (\Athenaeum\Services\AiReviewer::enabled()): ?>
      <form class="form form--tight" method="post" action="<?= e(url('admin.ai.paper', ['id' => $paper['id']])) ?>">
        <?= csrf_field() ?>
        <label class="checkbox small">
          <input type="checkbox" name="auto_publish" value="1">
          <?= e(__('ai.auto_publish_now')) ?>
        </label>
        <button class="btn <?= $aiFailed ? 'btn--primary' : 'btn--ghost' ?> btn--small" type="submit">
          <?= e(__('admin.ai_run')) ?>
        </button>
      </form>
    <?php else: ?>
      <p class="small muted"><?= e(__('ai.not_configured')) ?>
        <a href="<?= e(url('admin.ai')) ?>"><?= e(__('admin.ai')) ?> →</a></p>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2 class="card__title"><?= e(__('admin.version_info')) ?></h2>
    <?php if (($versions ?? []) === []): ?>
      <p class="muted small"><?= e(__('admin.no_rows')) ?></p>
    <?php else: ?>
      <table class="table table--compact">
        <tbody>
          <?php foreach ($versions as $version): ?>
            <?php $vt = $version['timestamp'] ?? null; ?>
            <tr>
              <td class="small">
                <strong><?= e((string) ($version['label'] ?: ('v' . $version['version_no']))) ?></strong>
                <?php if ((int) $version['version_no'] === (int) ($paper['version_no'] ?? 1)): ?>
                  <span class="badge badge--ok"><?= e(__('paper.version_current')) ?></span>
                <?php endif; ?>
                <br><span class="muted"><?= e(format_date((string) $version['created_at'], true)) ?>
                  · <?= e(human_size((int) $version['pdf_size'])) ?></span>
                <?php if (!empty($version['note'])): ?>
                  <br><?= e((string) $version['note']) ?>
                <?php endif; ?>
              </td>
              <td class="small">
                <?php if ($vt !== null): ?>
                  <span class="ots-chip ots-chip--<?= $vt['status'] === 'confirmed' ? 'ok' : ($vt['status'] === 'failed' ? 'bad' : 'warn') ?>">
                    <?= e(\Athenaeum\Models\Timestamp::statusLabel((string) $vt['status'])) ?>
                  </span>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
                <br><code class="hash small"><?= e(substr((string) $version['pdf_sha256'], 0, 20)) ?>…</code>
              </td>
              <td class="right nowrap">
                <a class="btn btn--ghost btn--tiny" download
                   href="<?= e(url('paper.version.download', ['uid' => $paper['uid'], 'version' => $version['version_no']])) ?>">
                  <?= e(__('common.download')) ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2 class="card__title"><?= e(__('admin.audit')) ?></h2>
    <table class="table table--compact">
      <tbody>
        <?php foreach ($audit as $row): ?>
          <tr>
            <td class="small"><?= e(\Athenaeum\Models\AuditLog::label((string) $row['action'])) ?></td>
            <td class="small muted"><?= e((string) ($row['actor_uid'] ?? '—')) ?></td>
            <td class="small muted right"><?= e(time_ago((string) $row['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
  </section>
</div>
