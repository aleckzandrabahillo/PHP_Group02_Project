<section class="page-head"><div><span class="eyebrow">FAVORITES</span><h1 class="sr-only">Favorites</h1></div></section>
<?php if (!empty($favoriteProducts)): ?>
  <div class="product-grid catalog-grid favorite-grid">
    <?php foreach ($favoriteProducts as $product): ?>
      <?php $showExcerpt = false; require dirname(__DIR__) . '/partials/product_card.php'; ?>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <?php
  $emptyTitle = 'No favorites yet';
  $emptyMessage = 'Save products you want to compare or come back to later.';
  $emptyActionLabel = 'Browse products';
  $emptyActionUrl = url('/shop');
  require dirname(__DIR__) . '/partials/empty_state.php';
  ?>
<?php endif; ?>
