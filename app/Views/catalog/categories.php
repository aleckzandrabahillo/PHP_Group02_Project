<section class="page-head">
  <div>
    <span class="eyebrow">CATALOG</span>
    <h1>Categories</h1>
    <p>Organize products by their main hair-care category.</p>
  </div>
  <button class="btn btn-primary" disabled title="Category management is still under development">Add Category</button>
</section>

<div class="table-card">
  <div class="data-table">
    <div class="data-row header">
      <span>Category</span>
      <span>Description</span>
      <span>Products</span>
      <span>Status</span>
    </div>

    <?php foreach (($categories ?? []) as $category): ?>
      <div class="data-row">
        <span><?= e($category['name']) ?></span>
        <span><?= e($category['description']) ?></span>
        <span><?= (int) $category['product_count'] ?></span>
        <span><?= e(ucfirst($category['status'])) ?></span>
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
