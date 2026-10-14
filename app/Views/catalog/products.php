<section class="page-head">
  <div>
    <span class="eyebrow">CATALOG</span>
    <h1>Products</h1>
    <p>Review product records, prices, stock, and availability.</p>
  </div>
  <a class="btn btn-primary" href="<?= e(url('/catalog/products?form=add#product-form')) ?>">Add Product</a>
</section>

<?php if ($errors): ?>
  <div class="alert alert-error" role="alert"><?= e($errors['form'] ?? 'Please correct the highlighted fields. The product was not saved.') ?></div>
<?php endif; ?>
<?php if ($showForm): ?><?php require __DIR__ . '/product_form.php'; ?><?php endif; ?>

<div class="table-card catalog-products-table">
  <p class="muted small">All products are shown here, including inactive and out-of-stock records. Archive products with customer or history records; only unused products can be deleted.</p>
  <div class="data-table">
    <div class="data-row header">
      <span>Product</span>
      <span>Category</span>
      <span>Price</span>
      <span>Stock</span>
      <span>Status / availability</span>
      <span>Actions</span>
    </div>

    <?php foreach (($products ?? []) as $product): ?>
      <div class="data-row">
        <div class="catalog-product-identity">
          <?php $imageUrl = product_image_url($product['image_path']); ?>
          <?php if ($imageUrl !== null): ?><img class="catalog-thumb" src="<?= e($imageUrl) ?>" alt="" loading="lazy"><?php endif; ?>
          <span><?= e($product['name']) ?><small class="muted"><?= e($product['sku']) ?></small></span>
        </div>
        <span><?= e($product['category_name']) ?><?= $product['category_status'] !== 'active' ? ' (inactive)' : '' ?></span>
        <span>₱<?= e(number_format((float) $product['price'], 2)) ?></span>
        <span><?= (int) $product['stock_qty'] ?></span>
        <span><?= e(ucfirst($product['status'])) ?><small class="muted catalog-block"><?= e(\App\Models\Product::availability($product)) ?></small></span>
        <div class="catalog-actions">
          <a class="btn btn-secondary btn-sm" href="<?= e(url('/catalog/products?edit=' . (int) $product['id'] . '#product-form')) ?>">Edit</a>
          <?php if ($product['status'] === 'active'): ?>
            <form method="post" action="<?= e(url('/catalog/products')) ?>">
              <?= csrf_field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
              <button class="btn btn-secondary btn-sm" type="submit">Archive</button>
            </form>
          <?php endif; ?>
          <?php if ((bool) $product['can_delete']): ?>
            <form method="post" action="<?= e(url('/catalog/products')) ?>">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
              <button class="btn btn-secondary btn-sm" type="submit">Delete unused</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (empty($products)): ?>
    <div class="table-empty">
      <h3>No products yet</h3>
      <p>Products will appear here once they are added.</p>
    </div>
  <?php endif; ?>
</div>
