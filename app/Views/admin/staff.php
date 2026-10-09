<?php
use App\Core\Session;
use App\Services\SecuritySettings;

$errors = Session::get('errors', []);
$rows = $rows ?? [];
$total = $total ?? 0;
$selfId = (int) ($selfId ?? 0);
$filters = ($filters ?? []) + ['q' => '', 'status' => ''];
$roleLabels = ['admin' => 'Administrator', 'catalog_manager' => 'Catalog Manager'];
$minLength = SecuritySettings::get('password_min_length');
?>
<section class="page-head">
  <div>
    <span class="eyebrow">ADMIN</span>
    <h1>Staff &amp; Roles</h1>
    <p>Administrator and Catalog Manager account controls.</p>
  </div>
</section>

<div class="panel" style="margin-bottom:20px">
  <div class="panel-head"><h2>Create staff account</h2></div>
  <form method="post" action="<?= e(url('/admin/staff/create')) ?>" class="form-stack" novalidate>
    <?= csrf_field() ?>
    <div class="field-grid two">
      <label class="field <?= isset($errors['full_name']) ? 'has-error' : '' ?>">
        <span>Full name</span>
        <input name="full_name" maxlength="120" value="<?= old('full_name') ?>" required>
        <small class="field-error"><?= e($errors['full_name'] ?? '') ?></small>
      </label>
      <label class="field <?= isset($errors['username']) ? 'has-error' : '' ?>">
        <span>Username</span>
        <input name="username" maxlength="40" value="<?= old('username') ?>" required>
        <small class="field-error"><?= e($errors['username'] ?? '') ?></small>
      </label>
      <label class="field <?= isset($errors['email']) ? 'has-error' : '' ?>">
        <span>Email address</span>
        <input type="email" name="email" maxlength="190" value="<?= old('email') ?>" required>
        <small class="field-error"><?= e($errors['email'] ?? '') ?></small>
      </label>
      <label class="field <?= isset($errors['role']) ? 'has-error' : '' ?>">
        <span>Role</span>
        <select name="role" required>
          <?php foreach ($roleLabels as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= old('role') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <small class="field-error"><?= e($errors['role'] ?? '') ?></small>
      </label>
      <label class="field <?= isset($errors['password']) ? 'has-error' : '' ?>">
        <span>Temporary password <em>(min. <?= (int) $minLength ?> characters)</em></span>
        <input type="password" name="password" autocomplete="new-password" required>
        <small class="field-error"><?= e($errors['password'] ?? '') ?></small>
      </label>
      <label class="field <?= isset($errors['password_confirmation']) ? 'has-error' : '' ?>">
        <span>Confirm password</span>
        <input type="password" name="password_confirmation" autocomplete="new-password" required>
        <small class="field-error"><?= e($errors['password_confirmation'] ?? '') ?></small>
      </label>
    </div>
    <p class="muted small">Staff sign in with an email OTP. Share the temporary password through a secure channel.</p>
    <button class="btn btn-primary" type="submit">Create staff account</button>
  </form>
</div>

<div class="table-card">
  <form method="get" action="<?= e(url('/admin/staff')) ?>" class="table-toolbar">
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
      <span>Role</span>
      <span>Status</span>
      <span>Actions</span>
    </div>

    <?php foreach ($rows as $row): ?>
      <?php
        $isSelf = (int) $row['id'] === $selfId;
        $locked = !empty($row['locked_until']) && strtotime((string) $row['locked_until']) > time();
      ?>
      <div class="data-row">
        <span>
          <?= e($row['full_name']) ?>
          <small class="muted"><?= e($row['username']) ?><?= $isSelf ? ' (you)' : '' ?></small>
        </span>
        <span><?= e($row['email']) ?></span>
        <span><?= e($roleLabels[$row['role']] ?? $row['role']) ?></span>
        <span>
          <?= e(ucfirst((string) $row['status'])) ?>
          <?php if ($locked): ?>
            <small class="muted">Locked until <?= e(date('M j, g:i A', strtotime((string) $row['locked_until']))) ?></small>
          <?php endif; ?>
        </span>
        <span style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
          <?php if ($locked): ?>
            <form method="post" action="<?= e(url('/admin/users/unlock')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
              <input type="hidden" name="return" value="/admin/staff">
              <button class="btn btn-secondary btn-sm" type="submit">Unlock</button>
            </form>
          <?php endif; ?>

          <?php if ($isSelf): ?>
            <small class="muted">Your account</small>
          <?php else: ?>
            <form method="post" action="<?= e(url('/admin/staff/update')) ?>" style="display:flex;gap:6px">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
              <select name="role" aria-label="Role">
                <?php foreach ($roleLabels as $value => $label): ?>
                  <option value="<?= e($value) ?>" <?= $row['role'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-secondary btn-sm" type="submit">Save role</button>
            </form>

            <form method="post" action="<?= e(url('/admin/staff/update')) ?>"
                  <?= $row['status'] === 'active' ? 'onsubmit="return confirm(\'Deactivate this staff account?\');"' : '' ?>>
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
              <input type="hidden" name="status" value="<?= $row['status'] === 'active' ? 'inactive' : 'active' ?>">
              <button class="btn btn-secondary btn-sm" type="submit"><?= $row['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
            </form>
          <?php endif; ?>
        </span>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (empty($rows)): ?>
    <div class="table-empty">
      <p>No staff records match your filters.</p>
    </div>
  <?php endif; ?>

  <?php require dirname(__DIR__) . '/partials/pagination.php'; ?>
</div>
