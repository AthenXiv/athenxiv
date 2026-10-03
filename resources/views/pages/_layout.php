<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Static page layout used by /about, /about/timestamping and /guidelines.
 *
 * @var string $pageTitle
 * @var string $body  already-rendered HTML
 */
?>
<div class="container">
  <article class="prose-page">
    <h1><?= e($pageTitle) ?></h1>
    <?= $body ?>
  </article>
</div>
