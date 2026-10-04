<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Avela') ?> · Avela</title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="app-body">
<?php $current = auth_user(); ?>
<?php if (($current['role'] ?? '') === 'customer'): ?>
  <?php require dirname(__DIR__) . '/partials/customer_nav.php'; ?>
  <main class="customer-main">
    <?php require dirname(__DIR__) . '/partials/flashes.php'; ?>
    <?= $content ?>
  </main>
<?php else: ?>
  <div class="staff-shell">
    <?php require dirname(__DIR__) . '/partials/staff_sidebar.php'; ?>
    <div class="staff-workspace">
      <header class="staff-topbar">
        <button class="icon-button sidebar-toggle" type="button" aria-label="Toggle menu">☰</button>
        <div><span class="muted small"><?= e(($current['role'] ?? '') === 'admin' ? 'Administrator' : 'Catalog Manager') ?></span><strong><?= e($pageTitle ?? 'Avela') ?></strong></div>
        <div class="topbar-user"><span class="avatar"><?= e(strtoupper(substr($current['username'] ?? 'A',0,1))) ?></span><span><?= e($current['username'] ?? '') ?></span></div>
      </header>
      <main class="staff-main">
        <?php require dirname(__DIR__) . '/partials/flashes.php'; ?>
        <?= $content ?>
      </main>
    </div>
  </div>
<?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
