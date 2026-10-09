<?php
$rows = $rows ?? [];
$total = $total ?? 0;
$filters = ($filters ?? []) + ['q' => ''];
?>
<section class="page-head">
  <div>
    <span class="eyebrow">ADMIN</span>
    <h1>Audit Logs</h1>
    <p>Review important management actions recorded by the system.</p>
  </div>
</section>

<div class="table-card">
  <form method="get" action="<?= e(url('/admin/audit-logs')) ?>" class="table-toolbar">
    <input class="search-shell" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search action, details or actor email">
    <button class="btn btn-secondary btn-sm" type="submit">Search</button>
  </form>

  <div class="data-table">
    <div class="data-row header">
      <span>Time</span>
      <span>Actor</span>
      <span>Action</span>
      <span>Target</span>
      <span>Details</span>
    </div>

    <?php foreach ($rows as $row): ?>
      <div class="data-row">
        <span><?= e(date('M j, Y g:i:s A', strtotime((string) $row['created_at']))) ?></span>
        <span><?= e($row['actor_email'] ?? 'System / deleted user') ?></span>
        <span><?= e($row['action']) ?></span>
        <span>
          <?= e($row['target_type']) ?>
          <?php if (!empty($row['target_id'])): ?>
            <small class="muted">#<?= (int) $row['target_id'] ?></small>
          <?php endif; ?>
        </span>
        <span><small class="muted"><?= e($row['details'] ?? '') ?></small></span>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (empty($rows)): ?>
    <div class="table-empty">
      <p>No audit records match your search.</p>
    </div>
  <?php endif; ?>

  <?php require dirname(__DIR__) . '/partials/pagination.php'; ?>
</div>
