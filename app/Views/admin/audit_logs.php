<section class="page-head"><div><span class="eyebrow">ADMINISTRATION</span><h1>Audit Logs</h1><p>Management action history.</p></div></section>
<section class="module-status" aria-labelledby="module-status-title">
  <a class="btn btn-secondary" href="<?= e(url('/admin/security')) ?>">Open security controls</a>
</section>

<div class="table-card">
  <form class="table-toolbar" method="get" action="<?= e(url('/admin/audit-logs')) ?>" role="search">
    <input class="search-shell" type="search" name="q" value="<?= e($term ?? '') ?>" maxlength="100"
           placeholder="Search by action, target, details or actor email" aria-label="Search audit logs">
    <button class="btn btn-secondary btn-sm" type="submit">Search</button>
    <?php if (($term ?? '') !== ''): ?>
      <a class="btn btn-secondary btn-sm" href="<?= e(url('/admin/audit-logs')) ?>">Clear</a>
    <?php endif; ?>
  </form>

  <div class="data-table">
    <div class="data-row header">
      <span>Time</span>
      <span>Actor</span>
      <span>Action</span>
      <span>Target</span>
      <span>Details</span>
    </div>
    <?php foreach (($logs ?? []) as $log): ?>
      <div class="data-row">
        <span><?= e($log['created_at']) ?></span>
        <span><?= e($log['actor_email'] ?? 'System / deleted user') ?></span>
        <span><?= e($log['action']) ?></span>
        <span><?= e($log['target_type'] . ($log['target_id'] !== null ? ' #' . $log['target_id'] : '')) ?></span>
        <span><?= e($log['details'] ?? '') ?></span>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (empty($logs)): ?>
    <div class="table-empty">
      <p><?= ($term ?? '') !== '' ? 'No audit records match your search.' : 'No audit records to display yet.' ?></p>
    </div>
  <?php endif; ?>

  <?php require __DIR__ . '/../partials/pagination.php'; ?>
</div>
