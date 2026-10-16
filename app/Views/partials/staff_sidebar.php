<?php
$u=auth_user(); $role=$u['role'] ?? '';
$items = $role === 'admin' ? [
 ['/admin','Dashboard'], ['/admin/users','Users'], ['/admin/staff','Staff & Roles'], ['/admin/orders','Orders'], ['/catalog','Catalog overview'], ['/catalog/products','Products'], ['/catalog/categories','Categories'], ['/catalog/inventory','Inventory'], ['/admin/security','Security'], ['/admin/auth-logs','Authentication Logs'], ['/admin/audit-logs','Audit Logs'], ['/admin/profile','Profile']
] : [
 ['/catalog','Dashboard'], ['/catalog/products','Products'], ['/catalog/categories','Categories'], ['/catalog/inventory','Inventory'], ['/catalog/profile','Profile']
];
$items[] = ['/account/mfa', 'Authenticator'];
$currentStaffPath = parse_url(current_relative_uri(), PHP_URL_PATH);
?>
<button class="staff-nav-backdrop" type="button" data-staff-nav-close aria-label="Close staff navigation" hidden></button>
<aside class="staff-sidebar" id="staff-navigation" aria-label="Staff navigation">
  <button class="staff-nav-close btn btn-secondary btn-sm" type="button" data-staff-nav-close>Close navigation</button>
  <a class="brand staff-brand" href="<?= e(url($role==='admin'?'/admin':'/catalog')) ?>"><img src="<?= e(asset('images/avela-logo.png')) ?>" alt="Avela"></a>
  <div class="role-pill"><?= e($role==='admin'?'Administrator':'Catalog Manager') ?></div>
  <nav aria-label="Staff pages">
    <?php foreach($items as [$path,$label]): ?>
      <?php $active = in_array($path, ['/admin', '/catalog'], true) ? $currentStaffPath === $path : is_active_path($path); ?>
      <a class="<?= $active?'active':'' ?>" <?= $active?'aria-current="page"':'' ?> href="<?= e(url($path)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <form method="post" action="<?= e(url('/logout')) ?>" class="sidebar-logout"><?= csrf_field() ?><button type="submit" class="btn btn-secondary w-full">Sign out</button></form>
</aside>
