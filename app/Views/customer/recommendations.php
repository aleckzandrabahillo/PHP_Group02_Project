<section class="page-head"><div><span class="eyebrow">YOUR RESULTS</span><h1>Care that fits your profile.</h1>
  <p>Review your answers, explore your matches and save a routine.</p>
</div>
  <a class="btn btn-secondary" href="<?= e(url('/assessment?edit=1')) ?>">Edit hair profile</a>
</section>
<?php if ($error): ?><div class="alert alert-error" role="alert" tabindex="-1" data-error-summary><?= e($error) ?></div><?php endif; ?>
<p class="matching-disclosure">Based on your saved answers and listed product attributes. Matches are a guide, not a guarantee of results.</p>
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
  <section class="assessment-results" aria-labelledby="profile-results-title">
    <div class="results-profile-heading"><h2 id="profile-results-title">Your hair, at a glance</h2><a class="text-link" href="<?= e(url('/assessment?edit=1')) ?>">Edit assessment →</a></div>
    <dl class="results-characteristics"><?php foreach (['hair_texture' => 'Texture', 'scalp_type' => 'Scalp', 'hair_condition' => 'Lengths & ends'] as $field => $label): ?>
      <div><dt><?= e($label) ?></dt><dd><?= e($choices[$field][$profile[$field]] ?? 'Not specified') ?></dd></div>
    <?php endforeach; ?></dl>
    <div class="results-sources">
      <div><h3>From your answers</h3>
        <p><?= $answers['concerns'] ? e(implode(' · ', array_map(static fn($code) => $choices['concerns'][$code], $answers['concerns']))) : 'No specific concerns recorded.' ?></p>
        <p class="muted small">Treatments: <?= $answers['treatments'] ? e(implode(', ', array_map(static fn($code) => $choices['treatments'][$code], $answers['treatments']))) : 'None recorded' ?>.</p>
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
  </section>
  <section class="matching-routine" aria-labelledby="routine-preview-title">
    <div><span class="eyebrow">KEEP IT SIMPLE</span><h2 id="routine-preview-title">Your suggested routine.</h2><p>One product per care step. Extras are optional; follow each product's listed instructions.</p></div>
    <?php foreach ($result['routine']['warnings'] as $warning): ?><p class="alert alert-warning"><?= e($warning) ?></p><?php endforeach; ?>
    <?php if ($result['routine']['items']): ?>
      <ol class="routine-preview-list"><?php foreach ($result['routine']['items'] as $item): ?>
        <li><strong><?= e($item['title']) ?></strong><a href="<?= e(url('/product?id=' . (int) $item['product']['id'])) ?>"><?= e($item['product']['name']) ?></a>
          <span><?= e($item['product']['reasons'][0]['text']) ?></span></li>
      <?php endforeach; ?></ol>
      <form method="post" action="<?= e(url('/routine')) ?>"><?= csrf_field() ?>
        <input type="hidden" name="plan_fingerprint" value="<?= e($result['fingerprint']) ?>">
        <button class="btn btn-primary" type="submit">Save this routine</button><a class="btn btn-secondary" href="<?= e(url('/routine')) ?>">View saved routine</a>
      </form>
    <?php else: ?><p>No purchasable routine is available for this profile. You can refine your answers or browse the catalog without a recommendation.</p><?php endif; ?>
  </section>
  <?php if (!$result['ranked']): ?>
    <section class="empty-state"><h2>No supported matches yet.</h2><p>We leave out products without enough matching information, including undocumented treatment suitability. Try refining your answers or explore the catalog.</p><a class="btn btn-secondary" href="<?= e(url('/shop')) ?>">Browse all products</a></section>
  <?php else: ?>
    <h2>Explore your matches</h2><p class="muted">Out-of-stock products retain their compatibility ranking but cannot be added to your bag.</p>
    <div class="matching-grid"><?php foreach ($result['ranked'] as $match): ?>
      <section class="matching-product">
        <?php $productCard=$match; $showExcerpt=false; require dirname(__DIR__).'/partials/product_card.php'; unset($productCard); ?>
        <div class="matching-reasons"><h3>Why it matches</h3><ul><?php foreach (array_slice($match['reasons'], 0, 2) as $reason): ?><li><?= e($reason['text']) ?></li><?php endforeach; ?></ul>
          <details><summary>More about this match</summary>
            <?php if (count($match['reasons']) > 2): ?><ul><?php foreach (array_slice($match['reasons'], 2) as $reason): ?><li><?= e($reason['text']) ?></li><?php endforeach; ?></ul><?php endif; ?>
            <p>We compare your answers with listed product attributes, giving concerns and cleansing suitability more weight than texture. This does not verify ingredients, treatment compatibility or effectiveness.</p>
          </details>
          <?php if ($match['available']): ?><form method="post" action="<?= e(url('/cart/add')) ?>"><?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= e($match['id']) ?>"><input type="hidden" name="quantity" value="1"><input type="hidden" name="return_to" value="/recommendations">
            <button class="btn btn-primary" type="submit" aria-label="Add <?= e($match['name']) ?> to bag">Add to bag</button>
          </form><?php else: ?><p class="stock-label out-stock">Out of stock</p><?php endif; ?>
        </div>
      </section>
    <?php endforeach; ?></div>
  <?php endif; ?>
<?php endif; ?>
