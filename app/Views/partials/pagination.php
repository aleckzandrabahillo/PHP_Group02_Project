<?php
// Expects $total, $limit and $page from the controller. Keeps the current search/filter in the links.
$perPage = max(1, (int) ($limit ?? 25));
$pages = max(1, (int) ceil(((int) ($total ?? 0)) / $perPage));
$current = min(max(1, (int) ($page ?? 1)), $pages);
$query = $_GET;
unset($query['page']);
$pageLink = static fn(int $target): string => '?' . http_build_query($query + ['page' => $target]);
?>
<?php if ($pages > 1): ?>
  <nav class="pager" aria-label="Pagination">
    <span class="muted small">Page <?= $current ?> of <?= $pages ?> · <?= (int) $total ?> total</span>
    <span class="pager-links">
      <?php if ($current > 1): ?>
        <a class="btn btn-secondary btn-sm" href="<?= e($pageLink($current - 1)) ?>">Previous</a>
      <?php endif; ?>
      <?php if ($current < $pages): ?>
        <a class="btn btn-secondary btn-sm" href="<?= e($pageLink($current + 1)) ?>">Next</a>
      <?php endif; ?>
    </span>
  </nav>
<?php endif; ?>
