<?php $current = auth_user(); $customerLayout = ($current['role'] ?? '') === 'customer'; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Avela') ?> · Avela</title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?php if ($customerLayout): ?><link rel="stylesheet" href="<?= e(asset('css/customer.css')) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/experience.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/motion.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/refinement.css')) ?>">
</head>
<body class="app-body <?= $customerLayout ? 'customer-experience' : '' ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<?php if (($current['role'] ?? '') === 'customer'): ?>
  <?php require dirname(__DIR__) . '/partials/customer_nav.php'; ?>
  <main id="main-content" tabindex="-1" class="customer-main">
    <?php require dirname(__DIR__) . '/partials/flashes.php'; ?>
    <?= $content ?>
  </main>
  <?php require dirname(__DIR__) . '/partials/store_footer.php'; ?>
<?php else: $headerPhoto=(new \App\Models\User())->profileFor((int)$current['id'])['profile_image']??null; ?>
  <div class="staff-shell">
    <?php require dirname(__DIR__) . '/partials/staff_sidebar.php'; ?>
    <div class="staff-workspace">
      <header class="staff-topbar">
        <button class="icon-button sidebar-toggle" type="button" aria-label="Open staff navigation" aria-controls="staff-navigation" aria-expanded="false">☰</button>
        <div><strong><?= e($pageTitle ?? 'Avela') ?></strong></div>
        <a class="topbar-user staff-profile-link" href="<?= e(url(($current['role']??'')==='admin'?'/admin/profile':'/catalog/profile')) ?>"><span class="avatar" aria-hidden="true"><?php if($headerPhoto): ?><img src="<?= e(url('/'.$headerPhoto)) ?>" alt=""><?php else: ?><?= e(strtoupper(substr($current['username'] ?? 'A',0,1))) ?><?php endif; ?></span><span><?= e($current['username'] ?? '') ?><small><?= ($current['role']??'')==='admin'?'Administrator':'Catalog Manager' ?></small></span></a>
      </header>
      <main id="main-content" tabindex="-1" class="staff-main">
        <?php require dirname(__DIR__) . '/partials/flashes.php'; ?>
        <?= $content ?>
      </main>
    </div>
  </div>
<?php endif; ?>
<script src="<?= e(asset('js/motion.js')) ?>" defer></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php require dirname(__DIR__) . '/partials/session_watch.php'; ?>
<script src="<?= e(asset('js/experience.js')) ?>" defer></script>
<script src="<?= e(asset('js/refinement.js')) ?>" defer></script>
<script src="<?= e(asset('js/collections.js')) ?>" defer></script>
<script src="<?= e(asset('js/photo-actions.js')) ?>" defer></script>
<script src="<?= e(asset('js/staff-navigation.js')) ?>" defer></script>
</body>
</html>
