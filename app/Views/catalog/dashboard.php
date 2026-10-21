<section class="page-head">
  <div>
    <span class="eyebrow">CATALOG OVERVIEW</span>
    <h1>Welcome, <?= e($profile['full_name'] ?? 'Catalog Manager') ?>.</h1>
    <p>Review products, categories, stock levels, and catalog availability.</p>
  </div>
</section>


<div class="metric-grid five">
  <div class="metric-card"><span>Total products</span><strong><?= e($stats['products']) ?></strong></div>
  <div class="metric-card"><span>Active / inactive</span><strong><?= (int)$analytics['stock']['active'] ?> / <?= (int)$analytics['stock']['inactive'] ?></strong></div>
  <div class="metric-card"><span>Low stock</span><strong><?= (int)$analytics['stock']['low_stock'] ?></strong></div>
  <div class="metric-card"><span>Out of stock</span><strong><?= (int)$analytics['stock']['out_of_stock'] ?></strong></div>
  <div class="metric-card"><span>Categories</span><strong><?= e($stats['categories']) ?></strong></div>
</div>

<?php $catalogReport=true;require dirname(__DIR__).'/partials/dashboard_analytics.php'; ?>
<section class="panel" aria-labelledby="catalog-actions-title">
  <h2 id="catalog-actions-title">Manage the catalog</h2>
  <div class="quick-links">
    <a href="<?= e(url('/catalog/products')) ?>">Products and images</a>
    <a href="<?= e(url('/catalog/inventory')) ?>">Stock and restocking</a>
    <a href="<?= e(url('/catalog/categories')) ?>">Categories</a>
  </div>
</section>

<section class="panel" aria-labelledby="catalog-activity-title">
  <h2 id="catalog-activity-title">Recent catalog activity</h2>
  <?php if (!$activity): ?>
    <p class="muted">No catalog changes recorded yet.</p>
  <?php else: ?>
    <?php $activityLabels = [
        'product.create' => 'Created product', 'product.update' => 'Updated product',
        'product.archive' => 'Archived product', 'product.reactivate' => 'Reactivated product',
        'product.delete' => 'Deleted unused product', 'inventory.set' => 'Adjusted stock',
        'inventory.restock' => 'Restocked product', 'category.create' => 'Created category',
        'category.update' => 'Updated category', 'category.archive' => 'Archived category',
        'category.delete' => 'Deleted unused category',
    ]; ?>
    <div class="catalog-activity-table" role="region" aria-label="Recent catalog changes" tabindex="0">
      <table>
        <thead><tr><th scope="col">Time</th><th scope="col">Staff</th><th scope="col">Action</th><th scope="col">Record</th></tr></thead>
        <tbody>
          <?php foreach ($activity as $event): ?>
            <tr>
              <td><time><?= e($event['created_at']) ?></time></td>
              <td><?= e($event['actor_name']) ?></td>
              <td><?= e($activityLabels[$event['action']] ?? 'Catalog update') ?></td>
              <td><?= e(ucfirst($event['target_type'])) ?> #<?= (int) $event['target_id'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
