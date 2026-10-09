<?php
$rows = $rows ?? [];
$total = $total ?? 0;
$filters = ($filters ?? []) + ['q' => '', 'event' => '', 'result' => ''];

// metadata_json is stored as JSON text. Turn it into "key: value" pairs for display.
// The returned string is NOT trusted: every use below is wrapped in e().
$formatMeta = static function (?string $json): string {
    if ($json === null || $json === '') {
        return '';
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return $json;
    }
    $parts = [];
    foreach ($data as $key => $value) {
        $parts[] = $key . ': ' . (is_scalar($value) || $value === null ? (string) $value : json_encode($value));
    }
    return implode(', ', $parts);
};

$knownEvents = ['registration', 'activation', 'login', 'logout', 'lockout', 'admin_unlock'];
?>
<section class="page-head">
  <div>
    <span class="eyebrow">ADMIN</span>
    <h1>Authentication Logs</h1>
    <p>Review login, OTP, activation, lockout, and logout events.</p>
  </div>
</section>

<div class="table-card">
  <form method="get" action="<?= e(url('/admin/auth-logs')) ?>" class="table-toolbar">
    <input class="search-shell" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search user email or IP address">
    <input class="filter-chip" type="text" name="event" list="auth-events" value="<?= e($filters['event']) ?>" placeholder="Event">
    <datalist id="auth-events">
      <?php foreach ($knownEvents as $event): ?>
        <option value="<?= e($event) ?>"></option>
      <?php endforeach; ?>
    </datalist>
    <select class="filter-chip" name="result" aria-label="Filter by result">
      <option value="">All results</option>
      <option value="success" <?= $filters['result'] === 'success' ? 'selected' : '' ?>>Success</option>
      <option value="failure" <?= $filters['result'] === 'failure' ? 'selected' : '' ?>>Failure</option>
    </select>
    <button class="btn btn-secondary btn-sm" type="submit">Filter</button>
  </form>

  <div class="data-table">
    <div class="data-row header">
      <span>Time</span>
      <span>User</span>
      <span>Event</span>
      <span>Result</span>
      <span>IP</span>
      <span>Details</span>
    </div>

    <?php foreach ($rows as $row): ?>
      <div class="data-row">
        <span><?= e(date('M j, Y g:i:s A', strtotime((string) $row['created_at']))) ?></span>
        <span><?= e($row['email'] ?? 'Unknown / not signed in') ?></span>
        <span><?= e($row['event']) ?></span>
        <span><?= e(ucfirst((string) $row['result'])) ?></span>
        <span title="<?= e($row['user_agent'] ?? '') ?>"><?= e($row['ip_address'] ?? '') ?></span>
        <span><small class="muted"><?= e($formatMeta($row['metadata_json'] ?? null)) ?></small></span>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (empty($rows)): ?>
    <div class="table-empty">
      <p>No authentication records match your filters.</p>
    </div>
  <?php endif; ?>

  <?php require dirname(__DIR__) . '/partials/pagination.php'; ?>
</div>
