<?php
$current = auth_user();
$role = $current['role'] ?? null;
$transparentHeader = (bool) ($transparentHeader ?? false);
$assessmentUrl = $role === 'customer' ? url('/assessment') : ($role === null ? url('/register') : null);
$storeNav = $storeNav ?? ['categories' => [], 'concerns' => []];
$favoriteCount = (int) ($favoriteCount ?? 0);
$cartCount = (int) ($cartCount ?? 0);
$shopActive = is_active_path('/shop') || is_active_path('/product');
?>
<header class="site-header <?= $transparentHeader ? 'site-header--overlay' : 'site-header--solid' ?>"
        data-site-header
        data-header-overlay="<?= $transparentHeader ? 'true' : 'false' ?>">
  <div class="site-header-inner">
    <a class="brand site-header-brand" href="<?= e(url('/')) ?>" aria-label="Avela home">
      <img src="<?= e(asset('images/avela-logo.png')) ?>" alt="Avela">
    </a>

    <nav class="store-nav-links" aria-label="Primary navigation">
      <div class="store-nav-item store-nav-item--shop" data-mega-menu>
        <button class="store-nav-trigger <?= $shopActive ? 'active' : '' ?>" type="button"
                aria-haspopup="true" aria-expanded="false" aria-controls="shop-mega-menu" data-mega-trigger>Shop</button>
        <div class="shop-mega-menu" id="shop-mega-menu" data-mega-panel aria-label="Shop menu" aria-hidden="true">
          <div class="shop-mega-menu-inner">
            <section>
              <span class="mega-kicker">SHOP</span>
              <a href="<?= e(url('/shop')) ?>">Shop all hair care</a>
              <a href="<?= e(url('/shop?availability=in_stock')) ?>">In-stock products</a>
            </section>
            <section>
              <span class="mega-kicker">BY CATEGORY</span>
              <?php foreach (($storeNav['categories'] ?? []) as $category): ?>
                <a href="<?= e(url('/shop?category[]=' . (int) $category['id'])) ?>"><?= e($category['name']) ?></a>
              <?php endforeach; ?>
            </section>
            <section>
              <span class="mega-kicker">BY HAIR CONCERN</span>
              <?php foreach (array_slice($storeNav['concerns'] ?? [], 0, 8) as $concern): ?>
                <a href="<?= e(url('/shop?concern[]=' . rawurlencode((string) $concern['code']))) ?>"><?= e($concern['display_name']) ?></a>
              <?php endforeach; ?>
            </section>
            <section>
              <span class="mega-kicker">PERSONALIZED</span>
              <?php if ($assessmentUrl !== null): ?>
                <a href="<?= e($assessmentUrl) ?>">Hair Assessment</a>
              <?php else: ?>
                <a href="<?= e(url('/shop')) ?>">Explore the catalog</a>
              <?php endif; ?>
            </section>
          </div>
        </div>
      </div>

      <?php if ($assessmentUrl !== null): ?>
        <a class="<?= is_active_path('/assessment') ? 'active' : '' ?>" href="<?= e($assessmentUrl) ?>">Hair Assessment</a>
      <?php endif; ?>
      <a class="<?= is_active_path('/about') ? 'active' : '' ?>" href="<?= e(url('/about')) ?>">About</a>
    </nav>

    <div class="nav-actions site-header-actions">
      <button class="header-icon-link" type="button" data-global-search-open aria-label="Search" title="Search">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/></svg>
      </button>

      <?php if ($role === 'customer'): ?>
        <details class="account-menu">
          <summary class="header-icon-link" aria-label="Account menu" title="Account">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/></svg>
          </summary>
          <div class="account-menu-panel">
            <a href="<?= e(url('/profile')) ?>">Profile</a>
            <a href="<?= e(url('/routine')) ?>">My Routine</a>
            <a href="<?= e(url('/orders')) ?>">My Orders</a>
            <form method="post" action="<?= e(url('/logout')) ?>">
              <?= csrf_field() ?>
              <button type="submit">Sign out</button>
            </form>
          </div>
        </details>

        <a class="header-icon-link header-favorite-link <?= is_active_path('/favorites') ? 'active' : '' ?>" href="<?= e(url('/favorites')) ?>" aria-label="Favorites" title="Favorites">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/></svg>
          <span class="header-count-badge" data-favorite-count <?= $favoriteCount > 0 ? '' : 'hidden' ?>><?= $favoriteCount ?></span>
        </a>

        <a class="header-icon-link <?= is_active_path('/cart') ? 'active' : '' ?>" href="<?= e(url('/cart')) ?>" aria-label="Shopping bag" title="Shopping bag">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8.5h11l1 11h-13l1-11Z"/><path d="M9 9V6.5a3 3 0 0 1 6 0V9"/></svg>
          <span class="header-count-badge" data-cart-count <?= $cartCount > 0 ? '' : 'hidden' ?>><?= $cartCount ?></span>
        </a>
      <?php elseif ($role === 'admin' || $role === 'catalog_manager'): ?>
        <a class="btn btn-secondary btn-sm" href="<?= e(url($role === 'admin' ? '/admin' : '/catalog')) ?>">Dashboard</a>
      <?php else: ?>
        <a class="signin-link" href="<?= e(url('/login')) ?>">Sign in</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('/register')) ?>">Create account</a>
      <?php endif; ?>

      <details class="mobile-nav-menu">
        <summary class="header-icon-link" aria-label="Open navigation" title="Menu">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </summary>
        <nav class="mobile-nav-panel" aria-label="Mobile navigation">
          <a class="<?= $shopActive ? 'active' : '' ?>" href="<?= e(url('/shop')) ?>">Shop all</a>
          <?php foreach (($storeNav['categories'] ?? []) as $category): ?>
            <a class="mobile-nav-sub" href="<?= e(url('/shop?category[]=' . (int) $category['id'])) ?>"><?= e($category['name']) ?></a>
          <?php endforeach; ?>
          <?php if ($assessmentUrl !== null): ?>
            <a class="<?= is_active_path('/assessment') ? 'active' : '' ?>" href="<?= e($assessmentUrl) ?>">Hair Assessment</a>
          <?php endif; ?>
          <a class="<?= is_active_path('/about') ? 'active' : '' ?>" href="<?= e(url('/about')) ?>">About</a>
        </nav>
      </details>
    </div>
  </div>
</header>

<div class="global-search-backdrop" data-global-search-backdrop hidden></div>
<section class="global-search-drawer" data-global-search-drawer data-search-endpoint="<?= e(url('/search/suggest')) ?>" hidden aria-hidden="true">
  <div class="global-search-inner" role="dialog" aria-labelledby="global-search-title">
    <form class="global-search-form" method="get" action="<?= e(url('/search')) ?>">
      <label>
        <span class="sr-only" id="global-search-title">Search Avela</span>
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/></svg>
        <input type="text" name="q" maxlength="100" autocomplete="off" data-global-search-input aria-label="Search products, categories, hair concerns, or pages">
      </label>
      <button class="global-search-close" type="button" data-global-search-close aria-label="Close search" title="Close search">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
      </button>
    </form>
    <div class="global-search-results" data-global-search-results>
      <p class="search-hint">Search products, categories, hair concerns, or pages.</p>
    </div>
  </div>
</section>
