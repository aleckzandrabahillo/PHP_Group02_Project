<?php
$rows = $rows ?? [];
$total = $total ?? 0;
$statuses = $statuses ?? ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
$filters = ($filters ?? []) + ['q' => '', 'status' => ''];
?>
<section class="page-head">
  <div>
    <span class="eyebrow">ADMIN</span>
    <h1>Orders</h1>
    <p>Review customer orders and their current status.</p>
  </div>
</section>

<div class="table-card">
  <form method="get" action="<?= e(url('/admin/orders')) ?>" class="table-toolbar">
    <input class="search-shell" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search order #, customer name or email">
    <select class="filter-chip" name="status" aria-label="Filter by status">
      <option value="">All status</option>
      <?php foreach ($statuses as $s): ?>
        <option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
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
        <span>
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
