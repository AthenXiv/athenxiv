<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * OpenTimestamps block shown on a paper page.
 *
 * The requirement in one line: display the timestamp, put a small button next
 * to it that links to opentimestamps.org, and explain on hover what the link
 * is for. We also offer the .ots proof itself, because the official verifier
 * needs that file as input — there is no per-proof verification URL.
 *
 * @var array $timestamp
 * @var bool $compact  history entries: a single tidy line, details collapsed
 */

use Athenaeum\Core\Settings;
use Athenaeum\Models\Timestamp;

$compact = !empty($compact);
$confirmed = ($timestamp['status'] ?? '') === 'confirmed';
$failed = ($timestamp['status'] ?? '') === 'failed';
$isPage = ($timestamp['target_type'] ?? '') === 'page';
$verifyUrl = (string) ($timestamp['verify_url'] ?? \Athenaeum\Services\OpenTimestamps::verifyUrl());
$proofUrl = $timestamp['proof_url'] ?? null;
$snapshotUrl = $timestamp['snapshot_url'] ?? null;
$stateClass = $confirmed ? 'ok' : ($failed ? 'bad' : 'warn');
?>
<div class="ots ots--<?= $stateClass ?><?= $compact ? ' ots--compact' : '' ?>">
  <div class="ots__line">
    <span class="ots__icon" aria-hidden="true"><?= $confirmed ? '✓' : ($failed ? '×' : '⧗') ?></span>
    <span class="ots__label">
      <strong>OpenTimestamps</strong>
      <?php if ($compact): ?>
        <span class="muted small">· <?= e(Timestamp::shortStatusLabel((string) $timestamp['status'])) ?></span>
      <?php elseif ($isPage): ?>
        <span class="muted small">· <?= e(__('ots.target_page')) ?></span>
      <?php elseif ($timestamp['target_type'] === 'attachment'): ?>
        <span class="muted small">· <?= e(__('ots.target_attachment')) ?>: <?= e((string) ($timestamp['file_name'] ?? '')) ?></span>
      <?php else: ?>
        <span class="muted small">· <?= e(__('ots.target_pdf')) ?></span>
      <?php endif; ?>
    </span>

    <span class="ots__date">
      <?php if ($confirmed): ?>
        <?= e(format_date((string) ($timestamp['bitcoin_time'] ?? $timestamp['upgraded_at']), true)) ?>
        <?php if (!empty($timestamp['bitcoin_height'])): ?>
          <span class="muted small">· <?= e(__('ots.block')) ?> #<?= (int) $timestamp['bitcoin_height'] ?></span>
        <?php endif; ?>
      <?php else: ?>
        <?= e(format_date((string) $timestamp['submitted_at'], true)) ?>
        <?php if (!$compact): ?>
          <span class="muted small">· <?= e(Timestamp::statusLabel((string) $timestamp['status'])) ?></span>
        <?php endif; ?>
      <?php endif; ?>
    </span>

    <span class="ots__actions">
      <a class="btn btn--ghost btn--tiny ots__verify"
         href="<?= e($verifyUrl) ?>"
         target="_blank" rel="noopener noreferrer"
         title="<?= e(__('ots.verify_tooltip')) ?>"
         aria-label="<?= e(__('ots.verify_tooltip')) ?>"><?= e(__('ots.verify_button')) ?></a>
      <?php if ($proofUrl): ?>
        <a class="btn btn--ghost btn--tiny" href="<?= e($proofUrl) ?>" download
           title="<?= e(__('ots.download_proof')) ?>">.ots</a>
      <?php endif; ?>
      <?php if ($snapshotUrl): ?>
        <a class="btn btn--ghost btn--tiny" href="<?= e($snapshotUrl) ?>" download
           title="<?= e(__('ots.download_snapshot')) ?>">.txt</a>
      <?php endif; ?>
    </span>
  </div>

  <div class="ots__detail small">
    <details>
      <summary><?= e(__('ots.heading')) ?></summary>
      <dl class="kv">
        <dt><?= e(__('ots.hash')) ?></dt>
        <dd><code class="hash"><?= e((string) $timestamp['file_sha256']) ?></code></dd>
        <dt><?= e(__('ots.submitted_at')) ?></dt>
        <dd><?= e(format_date((string) $timestamp['submitted_at'], true)) ?></dd>
        <?php if ($confirmed): ?>
          <dt><?= e(__('ots.confirmed_at')) ?></dt>
          <dd><?= e(format_date((string) ($timestamp['bitcoin_time'] ?? $timestamp['upgraded_at']), true)) ?></dd>
          <?php if (!empty($timestamp['bitcoin_height'])): ?>
            <dt><?= e(__('ots.block')) ?></dt>
            <dd>#<?= (int) $timestamp['bitcoin_height'] ?></dd>
          <?php endif; ?>
        <?php endif; ?>
        <?php if (!empty($timestamp['calendar_count'])): ?>
          <dt><?= e(__('ots.calendars')) ?></dt>
          <dd><?= (int) $timestamp['calendar_count'] ?></dd>
        <?php endif; ?>
        <?php if (!empty($timestamp['last_error']) && $failed): ?>
          <dt><?= e(__('common.warning')) ?></dt>
          <dd><?= e((string) $timestamp['last_error']) ?></dd>
        <?php endif; ?>
      </dl>
      <p class="muted">
        <?php if ($isPage): ?>
          <?= e($confirmed ? __('ots.page_confirmed_explainer') : __('ots.page_pending_explainer')) ?>
        <?php else: ?>
          <?= e($confirmed ? __('ots.confirmed_explainer') : __('ots.pending_explainer')) ?>
        <?php endif; ?>
      </p>
      <?php if (!$confirmed && !$failed): ?>
        <p class="muted"><?= e(__('ots.pending_no_date')) ?></p>
      <?php endif; ?>
    </details>
  </div>
</div>
