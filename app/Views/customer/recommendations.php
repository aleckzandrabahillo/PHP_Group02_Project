<link rel="stylesheet" href="<?= e(asset('css/recommendations.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/routine-guide.css')) ?>">
<div class="recommendation-page"><section class="match-page-heading"><div><h1>Your care routine.</h1><?php if($result['profile']): ?><p>A step-by-step guide shaped by your hair profile.</p><nav class="care-page-nav" aria-label="Your care plan"><a href="#routine-preview-title">Routine guide</a><a href="#product-matches-title">Product matches</a></nav><?php endif; ?>

</div>

</section>
<?php if ($error): ?><div class="alert alert-error" role="alert" tabindex="-1" data-error-summary><?= e($error) ?></div><?php endif; ?>

<?php if (!$result['profile'] && !$error): ?>
  <section class="empty-state"><h2>Start with your hair profile.</h2><p>A few choices help us narrow down the catalog.</p><a class="btn btn-primary" href="<?= e(url('/assessment')) ?>">Create hair profile</a></section>
<?php elseif ($result['profile']): ?>
  <?php
    $profile = $result['profile'];
    $choices = \App\Core\HairProfileValidator::CHOICES;
    $answers = \App\Models\HairProfile::values($profile);
    $inferred = array_values(array_filter($profile['tags'], static fn($tag) => $tag['source'] === 'nlp'));
    $analysis = $result['analysis'] ?? [];
    $inferredTreatments = array_values(array_filter($analysis['tags'] ?? [], static fn($tag) => $tag['type'] === 'treatment'));
    $conflicts = array_filter($analysis['tags'] ?? [], static fn($tag) =>
      ($tag['type'] === 'scalp' && $profile['scalp_type'] !== 'unsure' && $tag['code'] !== $profile['scalp_type'])
      || ($tag['type'] === 'texture' && $profile['hair_texture'] !== 'unsure' && $tag['code'] !== $profile['hair_texture']));
  ?>
<div class="match-workspace"><aside class="match-profile" aria-label="Your assessment summary"><details open data-match-profile><summary><h2 id="profile-results-title">Your hair, at a glance</h2><span class="menu-chevron" aria-hidden="true">⌄</span></summary><div class="match-profile-body">
<a class="match-edit-profile" href="<?= e(url('/assessment?edit=1')) ?>">Edit assessment</a>
    <dl class="results-characteristics"><?php foreach (['hair_texture' => 'Texture', 'scalp_type' => 'Scalp', 'hair_condition' => 'Lengths & ends'] as $field => $label): ?>
      <div><dt><?= e($label) ?></dt><dd><?= e($choices[$field][$profile[$field]] ?? 'Not specified') ?></dd></div>
    <?php endforeach; ?></dl>
    <div class="results-sources">
      <div><h3>Your priorities</h3>
        <p><?= $answers['concerns'] ? e(implode(' · ', array_map(static fn($code) => $choices['concerns'][$code], $answers['concerns']))) : 'No specific concerns recorded.' ?></p>
        <?php if($answers['treatments']): ?><p class="muted small">Treatments: <?= e(implode(', ',array_map(static fn($code)=>$choices['treatments'][$code],$answers['treatments']))) ?>.</p><?php endif; ?>
      </div>
      <?php if (trim((string) $profile['concern_text']) !== ''): ?><details class="results-description"><summary>From your description</summary>
        <?php if ($inferred || $inferredTreatments): ?><ul class="results-signals"><?php foreach ($inferred as $tag): ?><li><?= e($tag['display_name']) ?></li><?php endforeach; ?><?php foreach ($inferredTreatments as $tag): ?><li><?= e($choices['treatments'][$tag['code']] ?? $tag['code']) ?></li><?php endforeach; ?></ul>
        <?php else: ?><p><?= trim((string) $profile['concern_text']) !== '' ? 'No additional supported characteristics were identified.' : 'No description added.' ?></p><?php endif; ?>
        <p class="muted small">Interpreted from your wording. Your selected answers take priority.</p>
      </details><?php endif; ?>
    </div>
    <?php if ($conflicts): ?><p class="alert alert-warning" role="status">Your description differs from a selected texture or scalp answer. We kept your selected answer; edit the assessment if it needs changing.</p><?php endif; ?>
    <?php foreach ($analysis['warnings'] ?? [] as $warning): ?><p class="muted small"><?= e($warning) ?></p><?php endforeach; ?>
    <?php if (in_array('unsure', [$profile['hair_texture'], $profile['scalp_type'], $profile['hair_condition']], true)): ?><p class="muted small">Some details are still “Not sure”. Matches use only the characteristics available; you can refine your answers later.</p><?php endif; ?>
  </div></details><p class="match-profile-glance"><?php $glance=[];foreach(['hair_texture','scalp_type','hair_condition'] as $field)$glance[]=$choices[$field][$profile[$field]]??'Not specified';if($profile['scalp_type']==='normal_scalp')$glance[1]='Balanced scalp';echo e(implode(' · ',$glance)); ?></p></aside><div class="match-main">
  <section class="match-routine" aria-labelledby="routine-preview-title">
    <header class="match-section-heading"><h2 id="routine-preview-title">Your suggested routine.</h2></header>
    <?php $careGuide=\App\Services\RoutineGuide::forProfile($profile,$result['routine']['items']);$guideId='suggested-care';$guideSaved=false;require dirname(__DIR__).'/partials/routine_guide.php'; ?>
    <?php if ($result['routine']['items']): ?>
      <form class="match-save-actions" method="post" action="<?= e(url('/routine')) ?>"><?= csrf_field() ?>
        <input type="hidden" name="plan_fingerprint" value="<?= e($result['fingerprint']) ?>">
        <button class="btn btn-primary" type="submit">Save this routine</button><a class="text-link" href="<?= e(url('/routine')) ?>">View saved routine</a>
      </form>
    <?php else: ?><p>No purchasable routine is available for this profile. You can refine your answers or browse the catalog without a recommendation.</p><?php endif; ?>
  </section>
  <?php if (!$result['ranked']): ?>
    <section class="empty-state"><h2>No supported matches yet.</h2><p>We leave out products without enough matching information, including undocumented treatment suitability. Try refining your answers or explore the catalog.</p><a class="btn btn-secondary" href="<?= e(url('/shop')) ?>">Browse all products</a></section>
  <?php else: ?>
    <div class="match-alternatives-heading"><h2 id="product-matches-title">Your product matches.</h2><p>Based on your answers and listed product attributes, not a guarantee of results.</p></div>
    <div class="recommendation-filters" role="group" aria-label="Filter matches by care step"><button type="button" data-match-filter="all" aria-pressed="true">All matches</button><?php foreach(['cleanse'=>'Cleanse','condition'=>'Condition','treat'=>'Treat','finish'=>'Finish','accessory'=>'Tools'] as $role=>$label):if(!in_array($role,array_column($result['ranked'],'role'),true))continue; ?><button type="button" data-match-filter="<?= e($role) ?>" aria-pressed="false"><?= e($label) ?></button><?php endforeach; ?><span data-match-count role="status"><?= count($result['ranked']) ?> matches</span></div>
    <div class="matching-grid"><?php foreach ($result['ranked'] as $match): ?>
      <section class="matching-product" data-match-role="<?= e($match['role']) ?>">
        <?php $productCard=$match; $showExcerpt=false;$showQuickAdd=false; require dirname(__DIR__).'/partials/product_card.php'; unset($productCard,$showQuickAdd); ?>
        <div class="matching-reasons"><h3>Why it matches</h3><ul><?php foreach (array_slice($match['reasons'], 0, 2) as $reason): ?><li><?= e(\App\Services\RecommendationCopy::reason($reason)) ?></li><?php endforeach; ?></ul>
          <details><summary>More about this match</summary>
            <?php if (count($match['reasons']) > 2): ?><ul><?php foreach (array_slice($match['reasons'], 2) as $reason): ?><li><?= e(\App\Services\RecommendationCopy::reason($reason)) ?></li><?php endforeach; ?></ul><?php endif; ?>
            <p>Based on listed suitability tags. Ingredient benefits and treatment compatibility are not independently verified.</p>
          </details>
          <?php if ($match['available']): ?><form method="post" action="<?= e(url('/cart/add')) ?>" data-quick-add><?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= e($match['id']) ?>"><input type="hidden" name="quantity" value="1"><input type="hidden" name="return_to" value="/recommendations">
            <button class="btn btn-primary" type="submit" aria-label="Add <?= e($match['name']) ?> to bag">Add to bag</button>
          </form><?php else: ?><p class="stock-label out-stock">Out of stock</p><?php endif; ?>
        </div>
      </section>
    <?php endforeach; ?></div>
  <?php endif; ?></div></div>
<?php endif; ?></div>
<script src="<?= e(asset('js/recommendations.js')) ?>" defer></script>

<script src="<?= e(asset('js/routine-guide.js')) ?>" defer></script>
