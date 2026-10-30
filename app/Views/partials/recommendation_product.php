<?php // One match presentation for the chosen shelf and its alternatives. ?>
<article class="matching-product" <?= !isset($featured[$match['id']])?'data-match-role="'.e($match['role']).'"':'' ?>>
  <p class="recommendation-product-role"><?= e($roleLabels[$match['role']] ?? 'Care') ?></p>
  <?php $productCard=$match; $showExcerpt=false; $showQuickAdd=false; require __DIR__.'/product_card.php'; unset($productCard,$showQuickAdd); ?>
  <div class="matching-reasons"><p class="recommendation-product-reason"><?= e(\App\Services\RecommendationCopy::reason($match['reasons'][0] ?? [])) ?></p>
    <details><summary>Why this matches</summary><div class="disclosure-body"><ul><?php foreach($match['reasons'] as $reason): ?><li><?= e(\App\Services\RecommendationCopy::reason($reason)) ?></li><?php endforeach; ?></ul></div></details>
    <?php if($match['available']): ?><form method="post" action="<?= e(url('/cart/add')) ?>" data-quick-add><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int)$match['id'] ?>"><input type="hidden" name="quantity" value="1"><input type="hidden" name="return_to" value="/recommendations#product-matches-title"><button class="btn btn-secondary" type="submit" aria-label="Add <?= e($match['name']) ?> to bag">Add to bag</button></form><?php else: ?><p class="stock-label out-stock">Currently unavailable</p><?php endif; ?>
  </div>
</article>
