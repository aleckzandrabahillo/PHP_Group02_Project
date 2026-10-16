<?php
$rows = $rows ?? [];
$filters = $filters ?? ['q' => '', 'status' => ''];
$roles = ['admin' => 'Administrator', 'catalog_manager' => 'Catalog Manager'];
$statusOptions = ['active' => 'Active', 'inactive' => 'Inactive', 'locked' => 'Locked'];
$selfId = (int) (auth_user()['id'] ?? 0);
?>
<section class="page-head">
  <div>
    <span class="eyebrow">ADMIN</span>
    <h1>Staff &amp; Roles</h1>
    <p>Administrator and Catalog Manager account controls.</p>
  </div>
</section>

<div class="table-card">
  <details style="margin-bottom:16px">
    <summary class="btn btn-primary btn-sm" style="display:inline-flex;cursor:pointer">Add staff account</summary>
    <form method="post" action="<?= e(url('/admin/staff/create')) ?>" style="margin-top:14px" autocomplete="off">
      <?= csrf_field() ?>
      <div class="field-grid two">
        <div class="field">
          <label for="staff_full_name">Full name</label>
          <input id="staff_full_name" name="full_name" type="text" maxlength="120" required value="<?= old('full_name') ?>">
        </div>
        <div class="field">
          <label for="staff_email">Email</label>
          <input id="staff_email" name="email" type="email" maxlength="190" required value="<?= old('email') ?>">
        </div>
        <div class="field">
          <label for="staff_username">Username</label>
          <input id="staff_username" name="username" type="text" maxlength="40" required value="<?= old('username') ?>">
        </div>
        <div class="field">
          <label for="staff_role">Role</label>
          <select id="staff_role" name="role" required>
            <?php foreach ($roles as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= \App\Core\Session::old('role', 'catalog_manager') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="staff_password">Temporary password</label>
          <input id="staff_password" name="password" type="password" minlength="12" required autocomplete="new-password">
        </div>
      </div>
      <p class="muted small">Staff sign in with a one-time code sent to their email.</p>
      <button class="btn btn-primary btn-sm" type="submit">Create account</button>
    </form>
  </details>

  <form class="table-toolbar" method="get" action="<?= e(url('/admin/staff')) ?>" role="search">
    <input class="search-shell" type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" maxlength="100"
           placeholder="Search by name or email" aria-label="Search staff">
    <select class="filter-chip" name="status" aria-label="Filter by status">
      <option value="">All status</option>
      <?php foreach ($statusOptions as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-secondary btn-sm" type="submit">Filter</button>
    <?php if (($filters['q'] ?? '') !== '' || ($filters['status'] ?? '') !== ''): ?>
      <a class="btn btn-secondary btn-sm" href="<?= e(url('/admin/staff')) ?>">Clear</a>
    <?php endif; ?>
  </form>

  <div class="data-table">
    <div class="data-row header">
      <span>Name</span>
      <span>Email</span>
      <span>Role</span>
      <span>Status</span>
      <span>Actions</span>
    </div>
    <?php foreach ($rows as $r):
      $locked = !empty($r['locked_until']) && strtotime((string) $r['locked_until']) > time();
      $isSelf = (int) $r['id'] === $selfId;
    ?>
      <div class="data-row">
        <span><?= e($r['full_name']) ?><?= $isSelf ? ' (you)' : '' ?></span>
        <span><?= e($r['email']) ?></span>
        <span>
          <?php if ($isSelf): ?>
            <?= e($roles[$r['role']] ?? $r['role']) ?>
          <?php else: ?>
            <form method="post" action="<?= e(url('/admin/staff/role')) ?>" style="display:flex;gap:6px">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <select name="role" aria-label="Role for <?= e($r['email']) ?>">
                <?php foreach ($roles as $value => $label): ?>
                  <option value="<?= e($value) ?>" <?= $r['role'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-secondary btn-sm" type="submit">Save</button>
            </form>
          <?php endif; ?>
        </span>
        <span><?= e($locked ? 'Locked' : ucfirst((string) $r['status'])) ?></span>
        <span style="display:flex;gap:8px;flex-wrap:wrap">
          <?php if ($isSelf): ?>
            <span class="muted small">—</span>
          <?php else: ?>
            <?php if ($locked): ?>
              <form method="post" action="<?= e(url('/admin/users/unlock')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <input type="hidden" name="return" value="/admin/staff">
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
          <?php endif; ?>
        </span>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (empty($rows)): ?>
    <div class="table-empty">
      <p><?= (($filters['q'] ?? '') !== '' || ($filters['status'] ?? '') !== '') ? 'No staff match your search.' : 'No staff records to display yet.' ?></p>
    </div>
  <?php endif; ?>

  <?php require __DIR__ . '/../partials/pagination.php'; ?>
</div>
