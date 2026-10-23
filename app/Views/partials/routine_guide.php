<?php
/** $careGuide is presentation-only; it never adds products to the saved plan. */
$guidePaths = [
    'cleanse'=>'M5 9h14M8 9V6a4 4 0 0 1 8 0v3M7 13v1m5-1v1m5-1v1M5 18v2m7-2v2m7-2v2',
    'condition'=>'M12 3c-2 4-6 7-6 11a6 6 0 0 0 12 0c0-4-4-7-6-11ZM9 15c0 2 1 3 3 3',
    'treat'=>'M5 9h14v10H5zM4 6h16v3H4zM9 13h6',
    'detangle'=>'M5 5h14v5H5zM6 10v9m3-9v9m3-9v9m3-9v9m3-9v9',
    'dry'=>'M6 4h12v16H6zM9 4v16m6-16v16',
    'finish'=>'M9 7h6v14H9zM10 7V4h4v3M12 4V2m5 1h3m-1-1v3',
    'check'=>'M10 4a6 6 0 1 0 0 12 6 6 0 0 0 0-12Zm5 11 6 6',
    'handle'=>'M5 5h14v5H5zM6 10v9m3-9v9m3-9v9m3-9v9m3-9v9',
    'heat'=>'M9 4a3 3 0 0 1 6 0v10a5 5 0 1 1-6 0V4Zm3 5v9',
];
?>
<div class="care-guide" data-care-guide>
  <p class="care-rhythm"><?= e($careGuide['rhythm']) ?></p>
  <div class="care-tabs" role="group" aria-label="Choose routine timing">
    <button type="button" data-care-tab="wash" aria-pressed="true" aria-controls="<?= e($guideId) ?>-wash">Wash day</button>
    <button type="button" data-care-tab="between" aria-pressed="false" aria-controls="<?= e($guideId) ?>-between">Between washes</button>
  </div>
  <?php foreach (['wash'=>'Your wash-day steps', 'between'=>'Between-wash care'] as $phase=>$phaseLabel): ?>
  <section class="care-phase" id="<?= e($guideId.'-'.$phase) ?>" data-care-phase="<?= e($phase) ?>" aria-label="<?= e($phaseLabel) ?>">
    <h3 class="care-phase-title"><?= e($phaseLabel) ?></h3>
    <ol class="care-steps"><?php foreach($careGuide[$phase] as $index=>$step): ?>
      <li class="care-step" data-care-step="<?= e($step['key']) ?>">
        <details <?= $index===0?'open':'' ?>><summary><svg class="care-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?= e($guidePaths[$step['key']]??$guidePaths['condition']) ?>"/></svg><span><strong><?= e($step['title']) ?></strong><span><?= e($step['when']) ?></span></span><span class="care-toggle" aria-hidden="true"></span></summary>
          <div class="care-instructions"><p><?= e($step['instruction']) ?></p><p class="care-for-you"><strong>For your hair</strong> <?= e($step['why']) ?></p>
          <?php if($step['product']): $careProduct=$step['product'];$careImage=product_image_url($careProduct['image_path']??null); ?>
            <a class="care-product" href="<?= e(url('/product?id='.(int)($careProduct['id']??$careProduct['product_id']))) ?>">
              <span class="match-routine-photo"><?php if($careImage): ?><img src="<?= e($careImage) ?>" alt="" width="1200" height="1500" loading="lazy" decoding="async"><?php else:$visualCategory=$careProduct['category_name']??'';$placeholderLarge=false;require __DIR__.'/product_placeholder.php';endif; ?></span>
              <span><small><?= $guideSaved?'In your saved routine':'Matched for this step' ?></small><strong><?= e($careProduct['name']) ?></strong><span class="match-step-price">₱<?= e(number_format((float)$careProduct['price'],2)) ?></span><?php if(isset($careProduct['available'])&&!$careProduct['available']): ?><span>Currently unavailable</span><?php endif; ?></span><span class="care-product-link">View product <span aria-hidden="true">→</span></span>
            </a>
          <?php elseif(in_array($step['key'],['cleanse','condition'],true)): ?><p class="care-unmatched">No <?= $step['key']==='cleanse'?'cleansing':'conditioning' ?> match is available for your recorded details. You can follow the care step with a suitable product you already use.</p><?php endif; ?>
          </div>
        </details>
      </li>
    <?php endforeach; ?></ol>
  </section>
  <?php endforeach; ?>
  <details class="care-sources"><summary>About this guidance</summary><p>General care guidance adapted to your recorded answers; follow each product’s label for amounts, timing and treatment compatibility. Product matching uses catalog suitability, not verified effectiveness.</p><p>Sources: <a href="https://www.aad.org/public/everyday-care/hair-scalp-care/hair/healthy-hair-tips" target="_blank" rel="noopener noreferrer">American Academy of Dermatology</a> · <a href="https://www.hair.com/hair-care-routine.html" target="_blank" rel="noopener noreferrer">Hair.com routine guidance</a>.</p></details>
</div>
