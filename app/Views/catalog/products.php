<section class="page-head">
  <div>
    <span class="eyebrow">CATALOG</span>
    <h1>Products</h1>
    <p>Review product records, prices, stock, and availability.</p>
  </div>
  <button class="btn btn-primary" disabled title="Product management is still under development">Add Product</button>
</section>

<div class="table-card">
  <div class="data-table">
    <div class="data-row header">
      <span>Product</span>
      <span>Category</span>
      <span>Price</span>
      <span>Stock</span>
      <span>Status</span>
    </div>

    <?php foreach (($products ?? []) as $product): ?>
      <div class="data-row">
        <span>
          <?= e($product['name']) ?>
          <small class="muted"><?= e($product['sku']) ?></small>
        </span>
        <span><?= e($product['category_name']) ?></span>
        <span>₱<?= e(number_format((float) $product['price'], 2)) ?></span>
        <span><?= (int) $product['stock_qty'] ?></span>
        <span><?= $product['status'] !== 'active' ? 'Inactive' : ((int) $product['stock_qty'] > 0 ? 'Active' : 'Out of stock') ?></span>
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
