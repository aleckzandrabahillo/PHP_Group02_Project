<section class="page-head">
  <div>
    <span class="eyebrow">CATALOG OVERVIEW</span>
    <h1>Welcome, <?= e($profile['full_name'] ?? 'Catalog Manager') ?>.</h1>
    <p>Review products, categories, stock levels, and catalog availability.</p>
  </div>
</section>

<div class="metric-grid five">
  <div class="metric-card"><span>Total products</span><strong><?= e($stats['products']) ?></strong></div>
  <div class="metric-card"><span>Active</span><strong><?= e($stats['active_products']) ?></strong></div>
  <div class="metric-card"><span>Low stock</span><strong><?= e($stats['low_stock']) ?></strong></div>
  <div class="metric-card"><span>Out of stock</span><strong><?= e($stats['out_of_stock']) ?></strong></div>
  <div class="metric-card"><span>Categories</span><strong><?= e($stats['categories']) ?></strong></div>
</div>

<section class="panel" aria-labelledby="catalog-actions-title">
  <h2 id="catalog-actions-title">Manage the catalog</h2>
  <div class="quick-links">
    <a href="<?= e(url('/catalog/products')) ?>">Products and images</a>
    <a href="<?= e(url('/catalog/inventory')) ?>">Stock and restocking</a>
    <a href="<?= e(url('/catalog/categories')) ?>">Categories</a>
  </div>
</section>
