<section class="page-head">
  <div>
    <span class="eyebrow">YOUR AVELA</span>
    <h1>Hi, <?= e($profile['full_name'] ?? $profile['username'] ?? 'there') ?>.</h1>
    <p>Shop hair care, build your hair profile, or return to your routine.</p>
  </div>
  <a class="btn btn-primary" href="<?= e(url('/assessment')) ?>">Start hair assessment</a>
</section>

<div class="metric-grid three">
  <div class="metric-card"><span>Hair profiles</span><strong><?= e($stats['hair_profiles']) ?></strong></div>
  <div class="metric-card"><span>Cart items</span><strong><?= e($stats['cart_items']) ?></strong></div>
  <div class="metric-card"><span>Orders</span><strong><?= e($stats['orders']) ?></strong></div>
</div>

<section class="action-grid">
  <a class="action-card" href="<?= e(url('/shop')) ?>">
    <span>Shop</span>
    <h3>Browse hair care</h3>
    <p>Explore the current Avela catalog.</p>
  </a>

  <a class="action-card" href="<?= e(url('/assessment')) ?>">
    <span>Hair Profile</span>
    <h3>Understand your hair</h3>
    <p>Record your texture, scalp type, and main concerns.</p>
  </a>

  <a class="action-card" href="<?= e(url('/routine')) ?>">
    <span>Routine</span>
    <h3>Your routine space</h3>
    <p>Keep your recommended steps together in one place.</p>
  </a>
</section>
