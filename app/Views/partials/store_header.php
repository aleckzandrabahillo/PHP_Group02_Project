<?php
require_once __DIR__.'/handoff_art.php';
$current=auth_user();$role=$current['role']??null;
$assessmentUrl=$role==='customer'?url('/assessment'):url($role===null?'/login':'/shop');
$cartCount=(int)($cartCount??0);
?>
<header class="site-header" data-handoff-header>
 <a class="wordmark" href="<?= e(url('/')) ?>" aria-label="Avela home"><?php require __DIR__.'/brand.php'; ?></a>
 <nav class="desktop-nav" aria-label="Primary navigation">
  <button type="button" data-nav-open="shop" aria-expanded="false" aria-controls="shop-panel">Shop</button>
  <a href="<?= e(url('/care-guide')) ?>" <?= is_active_path('/care-guide')?'aria-current="page"':'' ?>>Care guide</a>
  <a href="<?= e(url('/about')) ?>" <?= is_active_path('/about')?'aria-current="page"':'' ?>>Our approach</a>
 </nav>
 <div class="header-actions">
  <a class="header-icon profile-link" href="<?= e(url(match($role){'customer'=>'/profile','admin'=>'/admin','catalog_manager'=>'/catalog',default=>'/login'})) ?>" aria-label="<?= $role===null?'Sign in':'Your profile' ?>"><?= handoff_icon('account') ?></a>
  <a class="header-icon" href="<?= e(url('/search')) ?>" data-nav-open="search" aria-label="Search" aria-expanded="false" aria-controls="search-panel"><?= handoff_icon('search') ?></a>
  <?php if(!in_array($role,['admin','catalog_manager'],true)): ?>
  <a class="header-icon" href="<?= e(url('/favorites')) ?>" aria-label="Favorites" <?= $role==='customer'?'data-favorite-link data-favorite-state-url="'.e(url('/favorites/state')).'"':'' ?>><?= handoff_icon('heart') ?></a>
  <a class="header-icon" href="<?= e(url('/cart')) ?>" aria-label="Shopping bag" <?= $role==='customer'?'data-bag-open aria-haspopup="dialog" aria-controls="avela-bag" aria-expanded="false"':'' ?>><?= handoff_icon('bag') ?><span class="icon-count" data-cart-count <?= $cartCount?'':'hidden' ?>><?= $cartCount ?></span></a>
  <?php else: ?><a class="text-link" href="<?= e(url($role==='admin'?'/admin':'/catalog')) ?>">Workspace</a><?php endif; ?>
  <button class="icon-button mobile-menu" type="button" data-nav-open="shop" aria-label="Open navigation" aria-controls="shop-panel" aria-expanded="false"><?= handoff_icon('menu') ?></button>
 </div>
</header>
<div class="navigation-scrim" data-nav-scrim hidden></div>
<section id="shop-panel" class="commerce-dialog nav-overlay" aria-label="Shop navigation" data-nav-panel="shop" hidden>
 <div class="shop-menu-grid">
  <div class="shop-menu-group"><h3>Featured</h3><a class="shop-all-link" data-menu-entry href="<?= e(url('/shop')) ?>">Shop all essentials</a><a href="<?= e(url('/shop?routine_step[]=cleanse&routine_step[]=condition')) ?>">Wash-day care</a><a href="<?= e(url('/shop?routine_step[]=finish')) ?>">Finishing touches</a><a href="<?= e($assessmentUrl) ?>">Find your routine</a></div>
  <div class="shop-menu-group"><h3>Care categories</h3><?php foreach(handoff_shop_links($storeNav['categories']??[]) as $careLink): ?><a href="<?= e($careLink['url']) ?>"><?= e($careLink['label']) ?></a><?php endforeach; ?><a class="mobile-only" href="<?= e(url('/care-guide')) ?>">Care guide</a><a class="mobile-only" href="<?= e(url('/about')) ?>">Our approach</a></div>
  <div class="shop-menu-card" aria-hidden="true"><div><img src="<?= e(asset('editorial-v2/menu-movement.webp')) ?>" alt="" width="640" height="640"></div></div>
  <div class="shop-menu-card" aria-hidden="true"><div><img src="<?= e(asset('editorial-v2/menu-ritual.webp')) ?>" alt="" width="640" height="640"></div></div>
 </div>
</section>
<section id="search-panel" class="commerce-dialog nav-overlay" data-nav-panel="search" aria-label="Search Avela" hidden>
 <div class="nav-search-top"><form method="get" action="<?= e(url('/search')) ?>" class="nav-search-field"><?= handoff_icon('search') ?><label class="visually-hidden" for="header-search">Search Avela</label><input id="header-search" name="q" type="search" maxlength="100" placeholder="What are you looking for?" autocomplete="off" data-handoff-search="<?= e(url('/search/suggest')) ?>"><button class="clear-search-label" type="button" data-search-clear hidden>Clear</button><button class="icon-button" type="button" data-nav-close aria-label="Close search"><?= handoff_icon('close') ?></button></form></div>
 <div class="search-scroll" data-handoff-search-results aria-live="polite"><p>Search products, care categories, hair concerns, or pages.</p></div>
</section>
<?php if($role==='customer'): ?>
<div class="customer-feedback" role="status" aria-live="polite" data-favorite-feedback hidden></div>
<?php require __DIR__.'/bag_drawer.php'; ?>
<?php endif; ?>
