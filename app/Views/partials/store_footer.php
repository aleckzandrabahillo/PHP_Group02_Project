<?php $footerAccountPath = match (auth_user()['role'] ?? '') { 'customer' => '/profile', 'catalog_manager' => '/catalog/profile', 'admin' => '/admin/profile', default => '/login' }; ?>
<footer class="store-footer">
  <div class="editorial-footer-top">
    <div><a href="<?= e(url('/')) ?>" aria-label="Avela home"><?php require __DIR__ . '/brand.php'; ?></a><p>Individual hair. Considered care.</p></div>
    <nav aria-label="Explore Avela"><span class="eyebrow">EXPLORE</span><a href="<?= e(url('/shop')) ?>">Shop all care</a><a href="<?= e(url('/about')) ?>">About Avela</a><a href="<?= e(url('/search')) ?>">Search</a></nav>
    <nav aria-label="Your Avela"><span class="eyebrow">YOUR AVELA</span><a href="<?= e(url($footerAccountPath)) ?>">Your account</a><?php if (in_array(auth_user()['role'] ?? '', ['', 'customer'], true)): ?><a href="<?= e(url('/favorites')) ?>">Favorites</a><a href="<?= e(url('/orders')) ?>">Orders</a><?php endif; ?></nav>
  </div>
  <div class="editorial-footer-bottom"><small>Avela · Student demonstration store. No online payments are collected.</small><a href="<?= e(url('/privacy')) ?>">Privacy &amp; cookies</a><a href="<?= e(asset('images/editorial/credits.html')) ?>">Image &amp; wordmark credits</a></div>
</footer>
