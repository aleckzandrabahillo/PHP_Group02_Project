<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Account') ?> · Avela</title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?php if (!empty($recaptchaSiteKey)): ?><script src="https://www.google.com/recaptcha/api.js" async defer></script><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/customer.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/experience.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/motion.css')) ?>">
</head>
<body class="customer-experience auth-body">
<a class="skip-link" href="#main-content">Skip to content</a>
<a class="auth-logo" href="<?= e(url('/')) ?>"><?php require dirname(__DIR__) . '/partials/brand.php'; ?></a>
<div class="auth-shell">
  <aside class="auth-art">
    <span class="eyebrow">PERSONAL HAIR CARE</span>
    <h2>Care that starts with understanding your hair.</h2>
    <p>Everyday essentials. A personal shortlist. Your care, at your own pace.</p>
    <img class="auth-editorial" src="<?= e(asset('images/editorial/hair-editorial.jpg')) ?>" alt="" width="1200" height="1598" decoding="async">
  </aside>
  <main id="main-content" tabindex="-1" class="auth-panel">
    <?php require dirname(__DIR__) . '/partials/flashes.php'; ?>
    <?= $content ?>

    <p class="auth-switch"><a href="<?= e(url('/privacy')) ?>">Privacy &amp; cookies</a></p>
  </main>
</div>
<script src="<?= e(asset('js/motion.js')) ?>" defer></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php require dirname(__DIR__) . '/partials/session_watch.php'; ?>
<script src="<?= e(asset('js/experience.js')) ?>" defer></script>
</body>
</html>
