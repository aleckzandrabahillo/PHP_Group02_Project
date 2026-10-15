<?php
$routineState = $routineState ?? ['state' => 'assessment_needed', 'profile' => null, 'routine' => null, 'items' => []];
$state = (string) ($routineState['state'] ?? 'assessment_needed');
?>
<section class="page-head"><div><span class="eyebrow">MY ROUTINE</span><h1>Your routine, in the right order.</h1></div></section>

<?php if ($state === 'assessment_needed'): ?>
  <?php
  $emptyTitle = 'Your routine space';
  $emptyMessage = 'Hair-profile collection and new personalized routine building are still in development. You can browse the catalog while these features are prepared.';
  $emptyActionLabel = 'Preview planned hair profile';
  $emptyActionUrl = url('/assessment');
  $emptyExtraClass = 'routine-empty-state';
  require dirname(__DIR__) . '/partials/empty_state.php';
  ?>
<?php elseif ($state === 'profile_ready'): ?>
  <?php
  $emptyTitle = 'Your hair profile is ready';
  $emptyMessage = 'Your saved hair profile remains available. Building a new personalized routine is still in development.';
  $emptyActionLabel = 'Browse products';
  $emptyActionUrl = url('/shop');
  $emptyExtraClass = 'routine-empty-state';
  require dirname(__DIR__) . '/partials/empty_state.php';
  ?>
<?php else: ?>
  <div class="routine-row routine-row--personalized">
    <?php foreach (($routineState['items'] ?? []) as $index => $item): ?>
      <article class="routine-step routine-step--product">
        <span><?= e(str_pad((string) ((int) $index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
        <strong><?= e(ucfirst((string) $item['routine_step'])) ?></strong>
        <a href="<?= e(url('/product?id=' . (int) $item['product_id'])) ?>"><?= e($item['name']) ?></a>
        <p><?= e($item['category_name']) ?> · ₱<?= e(number_format((float) $item['price'], 2)) ?></p>
      </article>
      <?php if ($index < count($routineState['items']) - 1): ?><div class="routine-arrow" aria-hidden="true">→</div><?php endif; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
