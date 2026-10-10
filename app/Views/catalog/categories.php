<section class="page-head">
  <div>
    <span class="eyebrow">CATALOG</span>
    <h1>Categories</h1>
    <p>Organize products by their main hair-care category.</p>
  </div>
  <a class="btn btn-primary" href="<?= e(url('/catalog/categories?form=add#category-form')) ?>">Add Category</a>
</section>

<?php if ($errors): ?>
  <div class="alert alert-error" role="alert">
    <?= e($errors['form'] ?? 'Please correct the highlighted fields. The category was not saved.') ?>
  </div>
<?php endif; ?>

<?php if ($showForm): ?>
  <section id="category-form" class="panel category-form" aria-labelledby="category-form-title">
    <h2 id="category-form-title"><?= $editId !== null ? 'Edit Category' : 'Add Category' ?></h2>
    <form class="form-stack" method="post" action="<?= e(url('/catalog/categories')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editId !== null ? 'update' : 'create' ?>">
      <?php if ($editId !== null): ?><input type="hidden" name="id" value="<?= (int) $editId ?>"><?php endif; ?>
      <div class="field-grid two">
        <label class="field <?= isset($errors['name']) ? 'has-error' : '' ?>">
          <span>Category name</span>
          <input type="text" name="name" value="<?= e($form['name']) ?>" required maxlength="100" aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>" aria-describedby="category-name-error">
          <small id="category-name-error" class="field-error"><?= e($errors['name'] ?? '') ?></small>
        </label>
        <label class="field <?= isset($errors['status']) ? 'has-error' : '' ?>">
          <span>Status</span>
          <select name="status" required aria-invalid="<?= isset($errors['status']) ? 'true' : 'false' ?>" aria-describedby="category-status-help category-status-error">
            <option value="" <?= !in_array($form['status'], ['active', 'inactive'], true) ? 'selected' : '' ?> disabled>Choose a status</option>
            <option value="active" <?= $form['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $form['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (archived)</option>
          </select>
          <small id="category-status-help" class="help">Inactive categories and their products are hidden from public browsing.</small>
          <small id="category-status-error" class="field-error"><?= e($errors['status'] ?? '') ?></small>
        </label>
      </div>
      <label class="field <?= isset($errors['description']) ? 'has-error' : '' ?>">
        <span>Description <em>(optional)</em></span>
        <textarea name="description" maxlength="500" aria-invalid="<?= isset($errors['description']) ? 'true' : 'false' ?>" aria-describedby="category-description-error"><?= e($form['description'] ?? '') ?></textarea>
        <small id="category-description-error" class="field-error"><?= e($errors['description'] ?? '') ?></small>
      </label>
      <div class="category-actions">
        <button class="btn btn-primary" type="submit"><?= $editId !== null ? 'Save Changes' : 'Create Category' ?></button>
        <a class="btn btn-secondary" href="<?= e(url('/catalog/categories')) ?>">Cancel</a>
      </div>
    </form>
  </section>
<?php endif; ?>

<div class="table-card category-table">
  <p class="muted small">Archive categories to keep their product links. Only unused categories can be permanently deleted. Edit an archived category to reactivate it.</p>
  <div class="data-table">
    <div class="data-row header">
      <span>Category</span>
      <span>Description</span>
      <span>Products</span>
      <span>Status</span>
      <span>Actions</span>
    </div>

    <?php foreach (($categories ?? []) as $category): ?>
      <div class="data-row">
        <span><?= e($category['name']) ?></span>
        <span><?= e($category['description']) ?></span>
        <span><?= (int) $category['product_count'] ?></span>
        <span><?= e(ucfirst($category['status'])) ?></span>
        <div class="category-actions">
          <a class="btn btn-secondary btn-sm" href="<?= e(url('/catalog/categories?edit=' . (int) $category['id'] . '#category-form')) ?>">Edit</a>
          <?php if ($category['status'] === 'active'): ?>
            <form method="post" action="<?= e(url('/catalog/categories')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="archive">
              <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
              <button class="btn btn-secondary btn-sm" type="submit">Archive</button>
            </form>
          <?php endif; ?>
          <?php if ((int) $category['product_count'] === 0): ?>
            <form method="post" action="<?= e(url('/catalog/categories')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
              <button class="btn btn-secondary btn-sm" type="submit">Delete unused</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (empty($categories)): ?>
    <div class="table-empty">
      <h3>No categories yet</h3>
      <p>Categories will appear here once they are added.</p>
    </div>
  <?php endif; ?>
</div>
