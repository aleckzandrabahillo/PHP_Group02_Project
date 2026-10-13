<section class="page-head">
  <div><span class="eyebrow">CATALOG</span><h1>Inventory</h1><p>Update stock quantities and record the reason for each adjustment.</p></div>
</section>
<div class="table-card">
  <div class="data-table">
    <div class="data-row header"><span>Product</span><span>SKU</span><span>Current stock</span><span>Availability</span><span>Update stock</span></div>
    <?php foreach (($products ?? []) as $product): $qty=(int)$product['stock_qty']; ?>
      <div class="data-row">
        <span class="inventory-product-info">
          <strong><?= e($product['name']) ?></strong>
            <small class="inventory-product-status <?= $product['status'] === 'active' ? 'is-active' : 'is-inactive' ?>">
              <?= e(ucfirst($product['status'])) ?>
            </small>
        </span>
        <span><?= e($product['sku']) ?></span>
        <span><?= $qty ?></span>
        <span><?= $qty===0?'Out of stock':($qty<=5?'Low stock':'In stock') ?></span>
        <form class="form-stack" method="post" action="<?= e(url('/catalog/inventory')) ?>">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
          <label class="field"><span>New quantity</span><input type="number" name="stock_qty" min="0" max="2147483647" step="1" value="<?= $qty ?>" required></label>
          <label class="field"><span>Reason for change</span><input type="text" name="reason" maxlength="500" placeholder="e.g. New delivery, stock count" required></label>
          <button class="btn btn-primary btn-sm" type="submit">Save stock</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (empty($products)): ?><div class="table-empty"><h3>No inventory yet</h3><p>Stock information will appear after products are added.</p></div><?php endif; ?>
</div>
