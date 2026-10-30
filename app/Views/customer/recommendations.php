<div class="recommendation-page" data-recommendations data-animate-disclosures>
  <header class="recommendation-heading">
    <div><p class="eyebrow">SHAPED BY YOUR HAIR</p><h1>Your recommendations</h1><p>A few thoughtful steps, based on your saved hair profile.</p></div>
    <?php if ($result['profile'] && $result['routine']['items']): ?>
      <form class="recommendation-actions" method="post" action="<?= e(url('/routine')) ?>"><?= csrf_field() ?><input type="hidden" name="plan_fingerprint" value="<?= e($result['fingerprint']) ?>"><button class="btn btn-primary" type="submit">Save routine</button><a class="text-link" href="<?= e(url('/routine')) ?>">View saved routine</a></form>
    <?php endif; ?>
  </header>
  <?php if ($error): ?><div class="alert alert-error" role="alert" tabindex="-1" data-error-summary><?= e($error) ?></div><?php endif; ?>
  <?php if (!$result['profile'] && !$error): ?>
    <section class="empty-state"><h2>Start with your hair profile.</h2><p>A few choices help us narrow down the catalog.</p><a class="btn btn-primary" href="<?= e(url('/assessment')) ?>">Create hair profile</a></section>
  <?php elseif ($result['profile']):
    $profile=$result['profile']; $choices=\App\Core\HairProfileValidator::CHOICES; $answers=\App\Models\HairProfile::values($profile); $analysis=$result['analysis'] ?? [];
    $inferred=array_values(array_filter($profile['tags'],static fn($tag)=>$tag['source']==='nlp'));
    $inferredTreatments=array_values(array_filter($analysis['tags'] ?? [],static fn($tag)=>$tag['type']==='treatment'));
    $conflicts=array_filter($analysis['tags'] ?? [],static fn($tag)=>($tag['type']==='scalp' && $profile['scalp_type']!=='unsure' && $tag['code']!==$profile['scalp_type']) || ($tag['type']==='texture' && $profile['hair_texture']!=='unsure' && $tag['code']!==$profile['hair_texture']));
  ?>
    <section class="recommendation-profile" aria-label="Your saved hair profile">
      <div class="recommendation-facts"><dl><?php foreach (['hair_texture'=>'Texture','scalp_type'=>'Scalp','hair_condition'=>'Lengths & ends'] as $field=>$label): ?><div><dt><?= e($label) ?></dt><dd><?= e($choices[$field][$profile[$field]] ?? 'Not specified') ?></dd></div><?php endforeach; ?></dl><p><strong>Priorities:</strong> <?= $answers['concerns'] ? e(implode(' · ',array_map(static fn($code)=>$choices['concerns'][$code],$answers['concerns']))) : 'No specific concerns recorded.' ?></p></div>
      <div class="recommendation-profile-links"><a class="text-link" href="<?= e(url('/assessment')) ?>">View hair profile</a><a class="text-link" href="<?= e(url('/assessment?edit=1')) ?>">Edit assessment</a></div>
      <details class="recommendation-profile-details"><summary>More about your assessment</summary><div class="disclosure-body">
        <?php if ($answers['treatments']): ?><p>Treatments: <?= e(implode(', ',array_map(static fn($code)=>$choices['treatments'][$code],$answers['treatments']))) ?>.</p><?php endif; ?>
        <?php if (trim((string)$profile['concern_text'])!==''): ?><p>From your description: <?= ($inferred || $inferredTreatments) ? e(implode(', ',array_merge(array_column($inferred,'display_name'),array_map(static fn($tag)=>$choices['treatments'][$tag['code']] ?? $tag['code'],$inferredTreatments)))) : 'No additional supported characteristics were identified.' ?></p><?php endif; ?>
        <p>Your selected answers take priority over interpreted wording. Suggestions guide cosmetic product discovery, not medical care or guaranteed results.</p>
      </div></details>
    </section>
    <?php if ($conflicts): ?><p class="alert alert-warning" role="status">Your description differs from a selected texture or scalp answer. We kept your selected answer; edit the assessment if it needs changing.</p><?php endif; ?>
    <?php foreach ($analysis['warnings'] ?? [] as $warning): ?><p class="recommendation-notice"><?= e($warning) ?></p><?php endforeach; ?>
    <?php if (in_array('unsure',[$profile['hair_texture'],$profile['scalp_type'],$profile['hair_condition']],true)): ?><p class="recommendation-notice">Some details are still “Not sure”. Matches use only the characteristics available; you can refine your answers later.</p><?php endif; ?>
    <nav class="recommendation-tabs" aria-label="Your recommendations" data-result-tabs><a id="routine-guide-tab" href="#routine-preview-title" data-result-tab="routine">Routine guide</a><a id="product-matches-tab" href="#product-matches-title" data-result-tab="matches">Product matches <span><?= count($result['ranked']) ?></span></a></nav>
    <section id="routine-preview-title" class="recommendation-panel" data-result-panel="routine" aria-labelledby="routine-guide-tab"><h2 class="visually-hidden">Your suggested routine</h2>
      <?php $careGuide=\App\Services\RoutineGuide::forProfile($profile,$result['routine']['items']); require dirname(__DIR__).'/partials/recommendation_guide.php'; ?>
      <?php foreach ($result['routine']['warnings'] ?? [] as $warning): ?><p class="recommendation-notice"><?= e($warning) ?></p><?php endforeach; ?>
      <?php if (!$result['routine']['items']): ?><p class="recommendation-notice">No purchasable routine is available for this profile. You can still follow these care steps, refine your answers or browse the catalog.</p><?php endif; ?>
    </section>
    <section id="product-matches-title" class="recommendation-panel" data-result-panel="matches" aria-labelledby="product-matches-tab"><h2 class="visually-hidden">Your product matches</h2><p class="recommendation-notice">Suggested from your answers and listed product attributes. These alternatives are not automatically added to your saved routine.</p>
      <?php if (!$result['ranked']): ?><div class="empty-state"><h3>No supported matches yet.</h3><p>We leave out products without enough matching information, including undocumented treatment suitability. Try refining your answers or explore the catalog.</p><a class="btn btn-secondary" href="<?= e(url('/shop')) ?>">Browse all products</a></div>
      <?php else: ?>
        <div class="recommendation-filters" role="group" aria-label="Filter matches by care step"><button type="button" data-match-filter="all" aria-pressed="true">All matches</button><?php foreach (['cleanse'=>'Cleanse','condition'=>'Condition','treat'=>'Treat','finish'=>'Finish','accessory'=>'Tools'] as $role=>$label): if (!in_array($role,array_column($result['ranked'],'role'),true)) continue; ?><button type="button" data-match-filter="<?= e($role) ?>" aria-pressed="false"><?= e($label) ?></button><?php endforeach; ?><span data-match-count role="status"><?= count($result['ranked']) ?> matches</span></div>
        <div class="matching-grid"><?php foreach ($result['ranked'] as $match): ?><section class="matching-product" data-match-role="<?= e($match['role']) ?>">
          <?php $productCard=$match; $showExcerpt=false; $showQuickAdd=false; require dirname(__DIR__).'/partials/product_card.php'; unset($productCard,$showQuickAdd); ?>
          <div class="matching-reasons"><p><?= e(\App\Services\RecommendationCopy::reason($match['reasons'][0] ?? [])) ?></p><details><summary>Matching details</summary><div class="disclosure-body"><ul><?php foreach ($match['reasons'] as $reason): ?><li><?= e(\App\Services\RecommendationCopy::reason($reason)) ?></li><?php endforeach; ?></ul><p>Based on listed suitability tags. Ingredient benefits and treatment compatibility are not independently verified.</p></div></details>
          <?php if ($match['available']): ?><p class="match-availability">In stock</p><form method="post" action="<?= e(url('/cart/add')) ?>" data-quick-add><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int)$match['id'] ?>"><input type="hidden" name="quantity" value="1"><input type="hidden" name="return_to" value="/recommendations#product-matches-title"><button class="btn btn-primary" type="submit" aria-label="Add <?= e($match['name']) ?> to bag">Add to bag</button></form><?php else: ?><p class="stock-label out-stock">Currently unavailable</p><?php endif; ?></div>
        </section><?php endforeach; ?></div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
<script src="<?= e(asset('js/recommendations.js')) ?>" defer></script>
<script src="<?= e(asset('js/reading.js')) ?>" defer></script>
