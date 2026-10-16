<section class="page-head"><div><span class="eyebrow">YOUR MATCHES</span><h1>A shortlist with a reason.</h1>
  <p>Based on your saved hair profile and reviewed catalog suitability tags.</p></div>
  <a class="btn btn-secondary" href="<?= e(url('/assessment')) ?>">Edit hair profile</a>
</section>
<?php if ($error): ?><div class="alert alert-error" role="alert" tabindex="-1" data-error-summary><?= e($error) ?></div><?php endif; ?>
<p class="matching-disclosure">This demonstration catalog uses fictional product specifications. Matches explain those specifications; they do not verify ingredients, clinical benefits or product effectiveness.</p>
<?php if (!$result['profile'] && !$error): ?>
  <section class="empty-state"><h2>Start with your hair profile.</h2><p>A few choices help us narrow down the catalog.</p><a class="btn btn-primary" href="<?= e(url('/assessment')) ?>">Create hair profile</a></section>
<?php elseif ($result['profile']): ?>
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
    <section class="empty-state"><h2>No supported matches yet.</h2><p>We leave out products with missing or incompatible suitability information. Treatment compatibility is not verified for the current fictional formulations.</p><a class="btn btn-secondary" href="<?= e(url('/shop')) ?>">Browse all products</a></section>
  <?php else: ?>
    <h2>Explore your matches</h2><p class="muted">Out-of-stock products retain their compatibility ranking but cannot be added to your bag.</p>
    <div class="matching-grid"><?php foreach ($result['ranked'] as $match): ?>
      <section class="matching-product">
        <?php $productCard=$match; $showExcerpt=false; require dirname(__DIR__).'/partials/product_card.php'; unset($productCard); ?>
        <div class="matching-reasons"><h3>Why it matches</h3><ul><?php foreach ($match['reasons'] as $reason): ?><li><?= e($reason['text']) ?></li><?php endforeach; ?></ul>
          <details><summary>Match basis</summary><p>Design score: <?= e($match['score']) ?> points. Concern and cleansing/scalp matches add 4 points each; texture adds 2. These are project ranking choices, not effectiveness percentages.</p>
            <p>Evidence: <?= e($match['evidence']['reference']) ?> · reviewed <?= e($match['evidence']['reviewed_at']) ?> · demonstration design.</p>
            <p>Rules: <?php foreach ($match['reasons'] as $reason): ?><code><?= e($reason['code']) ?></code> <?php endforeach; ?></p>
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
