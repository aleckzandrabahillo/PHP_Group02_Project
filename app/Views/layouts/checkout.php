<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Checkout') ?> · Avela</title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/customer.css')) ?>">
</head>
<body class="customer-experience checkout-body">
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="checkout-header">
  <div class="checkout-header-inner">
    <span aria-hidden="true"></span>
    <a class="checkout-brand" href="<?= e(url('/')) ?>" aria-label="Avela home">
      <?php require dirname(__DIR__) . '/partials/brand.php'; ?>
    </a>
    <a class="checkout-bag-link" href="<?= e(url('/cart')) ?>" aria-label="Return to shopping bag" title="Shopping bag">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8.5h11l1 11h-13l1-11Z"/><path d="M9 9V6.5a3 3 0 0 1 6 0V9"/></svg>
      <?php if ((int) ($cart['item_count'] ?? 0) > 0): ?><span><?= (int) $cart['item_count'] ?></span><?php endif; ?>
    </a>
  </div>
</header>
<div class="checkout-flash-stack"><?php require dirname(__DIR__) . '/partials/flashes.php'; ?></div>
<main id="main-content" tabindex="-1"><?= $content ?></main>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
