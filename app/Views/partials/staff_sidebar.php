<?php
$u=auth_user();$admin=($u['role']??'')==='admin';$currentStaffPath=parse_url(current_relative_uri(),PHP_URL_PATH);$dashboardPath=$admin?'/admin':'/catalog';
$groups=[];
if($admin)$groups['Customers & team']=['customers',[['/admin/users','Customers'],['/admin/staff','Staff & roles']]];
$groups['Catalog']=['catalog',[['/catalog','Overview'],['/catalog/products','Products'],['/catalog/categories','Categories'],['/catalog/inventory','Inventory'],['/catalog/discounts','Discounts']]];
if(!$admin)array_shift($groups['Catalog'][1]);
if($admin)$groups['Orders']=['orders','/admin/orders'];
if($admin)$groups['Security & activity']=['security',[['/admin/security','Security controls'],['/admin/auth-logs','Sign-in activity'],['/admin/audit-logs','Audit trail']]];
$groups['My account']=['user',[[$admin?'/admin/profile':'/catalog/profile','Profile'],['/account/mfa','2-step verification'],['/account/password','Change password']]];
?>
<button class="staff-nav-backdrop" type="button" data-staff-nav-close aria-label="Close staff navigation" hidden></button>
<aside class="staff-sidebar" id="staff-navigation" aria-label="Staff navigation">
 <button class="staff-nav-close btn btn-secondary btn-sm" type="button" data-staff-nav-close>Close navigation</button>
 <a class="brand staff-brand" href="<?= e(url($dashboardPath)) ?>" aria-label="Avela dashboard"><?php require __DIR__.'/brand.php'; ?></a>
 <p class="staff-workspace-name"><?= $admin?'Store administration':'Catalog workspace' ?></p>
 <nav aria-label="Staff pages"><a class="staff-dashboard-link <?= $currentStaffPath===$dashboardPath?'active':'' ?>" href="<?= e(url($dashboardPath)) ?>" <?= $currentStaffPath===$dashboardPath?'aria-current="page"':'' ?>><?= staff_icon('overview') ?><span>Overview</span></a>
 <?php foreach($groups as $label=>[$icon,$items]): if(is_string($items)||count($items)===1):$path=is_string($items)?$items:$items[0][0];$active=is_active_path($path); ?><a class="staff-single-link <?= $active?'active':'' ?>" href="<?= e(url($path)) ?>" <?= $active?'aria-current="page"':'' ?>><?= staff_icon($icon) ?><span><?= e($label) ?></span></a><?php continue;endif; $inGroup=false;foreach($items as [$path,$name])if($path==='/catalog'?$currentStaffPath===$path:is_active_path($path))$inGroup=true; ?>
 <details <?= $inGroup?'open':'' ?>><summary><?= staff_icon($icon) ?><span><?= e($label) ?></span><span class="staff-group-sign" aria-hidden="true"></span></summary><div><?php foreach($items as [$path,$name]):$active=$path==='/catalog'?$currentStaffPath===$path:is_active_path($path); ?><a class="<?= $active?'active':'' ?>" href="<?= e(url($path)) ?>" <?= $active?'aria-current="page"':'' ?>><?= e($name) ?></a><?php endforeach; ?></div></details>
 <?php endforeach; ?></nav>
 <div class="staff-sidebar-bottom"><span class="staff-workspace-role"><?= $admin?'Administrator':'Catalog Manager' ?></span><form class="account-signout staff-signout" method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="btn btn-secondary" type="submit"><?= staff_icon('logout') ?>Sign out</button></form></div>
</aside>
