<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Public author profile: avatar, bio, Markdown page, linked accounts,
 * published papers.
 *
 * @var array $profile,$result,$links
 * @var string|null $avatar,$orcid
 * @var string $homepageHtml
 * @var bool $isSelf
 */

$name = (string) (($profile['display_name'] ?? '') !== '' ? $profile['display_name'] : $profile['nickname']);
?>
<div class="container">
  <header class="profile-head">
    <div class="profile-head__avatar">
      <?php if ($avatar !== null): ?>
        <img class="avatar" src="<?= e($avatar) ?>" alt="<?= e($name) ?>">
      <?php else: ?>
        <span class="avatar avatar--initial avatar--large"><?= e(mb_substr($name, 0, 1)) ?></span>
      <?php endif; ?>
    </div>

    <div class="profile-head__body">
      <h1><?= e($name) ?></h1>
      <p class="profile-head__id small muted">
        <?= e(__('user.uid')) ?>: <code><?= e($profile['uid']) ?></code>
        <?php if (!empty($profile['affiliation'])): ?>
          · <?= e((string) $profile['affiliation']) ?>
        <?php endif; ?>
        · <?= e(__('user.joined')) ?> <?= e(format_date((string) $profile['created_at'])) ?>
      </p>

      <?php if (!empty($profile['bio'])): ?>
        <p class="profile-head__bio"><?= e((string) $profile['bio']) ?></p>
      <?php elseif ($isSelf): ?>
        <p class="muted small"><?= e(__('user.bio_empty')) ?></p>
      <?php endif; ?>

      <?php if ($links !== []): ?>
        <ul class="profile-links">
          <?php foreach ($links as $link): ?>
            <li>
              <a href="<?= e($link['url']) ?>" target="_blank" rel="noopener me"
                 class="profile-links__item profile-links__item--<?= e($link['platform']) ?>">
                <?= e($link['label']) ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if ($isSelf): ?>
        <p class="profile-head__actions">
          <a class="btn btn--ghost btn--small" href="<?= e(url('settings')) ?>"><?= e(__('user.edit_profile')) ?></a>
          <a class="btn btn--ghost btn--small" href="<?= e(url('dashboard')) ?>"><?= e(__('nav.dashboard')) ?></a>
        </p>
      <?php endif; ?>
    </div>

    <?php
    $published = 0;
    $downloads = 0;
    foreach ($result['items'] as $item) {
        $downloads += (int) $item['downloads'];
    }
    $published = (int) $result['total'];
    ?>
    <dl class="profile-head__stats">
      <div><dt><?= e(__('user.papers_published')) ?></dt><dd><?= $published ?></dd></div>
      <div><dt><?= e(__('user.total_downloads')) ?></dt><dd><?= $downloads ?></dd></div>
    </dl>
  </header>

  <?php if ($homepageHtml !== ''): ?>
    <section class="profile-page markdown">
      <?= $homepageHtml ?>
    </section>
  <?php endif; ?>

  <section class="section-block">
    <div class="section-block__head">
      <h2><?= e(__('user.papers_of', ['name' => $name])) ?></h2>
      <?php if ($result['total'] > count($result['items'])): ?>
        <a class="small" href="<?= e(url('user.papers', ['uid' => $profile['uid']])) ?>"><?= e(__('home.view_all')) ?> →</a>
      <?php endif; ?>
    </div>

    <?php if ($result['items'] === []): ?>
      <p class="empty"><?= e(__('user.no_papers')) ?></p>
    <?php else: ?>
      <div class="paper-grid">
        <?php foreach ($result['items'] as $paper): ?>
          <?php \Athenaeum\Core\View::partial('partials/paper_card', ['paper' => $paper]); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
