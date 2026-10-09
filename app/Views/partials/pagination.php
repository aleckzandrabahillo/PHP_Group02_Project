<?php
// Expects: $total, $limit, $page (sent by AdminController). Keeps current search/filter in the links.
$perPage = max(1, (int) ($limit ?? 25));
$pages = max(1, (int) ceil(((int) ($total ?? 0)) / $perPage));
$current = max(1, (int) ($page ?? 1));
$query = $_GET;
unset($query['page']);
$link = static fn(int $p): string => '?' . http_build_query($query + ['page' => $p]);
?>
<?php if ($pages > 1): ?>
  <nav aria-label="Pagination" style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:16px">
    <span class="muted small">Page <?= $current ?> of <?= $pages ?> · <?= (int) $total ?> total</span>
    <span style="display:flex;gap:8px">
      <?php if ($current > 1): ?>
        <a class="btn btn-secondary btn-sm" href="<?= e($link($current - 1)) ?>">Previous</a>
      <?php endif; ?>
      <?php if ($current < $pages): ?>
        <a class="btn btn-secondary btn-sm" href="<?= e($link($current + 1)) ?>">Next</a>
      <?php endif; ?>
    </span>
  </nav>
<?php endif; ?>