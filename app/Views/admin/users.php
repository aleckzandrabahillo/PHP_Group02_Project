<?php
$rows = $rows ?? [];
$total = $total ?? 0;
$filters = ($filters ?? []) + ['q' => '', 'status' => ''];
?>
<section class="page-head">
  <div>
    <span class="eyebrow">ADMIN</span>
    <h1>Users</h1>
    <p>Customer account management.</p>
  </div>
</section>

<div class="table-card">
  <form method="get" action="<?= e(url('/admin/users')) ?>" class="table-toolbar">
    <input class="search-shell" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search name, email or username">
    <select class="filter-chip" name="status" aria-label="Filter by status">
      <option value="">All status</option>
      <?php foreach (['active', 'pending', 'inactive'] as $s): ?>
        <option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-secondary btn-sm" type="submit">Filter</button>
  </form>

  <div class="data-table">
    <div class="data-row header">
      <span>Name</span>
      <span>Email</span>
      <span>Status</span>
      <span>Joined</span>
      <span>Actions</span>
    </div>

    <?php foreach ($rows as $row): ?>
      <?php $locked = !empty($row['locked_until']) && strtotime((string) $row['locked_until']) > time(); ?>
      <div class="data-row">
        <span>
          <?= e($row['full_name']) ?>
          <small class="muted"><?= e($row['username']) ?></small>
        </span>
        <span><?= e($row['email']) ?></span>
        <span>
          <?= e(ucfirst((string) $row['status'])) ?>
          <?php if ($locked): ?>
            <small class="muted">Locked until <?= e(date('M j, g:i A', strtotime((string) $row['locked_until']))) ?></small>
          <?php endif; ?>
        </span>
        <span><?= e(date('M j, Y', strtotime((string) $row['created_at']))) ?></span>
        <span style="display:flex;gap:6px;flex-wrap:wrap">
          <?php if ($locked): ?>
            <form method="post" action="<?= e(url('/admin/users/unlock')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
              <input type="hidden" name="return" value="/admin/users">
              <button class="btn btn-secondary btn-sm" type="submit">Unlock</button>
            </form>
          <?php endif; ?>

          <?php if ($row['status'] === 'active'): ?>
            <form method="post" action="<?= e(url('/admin/users/status')) ?>" onsubmit="return confirm('Deactivate this account?');">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
              <input type="hidden" name="status" value="inactive">
              <button class="btn btn-secondary btn-sm" type="submit">Deactivate</button>
            </form>
          <?php elseif ($row['status'] === 'inactive'): ?>
            <form method="post" action="<?= e(url('/admin/users/status')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
              <input type="hidden" name="status" value="active">
              <button class="btn btn-secondary btn-sm" type="submit">Activate</button>
            </form>
          <?php endif; ?>
        </span>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (empty($rows)): ?>
    <div class="table-empty">
      <p>No customer records match your filters.</p>
    </div>
  <?php endif; ?>

  <?php require dirname(__DIR__) . '/partials/pagination.php'; ?>
</div>
