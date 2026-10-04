<?php
$_productCard = isset($productCard) && is_array($productCard) ? $productCard : $product;
$productUrl = url('/product?id=' . (int) $_productCard['id']);
$showExcerpt = (bool) ($showExcerpt ?? false);
$imageUrl = product_image_url($_productCard['image_path'] ?? null);
?>
<article class="product-card">
  <a class="product-card-main" href="<?= e($productUrl) ?>" aria-label="View <?= e($_productCard['name']) ?>">
    <span class="product-art">
      <?php if ($imageUrl !== null): ?>
        <img class="product-image" src="<?= e($imageUrl) ?>" alt="<?= e($_productCard['name']) ?>" loading="lazy">
      <?php else: ?>
        <?php $visualCategory = $_productCard['category_name'] ?? ''; $placeholderLarge = false; require __DIR__ . '/product_placeholder.php'; ?>
      <?php endif; ?>
    </span>
    <span class="product-card-body">
      <small><?= e($_productCard['category_name']) ?></small>
      <span class="product-card-title"><?= e($_productCard['name']) ?></span>
      <?php if ($showExcerpt): ?>
        <span class="product-excerpt"><?= e(strlen((string) $_productCard['description']) > 92 ? substr((string) $_productCard['description'], 0, 89) . '…' : (string) $_productCard['description']) ?></span>
      <?php endif; ?>
      <span class="product-card-footer">
        <strong>₱<?= e(number_format((float) $_productCard['price'], 2)) ?></strong>
        <span class="stock-label <?= (int) $_productCard['stock_qty'] > 0 ? 'in-stock' : 'out-stock' ?>"><?= (int) $_productCard['stock_qty'] > 0 ? 'In stock' : 'Out of stock' ?></span>
      </span>
    </span>
  </a>
  <div class="product-card-favorite">
    <?php $favoriteProductId = (int) $_productCard['id']; $favoriteProductName = (string) $_productCard['name']; require __DIR__ . '/favorite_button.php'; unset($favoriteProductName); ?>
  </div>
</article>
<?php unset($_productCard, $productUrl, $imageUrl, $favoriteProductId); ?>
