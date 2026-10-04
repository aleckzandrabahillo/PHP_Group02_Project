<?php
$emptyTitle = (string) ($emptyTitle ?? 'Nothing here yet');
$emptyMessage = trim((string) ($emptyMessage ?? ''));
$emptyActionLabel = trim((string) ($emptyActionLabel ?? ''));
$emptyActionUrl = trim((string) ($emptyActionUrl ?? ''));
$emptyExtraClass = trim((string) ($emptyExtraClass ?? ''));
?>
<div class="empty-card shared-empty-state<?= $emptyExtraClass !== '' ? ' ' . e($emptyExtraClass) : '' ?>">
  <h3><?= e($emptyTitle) ?></h3>
  <?php if ($emptyMessage !== ''): ?><p><?= e($emptyMessage) ?></p><?php endif; ?>
  <?php if ($emptyActionLabel !== '' && $emptyActionUrl !== ''): ?>
    <a class="btn btn-primary" href="<?= e($emptyActionUrl) ?>"><?= e($emptyActionLabel) ?></a>
  <?php endif; ?>
</div>
<?php unset($emptyTitle, $emptyMessage, $emptyActionLabel, $emptyActionUrl, $emptyExtraClass); ?>
