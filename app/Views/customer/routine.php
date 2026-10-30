<?php $state=$routineState['state']; ?>
<section class="account-shell"><?php require dirname(__DIR__).'/partials/account_navigation.php'; ?><div class="account-main"><div class="saved-care-page page-shell routine-page-main">
<section class="routine-page-heading"><div><p class="eyebrow">ROOTED IN YOU</p><h1 class="account-page-title">My Routine</h1><p>Care steps and product matches, kept in sync with your hair profile.</p></div>
  <a class="btn btn-secondary" href="<?= e(url('/recommendations')) ?>">Review current matches</a>
</section>
<?php if(!empty($routineState['profile'])):$summary=$routineState['profile'];$profileChoices=\App\Core\HairProfileValidator::CHOICES; ?>
<div class="routine-profile-strip"><div><strong><?= e($profileChoices['hair_texture'][$summary['hair_texture']]??'Your hair profile') ?></strong><p><?= e($profileChoices['scalp_type'][$summary['scalp_type']]??'') ?> · <?= e($profileChoices['hair_condition'][$summary['hair_condition']]??'') ?></p></div><a class="text-link" href="<?= e(url('/assessment')) ?>">View Hair Profile</a></div>
<?php endif; ?>
<?php if ($state==='assessment_needed'): ?>
  <section class="empty-state"><h2>Begin with your hair profile.</h2><p>Tell us about your scalp, lengths and cosmetic concerns.</p><a class="btn btn-primary" href="<?= e(url('/assessment')) ?>">Create hair profile</a></section>
<?php elseif ($state==='profile_ready'): ?>
  <section class="empty-state"><h2>Your profile is ready.</h2><p>Your current profile is the basis for your personalized routine.</p><a class="btn btn-primary" href="<?= e(url('/recommendations')) ?>">See your matches</a></section>
<?php else: ?>
  <?php if ($routineState['stale']): ?><div class="alert alert-warning" role="status">Your profile or the catalog has changed since this routine was saved, or its original explanations are unavailable. Review current matches before using it as a recommendation.</div><?php endif; ?>
  <?php if(!$routineState['stale']): ?>
    <section class="saved-care-content" aria-label="Your personalized care guide"><?php $careItems=array_map(static fn($item)=>['product'=>$item],$routineState['items']);$careGuide=\App\Services\RoutineGuide::forProfile($routineState['profile'],$careItems);$guideId='saved-care';$guideSaved=false;require dirname(__DIR__).'/partials/routine_guide.php'; ?></section>
  <?php endif; ?>
  <?php foreach($routineState['warnings'] ?? [] as $warning): ?><p class="recommendation-notice"><?= e($warning) ?></p><?php endforeach; ?>
  <section class="saved-care-products" aria-labelledby="saved-products-title"><h2 id="saved-products-title">Products in your routine</h2>
  <ol class="saved-routine-list"><?php foreach ($routineState['items'] as $item): ?>
    <li class="saved-routine-item">
      <a class="routine-product-art" href="<?= e(url('/product?id=' . (int) $item['product_id'])) ?>" aria-label="View <?= e($item['name']) ?>">
        <?php $routineImage = product_image_url($item['image_path'] ?? null); ?>
        <?php if ($routineImage !== null): ?><img src="<?= e($routineImage) ?>" alt="<?= e($item['name']) ?>" width="1200" height="1500" loading="lazy" decoding="async">
        <?php else: ?><?php $visualCategory = $item['category_name'] ?? ''; $placeholderLarge = false; require dirname(__DIR__) . '/partials/product_placeholder.php'; ?><?php endif; ?>
      </a>
      <div><span class="saved-product-role"><?= e($item['explanation']['title'] ?? ucfirst($item['routine_step'])) ?></span>
        <h3><a href="<?= e(url('/product?id='.(int)$item['product_id'])) ?>"><?= e($item['name']) ?></a></h3>
        <p><?= e($item['category_name']) ?> · ₱<?= e(number_format((float)$item['price'],2)) ?></p>
        <?php if ($item['explanation']): ?><ul><?php foreach ($item['explanation']['reasons'] as $reason): ?><li><?= e(\App\Services\RecommendationCopy::reason($reason)) ?></li><?php endforeach; ?></ul><?php endif; ?>
      </div>
      <?php if ($item['available'] && !$routineState['stale']): ?>
        <form method="post" action="<?= e(url('/cart/add')) ?>" data-quick-add><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= e($item['product_id']) ?>"><input type="hidden" name="quantity" value="1"><input type="hidden" name="return_to" value="/routine">
          <button class="btn btn-primary" type="submit" aria-label="Add <?= e($item['name']) ?> to bag">Add to bag</button></form>
      <?php elseif (!$item['available']): ?><p class="stock-label out-stock">Currently unavailable</p><?php endif; ?>
    </li>
  <?php endforeach; ?></ol></section>
<?php endif; ?>
</div><script src="<?= e(asset('js/routine-guide.js')) ?>" defer></script></div></section>
