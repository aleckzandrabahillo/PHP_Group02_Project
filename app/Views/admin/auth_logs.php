<section class="page-head">
  <div>
    <span class="eyebrow">ADMIN</span>
    <h1>Authentication Logs</h1>
    <p>Review login, OTP, activation, lockout, and logout events.</p>
  </div>
</section>

<div class="table-card">
  <form class="table-toolbar" method="get" action="<?= e(url('/admin/auth-logs')) ?>" role="search">
    <input class="search-shell" type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" maxlength="100"
           placeholder="Search by user email or IP" aria-label="Search authentication logs">
    <select name="event" aria-label="Filter by event">
      <option value="">All events</option>
      <?php foreach (($events ?? []) as $ev): ?>
        <option value="<?= e($ev) ?>" <?= ($filters['event'] ?? '') === $ev ? 'selected' : '' ?>><?= e($ev) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="result" aria-label="Filter by result">
      <option value="">All results</option>
      <option value="success" <?= ($filters['result'] ?? '') === 'success' ? 'selected' : '' ?>>Success</option>
      <option value="failure" <?= ($filters['result'] ?? '') === 'failure' ? 'selected' : '' ?>>Failure</option>
    </select>
    <button class="btn btn-secondary btn-sm" type="submit">Filter</button>
    <?php if (($filters['q'] ?? '') !== '' || ($filters['event'] ?? '') !== '' || ($filters['result'] ?? '') !== ''): ?>
      <a class="btn btn-secondary btn-sm" href="<?= e(url('/admin/auth-logs')) ?>">Clear</a>
    <?php endif; ?>
  </form>

  <div class="data-table">
    <div class="data-row header">
      <span>Time</span>
      <span>User</span>
      <span>Event</span>
      <span>Result</span>
      <span>IP</span>
    </div>
    <?php foreach (($rows ?? []) as $r): ?>
      <div class="data-row">
        <span><?= e($r['created_at']) ?></span>
        <span><?= e($r['email'] ?? 'Unknown / deleted user') ?></span>
        <span><?= e($r['event']) ?></span>
        <span><?= e($r['result']) ?></span>
        <span><?= e($r['ip_address'] ?? '') ?></span>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (empty($rows)): ?>
    <div class="table-empty">
      <p>No authentication records match.</p>
    </div>
  <?php endif; ?>

  <?php require __DIR__ . '/../partials/pagination.php'; ?>
</div>
