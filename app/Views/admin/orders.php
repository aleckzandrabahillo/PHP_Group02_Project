<?php
$rows = $rows ?? [];
$total = $total ?? 0;
$statuses = $statuses ?? [];
$filters = ($filters ?? []) + ['q' => '', 'status' => ''];
?>
<section class="page-head">
  <div>
    <span class="eyebrow">ADMIN</span>
    <h1>Orders</h1>
    <p>Review customer orders and their current status.</p>
  </div>
</section>

<div class="table-card admin-list">
  <form method="get" action="<?= e(url('/admin/orders')) ?>" class="table-toolbar">
    <input class="search-shell" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search order #, customer name or email" aria-label="Search orders">
    <select class="filter-chip" name="status" aria-label="Filter by status">
      <option value="">All status</option>
      <?php foreach ($statuses as $status): ?>
        <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-secondary btn-sm" type="submit">Filter</button>
  </form>

  <div class="data-table">
    <div class="data-row header">
      <span>Order</span>
      <span>Customer</span>
      <span>Total</span>
      <span>Date</span>
      <span>Status</span>
      <span>Actions</span>
    </div>

    <?php foreach ($rows as $row): ?>
      <div class="data-row">
        <span>#<?= (int) $row['id'] ?></span>
        <span>
          <?= e($row['customer']) ?>
          <small class="muted"><?= e($row['email']) ?></small>
        </span>
        <span>₱<?= e(number_format((float) $row['total'], 2)) ?></span>
        <span><?= e(date('M j, Y', strtotime((string) $row['created_at']))) ?></span>
        <span><?= e(ucfirst((string) $row['status'])) ?></span>
        <span class="row-actions">
          <a class="btn btn-secondary btn-sm" href="<?= e(url('/admin/orders/view?id=' . (int) $row['id'])) ?>">View</a>
        </span>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (empty($rows)): ?>
    <div class="table-empty">
      <p>No orders match your filters.</p>
    </div>
  <?php endif; ?>

  <?php require dirname(__DIR__) . '/partials/pagination.php'; ?>
</div>
<section class="page-head"><div><span class="eyebrow">ADMINISTRATION</span><h1>Orders</h1><p>Order administration.</p></div></section>
<section class="module-status" aria-labelledby="module-status-title">

  <a class="btn btn-secondary" href="<?= e(url('/admin/security')) ?>">Open security controls</a>
</section>
