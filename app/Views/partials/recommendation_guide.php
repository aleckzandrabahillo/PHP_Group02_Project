<?php
/** Compact presentation of the same ordered guide; Saved Routine keeps routine_guide.php. */
require_once __DIR__.'/handoff_art.php';
$stepIllustrations = [
    'detangle'=>'M5 5h14v5H5zM6 10v9m3-9v9m3-9v9m3-9v9m3-9v9',
    'handle'=>'M5 5h14v5H5zM6 10v9m3-9v9m3-9v9m3-9v9m3-9v9',
    'dry'=>'M6 4h12v16H6zM9 4v16m6-16v16',
    'check'=>'M10 4a6 6 0 1 0 0 12 6 6 0 0 0 0-12Zm5 11 6 6',
    'heat'=>'M9 4a3 3 0 0 1 6 0v10a5 5 0 1 1-6 0V4Zm3 5v9',
];
$rankedById = array_column($result['ranked'],null,'id');
?>
<div class="recommendation-guide" data-result-modes>
  <p class="recommendation-rhythm"><?= e($careGuide['rhythm']) ?></p>
  <nav class="recommendation-modes" aria-label="Routine timing" data-mode-tabs><a id="wash-mode-tab" href="#suggested-care-wash" data-mode-tab="wash">Wash day</a><a id="between-mode-tab" href="#suggested-care-between" data-mode-tab="between">Between washes</a></nav>
  <?php foreach (['wash'=>'Wash-day steps','between'=>'Between-wash care'] as $phase=>$phaseLabel): ?>
    <section id="suggested-care-<?= e($phase) ?>" data-mode-panel="<?= e($phase) ?>" aria-labelledby="<?= $phase==='wash'?'wash':'between' ?>-mode-tab">
      <h3 class="visually-hidden"><?= e($phaseLabel) ?></h3>
      <ol class="recommendation-steps"><?php foreach ($careGuide[$phase] as $index=>$step):
        $stepProduct=$step['product'];
        $stepMatch=$stepProduct ? ($rankedById[$stepProduct['id'] ?? $stepProduct['product_id']] ?? null) : null;
        preg_match('/^.*?[.!?](?:\s|$)/u',$step['instruction'],$firstSentence);
        $instruction=$firstSentence[0] ?? $step['instruction'];
      ?>
        <li class="recommendation-step" data-care-step="<?= e($step['key']) ?>">
          <header><span class="recommendation-step-number"><?= str_pad((string)($index+1),2,'0',STR_PAD_LEFT) ?></span><div><p class="recommendation-step-when"><?= e(str_replace('Â·','·',$step['when'])) ?></p><h3><?= e($step['title']) ?></h3></div></header>
          <?php if ($stepProduct): $stepImage=product_image_url($stepProduct['image_path'] ?? null); ?>
            <a class="recommendation-step-product" href="<?= e(url('/product?id='.(int)($stepProduct['id'] ?? $stepProduct['product_id']))) ?>">
              <span class="recommendation-step-visual"><?php if ($stepImage): ?><img src="<?= e($stepImage) ?>" alt="" width="400" height="400" loading="lazy" decoding="async"><?php else: $visualCategory=$stepProduct['category_name'] ?? ''; $placeholderLarge=false; require __DIR__.'/product_placeholder.php'; endif; ?></span>
              <span><small>Suggested for this step</small><strong><?= e($stepProduct['name']) ?></strong><span>₱<?= e(number_format((float)$stepProduct['price'],2)) ?></span></span>
            </a>
            <?php if (isset($stepProduct['available']) && !$stepProduct['available']): ?><p class="stock-label out-stock">Currently unavailable</p><?php endif; ?>
          <?php else: ?>
            <div class="recommendation-step-visual recommendation-step-illustration" aria-hidden="true"><?php if (isset($stepIllustrations[$step['key']])): ?><svg viewBox="0 0 24 24" focusable="false"><path d="<?= e($stepIllustrations[$step['key']]) ?>"/></svg><?php else: ?><?= handoff_care_art($step['key']==='condition'?'condition':($step['key']==='cleanse'?'cleanse':'finish')) ?><?php endif; ?></div>
          <?php endif; ?>
          <p class="recommendation-instruction"><?= e(trim($instruction)) ?></p>
          <p class="recommendation-reason"><?= e($step['why']) ?></p>
          <?php if (!$stepProduct && in_array($step['key'],['cleanse','condition'],true)): ?><p class="recommendation-unmatched">No supported product match for this step. You can use a suitable product you already have.</p><?php endif; ?>
          <details><summary>Step details<span class="visually-hidden">: <?= e($step['title']) ?></span></summary><div class="disclosure-body"><p><?= e($step['instruction']) ?></p>
            <?php if ($stepMatch): ?><ul><?php foreach ($stepMatch['reasons'] as $reason): ?><li><?= e(\App\Services\RecommendationCopy::reason($reason)) ?></li><?php endforeach; ?></ul><?php endif; ?>
            <p>Follow the product label for amounts, timing and treatment compatibility. Listed suitability supports matching, not independently verified effectiveness.</p>
            <a class="text-link" href="<?= e(url('/care-guide')) ?>">Care guidance &amp; references</a>
          </div></details>
        </li>
      <?php endforeach; ?></ol>
    </section>
  <?php endforeach; ?>
</div>
