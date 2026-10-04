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

<div class="panel">
  <div class="panel-head"><h2>Recent catalog activity</h2></div>
  <div class="table-empty">
    <p>Catalog activity will be shown here after product management is connected.</p>
  </div>
</div>
