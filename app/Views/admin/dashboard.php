<section class="page-head">
  <div>
    <span class="eyebrow">SYSTEM OVERVIEW</span>
    <h1>Welcome, <?= e($profile['full_name'] ?? 'Administrator') ?>.</h1>
    <p>Review account counts, manage security controls, and access the shared catalog.</p>
  </div>
</section>

<div class="metric-grid four">
  <div class="metric-card"><span>Customers</span><strong><?= e($stats['customers']) ?></strong></div>
  <div class="metric-card"><span>Staff</span><strong><?= e($stats['staff']) ?></strong></div>
  <div class="metric-card"><span>Orders · all time</span><strong><?= e($stats['orders']) ?></strong></div>
  <div class="metric-card"><span>Locked accounts</span><strong><?= e($stats['locked']) ?></strong></div>
</div>

<?php require dirname(__DIR__).'/partials/dashboard_analytics.php'; ?>
<section class="panel analytics-recent"><h2>Recent activity</h2><?php if(!$analytics['recent']): ?><p class="muted">No activity recorded yet.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Record</th></tr></thead><tbody><?php foreach($analytics['recent'] as $event): ?><tr><td><?= e($event['created_at']) ?></td><td><?= e($event['actor']) ?></td><td><?= e(str_replace(['.','_'],' ',$event['action'])) ?></td><td><?= e(ucfirst($event['target_type'])) ?> <?= (int)$event['target_id'] ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?><a class="text-link" href="<?= e(url('/admin/audit-logs')) ?>">Open audit trail →</a></section>
<div class="panel-grid">
  <div class="panel">
    <div class="panel-head">
      <h2>Account security</h2>
      <a href="<?= e(url('/admin/security')) ?>">Review locks</a>
    </div>
    <div class="table-empty">
      <p>Review current account locks, recorded lock incidents, and the active password and session policy in Security.</p>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Quick access</h2></div>
    <div class="quick-links">
      <a href="<?= e(url('/admin/users')) ?>">Users</a>
      <a href="<?= e(url('/admin/staff')) ?>">Staff &amp; roles</a>
      <a href="<?= e(url('/admin/security')) ?>">Security settings</a>
      <a href="<?= e(url('/catalog')) ?>">Catalog</a>
    </div>
  </div>
</div>
