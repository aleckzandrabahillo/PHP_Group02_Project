<section class="page-head">
  <div>
    <span class="eyebrow">ADMIN</span>
    <h1>Audit Logs</h1>
    <p>Review important management actions recorded by the system.</p>
  </div>
</section>

<div class="table-card">
  <div class="data-table">
    <div class="data-row header"><span>Time</span><span>Actor</span><span>Action</span><span>Target</span><span>Details</span></div>
    <?php foreach (($auditLogs ?? []) as $event): ?>
      <div class="data-row">
        <span><?= e($event['created_at']) ?></span><span><?= e($event['actor_name']) ?></span>
        <span><?= e($event['action']) ?></span>
        <span><?= e($event['target_type']) ?><?= $event['target_id'] !== null ? ' #'.(int)$event['target_id'] : '' ?></span>
        <span><?= e($event['details'] ?? '') ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (empty($auditLogs)): ?><div class="table-empty"><p>No audit records to display yet.</p></div><?php endif; ?>
</div>
