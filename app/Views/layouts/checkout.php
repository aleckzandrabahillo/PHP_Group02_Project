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
<body class="avela-v3 checkout-page">
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="checkout-header">
    <span aria-hidden="true"></span>
    <a class="wordmark checkout-brand" href="<?= e(url('/')) ?>" aria-label="Avela home">
      <?php require dirname(__DIR__) . '/partials/brand.php'; ?>
    </a>
    <a class="checkout-bag-link" data-bag-open aria-controls="avela-bag" aria-expanded="false" href="<?= e(url('/cart')) ?>" aria-label="Return to shopping bag" title="Shopping bag">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8.5h11l1 11h-13l1-11Z"/><path d="M9 9V6.5a3 3 0 0 1 6 0V9"/></svg>
      <?php if ((int) ($cart['item_count'] ?? 0) > 0): ?><span><?= (int) $cart['item_count'] ?></span><?php endif; ?>
    </a>
</header>
<div class="checkout-flash-stack"><?php require dirname(__DIR__) . '/partials/flashes.php'; ?></div>
<main id="main-content" tabindex="-1"><?= $content ?></main>
<?php require dirname(__DIR__) . '/partials/bag_drawer.php'; ?>
<script src="<?= e(asset('js/motion.js')) ?>" defer></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<script src="<?= e(asset('js/checkout-delivery.js')) ?>" defer></script>
<?php require dirname(__DIR__) . '/partials/session_watch.php'; ?>
<script src="<?= e(asset('js/experience.js')) ?>" defer></script>
<script src="<?= e(asset('js/refinement.js')) ?>" defer></script>
<script src="<?= e(asset('js/collections.js')) ?>" defer></script>
<script src="<?= e(asset('js/photo-actions.js')) ?>" defer></script>
<script src="<?= e(asset('js/handoff-ui.js')) ?>" defer></script>
</body>
</html>
