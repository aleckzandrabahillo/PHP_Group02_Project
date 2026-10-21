<?php $state=$routineState['state']; ?>
<section class="page-head"><div><h1>Your saved routine</h1></div>
  <a class="btn btn-secondary" href="<?= e(url('/recommendations')) ?>">Review current matches</a>
</section>
<?php if ($state==='assessment_needed'): ?>
  <section class="empty-state"><h2>Begin with your hair profile.</h2><p>Tell us about your scalp, lengths and cosmetic concerns.</p><a class="btn btn-primary" href="<?= e(url('/assessment')) ?>">Create hair profile</a></section>
<?php elseif ($state==='profile_ready'): ?>
  <section class="empty-state"><h2>Your profile is ready.</h2><p>Review the supported matches and save a routine that fits your needs.</p><a class="btn btn-primary" href="<?= e(url('/recommendations')) ?>">See your matches</a></section>
<?php else: ?>
  <?php if ($routineState['stale']): ?><div class="alert alert-warning" role="status">Your profile or the catalog has changed since this routine was saved, or its original explanations are unavailable. Review current matches before using it as a recommendation.</div><?php endif; ?>
  <p class="matching-disclosure">Based on your saved match reasons. Current prices and availability are shown below; matches do not verify ingredients or effectiveness.</p>
  <ol class="saved-routine-list"><?php foreach ($routineState['items'] as $item): ?>
    <li class="saved-routine-item">
      <a class="routine-product-art" href="<?= e(url('/product?id=' . (int) $item['product_id'])) ?>" aria-label="View <?= e($item['name']) ?>">
        <?php $routineImage = product_image_url($item['image_path'] ?? null); ?>
        <?php if ($routineImage !== null): ?><img src="<?= e($routineImage) ?>" alt="<?= e($item['name']) ?>" width="1200" height="1500" loading="lazy" decoding="async">
        <?php else: ?><?php $visualCategory = $item['category_name'] ?? ''; $placeholderLarge = false; require dirname(__DIR__) . '/partials/product_placeholder.php'; ?><?php endif; ?>
      </a>
      <div><span class="eyebrow"><?= e($item['explanation']['title'] ?? ucfirst($item['routine_step'])) ?></span>
        <h2><a href="<?= e(url('/product?id='.(int)$item['product_id'])) ?>"><?= e($item['name']) ?></a></h2>
        <p><?= e($item['category_name']) ?> · ₱<?= e(number_format((float)$item['price'],2)) ?></p>
        <?php if ($item['explanation']): ?><ul><?php foreach ($item['explanation']['reasons'] as $reason): ?><li><?= e($reason['text']) ?></li><?php endforeach; ?></ul><?php endif; ?>
      </div>
      <?php if ($item['available'] && !$routineState['stale']): ?>
        <form method="post" action="<?= e(url('/cart/add')) ?>"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= e($item['product_id']) ?>"><input type="hidden" name="quantity" value="1"><input type="hidden" name="return_to" value="/routine">
          <button class="btn btn-primary" type="submit" aria-label="Add <?= e($item['name']) ?> to bag">Add to bag</button></form>
      <?php elseif (!$item['available']): ?><p class="stock-label out-stock">Currently unavailable</p><?php endif; ?>
    </li>
  <?php endforeach; ?></ol>
<?php endif; ?>
