<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/** Bare layout (PDF viewer page). @var string $content */
?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if (!empty($metaRobots)): ?>
<meta name="robots" content="<?= e((string) $metaRobots) ?>">
<?php endif; ?>
<title><?= e($title ?? '') ?></title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body class="blank">
<?= $content ?>
</body>
</html>
