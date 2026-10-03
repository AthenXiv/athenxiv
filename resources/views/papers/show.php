<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Paper detail page: metadata, OpenTimestamps block, online preview,
 * downloads, attachments, external links, citation export.
 *
 * @var array $paper,$authors,$attachments,$links,$timestamps
 * @var array|null $section,$category,$uploader
 * @var bool $canEdit,$isOwner
 */

use Athenaeum\Core\Auth;
use Athenaeum\Core\Config;
use Athenaeum\Core\Settings;
use Athenaeum\Models\Paper;
use Athenaeum\Models\PaperLink;
use Athenaeum\Models\Section;

$uploaderName = $uploader === null
    ? ''
    : (string) (($uploader['display_name'] ?? '') !== '' ? $uploader['display_name'] : $uploader['nickname']);
// One canonical PDF URL for the whole site: it ends in .pdf (Google Scholar),
// answers with application/pdf, and is what the viewer loads.
$pdfUrl = url('paper.pdf', ['uid' => $paper['uid']]);
$viewerAvailable = public_path('vendor/pdfjs/web/viewer.html') !== null;
$archived = Paper::isArchived($paper);
$publicTimestamps = array_values(array_filter($timestamps, static fn (array $t): bool => (string) $t['target_type'] === 'pdf'));
$attachmentTimestamps = [];
foreach ($timestamps as $timestamp) {
    if ((string) $timestamp['target_type'] === 'attachment') {
        $attachmentTimestamps[(int) $timestamp['attachment_id']] = $timestamp;
    }
}
?>
<div class="container">
  <?php if (!$isOwner && !$canEdit && $paper['status'] !== Paper::STATUS_APPROVED): ?>
    <div class="alert alert--info"><?= e(Paper::statusLabel((string) $paper['status'])) ?></div>
  <?php endif; ?>

  <?php if ($paper['visibility'] === 'unlisted'): ?>
    <div class="alert alert--info"><?= e(__('paper.visibility_unlisted')) ?></div>
  <?php endif; ?>

  <?php if (Auth::isAdmin() && in_array($paper['status'], [Paper::STATUS_PENDING, Paper::STATUS_REJECTED], true)): ?>
    <div class="alert alert--info">
      <?= e(Paper::statusLabel((string) $paper['status'])) ?> —
      <a href="<?= e(url('admin.paper', ['id' => $paper['id']])) ?>"><?= e(__('admin.review')) ?></a>
    </div>
  <?php endif; ?>

  <article class="paper">
    <header class="paper__head">
      <p class="paper__uid small muted">
        <?= e(__('paper.uid_label')) ?> <code><?= e($paper['uid']) ?></code>
        <?php if ($section !== null): ?>
          · <a class="badge" href="<?= e(url('paper.section', ['slug' => $section['slug']])) ?>"><?= e(Section::name($section)) ?></a>
        <?php endif; ?>
        <?php if ($category !== null): ?>
          · <a class="badge" href="<?= e(url('paper.category', ['slug' => $category['slug']])) ?>"><?= e(\Athenaeum\Models\Category::name($category)) ?></a>
        <?php endif; ?>
        <?php if ($paper['status'] !== Paper::STATUS_APPROVED): ?>
          <span class="<?= e(Paper::statusBadgeClass((string) $paper['status'])) ?>"><?= e(Paper::statusLabel((string) $paper['status'])) ?></span>
        <?php endif; ?>
      </p>

      <h1 class="paper__title"><?= e($paper['title']) ?>
        <?php if ($archived): ?>
          <span class="<?= e(Paper::rightsBadgeClass($paper)) ?>"
                title="<?= e(__('paper.rights_hint')) ?>"><?= e(Paper::rightsLabel($paper)) ?></span>
        <?php endif; ?>
      </h1>
      <?php if (!empty($paper['subtitle'])): ?>
        <p class="paper__subtitle"><?= e($paper['subtitle']) ?></p>
      <?php endif; ?>

      <?php if ($archived): ?>
        <?php
        // An archived work is not an AthenXiv submission: be explicit about the
        // original publication date, the rights situation and when the copy was
        // added here, so nobody mistakes our upload date for the paper's date.
        ?>
        <div class="alert alert--info paper__archive">
          <p><?= e(__('paper.archive_notice', [
              'source' => (string) ($paper['origin_source'] ?? '') !== '' ? (string) $paper['origin_source'] : __('paper.archive_source_default'),
          ])) ?></p>
          <ul class="paper__dates">
            <?php if (!empty($paper['origin_published_at'])): ?>
              <li><?= e(__('paper.origin_published', ['date' => format_date((string) $paper['origin_published_at'])])) ?></li>
            <?php endif; ?>
            <?php if (!empty($paper['copyright_expired_at'])): ?>
              <li><?= e(__('paper.copyright_expired', ['date' => format_date((string) $paper['copyright_expired_at'])])) ?></li>
            <?php else: ?>
              <li><?= e(__('paper.copyright_status', ['rights' => Paper::rightsLabel($paper)])) ?></li>
            <?php endif; ?>
            <li><?= e(__('paper.added_to_site', ['date' => format_date((string) ($paper['created_at'] ?? ''))])) ?></li>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($authors !== []): ?>
        <ul class="author-list">
          <?php foreach ($authors as $author): ?>
            <?php $linkedUser = $authorUsers[(int) ($author['user_id'] ?? 0)] ?? null; ?>
            <li<?= $linkedUser !== null ? ' class="author-list__item--linked"' : '' ?>>
              <?php if ($linkedUser !== null): ?>
                <?php $avatar = \Athenaeum\Models\User::avatarUrl($linkedUser); ?>
                <a class="author-list__avatar" href="<?= e(url('user.show', ['uid' => $linkedUser['uid']])) ?>"
                   title="<?= e(trim((string) ($linkedUser['display_name'] ?? '')) ?: (string) $linkedUser['nickname']) ?>">
                  <?php if ($avatar !== null): ?>
                    <img src="<?= e($avatar) ?>" alt="" loading="lazy">
                  <?php else: ?>
                    <span class="author-list__initial" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $author['name'], 0, 1))) ?></span>
                  <?php endif; ?>
                </a>
              <?php endif; ?>
              <?php if ($linkedUser !== null): ?>
                <a class="author-list__name" href="<?= e(url('user.show', ['uid' => $linkedUser['uid']])) ?>"><?= e($author['name']) ?></a>
              <?php else: ?>
                <span class="author-list__name"><?= e($author['name']) ?></span>
              <?php endif; ?>
              <?php if ((int) $author['is_corresponding'] === 1): ?>
                <span class="author-list__mark" title="<?= e(__('paper.author_corresponding')) ?>">✉</span>
              <?php endif; ?>
              <?php if (!empty($author['affiliation'])): ?>
                <span class="author-list__affiliation muted"><?= e($author['affiliation']) ?></span>
              <?php endif; ?>
              <?php if (!empty($author['orcid'])): ?>
                <a class="author-list__orcid small" href="https://orcid.org/<?= e($author['orcid']) ?>" target="_blank" rel="noopener">ORCID <?= e($author['orcid']) ?></a>
              <?php endif; ?>
              <?php if (!empty($author['email'])): ?>
                <a class="small" href="mailto:<?= e($author['email']) ?>"><?= e($author['email']) ?></a>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <p class="paper__meta small muted">
        <?php if ($uploaderName !== '' && $uploader !== null): ?>
          <?= e(__('paper.submitted_by')) ?>
          <a href="<?= e(url('user.show', ['uid' => $uploader['uid']])) ?>"><?= e($uploaderName) ?></a>
          <code class="muted"><?= e($uploader['uid']) ?></code>
        <?php endif; ?>
        <?php if ($paper['published_at']): ?>
          · <?= e(__('paper.published_on')) ?> <?= e(format_date((string) $paper['published_at'])) ?>
        <?php else: ?>
          · <?= e(__('paper.submitted_on')) ?> <?= e(format_date((string) $paper['submitted_at'])) ?>
        <?php endif; ?>
        <?php if (Settings::bool('ui.show_view_counts')): ?>
          · <?= (int) $paper['views'] ?> <?= e(__('paper.views')) ?>
          · <?= (int) $paper['downloads'] ?> <?= e(__('paper.downloads')) ?>
        <?php endif; ?>
        · <?= e(human_size((int) $paper['pdf_size'])) ?>
        <?php if (!empty($paper['language'])): ?>
          · <?= e($languageLabel ?? Paper::languageLabel($paper)) ?>
        <?php endif; ?>
      </p>

      <?php if (!empty($paper['proxy_uploader_id'])): ?>
        <?php $proxy = Athenaeum\Models\User::find((int) $paper['proxy_uploader_id']); ?>
        <?php if ($proxy !== null): ?>
          <p class="small muted">
            <?= e(__('paper.proxy_uploaded_by')) ?>
            <?= e((string) ($proxy['nickname'])) ?> (<code><?= e($proxy['uid']) ?></code>)
            <?php if ((int) $paper['size_exempt'] === 1): ?>
              · <span class="badge"><?= e(__('paper.size_exempt')) ?></span>
            <?php endif; ?>
          </p>
        <?php endif; ?>
      <?php endif; ?>

      <p class="paper__actions">
        <a class="btn btn--primary" href="<?= e(url('paper.preview', ['uid' => $paper['uid']])) ?>" target="_blank" rel="noopener">
          <?= e(__('paper.pdf_preview')) ?>
        </a>
        <a class="btn" href="<?= e(url('paper.download', ['uid' => $paper['uid']])) ?>" download>
          <?= e(__('paper.download_pdf')) ?> (<?= e(human_size((int) $paper['pdf_size'])) ?>)
        </a>
        <?php if ($canEdit): ?>
          <a class="btn btn--ghost" href="<?= e(url('paper.edit', ['uid' => $paper['uid']])) ?>"><?= e(__('common.edit')) ?></a>
        <?php endif; ?>
        <?php if (Auth::isAdmin()): ?>
          <a class="btn btn--ghost" href="<?= e(url('admin.paper', ['id' => $paper['id']])) ?>"><?= e(__('admin.review')) ?></a>
        <?php endif; ?>
        <?php if ($isOwner || Auth::isAdmin()): ?>
          <form method="post" action="<?= e(url('paper.withdraw', ['uid' => $paper['uid']])) ?>" class="inline"
                data-confirm="<?= e(__('paper.withdraw_confirm')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn--ghost" type="submit"><?= e(__('paper.withdraw')) ?></button>
          </form>
        <?php endif; ?>
      </p>
    </header>

    <section class="paper__abstract">
      <h2><?= e(__('paper.abstract')) ?></h2>
      <p class="prose"><?= nl2br(e($paper['abstract'])) ?></p>
      <?php if (!empty($paper['keywords'])): ?>
        <p class="paper__keywords small">
          <strong><?= e(__('paper.keywords')) ?>:</strong> <?= e($paper['keywords']) ?>
        </p>
      <?php endif; ?>
      <?php if (!empty($paper['doi'])): ?>
        <p class="small"><strong>DOI:</strong>
          <a href="https://doi.org/<?= e($paper['doi']) ?>" target="_blank" rel="noopener"><?= e($paper['doi']) ?></a>
        </p>
      <?php endif; ?>
      <?php if (!empty($paper['license'])): ?>
        <p class="small muted"><?= e(__('paper.license')) ?>: <?= e($paper['license']) ?></p>
      <?php endif; ?>
    </section>

    <?php if ($publicTimestamps !== [] && !$archived): ?>
      <section class="paper__ots">
        <h2><?= e(__('ots.heading')) ?></h2>
        <?php
        // A paper may carry one proof per revision. Only the current revision is
        // shown up front: a wall of identical status blocks pushed the actual
        // content off the page. Older proofs stay reachable, each labelled with
        // the version it belongs to, behind a toggle.
        $versionByHash = [];
        foreach (($versions ?? []) as $versionRow) {
            $versionByHash[(string) $versionRow['pdf_sha256']] = (int) $versionRow['version_no'];
        }
        $currentVersionNo = (int) ($paper['version_no'] ?? 1);
        $latestProof = null;
        $historyProofs = [];
        foreach ($publicTimestamps as $timestamp) {
            $versionNo = $versionByHash[(string) $timestamp['file_sha256']] ?? null;
            $isCurrent = $versionNo === null
                ? $latestProof === null
                : $versionNo === $currentVersionNo;
            if ($isCurrent && $latestProof === null) {
                $latestProof = ['timestamp' => $timestamp, 'version' => $versionNo];
            } else {
                $historyProofs[] = ['timestamp' => $timestamp, 'version' => $versionNo];
            }
        }
        // Newest first for the history list.
        usort($historyProofs, static fn (array $a, array $b): int => (int) ($b['timestamp']['id'] ?? 0) <=> (int) ($a['timestamp']['id'] ?? 0));
        ?>

        <?php if ($latestProof !== null): ?>
          <div class="ots-current">
            <?php if ($latestProof['version'] !== null && $currentVersionNo > 1): ?>
              <p class="ots-current__version muted small">
                <?= e(__('paper.version')) ?> v<?= (int) $latestProof['version'] ?> ·
                <?= e(__('paper.version_current')) ?>
              </p>
            <?php endif; ?>
            <?php \Athenaeum\Core\View::partial('partials/ots', ['timestamp' => $latestProof['timestamp']]); ?>
          </div>
        <?php endif; ?>

        <?php if ($historyProofs !== [] && !$archived): ?>
          <details class="ots-history">
            <summary>
              <?= e(__('ots.history_toggle', ['count' => (string) count($historyProofs)])) ?>
            </summary>
            <div class="ots-history__list">
              <?php foreach ($historyProofs as $entry): ?>
                <div class="ots-history__item">
                  <p class="ots-history__label">
                    <span class="badge badge--muted">
                      <?= e(__('paper.version')) ?>
                      <?= $entry['version'] !== null ? 'v' . (int) $entry['version'] : '—' ?>
                    </span>
                  </p>
                  <?php \Athenaeum\Core\View::partial('partials/ots', ['timestamp' => $entry['timestamp'], 'compact' => true]); ?>
                </div>
              <?php endforeach; ?>
            </div>
          </details>
        <?php endif; ?>
      </section>
    <?php elseif (!Settings::bool('ots.enabled')): ?>
      <p class="muted small"><?= e(__('ots.stamp_disabled')) ?></p>
    <?php endif; ?>

    <section class="paper__preview">
      <h2><?= e(__('paper.pdf_preview')) ?></h2>
      <?php if ($viewerAvailable): ?>
        <div class="pdf-frame" data-pdf-viewer>
          <iframe
            src="<?= e(url('paper.preview', ['uid' => $paper['uid']])) ?>"
            title="<?= e($paper['title']) ?>"
            loading="lazy"></iframe>
        </div>
      <?php else: ?>
        <?php
        // Never fall back to <object>/<embed>: Firefox treats those as plugin
        // content and offers to start its Flash emulator. An iframe hands the
        // PDF to the browser's own viewer instead.
        ?>
        <div class="pdf-frame" data-pdf-viewer>
          <iframe src="<?= e($pdfUrl) ?>" title="<?= e($paper['title']) ?>" loading="lazy"></iframe>
        </div>
        <p class="muted small">
          <?= e(__('paper.pdf_open_new')) ?> —
          <a href="<?= e($pdfUrl) ?>" target="_blank" rel="noopener"><?= e(__('paper.download_pdf')) ?></a>
        </p>
      <?php endif; ?>
    </section>

    <?php if ($attachments !== []): ?>
      <section class="paper__attachments">
        <h2><?= e(__('paper.attachments_section')) ?></h2>
        <table class="table">
          <thead>
            <tr>
              <th><?= e(__('ots.file')) ?></th>
              <th><?= e(__('upload.max_size')) ?></th>
              <th><?= e(__('ots.heading')) ?></th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($attachments as $attachment): ?>
              <?php $attachmentTimestamp = $attachmentTimestamps[(int) $attachment['id']] ?? null; ?>
              <tr>
                <td><?= e($attachment['original_name']) ?></td>
                <td><?= e(human_size((int) $attachment['size'])) ?></td>
                <td>
                  <?php if ($attachmentTimestamp !== null): ?>
                    <span class="ots-chip ots-chip--<?= $attachmentTimestamp['status'] === 'confirmed' ? 'ok' : 'warn' ?>"
                          title="<?= e($attachmentTimestamp['short_hash']) ?>">
                      <?= e(\Athenaeum\Models\Timestamp::statusLabel((string) $attachmentTimestamp['status'])) ?>
                    </span>
                    <?php if (!empty($attachmentTimestamp['proof_url'])): ?>
                      <a class="small" href="<?= e($attachmentTimestamp['proof_url']) ?>" download>.ots</a>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="muted small">—</span>
                  <?php endif; ?>
                </td>
                <td class="right">
                  <a class="btn btn--small" href="<?= e(url('paper.attachment', ['uid' => $paper['uid'], 'attachment' => $attachment['id']])) ?>" download>
                    <?= e(__('paper.download_attachment')) ?>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    <?php endif; ?>

    <?php if ($links !== []): ?>
      <section class="paper__links">
        <h2><?= e(__('paper.external_links')) ?></h2>
        <ul class="link-list">
          <?php foreach ($links as $link): ?>
            <li>
              <span class="link-list__kind"><?= e(PaperLink::kindLabel((string) $link['kind'])) ?></span>
              <a href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e($link['label']) ?></a>
              <span class="link-list__url small muted"><?= e(excerpt((string) $link['url'], 80)) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <?php if ($versions !== [] && !$archived): ?>
      <section class="paper__versions" id="versions">
        <h2><?= e(__('paper.version_history')) ?>
          <span class="muted small"><?= e(__('paper.version_count', ['count' => count($versions)])) ?></span>
        </h2>

        <?php if (count($versions) > 1 && $paper['status'] !== Paper::STATUS_APPROVED): ?>
          <p class="alert alert--info"><?= e(__('version.pending_notice')) ?></p>
        <?php endif; ?>

        <?php // Long revision lists push everything else off the page: collapsible. ?>
        <details class="version-history" <?= count($versions) <= 3 ? 'open' : '' ?>>
          <summary>
            <?= e(__('paper.version_toggle', ['count' => (string) count($versions)])) ?>
          </summary>
          <ol class="version-list">
          <?php foreach ($versions as $version): ?>
            <?php
            $isCurrent = (int) $version['version_no'] === (int) $paper['version_no'];
            $versionTimestamp = $version['timestamp'] ?? null;
            ?>
            <li class="version-list__item <?= $isCurrent ? 'is-current' : '' ?>">
              <div class="version-list__head">
                <strong><?= e((string) ($version['label'] ?: ('v' . $version['version_no']))) ?></strong>
                <?php if ($isCurrent): ?>
                  <span class="badge badge--ok"><?= e(__('paper.version_current')) ?></span>
                <?php endif; ?>
                <span class="muted small"><?= e(format_date((string) $version['created_at'], true)) ?></span>
                <span class="muted small">· <?= e(human_size((int) $version['pdf_size'])) ?></span>
                <?php if (!empty($version['uploader'])): ?>
                  <span class="muted small">· <?= e(__('paper.version_uploaded_by', ['name' => (string) ($version['uploader']['nickname'] ?? '')])) ?></span>
                <?php endif; ?>
              </div>

              <?php if (!empty($version['note'])): ?>
                <p class="version-list__note"><?= e((string) $version['note']) ?></p>
              <?php endif; ?>

              <p class="version-list__actions small">
                <a class="btn btn--small"
                   href="<?= e($isCurrent
                       ? url('paper.download', ['uid' => $paper['uid']])
                       : url('paper.version.download', ['uid' => $paper['uid'], 'version' => $version['version_no']])) ?>"
                   download><?= e(__('paper.version_download')) ?></a>

                <?php if (!$isCurrent): ?>
                  <a class="btn btn--ghost btn--small" target="_blank" rel="noopener"
                     href="<?= e(url('paper.version.file', ['uid' => $paper['uid'], 'version' => $version['version_no']])) ?>">
                    <?= e(__('paper.pdf_preview')) ?>
                  </a>
                <?php endif; ?>

                <?php if ($versionTimestamp !== null): ?>
                  <span class="ots-chip ots-chip--<?= $versionTimestamp['status'] === 'confirmed' ? 'ok' : ($versionTimestamp['status'] === 'failed' ? 'bad' : 'warn') ?>"
                        title="<?= e(\Athenaeum\Models\Timestamp::statusLabel((string) $versionTimestamp['status'])) ?>">
                    <?= e(\Athenaeum\Models\Timestamp::shortStatusLabel((string) $versionTimestamp['status'])) ?>
                  </span>
                  <?php if (!empty($versionTimestamp['ots_path'])): ?>
                    <a class="btn btn--ghost btn--tiny"
                       href="<?= e(url('paper.timestamp.download', ['uid' => $paper['uid'], 'timestamp' => $versionTimestamp['id']])) ?>"
                       download>.ots</a>
                  <?php endif; ?>
                <?php endif; ?>
              </p>

              <?php if (!empty($version['pdf_sha256'])): ?>
                <p class="small muted version-list__hash">
                  <?= e(__('paper.version_sha')) ?>:
                  <code class="hash"><?= e((string) $version['pdf_sha256']) ?></code>
                </p>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
          </ol>
        </details>

        <?php if ($canEdit && \Athenaeum\Core\Settings::bool('versions.enabled')): ?>
          <form class="form form--tight version-upload" method="post" enctype="multipart/form-data"
                action="<?= e(url('paper.version.store', ['uid' => $paper['uid']])) ?>">
            <?= csrf_field() ?>
            <h3><?= e(__('paper.version_upload')) ?></h3>
            <div class="form-row">
              <label for="version_pdf">PDF <span class="req">*</span></label>
              <input type="file" id="version_pdf" name="version_pdf" accept="application/pdf,.pdf" required>
            </div>
            <div class="form-row">
              <label for="version_note_inline"><?= e(__('paper.version_note')) ?>
                <span class="muted">(<?= e(__('common.optional')) ?>)</span></label>
              <input type="text" id="version_note_inline" name="version_note" maxlength="500">
            </div>
            <p class="help"><?= e(__('paper.version_upload_hint')) ?></p>
            <button class="btn btn--primary btn--small" type="submit"><?= e(__('paper.version_upload')) ?></button>
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <section class="paper__cite">
      <h2><?= e(__('paper.cite')) ?></h2>
      <p class="small">
        <?php foreach (['bibtex' => 'paper.cite_bibtex', 'ris' => 'paper.cite_ris', 'text' => 'paper.cite_text', 'json' => 'paper.cite_json'] as $format => $label): ?>
          <a class="btn btn--ghost btn--small"
             href="<?= e(url('paper.cite', ['uid' => $paper['uid'], 'format' => $format])) ?>"><?= e(__($label)) ?></a>
        <?php endforeach; ?>
      </p>
      <p class="small muted">
        <?= e(Paper::authorLine($paper)) ?> (<?= e(substr((string) ($paper['published_at'] ?: $paper['created_at']), 0, 4)) ?>).
        <?= e($paper['title']) ?>. <?= e(Settings::siteName()) ?>.
        <code><?= e(Config::baseUrl() . '/paper/' . $paper['uid']) ?></code>
      </p>
    </section>

    <?php if ($isOwner || Auth::isAdmin()): ?>
      <section class="paper__owner">
        <h2><?= e(__('paper.owner_actions')) ?></h2>
        <div class="row-actions">
          <a class="btn btn--ghost btn--small" href="<?= e(url('paper.edit', ['uid' => $paper['uid']])) ?>"><?= e(__('common.edit')) ?></a>
          <?php if (in_array($paper['status'], [Paper::STATUS_DRAFT, Paper::STATUS_REJECTED, Paper::STATUS_WITHDRAWN], true)): ?>
            <form method="post" action="<?= e(url('paper.submit', ['uid' => $paper['uid']])) ?>" class="inline">
              <?= csrf_field() ?>
              <button class="btn btn--primary btn--small" type="submit"><?= e(__('paper.submit_cta')) ?></button>
            </form>
          <?php endif; ?>
          <?php if (in_array($paper['status'], [Paper::STATUS_DRAFT, Paper::STATUS_REJECTED, Paper::STATUS_WITHDRAWN], true)): ?>
            <form method="post" action="<?= e(url('paper.destroy', ['uid' => $paper['uid']])) ?>" class="inline"
                  data-confirm="<?= e(__('paper.delete_confirm')) ?>">
              <?= csrf_field() ?>
              <button class="btn btn--danger btn--small" type="submit"><?= e(__('common.delete')) ?></button>
            </form>
          <?php endif; ?>
        </div>

        <?php if (!empty($paper['reject_reason'])): ?>
          <p class="alert alert--error"><?= e(__('paper.reject_reason')) ?>: <?= e((string) $paper['reject_reason']) ?></p>
        <?php endif; ?>
        <?php if (!empty($paper['review_note'])): ?>
          <p class="muted small"><?= e(__('paper.review_note')) ?>: <?= e((string) $paper['review_note']) ?></p>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </article>
</div>
