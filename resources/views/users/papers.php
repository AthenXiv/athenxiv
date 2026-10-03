<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/** All published papers of one author. @var array $profile,$result */
$name = (string) (($profile['display_name'] ?? '') !== '' ? $profile['display_name'] : $profile['nickname']);
?>
<div class="container">
  <header class="page-head">
    <h1><?= e(__('user.papers_of', ['name' => $name])) ?></h1>
    <p class="small"><a href="<?= e(url('user.show', ['uid' => $profile['uid']])) ?>">← <?= e(__('user.public_profile')) ?></a></p>
  </header>

  <?php if ($result['items'] === []): ?>
    <p class="empty"><?= e(__('user.no_papers')) ?></p>
  <?php else: ?>
    <div class="paper-grid">
      <?php foreach ($result['items'] as $paper): ?>
        <?php \Athenaeum\Core\View::partial('partials/paper_card', ['paper' => $paper]); ?>
      <?php endforeach; ?>
    </div>
    <?php \Athenaeum\Core\View::partial('partials/pagination', [
        'result'   => $result,
        'basePath' => '/u/' . $profile['uid'] . '/papers',
        'query'    => [],
    ]); ?>
  <?php endif; ?>
</div>
