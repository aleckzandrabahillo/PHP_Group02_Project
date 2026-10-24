<section class="page-head">
  <div>
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
      <label class="field <?= isset($errors['reason'])?'has-error':'' ?>">
        <span>Reason for adjustment</span>
        <input name="reason" maxlength="200" required value="<?= e($form['reason']) ?>" aria-invalid="<?= isset($errors['reason'])?'true':'false' ?>" aria-describedby="stock-reason-error" placeholder="For example, received shipment or stock count correction">
        <small id="stock-reason-error" class="field-error"><?= e($errors['reason']??'') ?></small>
      </label>
      <div class="catalog-actions">
        <button class="btn btn-primary" type="submit">Save Stock</button>
        <a class="btn btn-secondary" href="<?= e(url('/catalog/inventory?stock=' . (int) $currentProduct['id'] . '#stock-form')) ?>">Reload stock form</a>
        <a class="btn btn-secondary" href="<?= e(url('/catalog/inventory')) ?>">Cancel</a>
      </div>
    </form>
  </section>
<?php endif; ?>

<div class="table-card catalog-inventory-table" data-staff-list data-list-label="inventory">
  <?php $staffListLabel='inventory'; $staffStatusOptions=['In stock'=>'In stock','Low stock'=>'Low stock','Out of stock'=>'Out of stock','Inactive'=>'Inactive','Inactive category'=>'Inactive category']; $staffFilterLabel='availability'; $staffSearchHint='Search name or SKU'; $staffQuantityLabel='Stock: low to high'; require dirname(__DIR__) . '/partials/staff_list_tools.php'; ?>
  <div class="data-table" role="table" aria-label="Inventory" tabindex="0">
    <div class="data-row header" role="row">
      <span role="columnheader">Product</span>
      <span role="columnheader">SKU</span>
      <span role="columnheader" class="num">Stock</span>
      <span role="columnheader">Availability</span>
      <span role="columnheader">Actions</span>
    </div>

    <?php foreach (($products ?? []) as $product): ?>
      <div class="data-row" role="row" data-staff-row data-list-search="<?= e($product['name'] . ' ' . $product['sku']) ?>" data-list-name="<?= e($product['name']) ?>" data-list-status="<?= e(\App\Models\Product::availability($product)) ?>" data-list-quantity="<?= (int) $product['stock_qty'] ?>">
        <span role="cell"><?= e($product['name']) ?></span>
        <span role="cell"><?= e($product['sku']) ?></span>
        <span role="cell" class="num"><?= (int) $product['stock_qty'] ?></span>
        <span role="cell"><?= staff_status(\App\Models\Product::availability($product)) ?></span>
        <span role="cell"><a class="btn btn-secondary btn-sm" href="<?= e(url('/catalog/inventory?stock=' . (int) $product['id'] . '#stock-form')) ?>">Update stock</a></span>
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
