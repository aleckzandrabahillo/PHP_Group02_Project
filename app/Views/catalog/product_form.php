<?php $editId = $currentProduct['id'] ?? null; ?>
<section id="product-form" class="panel catalog-form" aria-labelledby="product-form-title">
  <h2 id="product-form-title"><?= $editId !== null ? 'Edit Product' : 'Add Product' ?></h2>
  <form class="form-stack" method="post" enctype="multipart/form-data" action="<?= e(url('/catalog/products')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editId !== null ? 'update' : 'create' ?>">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= \App\Services\ImageUploadPolicy::maxBytes() ?>">
    <?php if ($editId !== null): ?>
      <input type="hidden" name="id" value="<?= (int) $editId ?>">
      <input type="hidden" name="expected_stock" value="<?= e($form['expected_stock']) ?>">
    <?php endif; ?>
    <div class="field-grid two staff-product-fields">
      <?php foreach (['name' => ['Product name', 160], 'sku' => ['SKU', 50]] as $key => [$label, $limit]): ?>
        <label class="field <?= isset($errors[$key]) ? 'has-error' : '' ?>">
          <span><?= e($label) ?></span>
          <input type="text" name="<?= e($key) ?>" value="<?= e($form[$key]) ?>" required maxlength="<?= (int) $limit ?>" aria-invalid="<?= isset($errors[$key]) ? 'true' : 'false' ?>" aria-describedby="product-<?= e($key) ?>-error">
          <small id="product-<?= e($key) ?>-error" class="field-error"><?= e($errors[$key] ?? '') ?></small>
        </label>
      <?php endforeach; ?>
      <label class="field <?= isset($errors['category_id']) ? 'has-error' : '' ?>">
        <span>Category</span>
        <select name="category_id" required aria-invalid="<?= isset($errors['category_id']) ? 'true' : 'false' ?>" aria-describedby="product-category-error">
          <option value="">Choose a category</option>
          <?php foreach ($categories as $category): ?>
            <?php $retainInactive = (int) ($currentProduct['category_id'] ?? 0) === (int) $category['id']; ?>
            <option value="<?= (int) $category['id'] ?>" <?= (string) $form['category_id'] === (string) $category['id'] ? 'selected' : '' ?> <?= $category['status'] !== 'active' && !$retainInactive ? 'disabled' : '' ?>><?= e($category['name']) ?><?= $category['status'] !== 'active' ? ' (inactive)' : '' ?></option>
          <?php endforeach; ?>
        </select>
        <small id="product-category-error" class="field-error"><?= e($errors['category_id'] ?? '') ?></small>
      </label>
      <label class="field <?= isset($errors['price']) ? 'has-error' : '' ?>">
        <span>Price (₱)</span>
        <input type="number" name="price" value="<?= e($form['price']) ?>" min="0" max="99999999.99" step="0.01" required aria-invalid="<?= isset($errors['price']) ? 'true' : 'false' ?>" aria-describedby="product-price-error">
        <small id="product-price-error" class="field-error"><?= e($errors['price'] ?? '') ?></small>
      </label>
      <label class="field <?= isset($errors['stock_qty']) || isset($errors['expected_stock']) ? 'has-error' : '' ?>">
        <span>Stock quantity</span>
        <input type="number" name="stock_qty" value="<?= e($form['stock_qty']) ?>" min="0" max="4294967295" step="1" required aria-invalid="<?= isset($errors['stock_qty']) || isset($errors['expected_stock']) ? 'true' : 'false' ?>" aria-describedby="product-stock-error">
        <small id="product-stock-error" class="field-error"><?= e($errors['stock_qty'] ?? $errors['expected_stock'] ?? '') ?></small>
      </label>
      <?php if($editId!==null): ?><label class="field <?= isset($errors['stock_reason'])?'has-error':'' ?>">
        <span>Stock adjustment reason</span>
        <input name="stock_reason" maxlength="200" value="<?= e($form['stock_reason']) ?>" aria-invalid="<?= isset($errors['stock_reason'])?'true':'false' ?>" aria-describedby="product-stock-reason-help product-stock-reason-error">
        <small id="product-stock-reason-help" class="help">Required when changing this product's stock quantity.</small>
        <small id="product-stock-reason-error" class="field-error"><?= e($errors['stock_reason']??'') ?></small>
      </label><?php endif; ?>
      <label class="field <?= isset($errors['status']) ? 'has-error' : '' ?>">
        <span>Status</span>
        <select name="status" required aria-invalid="<?= isset($errors['status']) ? 'true' : 'false' ?>" aria-describedby="product-status-error">
          <option value="">Choose a status</option>
          <option value="active" <?= $form['status'] === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $form['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (archived)</option>
        </select>
        <small id="product-status-error" class="field-error"><?= e($errors['status'] ?? '') ?></small>
      </label>
      <label class="field <?= isset($errors['routine_step']) ? 'has-error' : '' ?>">
        <span>Routine step <em>(optional)</em></span>
        <select name="routine_step" aria-invalid="<?= isset($errors['routine_step']) ? 'true' : 'false' ?>" aria-describedby="product-step-error">
          <option value="">None</option>
          <?php foreach (['cleanse', 'condition', 'treat', 'finish'] as $step): ?>
            <option value="<?= e($step) ?>" <?= $form['routine_step'] === $step ? 'selected' : '' ?>><?= e(ucfirst($step)) ?></option>
          <?php endforeach; ?>
        </select>
        <small id="product-step-error" class="field-error"><?= e($errors['routine_step'] ?? '') ?></small>
      </label>
      <label class="field <?= isset($errors['image']) ? 'has-error' : '' ?>">
        <span>Product image <em>(optional)</em></span>
        <input type="file" name="image" data-image-upload data-max-bytes="<?= \App\Services\ImageUploadPolicy::maxBytes() ?>" data-max-label="<?= e(\App\Services\ImageUploadPolicy::label()) ?>" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-invalid="<?= isset($errors['image']) ? 'true' : 'false' ?>" aria-describedby="product-image-help product-image-error">
        <small id="product-image-help" class="help">JPEG, PNG or WebP; up to <?= e(\App\Services\ImageUploadPolicy::label()) ?>. Leave empty to keep the existing image.</small>
        <small id="product-image-error" class="field-error"><?= e($errors['image'] ?? '') ?></small>
      </label>
    </div>
    <?php $preview = product_image_url($currentProduct['image_path'] ?? null); ?>
    <?php if ($preview !== null): ?><img class="catalog-image-preview" src="<?= e($preview) ?>" alt="Current product image"><?php endif; ?>
    <label class="field <?= isset($errors['description']) ? 'has-error' : '' ?>">
      <span>Description <em>(optional)</em></span>
      <textarea name="description" aria-invalid="<?= isset($errors['description']) ? 'true' : 'false' ?>" aria-describedby="product-description-error"><?= e($form['description']) ?></textarea>
      <small id="product-description-error" class="field-error"><?= e($errors['description'] ?? '') ?></small>
    </label>
    <fieldset class="catalog-tags" aria-describedby="product-tags-help product-tags-error">
      <legend>Suitability tags <span class="muted small">(optional)</span></legend>
      <p id="product-tags-help" class="muted small">Choose existing catalog tags that apply to this product.</p>
      <?php $tagGroups = []; foreach ($tags as $tag) $tagGroups[$tag['tag_type']][] = $tag; ?>
      <?php foreach ($tagGroups as $type => $group): ?>
        <div class="catalog-tag-group">
          <strong><?= e(ucfirst($type)) ?></strong>
          <div class="catalog-tag-options">
            <?php foreach ($group as $tag): ?>
              <label><input type="checkbox" name="tag_ids[]" value="<?= (int) $tag['id'] ?>" <?= in_array((string) $tag['id'], $form['tag_ids'], true) ? 'checked' : '' ?>> <?= e($tag['display_name']) ?></label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$tags): ?><p class="muted">No suitability tags are currently available.</p><?php endif; ?>
      <small id="product-tags-error" class="field-error"><?= e($errors['tag_ids'] ?? '') ?></small>
    </fieldset>
    <details class="staff-help"><summary>How availability works</summary><p>Inactive products and products in inactive categories are hidden from the public store. Zero-stock active products remain visible as out of stock.</p></details>
    <div class="catalog-actions">
      <button class="btn btn-primary" type="submit"><?= $editId !== null ? 'Save changes' : 'Create product' ?></button>
      <a class="btn btn-secondary" href="<?= e(url('/catalog/products')) ?>">Cancel</a>
    </div>
  </form>
</section>
