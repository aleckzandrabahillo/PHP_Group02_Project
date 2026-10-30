<section class="page-head">
  <div>
    <h1>Products</h1>
    <p>Review product records, prices, stock, and availability.</p>
  </div>
  <a class="btn btn-primary" href="<?= e(url('/catalog/products?form=add#product-form')) ?>"><?= staff_icon('plus') ?>Add product</a>
</section>

<?php if ($errors): ?>
  <div class="alert alert-error" role="alert"><?= e($errors['form'] ?? 'Please correct the highlighted fields. The product was not saved.') ?></div>
<?php endif; ?>
<?php if ($showForm): ?><?php require __DIR__ . '/product_form.php'; ?><?php endif; ?>

<div class="table-card catalog-products-table" data-staff-list data-list-label="products">
  <?php $staffListLabel='products'; $staffStatusOptions=['active'=>'Active','inactive'=>'Archived']; $staffFilterLabel='statuses'; $staffSearchHint='Search name, SKU or category'; $staffQuantityLabel='Stock: low to high'; require dirname(__DIR__) . '/partials/staff_list_tools.php'; ?>
  <details class="staff-help"><summary>Archiving and product history</summary><p>Archive products with customer or history records; only unused products can be deleted.</p></details>
  <div class="data-table" role="table" aria-label="Products" tabindex="0">
    <div class="data-row header" role="row">
      <span role="columnheader">Product</span>
      <span role="columnheader">Category</span>
      <span role="columnheader" class="num">Price</span>
      <span role="columnheader" class="num">Stock</span>
      <span role="columnheader">Status / availability</span>
      <span role="columnheader">Actions</span>
    </div>

    <?php foreach (($products ?? []) as $product): ?>
      <div class="data-row" role="row" data-staff-row data-list-search="<?= e($product['name'] . ' ' . $product['sku'] . ' ' . $product['category_name']) ?>" data-list-name="<?= e($product['name']) ?>" data-list-status="<?= e($product['status']) ?>" data-list-quantity="<?= (int) $product['stock_qty'] ?>">
        <div class="catalog-product-identity" role="cell">
          <?php $imageUrl = product_image_url($product['image_path']); ?>
          <img class="catalog-thumb" src="<?= e($imageUrl ?? product_placeholder_url($product['category_name'])) ?>" alt="" loading="lazy">
          <span><?= e($product['name']) ?><small class="muted"><?= e($product['sku']) ?></small></span>
        </div>
        <span role="cell"><?= e($product['category_name']) ?><?= $product['category_status'] !== 'active' ? ' (inactive)' : '' ?></span>
        <span role="cell" class="num">₱<?= e(number_format((float) $product['price'], 2)) ?></span>
        <span role="cell" class="num"><?= (int) $product['stock_qty'] ?></span>
        <span role="cell"><?= staff_status($product['status']!=='active'?'Archived':\App\Models\Product::availability($product)) ?></span>
        <div class="catalog-actions" role="cell">
          <a class="btn btn-secondary btn-sm" href="<?= e(url('/catalog/products?edit=' . (int) $product['id'] . '#product-form')) ?>">Edit</a>
          <?php if ($product['status'] === 'active'): ?>
            <form method="post" action="<?= e(url('/catalog/products')) ?>">
              <?= csrf_field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
              <button class="btn btn-secondary btn-sm" type="submit">Archive</button>
            </form>
          <?php else: ?>
            <form method="post" action="<?= e(url('/catalog/products')) ?>">
              <?= csrf_field() ?><input type="hidden" name="action" value="reactivate"><input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
              <button class="btn btn-secondary btn-sm" type="submit">Reactivate</button>
            </form>
          <?php endif; ?>
          <?php if ((bool) $product['can_delete']): ?>
            <form method="post" action="<?= e(url('/catalog/products')) ?>" data-confirm="Permanently delete this unused product? This cannot be undone.">
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
