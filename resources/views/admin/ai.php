<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * AI review console: provider settings, connection test, the review queue with
 * batch selection, and the recent verdicts.
 *
 * @var array $status,$queue,$recent,$sections,$categories
 * @var array|null $probe
 * @var string $defaultPrompt
 */

use Athenaeum\Models\Paper;
?>
<header class="page-head">
  <h1><?= e(__('admin.ai')) ?></h1>
  <p class="muted small"><?= e(__('ai.how_it_works')) ?></p>
</header>

<?php if (!$status['enabled']): ?>
  <div class="alert alert--info">
    <?= e(__('ai.not_configured')) ?>
    <?php if ($status['reason'] !== 'disabled'): ?> — <?= e($status['reason']) ?><?php endif; ?>
  </div>
<?php endif; ?>

<div class="admin-grid admin-grid--wide">
  <section class="card">
    <h2 class="card__title"><?= e(__('ai.title')) ?></h2>
    <form class="form" method="post" action="<?= e(url('admin.ai.save')) ?>">
      <?= csrf_field() ?>

      <div class="form-row">
        <label class="checkbox">
          <input type="checkbox" name="ai_enabled" value="1" <?= \Athenaeum\Core\Settings::bool('ai.enabled') ? 'checked' : '' ?>>
          <?= e(__('ai.enabled')) ?>
        </label>
      </div>

      <div class="form-row">
        <label for="ai_mode"><?= e(__('ai.mode')) ?></label>
        <select id="ai_mode" name="ai_mode">
          <?php foreach (['off' => 'ai.mode_off', 'semi' => 'ai.mode_semi', 'auto' => 'ai.mode_auto'] as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= \Athenaeum\Core\Settings::string('ai.mode', 'off') === $value ? 'selected' : '' ?>>
              <?= e(__($label)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <label for="ai_base_url"><?= e(__('ai.base_url')) ?></label>
        <input type="text" id="ai_base_url" name="ai_base_url" maxlength="300"
               value="<?= e(\Athenaeum\Core\Settings::string('ai.base_url')) ?>">
        <p class="help"><?= e(__('ai.base_url_hint')) ?></p>
      </div>

      <div class="form-row">
        <label for="ai_api_key"><?= e(__('ai.api_key')) ?></label>
        <input type="password" id="ai_api_key" name="ai_api_key" autocomplete="new-password"
               placeholder="<?= \Athenaeum\Core\Settings::string('ai.api_key') !== '' ? '••••••••' : '' ?>">
        <p class="help"><?= e(__('ai.api_key_hint')) ?></p>
      </div>

      <div class="form-grid form-grid--3">
        <div class="form-row">
          <label for="ai_model"><?= e(__('ai.model')) ?></label>
          <?php
          $models = json_decode(\Athenaeum\Core\Settings::string('ai.models'), true) ?: [];
          ?>
          <input type="text" id="ai_model" name="ai_model" maxlength="120" list="ai-model-options"
                 value="<?= e(\Athenaeum\Core\Settings::string('ai.model')) ?>">
          <datalist id="ai-model-options">
            <?php foreach ($models as $model): ?>
              <option value="<?= e((string) $model) ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <p class="help">
            <?php if ($models === []): ?>
              <?= e(__('ai.models_hint')) ?>
            <?php else: ?>
              <?= e(__('ai.models_hint')) ?>: <?= e(implode(', ', array_slice($models, 0, 8))) ?>
            <?php endif; ?>
          </p>
        </div>
        <div class="form-row">
          <label for="ai_temperature"><?= e(__('ai.temperature')) ?></label>
          <input type="text" id="ai_temperature" name="ai_temperature" inputmode="decimal"
                 value="<?= e((string) \Athenaeum\Core\Settings::get('ai.temperature', 0)) ?>">
        </div>
        <div class="form-row">
          <label for="ai_timeout"><?= e(__('ai.timeout')) ?></label>
          <input type="number" id="ai_timeout" name="ai_timeout" min="10" max="600"
                 value="<?= (int) \Athenaeum\Core\Settings::get('ai.timeout', 90) ?>">
        </div>
        <div class="form-row">
          <label for="ai_max_input_chars"><?= e(__('ai.max_input_chars')) ?></label>
          <input type="number" id="ai_max_input_chars" name="ai_max_input_chars" min="2000" max="200000"
                 value="<?= (int) \Athenaeum\Core\Settings::get('ai.max_input_chars', 12000) ?>">
        </div>
        <div class="form-row">
          <label for="ai_min_confidence"><?= e(__('ai.min_confidence')) ?></label>
          <input type="number" id="ai_min_confidence" name="ai_min_confidence" min="0" max="100"
                 value="<?= (int) \Athenaeum\Core\Settings::get('ai.min_confidence', 80) ?>">
        </div>
      </div>

      <div class="form-row">
        <label class="checkbox">
          <input type="checkbox" name="ai_read_pdf" value="1" <?= \Athenaeum\Core\Settings::bool('ai.read_pdf') ? 'checked' : '' ?>>
          <?= e(__('ai.read_pdf')) ?>
        </label>
      </div>
      <div class="form-row">
        <label class="checkbox">
          <input type="checkbox" name="ai_auto_publish" value="1" <?= \Athenaeum\Core\Settings::bool('ai.auto_publish') ? 'checked' : '' ?>>
          <?= e(__('ai.auto_publish')) ?>
        </label>
      </div>
      <div class="form-row">
        <label class="checkbox">
          <input type="checkbox" name="ai_assign_section" value="1" <?= \Athenaeum\Core\Settings::bool('ai.assign_section') ? 'checked' : '' ?>>
          <?= e(__('ai.assign_section')) ?>
        </label>
      </div>
      <div class="form-row">
        <label class="checkbox">
          <input type="checkbox" name="ai_assign_category" value="1" <?= \Athenaeum\Core\Settings::bool('ai.assign_category') ? 'checked' : '' ?>>
          <?= e(__('ai.assign_category')) ?>
        </label>
      </div>
      <div class="form-row">
        <label class="checkbox">
          <input type="checkbox" name="ai_create_categories" value="1" <?= \Athenaeum\Core\Settings::bool('ai.create_categories') ? 'checked' : '' ?>>
          <?= e(__('ai.create_categories')) ?>
        </label>
      </div>

      <div class="form-row">
        <label for="ai_system_prompt"><?= e(__('ai.system_prompt')) ?></label>
        <textarea id="ai_system_prompt" name="ai_system_prompt" rows="10"
                  placeholder="<?= e($defaultPrompt) ?>"><?= e(\Athenaeum\Core\Settings::string('ai.system_prompt')) ?></textarea>
        <p class="help"><?= e(__('ai.default_prompt')) ?></p>
      </div>

      <div class="form-row">
        <label for="ai_rubric_extra"><?= e(__('ai.rubric_extra')) ?></label>
        <textarea id="ai_rubric_extra" name="ai_rubric_extra" rows="4"><?= e(\Athenaeum\Core\Settings::string('ai.rubric_extra')) ?></textarea>
      </div>

      <div class="form-actions">
        <button class="btn btn--primary" type="submit"><?= e(__('common.save_changes')) ?></button>
      </div>
    </form>

    <form class="form form--tight" method="post" action="<?= e(url('admin.ai.test')) ?>">
      <?= csrf_field() ?>
      <button class="btn btn--ghost btn--small" type="submit"><?= e(__('ai.test')) ?></button>
    </form>

    <?php if ($probe !== null): ?>
      <p class="small">
        <?php if (!empty($probe['ok'])): ?>
          <span class="badge badge--ok"><?= e(__('ai.probe_ok', ['count' => (string) count($probe['models'] ?? [])])) ?></span>
          <br><span class="muted"><?= e(implode(', ', array_slice($probe['models'] ?? [], 0, 12))) ?></span>
        <?php else: ?>
          <span class="badge badge--danger"><?= e(__('ai.probe_failed', ['error' => (string) ($probe['error'] ?? '')])) ?></span>
        <?php endif; ?>
      </p>
    <?php endif; ?>

    <?php
      // The usual cause of "error setting certificate verify locations" is a
      // host without a readable trust store; show what the server actually
      // resolved so the operator can see it at a glance.
      $caBundle = \Athenaeum\Core\Http::caBundle();
    ?>
    <p class="small muted">
      <?= e(__('ai.tls_ca', ['ca' => $caBundle ?? __('ai.tls_ca_none')])) ?>
      <?php if ($caBundle !== null): ?>
        <br><?= e(__('ai.tls_ca_hint')) ?>
      <?php else: ?>
        <br><?= e(__('ai.tls_ca_missing')) ?>
      <?php endif; ?>
    </p>
  </section>

  <section class="card">
    <h2 class="card__title">
      <?= e(__('ai.queue')) ?>
      <span class="badge badge--warn"><?= (int) $queue['total'] ?></span>
    </h2>

    <?php if ($queue['items'] === []): ?>
      <p class="muted small"><?= e(__('admin.no_rows')) ?></p>
    <?php else: ?>
      <form method="post" action="<?= e(url('admin.ai.review')) ?>" data-ai-form>
        <?= csrf_field() ?>
        <p class="row-actions small">
          <button type="button" class="btn btn--ghost btn--tiny" data-select-all="paper_ids"><?= e(__('ai.select_all')) ?></button>
          <button type="button" class="btn btn--ghost btn--tiny" data-select-invert="paper_ids"><?= e(__('ai.select_invert')) ?></button>
        </p>

        <table class="table table--compact">
          <thead>
            <tr>
              <th></th>
              <th><?= e(__('dashboard.table_title')) ?></th>
              <th><?= e(__('ai.status')) ?></th>
              <th><?= e(__('ai.decision')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($queue['items'] as $paper): ?>
              <tr>
                <td><input type="checkbox" name="paper_ids[]" value="<?= (int) $paper['id'] ?>"></td>
                <td class="small">
                  <a href="<?= e(url('admin.paper', ['id' => $paper['id']])) ?>"><?= e(excerpt((string) $paper['title'], 60)) ?></a>
                  <br><code class="muted"><?= e((string) $paper['uid']) ?></code> ·
                  <?= e(excerpt((string) $paper['author_line'], 40)) ?>
                </td>
                <td class="small">
                  <span class="badge <?= e(Paper::aiBadgeClass($paper)) ?>"><?= e(Paper::aiStatusLabel($paper)) ?></span>
                  <?php if (($paper['ai_status'] ?? '') === 'failed' && !empty($paper['ai_reason'])): ?>
                    <div class="muted small" style="max-width:26rem;overflow-wrap:anywhere"
                         title="<?= e((string) $paper['ai_reason']) ?>">
                      <?= e(excerpt((string) $paper['ai_reason'], 160)) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="small">
                  <?php if (!empty($paper['ai_decision'])): ?>
                    <?= e(Paper::aiDecisionLabel((string) $paper['ai_decision'])) ?>
                    <span class="muted">(<?= (int) $paper['ai_confidence'] ?>%)</span>
                  <?php else: ?>
                    <span class="muted">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div class="form-row">
          <label class="checkbox">
            <input type="checkbox" name="auto_publish" value="1"
              <?= \Athenaeum\Core\Settings::bool('ai.auto_publish') ? 'checked' : '' ?>>
            <?= e(__('ai.auto_publish_now')) ?>
          </label>
        </div>

        <div class="form-actions">
          <button class="btn btn--primary btn--small" type="submit"><?= e(__('ai.run_selected')) ?></button>
          <button class="btn btn--ghost btn--small" type="submit" name="scope" value="all_pending">
            <?= e(__('ai.run_all_pending')) ?>
          </button>
        </div>
      </form>
    <?php endif; ?>

    <?php if ($recent !== []): ?>
      <h3><?= e(__('ai.recent')) ?></h3>
      <table class="table table--compact">
        <tbody>
          <?php foreach ($recent as $row): ?>
            <tr>
              <td class="small">
                <a href="<?= e(url('admin.paper', ['id' => $row['id']])) ?>"><?= e(excerpt((string) $row['title'], 50)) ?></a>
              </td>
              <td class="small">
                <span class="badge <?= e(Paper::aiBadgeClass($row)) ?>"><?= e(Paper::aiDecisionLabel($row['ai_decision'])) ?></span>
                <span class="muted"><?= (int) $row['ai_confidence'] ?>%</span>
              </td>
              <td class="small muted right"><?= e(time_ago((string) ($row['ai_reviewed_at'] ?? ''))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <?php if (($unclassifiedTotal ?? 0) > 0): ?>
    <section class="card">
      <h2 class="card__title">
        <?= e(__('ai.unclassified_title')) ?>
        <span class="badge badge--warn"><?= (int) $unclassifiedTotal ?></span>
      </h2>
      <p class="muted small"><?= e(__('ai.unclassified_hint')) ?></p>

      <table class="table table--compact">
        <thead>
          <tr>
            <th><?= e(__('dashboard.table_title')) ?></th>
            <th><?= e(__('ai.author_area')) ?></th>
            <th><?= e(__('ai.status')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($unclassified as $row): ?>
            <tr>
              <td class="small">
                <a href="<?= e(url('admin.paper', ['id' => $row['id']])) ?>"><?= e(excerpt((string) $row['title'], 56)) ?></a>
                <br><code class="muted"><?= e((string) $row['uid']) ?></code>
              </td>
              <td class="small"><?= e(excerpt((string) $row['category_other'], 48)) ?></td>
              <td class="small">
                <span class="badge <?= e(Paper::aiBadgeClass($row)) ?>"><?= e(Paper::aiStatusLabel($row)) ?></span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <form method="post" action="<?= e(url('admin.ai.review')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="scope" value="unclassified">
        <div class="form-actions">
          <button class="btn btn--primary btn--small" type="submit"><?= e(__('ai.run_unclassified')) ?></button>
        </div>
      </form>
    </section>
  <?php endif; ?>
</div>
