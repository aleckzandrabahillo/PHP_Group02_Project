<?php
$u=auth_user();$role=$u['role']??'';$admin=$role==='admin';$currentStaffPath=parse_url(current_relative_uri(),PHP_URL_PATH);
$groups=[];
if($admin)$groups['Customers & team']=[['/admin/users','Customers'],['/admin/staff','Staff & roles']];
$groups['Catalog']=[['/catalog','Overview'],['/catalog/products','Products'],['/catalog/categories','Categories'],['/catalog/inventory','Inventory'],['/catalog/discounts','Discounts']];
if($admin)$groups['Orders']=[['/admin/orders','All orders']];
if($admin)$groups['Security & activity']=[['/admin/security','Security controls'],['/admin/auth-logs','Sign-in activity'],['/admin/audit-logs','Audit trail']];
$groups['My account']=[[$admin?'/admin/profile':'/catalog/profile','Profile'],['/account/mfa','Authenticator'],['/account/password','Change password']];
?>
<button class="staff-nav-backdrop" type="button" data-staff-nav-close aria-label="Close staff navigation" hidden></button>
<aside class="staff-sidebar" id="staff-navigation" aria-label="Staff navigation"><button class="staff-nav-close btn btn-secondary btn-sm" type="button" data-staff-nav-close>Close navigation</button>
<a class="brand staff-brand" href="<?= e(url($admin?'/admin':'/catalog')) ?>"><img src="<?= e(asset('images/brand/wordmark-humanist-refined.svg')) ?>" alt="Avela"></a>
<nav aria-label="Staff pages"><?php if($admin): ?><a class="<?= $currentStaffPath==='/admin'?'active':'' ?>" href="<?= e(url('/admin')) ?>" <?= $currentStaffPath==='/admin'?'aria-current="page"':'' ?>>Dashboard</a><?php endif; ?>
<?php foreach($groups as $label=>$items): $inGroup=false;foreach($items as [$path,$name])if(($path==='/catalog'?$currentStaffPath===$path:is_active_path($path)))$inGroup=true; ?>
<details <?= $inGroup||$label==='Catalog'?'open':'' ?>><summary><?= e($label) ?></summary><div><?php foreach($items as [$path,$name]): $active=$path==='/catalog'?$currentStaffPath===$path:is_active_path($path); ?><a class="<?= $active?'active':'' ?>" href="<?= e(url($path)) ?>" <?= $active?'aria-current="page"':'' ?>><?= e($name) ?></a><?php endforeach; ?></div></details>
<?php endforeach; ?></nav></aside>
