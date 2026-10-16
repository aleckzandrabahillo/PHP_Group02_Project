<?php
$rows = $rows ?? [];
$filters = $filters ?? ['q' => '', 'status' => ''];
$statusOptions = ['active' => 'Active', 'pending' => 'Pending', 'inactive' => 'Inactive', 'locked' => 'Locked'];
?>
<section class="page-head">
  <div>
    <span class="eyebrow">ADMIN</span>
    <h1>Users</h1>
    <p>Customer account management.</p>
  </div>
</section>

<div class="table-card">
  <form class="table-toolbar" method="get" action="<?= e(url('/admin/users')) ?>" role="search">
    <input class="search-shell" type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" maxlength="100"
           placeholder="Search by name or email" aria-label="Search users">
    <select class="filter-chip" name="status" aria-label="Filter by status">
      <option value="">All status</option>
      <?php foreach ($statusOptions as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-secondary btn-sm" type="submit">Filter</button>
    <?php if (($filters['q'] ?? '') !== '' || ($filters['status'] ?? '') !== ''): ?>
      <a class="btn btn-secondary btn-sm" href="<?= e(url('/admin/users')) ?>">Clear</a>
    <?php endif; ?>
  </form>

  <div class="data-table">
    <div class="data-row header">
      <span>Name</span>
      <span>Email</span>
      <span>Status</span>
      <span>Joined</span>
      <span>Actions</span>
    </div>
    <?php foreach ($rows as $r):
      $locked = !empty($r['locked_until']) && strtotime((string) $r['locked_until']) > time();
      $return = '/admin/users?' . http_build_query(array_filter(['q' => $filters['q'] ?? '', 'status' => $filters['status'] ?? '', 'page' => $_GET['page'] ?? ''], static fn($v) => $v !== '' && $v !== null));
    ?>
      <div class="data-row">
        <span><?= e($r['full_name']) ?></span>
        <span><?= e($r['email']) ?></span>
        <span><?= e($locked ? 'Locked' : ucfirst((string) $r['status'])) ?></span>
        <span><?= e(date('M j, Y', strtotime((string) $r['created_at']))) ?></span>
        <span style="display:flex;gap:8px;flex-wrap:wrap">
          <?php if ($locked): ?>
            <form method="post" action="<?= e(url('/admin/users/unlock')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <input type="hidden" name="return" value="/admin/users">
              <button class="btn btn-secondary btn-sm" type="submit">Unlock</button>
            </form>
          <?php endif; ?>
          <?php if ($r['status'] === 'active' || $r['status'] === 'inactive'): ?>
            <?php $to = $r['status'] === 'active' ? 'inactive' : 'active'; ?>
            <form method="post" action="<?= e(url('/admin/users/status')) ?>"
                  <?= $to === 'inactive' ? 'onsubmit="return confirm(\'Deactivate this account?\')"' : '' ?>>
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <input type="hidden" name="status" value="<?= e($to) ?>">
              <button class="btn btn-secondary btn-sm" type="submit"><?= $to === 'inactive' ? 'Deactivate' : 'Activate' ?></button>
            </form>
          <?php endif; ?>
        </span>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (empty($rows)): ?>
    <div class="table-empty">
      <p><?= (($filters['q'] ?? '') !== '' || ($filters['status'] ?? '') !== '') ? 'No customers match your search.' : 'No customer records to display yet.' ?></p>
    </div>
  <?php endif; ?>

  <?php require __DIR__ . '/../partials/pagination.php'; ?>
</div>
<section class="page-head"><div><span class="eyebrow">ADMINISTRATION</span><h1>Users</h1><p>Customer account management.</p></div></section>
<section class="module-status" aria-labelledby="module-status-title">
  <a class="btn btn-secondary" href="<?= e(url('/admin/security')) ?>">Open security controls</a>
</section>
