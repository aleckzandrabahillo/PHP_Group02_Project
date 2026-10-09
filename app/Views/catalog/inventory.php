<section class="page-head">
  <div>
    <span class="eyebrow">CATALOG</span>
    <h1>Inventory</h1>
    <p>Check current stock levels and product availability.</p>
  </div>
  <button class="btn btn-primary" disabled title="Stock editing is still under development">Update stock</button>
</section>

<div class="table-card">
  <div class="data-table">
    <div class="data-row header">
      <span>Product</span>
      <span>SKU</span>
      <span>Stock</span>
      <span>Availability</span>
    </div>

    <?php foreach (($products ?? []) as $product): ?>
      <div class="data-row">
        <span><?= e($product['name']) ?></span>
        <span><?= e($product['sku']) ?></span>
        <span><?= (int) $product['stock_qty'] ?></span>
        <span><?= $product['status'] !== 'active' ? 'Inactive' : ((int) $product['stock_qty'] === 0 ? 'Out of stock' : ((int) $product['stock_qty'] <= 5 ? 'Low stock' : 'In stock')) ?></span> <= 5 ? 'Low stock' : 'In stock') ?></span>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (empty($products)): ?>
    <div class="table-empty">
      <h3>No inventory yet</h3>
      <p>Stock information will appear after products are added.</p>
    </div>
  <?php endif; ?>
</div>
