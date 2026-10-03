<?php
/**
 * Search-engine visits (the poor man's access log for shared hosting).
 *
 * @var array<int,array{bot:string,hits:int,last:string,last_path:string}> $summary
 * @var array<int,array{time:string,bot:string,ip:string,path:string,ua:string}> $hits
 * @var string $file
 */
if (!defined('ATHENAEUM_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}
?>
<div class="page-head">
  <div>
    <h1><?= e(__('admin.crawlers_title')) ?></h1>
    <p class="muted"><?= e(__('admin.crawlers_intro')) ?></p>
  </div>
</div>

<?php if ($summary === []): ?>
  <div class="notice notice--warn">
    <strong><?= e(__('admin.crawlers_none')) ?></strong>
    <p class="small">
      Sitemap: <a href="<?= e(path_url('/sitemap.xml')) ?>" target="_blank" rel="noopener"><?= e(path_url('/sitemap.xml')) ?></a>
      · robots.txt: <a href="<?= e(path_url('/robots.txt')) ?>" target="_blank" rel="noopener"><?= e(path_url('/robots.txt')) ?></a>
    </p>
  </div>
<?php else: ?>
  <section class="card">
    <h2><?= e(__('admin.crawlers_summary')) ?></h2>
    <table class="table">
      <thead>
        <tr>
          <th><?= e(__('admin.crawlers_bot')) ?></th>
          <th><?= e(__('admin.crawlers_hits')) ?></th>
          <th><?= e(__('admin.crawlers_last')) ?></th>
          <th><?= e(__('admin.crawlers_path')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($summary as $row): ?>
          <tr>
            <td><strong><?= e((string) $row['bot']) ?></strong></td>
            <td><?= (int) $row['hits'] ?></td>
            <td class="small"><?= e((string) $row['last']) ?></td>
            <td class="small"><?= e((string) $row['last_path']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="card">
    <h2><?= e(__('admin.crawlers_recent')) ?></h2>
    <p class="muted small"><?= e($file) ?></p>
    <table class="table">
      <thead>
        <tr>
          <th><?= e(__('admin.crawlers_time')) ?></th>
          <th><?= e(__('admin.crawlers_bot')) ?></th>
          <th>IP</th>
          <th><?= e(__('admin.crawlers_path')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($hits as $hit): ?>
          <tr>
            <td class="small"><?= e((string) $hit['time']) ?></td>
            <td><?= e((string) $hit['bot']) ?></td>
            <td class="small"><?= e((string) $hit['ip']) ?></td>
            <td class="small"><?= e((string) $hit['path']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
<?php endif; ?>
