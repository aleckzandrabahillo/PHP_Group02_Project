<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Account') ?> · Avela</title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body class="auth-body">
<a class="auth-logo" href="<?= e(url('/')) ?>"><img src="<?= e(asset('images/avela-logo.png')) ?>" alt="Avela"></a>
<div class="auth-shell">
  <aside class="auth-art">
    <span class="eyebrow">PERSONAL HAIR CARE</span>
    <h1>Care that starts with understanding your hair.</h1>
    <p>Avela keeps shopping simple: understand your hair profile, find better-matched products, and build a routine that makes sense.</p>
    <div class="leaf-mark" aria-hidden="true"><span></span><span></span><span></span></div>
  </aside>
  <main class="auth-panel">
    <?php require dirname(__DIR__) . '/partials/flashes.php'; ?>
    <?= $content ?>
  </main>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
