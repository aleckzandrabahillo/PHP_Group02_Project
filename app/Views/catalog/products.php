<section class="page-head">
  <div>
    <span class="eyebrow">CATALOG</span>
    <h1>Products</h1>
    <p>Create and maintain product records, images, suitability tags, prices, and availability.</p>
  </div>
  <a class="btn btn-primary" href="<?= e(url('/catalog/products?form=add#product-form')) ?>">Add Product</a>
</section>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error" role="alert"><?= e($errors['form'] ?? 'Please correct the highlighted fields. The product was not saved.') ?></div>
<?php endif; ?>

<?php if (!empty($showForm)): ?>
<section id="product-form" class="panel category-form" aria-labelledby="product-form-title">
  <h2 id="product-form-title"><?= $editId !== null ? 'Edit Product' : 'Add Product' ?></h2>
  <form class="form-stack" method="post" enctype="multipart/form-data" action="<?= e(url('/catalog/products')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editId !== null ? 'update' : 'create' ?>">
    <?php if ($editId !== null): ?><input type="hidden" name="id" value="<?= (int)$editId ?>"><?php endif; ?>
    <div class="field-grid two">
      <label class="field <?= isset($errors['name'])?'has-error':'' ?>"><span>Product name</span>
        <input name="name" value="<?= e($form['name'] ?? '') ?>" maxlength="160" required>
        <small class="field-error"><?= e($errors['name'] ?? '') ?></small>
      </label>
      <label class="field <?= isset($errors['sku'])?'has-error':'' ?>"><span>SKU</span>
        <input name="sku" value="<?= e($form['sku'] ?? '') ?>" maxlength="50" required>
        <small class="field-error"><?= e($errors['sku'] ?? '') ?></small>
      </label>
      <label class="field <?= isset($errors['category_id'])?'has-error':'' ?>"><span>Category</span>
        <select name="category_id" required><option value="">Choose category</option>
          <?php foreach (($categories ?? []) as $category): ?>
            <?php if ($category['status'] === 'active' || (string)($form['category_id'] ?? '') === (string)$category['id']): ?>
              <option value="<?= (int)$category['id'] ?>" <?= (string)($form['category_id'] ?? '') === (string)$category['id']?'selected':'' ?>><?= e($category['name']) ?><?= $category['status']==='inactive'?' (archived)':'' ?></option>
            <?php endif; ?>
          <?php endforeach; ?>
        </select><small class="field-error"><?= e($errors['category_id'] ?? '') ?></small>
      </label>
      <label class="field <?= isset($errors['price'])?'has-error':'' ?>"><span>Price (₱)</span>
        <input type="number" name="price" min="0" max="99999999.99" step="0.01" value="<?= e($form['price'] ?? '') ?>" required>
        <small class="field-error"><?= e($errors['price'] ?? '') ?></small>
      </label>
      <?php if ($editId === null): ?>
      <label class="field <?= isset($errors['stock_qty'])?'has-error':'' ?>"><span>Initial stock quantity</span>
        <input type="number" name="stock_qty" min="0" step="1" value="<?= e($form['stock_qty'] ?? '0') ?>" required>
        <small class="field-error"><?= e($errors['stock_qty'] ?? '') ?></small>
      </label>
      <?php else: ?><input type="hidden" name="stock_qty" value="<?= (int)($form['stock_qty'] ?? 0) ?>"><?php endif; ?>
      <label class="field"><span>Status</span><select name="status">
        <option value="active" <?= ($form['status'] ?? '')==='active'?'selected':'' ?>>Active</option>
        <option value="inactive" <?= ($form['status'] ?? '')==='inactive'?'selected':'' ?>>Inactive</option>
      </select><small class="field-error"><?= e($errors['status'] ?? '') ?></small></label>
      <label class="field"><span>Routine step (optional)</span><select name="routine_step">
        <option value="">Not specified</option>
        <?php foreach (['cleanse'=>'Cleanse','condition'=>'Condition','treat'=>'Treat','finish'=>'Finish'] as $key=>$label): ?>
        <option value="<?= e($key) ?>" <?= ($form['routine_step'] ?? '')===$key?'selected':'' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select><small class="field-error"><?= e($errors['routine_step'] ?? '') ?></small></label>
    </div>
    <label class="field <?= isset($errors['description'])?'has-error':'' ?>"><span>Description</span>
      <textarea name="description" maxlength="5000"><?= e($form['description'] ?? '') ?></textarea><small class="field-error"><?= e($errors['description'] ?? '') ?></small>
    </label>
    <label class="field <?= isset($errors['image'])?'has-error':'' ?>"><span>Product image (JPG, PNG, WebP; max 3 MB)</span>
      <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
      <?php if (!empty($form['image_path'])): ?><small class="help">Current image is retained unless you upload a replacement.</small><?php endif; ?>
      <small class="field-error"><?= e($errors['image'] ?? '') ?></small>
    </label>
    
    <fieldset class="product-tags-panel">
      <p class="product-tags-legend">Product Tags</p>
      <p class="product-tags-description">
        Select all that apply. These tags power the existing suitability
        filters and recommendations.
      </p>

      

      <div class="product-tags-grid">
        <?php
        $tagGroups = [
          'concern'   => 'Hair concerns',
          'texture'   => 'Hair texture',
          'scalp'     => 'Scalp type',
          'condition' => 'Condition',
        ];
        ?>

        <?php foreach ($tagGroups as $type => $label): ?>
          <section class="product-tag-group">
            <h3 class="product-tag-group-title">
              <?= e($label) ?>
            </h3>

            <div class="product-tag-options">
              <?php foreach (($tags ?? []) as $tag): ?>
                <?php if ($tag['tag_type'] !== $type) continue; ?>

                <label class="product-tag-option">
                  <input
                    type="checkbox"
                    name="tags[<?= e($type) ?>][]"
                    value="<?= e($tag['code']) ?>"
                    <?= in_array(
                      $tag['code'],
                      $form['tag_ids'][$type] ?? [],
                      true
                    ) ? 'checked' : '' ?>
                  >

                  <span><?= e($tag['display_name']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endforeach; ?>
      </div>

      <small class="field-error">
        <?= e($errors['tags'] ?? '') ?>
      </small>
    </fieldset>


    <div class="category-actions">
      <button class="btn btn-primary" type="submit"><?= $editId !== null ? 'Save Changes' : 'Create Product' ?></button>
      <a class="btn btn-secondary" href="<?= e(url('/catalog/products')) ?>">Cancel</a>
    </div>
  </form>
</section>
<?php endif; ?>

<div class="table-card product-table">
  <div class="data-table">
    <div class="data-row header"><span>Product</span><span>Category</span><span>Price</span><span>Stock</span><span>Status</span><span>Actions</span></div>
    <?php foreach (($products ?? []) as $product): ?>
      <div class="data-row">

        <!-- PRODUCT -->
        <span class="product-info">
          <strong class="product-name"><?= e($product['name']) ?></strong>
          <small class="product-sku muted"><?= e($product['sku']) ?></small>
          <?php if (!empty($product['tags'])): ?>
            <small class="muted">Tags: <?= e(implode(', ',$product['tags'])) ?></small>
          <?php endif; ?>
        </span>

        <!-- CATEGORY -->
        <span><?= e($product['category_name']) ?></span>

        <!-- PRICE -->
        <span>₱<?= e(number_format((float)$product['price'],2)) ?></span>

        <!-- STOCK -->
        <span><?= (int)$product['stock_qty'] ?></span>
        
        <!-- STATUS -->
        <span class="product-status <?= $product['status'] === 'active' ? 'is-active' : 'is-inactive' ?>">
          <?= e(ucfirst($product['status'])) ?>
          <?= (int) $product['stock_qty'] === 0 ? ' · Out of stock' : '' ?>
        </span>

        <!-- ACTIONS -->
        <div class="products-actions-column">
          <a class="btn btn-secondary btn-sm" href="<?= e(url('/catalog/products?edit=' . (int) $product['id'] . '#product-form')) ?>">
            Edit
          </a>
          <form method="post" action="<?= e(url('/catalog/products')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
            <input type="hidden" name="action" value="<?= $product['status'] === 'active' ? 'deactivate' : 'activate' ?>">
            <button class="btn btn-secondary btn-sm" type="submit">
              <?= $product['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
            </button>
          </form>

          <?php if (!empty($product['can_delete'])): ?>
            <form class="product-delete-action"
                method="post"
                action="<?= e(url('/catalog/products')) ?>"
                onsubmit="return confirm('Permanently delete this product? This cannot be undone.');">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
              <input type="hidden" name="action" value="delete">
              <button class="btn btn-secondary btn-sm" type="submit">
                Delete unused
              </button>
            </form>
          <?php endif; ?>
        </div>

      </div>
    <?php endforeach; ?>
  </div>
  <?php if (empty($products)): ?><div class="table-empty"><h3>No products yet</h3><p>Use Add Product to create the first catalog item.</p></div><?php endif; ?>
</div>
