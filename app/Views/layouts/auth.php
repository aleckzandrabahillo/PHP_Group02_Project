<!doctype html>
<html lang="en" class="avela-v3-document">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Avela</title>
<?php if (!empty($recaptchaSiteKey)): ?><script src="https://www.google.com/recaptcha/api.js" async defer></script><?php endif; ?>
<link rel="icon" href="<?= e(asset('editorial-v2/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/handoff-v3.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/handoff-functional.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/control-surfaces.css')) ?>">
</head>
<body class="avela-v3 auth-page <?= is_active_path('/register')?'sign-up-page':'' ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="site-header focus-header"><a class="wordmark" href="<?= e(url('/')) ?>" aria-label="Avela home"><?php require dirname(__DIR__) . '/partials/brand.php'; ?></a></header>
<div class="auth-shell">
  <main id="main-content" tabindex="-1" class="auth-main auth-panel">
    <?php require dirname(__DIR__) . '/partials/flashes.php'; ?>
    <?= $content ?>
    <?php if(is_active_path('/login')||is_active_path('/register')): ?><nav class="auth-privacy" aria-label="Privacy"><a href="<?= e(url('/privacy')) ?>">Privacy policy</a></nav><?php endif; ?>

  </main>
  <aside class="auth-art">
    <div class="auth-art-caption"><p class="eyebrow">ROOTED IN YOU.</p><h2>Your hair.<br>Your kind of care.</h2><p>Every texture. Every version of you.</p></div>
    <img class="auth-editorial" src="<?= e(asset('editorial-v2/account-editorial.webp')) ?>" alt="" width="1024" height="1536" decoding="async">
  </aside>
</div>
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
