<div class="recommendation-page" data-recommendations data-animate-disclosures data-reading-page>
  <header class="recommendation-heading">
    <div><p class="eyebrow">YOUR PERSONAL CARE EDIT</p><h1>A little clarity.<br>A routine for you.</h1></div>
    <?php if($result['profile']): ?><a class="btn btn-secondary" href="<?= e(url('/routine')) ?>">My Routine</a><?php endif; ?>
  </header>
  <?php if ($error): ?><div class="alert alert-error" role="alert" tabindex="-1" data-error-summary><?= e($error) ?></div><?php endif; ?>
  <?php if (!$result['profile'] && !$error): ?>
    <section class="empty-state"><h2>Start with your hair profile.</h2><p>A few choices help us narrow down the catalog.</p><a class="btn btn-primary" href="<?= e(url('/assessment')) ?>">Create hair profile</a></section>
  <?php elseif ($result['profile']):
    require_once dirname(__DIR__).'/partials/handoff_art.php';
    $profile=$result['profile']; $choices=\App\Core\HairProfileValidator::CHOICES; $answers=\App\Models\HairProfile::values($profile); $analysis=$result['analysis'] ?? [];
    $careGuide=\App\Services\RoutineGuide::forProfile($profile,$result['routine']['items']);
    $inferred=array_values(array_filter($profile['tags'] ?? [],static fn($tag)=>$tag['source']==='nlp'));
    $inferredTreatments=array_values(array_filter($analysis['tags'] ?? [],static fn($tag)=>$tag['type']==='treatment'));
    $conflicts=array_filter($analysis['tags'] ?? [],static fn($tag)=>($tag['type']==='scalp' && $profile['scalp_type']!=='unsure' && $tag['code']!==$profile['scalp_type']) || ($tag['type']==='texture' && $profile['hair_texture']!=='unsure' && $tag['code']!==$profile['hair_texture']));
    $roleLabels=['cleanse'=>'Cleanse','condition'=>'Condition','treat'=>'Treat','finish'=>'Finish','accessory'=>'Tools'];
    $rankedById=array_column($result['ranked'],null,'id'); $featured=[];
    foreach($result['routine']['items'] as $item){$id=$item['product']['id'] ?? $item['product']['product_id'] ?? 0;if(isset($rankedById[$id]))$featured[$id]=$rankedById[$id];}
    $conditionStep=current(array_filter($careGuide['wash'],static fn($step)=>$step['key']==='condition'));
    $heatStep=current(array_filter($careGuide['between'],static fn($step)=>$step['key']==='heat'));
    $insights=[['Start at the scalp',$careGuide['wash'][0]['why']],['Give lengths their own care',$conditionStep['why']],[$heatStep ? 'Be thoughtful with heat' : 'Keep the finish thoughtful',$heatStep ? $heatStep['instruction'] : 'Add optional products only when you need them. Follow each label for timing, amounts and treatment compatibility.']];
  ?>
    <div class="recommendation-layout"><nav class="help-navigation recommendation-shortcuts" aria-label="On this page">
      <a href="#assessment-insights">01 <span>Your hair &amp; insights</span></a><a href="#product-matches-title">02 <span>Your products</span></a><a href="#routine-preview-title">03 <span>Your routine</span></a>
    </nav>
    <div class="recommendation-main"><section class="recommendation-overview" id="assessment-insights" aria-labelledby="assessment-insights-title">
      <aside class="recommendation-profile" aria-label="Your saved hair profile">
        <div class="recommendation-profile-top"><div><p class="eyebrow">AT A GLANCE</p><h2>Your hair profile</h2></div><a class="text-link" href="<?= e(url('/assessment?edit=1')) ?>">Edit</a></div>
        <div class="recommendation-texture"><?php if(in_array($profile['hair_texture'],['straight','wavy','curly','coily'],true)): ?><?= handoff_texture($profile['hair_texture']) ?><?php endif; ?><div><small>Natural texture</small><strong><?= e($choices['hair_texture'][$profile['hair_texture']] ?? 'Not specified') ?></strong></div></div>
        <dl class="recommendation-facts"><?php foreach(['scalp_type'=>'Scalp','hair_condition'=>'Lengths & ends'] as $field=>$label): ?><div><dt><?= e($label) ?></dt><dd><?= e($choices[$field][$profile[$field]] ?? 'Not specified') ?></dd></div><?php endforeach; ?></dl>
        <div class="recommendation-priorities"><small>Your focus</small><p><?= $answers['concerns'] ? e(implode(' · ',array_map(static fn($code)=>$choices['concerns'][$code],$answers['concerns']))) : 'No specific concerns recorded' ?></p></div>
        <a class="text-link" href="<?= e(url('/assessment')) ?>">View full profile</a>
      </aside>
      <div class="recommendation-insights">
        <p class="eyebrow">UNDERSTANDING YOUR ANSWERS</p><h2 id="assessment-insights-title">Different needs.<br>One thoughtful approach.</h2>
        <ol class="recommendation-insight-list"><?php foreach($insights as $index=>[$title,$copy]): ?><li><span aria-hidden="true"><?= str_pad((string)($index+1),2,'0',STR_PAD_LEFT) ?></span><div><h3><?= e($title) ?></h3><p><?= e($copy) ?></p></div></li><?php endforeach; ?></ol>
        <details class="recommendation-analysis"><summary>How we read your assessment</summary><div class="disclosure-body">
          <?php if($answers['treatments']): ?><p><strong>Recorded styling:</strong> <?= e(implode(', ',array_map(static fn($code)=>$choices['treatments'][$code],$answers['treatments']))) ?>.</p><?php endif; ?>
          <?php if(trim((string)$profile['concern_text'])!==''): ?><p><strong>From your description:</strong> <?= ($inferred || $inferredTreatments) ? e(implode(', ',array_merge(array_column($inferred,'display_name'),array_map(static fn($tag)=>$choices['treatments'][$tag['code']] ?? $tag['code'],$inferredTreatments)))) : 'No additional supported characteristics were identified.' ?></p><?php endif; ?>
          <p>Your selected answers take priority over interpreted wording. Products are matched against listed suitability, not a diagnosis or a prediction of results.</p>
        </div></details>
      </div>
    </section>
    <?php if($conflicts): ?><p class="alert alert-warning" role="status">Your description differs from a selected texture or scalp answer. We kept your selected answer; edit the assessment if it needs changing.</p><?php endif; ?>
    <?php foreach($analysis['warnings'] ?? [] as $warning): ?><p class="recommendation-notice" role="status"><?= e($warning) ?></p><?php endforeach; ?>
    <?php if(in_array('unsure',[$profile['hair_texture'],$profile['scalp_type'],$profile['hair_condition']],true)): ?><p class="recommendation-notice">Some details are still “Not sure”. Matches use only the characteristics available; you can refine your answers later.</p><?php endif; ?>
    <section id="product-matches-title" class="recommendation-section" aria-labelledby="recommendation-products-heading">
      <header class="recommendation-section-heading"><div><p class="eyebrow">02 / CHOSEN FOR YOUR PROFILE</p><h2 id="recommendation-products-heading">Your care shelf.</h2></div><p>A considered starting point, with a reason for every match. Buy only what you need.</p></header>
      <?php if($featured): ?><div class="matching-grid recommendation-featured recommendation-shelf-<?= min(4,count($featured)) ?>"><?php foreach($featured as $match): require dirname(__DIR__).'/partials/recommendation_product.php'; endforeach; ?></div><?php endif; ?>
      <?php if(!$result['ranked']): ?><div class="empty-state"><h3>No supported matches yet.</h3><p>Products without enough suitability information are left out. Refine your answers or explore the catalog.</p><a class="btn btn-secondary" href="<?= e(url('/shop')) ?>">Browse all products</a></div><?php endif; ?>
      <?php $shopParams=$answers['concerns'] ? ['concern'=>$answers['concerns']] : ($profile['hair_texture']!=='unsure' ? ['texture'=>[$profile['hair_texture']]] : ($profile['scalp_type']!=='unsure' ? ['scalp'=>[$profile['scalp_type']]] : [])); ?>
      <a class="recommendation-explore text-link" href="<?= e(url('/shop').($shopParams?'?'.http_build_query($shopParams):'')) ?>">Explore more product matches</a>
    </section>
    <section id="routine-preview-title" class="recommendation-section" aria-labelledby="recommendation-routine-heading">
      <header class="recommendation-section-heading"><div><p class="eyebrow">03 / PUT IT INTO PRACTICE</p><h2 id="recommendation-routine-heading">Your routine, in order.</h2></div><p>Simple steps for wash days and the days in between. Open a step when you want more guidance.</p></header>
      <?php require dirname(__DIR__).'/partials/recommendation_guide.php'; ?>
      <?php foreach($result['routine']['warnings'] ?? [] as $warning): ?><p class="recommendation-notice" role="status"><?= e($warning) ?></p><?php endforeach; ?>
      <?php if(!$result['routine']['items']): ?><p class="recommendation-notice">No purchasable routine is available for this profile. You can still follow these care steps, refine your answers or browse the catalog.</p><?php endif; ?>
    </section>
    <footer class="recommendation-guidance"><p>Shaped by your answers. General cosmetic guidance, not medical advice.</p><details><summary>Care guidance &amp; references</summary><div class="disclosure-body"><p>Follow the product label for amounts, timing and treatment compatibility. Ingredient benefits and treatment compatibility are not independently verified. Listed suitability supports matching, not independently verified effectiveness.</p><a class="text-link" href="<?= e(url('/care-guide')) ?>">Read the care references</a></div></details></footer>
    </div></div>
  <?php endif; ?>
</div>
<script src="<?= e(asset('js/recommendations.js')) ?>" defer></script>
<script src="<?= e(asset('js/reading.js')) ?>" defer></script>
