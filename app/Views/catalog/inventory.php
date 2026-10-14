<section class="page-head">
  <div>
    <span class="eyebrow">CATALOG</span>
    <h1>Inventory</h1>
    <p>Check current stock levels and product availability.</p>
  </div>
</section>

<?php if ($errors): ?>
  <div class="alert alert-error" role="alert"><?= e($errors['form'] ?? 'Please correct the highlighted fields. Stock was not changed.') ?></div>
<?php endif; ?>
<?php if ($currentProduct !== null): ?>
  <section id="stock-form" class="panel catalog-form" aria-labelledby="stock-form-title">
    <h2 id="stock-form-title">Update Stock · <?= e($currentProduct['name']) ?></h2>
    <p class="muted">Current quantity: <?= (int) $currentProduct['stock_qty'] ?> · <?= e(\App\Models\Product::availability($currentProduct)) ?></p>
    <form class="form-stack" method="post" action="<?= e(url('/catalog/inventory')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $currentProduct['id'] ?>">
      <input type="hidden" name="expected_stock" value="<?= e($form['expected_stock']) ?>">
      <div class="field-grid two">
        <label class="field <?= isset($errors['mode']) ? 'has-error' : '' ?>">
          <span>Stock operation</span>
          <select name="mode" required aria-invalid="<?= isset($errors['mode']) ? 'true' : 'false' ?>" aria-describedby="stock-mode-help stock-mode-error">
            <option value="">Choose an operation</option>
            <option value="add" <?= $form['mode'] === 'add' ? 'selected' : '' ?>>Restock (add units)</option>
            <option value="set" <?= $form['mode'] === 'set' ? 'selected' : '' ?>>Set quantity (replace total)</option>
          </select>
          <small id="stock-mode-help" class="help">Restock adds to current stock. Set quantity replaces it; zero marks the product out of stock.</small>
          <small id="stock-mode-error" class="field-error"><?= e($errors['mode'] ?? '') ?></small>
        </label>
        <label class="field <?= isset($errors['stock_qty']) ? 'has-error' : '' ?>">
          <span>Quantity</span>
          <input type="number" name="stock_qty" value="<?= e($form['stock_qty']) ?>" min="0" max="4294967295" step="1" required aria-invalid="<?= isset($errors['stock_qty']) ? 'true' : 'false' ?>" aria-describedby="stock-quantity-error">
          <small id="stock-quantity-error" class="field-error"><?= e($errors['stock_qty'] ?? '') ?></small>
        </label>
      </div>
      <div class="catalog-actions">
        <button class="btn btn-primary" type="submit">Save Stock</button>
        <a class="btn btn-secondary" href="<?= e(url('/catalog/inventory?stock=' . (int) $currentProduct['id'] . '#stock-form')) ?>">Reload stock form</a>
        <a class="btn btn-secondary" href="<?= e(url('/catalog/inventory')) ?>">Cancel</a>
      </div>
    </form>
  </section>
<?php endif; ?>

<div class="table-card catalog-inventory-table">
  <div class="data-table">
    <div class="data-row header">
      <span>Product</span>
      <span>SKU</span>
      <span>Stock</span>
      <span>Availability</span>
      <span>Actions</span>
    </div>

    <?php foreach (($products ?? []) as $product): ?>
      <div class="data-row">
        <span><?= e($product['name']) ?></span>
        <span><?= e($product['sku']) ?></span>
        <span><?= (int) $product['stock_qty'] ?></span>
        <span><?= e(\App\Models\Product::availability($product)) ?></span>
        <span><a class="btn btn-secondary btn-sm" href="<?= e(url('/catalog/inventory?stock=' . (int) $product['id'] . '#stock-form')) ?>">Update stock</a></span>
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
