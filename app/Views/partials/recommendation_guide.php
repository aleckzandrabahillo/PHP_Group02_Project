<?php // Ordered care instructions come from RoutineGuide; no ranking or saved-plan mutation. ?>
<div class="recommendation-guide">
  <nav class="recommendation-modes" aria-label="Routine timing" data-mode-tabs><a id="wash-mode-tab" href="#suggested-care-wash" data-mode-tab="wash">Wash day <span><?= count($careGuide['wash']) ?> steps</span></a><a id="between-mode-tab" href="#suggested-care-between" data-mode-tab="between">Between washes <span>As needed</span></a></nav>
  <p class="recommendation-rhythm"><?= e($careGuide['rhythm']) ?></p>
  <?php foreach(['wash'=>'Wash-day steps','between'=>'Between-wash care'] as $phase=>$phaseLabel): ?>
    <section id="suggested-care-<?= e($phase) ?>" data-mode-panel="<?= e($phase) ?>" aria-labelledby="<?= $phase==='wash'?'wash':'between' ?>-mode-tab">
      <h3 class="visually-hidden"><?= e($phaseLabel) ?></h3>
      <ol class="recommendation-steps"><?php foreach($careGuide[$phase] as $index=>$step):
        $stepProduct=$step['product'];
        preg_match('/^.*?[.!?](?:\s|$)/u',$step['instruction'],$firstSentence);$instruction=trim($firstSentence[0] ?? $step['instruction']);
      ?>
        <li class="recommendation-step" data-care-step="<?= e($step['key']) ?>">
          <span class="recommendation-step-number" aria-hidden="true"><?= str_pad((string)($index+1),2,'0',STR_PAD_LEFT) ?></span>
          <div class="recommendation-step-copy"><p class="recommendation-step-when"><?= e(str_replace('Â·','·',$step['when'])) ?></p><h3><?= e($step['title']) ?></h3><p class="recommendation-instruction"><?= e($instruction) ?></p>
            <details><summary>How &amp; why<span class="visually-hidden">: <?= e($step['title']) ?></span></summary><div class="disclosure-body"><p><?= e($step['instruction']) ?></p><p><strong>For your profile:</strong> <?= e($step['why']) ?></p></div></details>
            <?php if(!$stepProduct && in_array($step['key'],['cleanse','condition'],true)): ?><p class="recommendation-unmatched">No supported product match for this step. You can use a suitable product you already have.</p><?php endif; ?>
          </div>
          <?php if($stepProduct): $stepImage=product_image_url($stepProduct['image_path'] ?? null); ?>
            <a class="recommendation-step-product" href="<?= e(url('/product?id='.(int)($stepProduct['id'] ?? $stepProduct['product_id']))) ?>"><span class="recommendation-step-visual"><?php if($stepImage): ?><img src="<?= e($stepImage) ?>" alt="" width="80" height="96" loading="lazy" decoding="async"><?php else: $visualCategory=$stepProduct['category_name'] ?? ''; $placeholderLarge=false; require __DIR__.'/product_placeholder.php'; endif; ?></span><span><small>For this step</small><strong><?= e($stepProduct['name']) ?></strong><span>₱<?= e(number_format((float)$stepProduct['price'],2)) ?></span><?php if(isset($stepProduct['available']) && !$stepProduct['available']): ?><small class="stock-label out-stock">Currently unavailable</small><?php endif; ?></span></a>
          <?php else: ?><span class="recommendation-step-note"><?= $step['optional']?'Optional care':'Everyday technique' ?><small>No extra product needed</small></span><?php endif; ?>
        </li>
      <?php endforeach; ?></ol>
    </section>
  <?php endforeach; ?>
</div>
