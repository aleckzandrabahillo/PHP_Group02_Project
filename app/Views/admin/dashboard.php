<section class="page-head">
  <div>
    <span class="eyebrow">SYSTEM OVERVIEW</span>
    <h1>Welcome, <?= e($profile['full_name'] ?? 'Administrator') ?>.</h1>
    <p>Manage accounts, orders, security, logs, and shared catalog access.</p>
  </div>
</section>

<div class="metric-grid four">
  <div class="metric-card"><span>Customers</span><strong><?= e($stats['customers']) ?></strong></div>
  <div class="metric-card"><span>Staff</span><strong><?= e($stats['staff']) ?></strong></div>
  <div class="metric-card"><span>Orders</span><strong><?= e($stats['orders']) ?></strong></div>
  <div class="metric-card"><span>Locked accounts</span><strong><?= e($stats['locked']) ?></strong></div>
</div>

<div class="panel-grid">
  <div class="panel">
    <div class="panel-head">
      <h2>Recent security activity</h2>
      <a href="<?= e(url('/admin/auth-logs')) ?>">View logs</a>
    </div>
    <div class="table-empty">
      <?php if (!empty($recentLogs)): ?>
        <?php foreach ($recentLogs as $log): ?>
          <p>
            <?= e($log['email'] ?? 'Unknown') ?> · <?= e($log['event']) ?> · <?= e($log['result']) ?>
            <small class="muted"><?= e(date('M j, g:i A', strtotime((string) $log['created_at']))) ?></small>
          </p>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="table-empty"><p>No authentication activity yet.</p></div>
      <?php endif; ?>
    </div>
    <div class="panel">
      <div class="panel-head">
        <h2>Catalog health</h2>
        <a href="<?= e(url('/catalog/products')) ?>">Open catalog</a>
      </div>
      <?php $cs = $catalogStats ?? []; ?>
      <p><?= (int) ($cs['active_products'] ?? 0) ?> of <?= (int) ($cs['products'] ?? 0) ?> products active ·
        <?= (int) ($cs['low_stock'] ?? 0) ?> low stock ·
        <?= (int) ($cs['out_of_stock'] ?? 0) ?> out of stock</p>
      <?php foreach (($stockAlerts ?? []) as $p): ?>
        <p><?= e($p['name']) ?> <small class="muted"><?= e($p['sku']) ?> · <?= e($p['status'] === 'active' ? 'Out of stock' : 'Inactive') ?></small></p>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Quick access</h2></div>
    <div class="quick-links">
      <a href="<?= e(url('/admin/users')) ?>">Manage users</a>
      <a href="<?= e(url('/admin/staff')) ?>">Staff &amp; roles</a>
      <a href="<?= e(url('/admin/security')) ?>">Security settings</a>
      <a href="<?= e(url('/catalog')) ?>">Catalog</a>
    </div>
  </div>
</div>
