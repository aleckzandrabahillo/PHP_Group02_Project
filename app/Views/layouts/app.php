<?php
$current=auth_user();$customerLayout=($current['role']??'')==='customer';
// Account tasks share a focused shell; storefront/editorial views retain their footer.
$accountArea=str_starts_with($view,'account/')||in_array($view,['customer/dashboard','customer/profile','customer/profile-verify-otp','customer/assessment','customer/recommendations','customer/routine','customer/settings','customer/orders','customer/order'],true);
if($customerLayout&&$accountArea)$showStoreFooter=false;
?>
<!doctype html>
<html lang="en" class="<?= $customerLayout ? 'avela-v3-document' : '' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Avela</title>
<?php if ($customerLayout): ?>
<link rel="stylesheet" href="<?= e(asset('css/handoff-v3.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/handoff-functional.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/control-surfaces.css')) ?>">
<?php else: ?>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/experience.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/motion.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/refinement.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/staff.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/editorial-v2.css')) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/date-picker.css')) ?>">
<link rel="icon" href="<?= e(asset('editorial-v2/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body class="<?= $customerLayout ? 'avela-v3' : 'avela-v2 app-body avela-staff' ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<?php if (($current['role'] ?? '') === 'customer'): ?>
  <?php require dirname(__DIR__) . '/partials/customer_nav.php'; ?>
  <main id="main-content" tabindex="-1" class="customer-main">
    <?php require dirname(__DIR__) . '/partials/flashes.php'; ?>
    <?= $content ?>
  </main>
  <?php if($showStoreFooter??true) require dirname(__DIR__) . '/partials/store_footer.php'; ?>
<?php else: $headerPhoto=(new \App\Models\User())->profileFor((int)$current['id'])['profile_image']??null; ?>
  <div class="staff-shell">
    <?php require dirname(__DIR__) . '/partials/staff_sidebar.php'; ?>
    <div class="staff-workspace" data-ui>
      <header class="staff-topbar">
        <button class="icon-button sidebar-toggle" type="button" aria-label="Open staff navigation" aria-controls="staff-navigation" aria-expanded="false"><?= staff_icon('menu') ?></button>
        <div class="staff-breadcrumb"><span><?= ($current['role']??'')==='admin'?'Administration':'Catalog workspace' ?></span><span aria-hidden="true">/</span><strong><?= e($pageTitle ?? 'Avela') ?></strong></div><a class="staff-store-link" href="<?= e(url('/')) ?>"><?= staff_icon('store') ?><span>View store</span></a>
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
<script src="<?= e(asset('js/date-picker.js')) ?>" defer></script>
<script src="<?= e(asset('js/order-payment.js')) ?>" defer></script>
<?php require dirname(__DIR__) . '/partials/session_watch.php'; ?>
<script src="<?= e(asset('js/experience.js')) ?>" defer></script>
<script src="<?= e(asset('js/refinement.js')) ?>" defer></script>
<script src="<?= e(asset('js/collections.js')) ?>" defer></script>
<script src="<?= e(asset('js/photo-actions.js')) ?>" defer></script>
<script src="<?= e(asset('js/staff-navigation.js')) ?>" defer></script>
<?php if(!$customerLayout): ?><script src="<?= e(asset('js/staff-experience.js')) ?>" defer></script><?php endif; ?>
<script src="<?= e(asset('js/handoff-ui.js')) ?>" defer></script>
<?php if($customerLayout): ?><script src="<?= e(asset('js/account-navigation.js')) ?>" defer></script><?php endif; ?>
</body>
</html>
