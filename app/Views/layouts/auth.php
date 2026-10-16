<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Account') ?> · Avela</title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?php if (!empty($recaptchaSiteKey)): ?><script src="https://www.google.com/recaptcha/api.js" async defer></script><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/customer.css')) ?>">
</head>
<body class="customer-experience auth-body">
<a class="skip-link" href="#main-content">Skip to content</a>
<a class="auth-logo" href="<?= e(url('/')) ?>"><?php require dirname(__DIR__) . '/partials/brand.php'; ?></a>
<div class="auth-shell">
  <aside class="auth-art">
    <span class="eyebrow">PERSONAL HAIR CARE</span>
    <h2>Care that starts with understanding your hair.</h2>
    <p>Browse everyday hair care, compare product details, and keep your bag and orders in one place.</p>
  </aside>
  <main id="main-content" tabindex="-1" class="auth-panel">
    <?php require dirname(__DIR__) . '/partials/flashes.php'; ?>
    <h1 class="sr-only"><?= e($pageTitle ?? 'Your account') ?></h1>
    <?= $content ?>
  </main>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
