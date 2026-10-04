<?php
$u=auth_user(); $role=$u['role'] ?? '';
$items = $role === 'admin' ? [
 ['/admin','Dashboard'], ['/admin/users','Users'], ['/admin/staff','Staff & Roles'], ['/admin/orders','Orders'], ['/catalog','Catalog'], ['/admin/security','Security'], ['/admin/auth-logs','Authentication Logs'], ['/admin/audit-logs','Audit Logs'], ['/admin/profile','Profile']
] : [
 ['/catalog','Dashboard'], ['/catalog/products','Products'], ['/catalog/categories','Categories'], ['/catalog/inventory','Inventory'], ['/catalog/profile','Profile']
];
?>
<aside class="staff-sidebar">
  <a class="brand staff-brand" href="<?= e(url($role==='admin'?'/admin':'/catalog')) ?>"><img src="<?= e(asset('images/avela-logo.png')) ?>" alt="Avela"></a>
  <div class="role-pill"><?= e($role==='admin'?'Administrator':'Catalog Manager') ?></div>
  <nav>
    <?php foreach($items as [$path,$label]): ?><a class="<?= is_active_path($path)?'active':'' ?>" href="<?= e(url($path)) ?>"><?= e($label) ?></a><?php endforeach; ?>
  </nav>
  <form method="post" action="<?= e(url('/logout')) ?>" class="sidebar-logout"><?= csrf_field() ?><button type="submit" class="btn btn-secondary w-full">Sign out</button></form>
</aside>
