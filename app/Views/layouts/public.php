<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Avela') ?> · Avela</title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/customer.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/experience.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/motion.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/refinement.css')) ?>">
</head>
<?php $isHome = is_active_path('/'); ?>
<body class="customer-experience public-body <?= $isHome ? 'public-home' : 'public-inner' ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<?php $transparentHeader = $isHome; require dirname(__DIR__) . '/partials/store_header.php'; ?>
<div class="store-flash-stack"><?php require dirname(__DIR__) . '/partials/flashes.php'; ?></div>
<?= $content ?>
<?php require dirname(__DIR__) . '/partials/store_footer.php'; ?>
<script src="<?= e(asset('js/motion.js')) ?>" defer></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php require dirname(__DIR__) . '/partials/session_watch.php'; ?>
<script src="<?= e(asset('js/experience.js')) ?>" defer></script>
<script src="<?= e(asset('js/refinement.js')) ?>" defer></script>
<script src="<?= e(asset('js/collections.js')) ?>" defer></script>
<script src="<?= e(asset('js/photo-actions.js')) ?>" defer></script>
</body>
</html>
