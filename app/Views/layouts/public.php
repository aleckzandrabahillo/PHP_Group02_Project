<!doctype html>
<html lang="en" class="avela-v3-document">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Avela</title>
<link rel="icon" href="<?= e(asset('editorial-v2/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/handoff-v3.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/handoff-functional.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/control-surfaces.css')) ?>">
</head>
<?php $isHome = is_active_path('/'); ?>
<body class="avela-v3 <?= $isHome ? 'home-page' : '' ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<?php $transparentHeader = $isHome; require dirname(__DIR__) . '/partials/store_header.php'; ?>
<div class="store-flash-stack"><?php require dirname(__DIR__) . '/partials/flashes.php'; ?></div>
<?= $content ?>
<?php if ($isHome) require dirname(__DIR__) . '/partials/first_login_offer.php'; ?>
<?php require dirname(__DIR__) . '/partials/store_footer.php'; ?>
<script src="<?= e(asset('js/motion.js')) ?>" defer></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php require dirname(__DIR__) . '/partials/session_watch.php'; ?>
<script src="<?= e(asset('js/experience.js')) ?>" defer></script>
<script src="<?= e(asset('js/refinement.js')) ?>" defer></script>
<script src="<?= e(asset('js/collections.js')) ?>" defer></script>
<script src="<?= e(asset('js/photo-actions.js')) ?>" defer></script>
<script src="<?= e(asset('js/handoff-ui.js')) ?>" defer></script>
</body>
</html>
