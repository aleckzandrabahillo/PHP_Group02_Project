<?php
$homeRole = auth_user()['role'] ?? null;
$profileLink = $homeRole === 'customer' ? url('/assessment') : ($homeRole === null ? url('/login') : url('/shop'));
?>
<main id="main-content" tabindex="-1" class="editorial-home">
  <section class="editorial-hero" aria-labelledby="home-hero-title">
    <div class="editorial-hero-visual">
      <img src="<?= e(asset('images/editorial/hair-campaign.jpg')) ?>" alt="Natural curls photographed close up in warm light" width="2400" height="1998" fetchpriority="high" decoding="async">
    </div>
    <div class="editorial-hero-copy">
      <h1 id="home-hero-title">A routine that<br>starts with you.</h1>
      <div class="hero-actions">
        <a class="btn btn-primary" href="<?= e($profileLink) ?>"><?= $homeRole === 'admin' || $homeRole === 'catalog_manager' ? 'Explore the catalog' : 'Find your routine' ?><span aria-hidden="true">→</span></a>
        <a class="editorial-text-link" href="<?= e(url('/shop')) ?>">Shop Avela <span aria-hidden="true">↗</span></a>
      </div>
    </div>
    <div class="editorial-hero-caption"><span>INDIVIDUAL HAIR. CONSIDERED CARE.</span><span>THE AVELA APPROACH</span></div>
  </section>
  <section class="editorial-section" id="featured" aria-labelledby="home-shop-title">
    <div class="editorial-section-heading"><h2 id="home-shop-title">Shop Avela.</h2><span>Care for your everyday.</span></div>
    <nav class="category-tabs" aria-label="Browse product categories">
      <a href="<?= e(url('/shop')) ?>">All care</a>
      <?php foreach (($categories ?? []) as $category): ?><a href="<?= e(url('/shop?category[]=' . (int) $category['id'])) ?>"><?= e($category['name']) ?></a><?php endforeach; ?>
    </nav>
    <?php if (!empty($featuredProducts)): ?>
      <div class="product-grid">
        <?php foreach ($featuredProducts as $product): ?>
          <?php $showExcerpt = false; require dirname(__DIR__) . '/partials/product_card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php else: ?><div class="empty-store-state"><p>New additions to the catalog will appear here.</p></div><?php endif; ?>
    <div class="editorial-collection-footer"><span>Selected from our demonstration collection.</span><a class="editorial-text-link" href="<?= e(url('/shop')) ?>">View all care <span aria-hidden="true">→</span></a></div>
  </section>
  <section class="editorial-concerns" aria-labelledby="home-concern-title">
    <figure><img src="<?= e(asset('images/editorial/hair-editorial.jpg')) ?>" alt="A close-up study of flowing brunette hair" width="1200" height="1800" loading="lazy" decoding="async"><figcaption>THE TEXTURE OF YOU.</figcaption></figure>
    <div class="editorial-concern-copy">
      <h2 id="home-concern-title">Start with<br>what you notice.</h2>
      <p>Different hair. Different needs. Find your starting point.</p>
      <div class="editorial-concern-links">
        <?php foreach (($concerns ?? []) as $concern): ?>
          <a href="<?= e(url('/shop?concern[]=' . rawurlencode((string) $concern['code']))) ?>"><?= e($concern['display_name']) ?><span aria-hidden="true">↗</span></a>
        <?php endforeach; ?>
      </div>
      <small>Browse the catalog by its listed suitability tags.</small>
    </div>
  </section>
  <section class="editorial-section editorial-approach" aria-labelledby="home-approach-title">
    <div><h2 id="home-approach-title">A little about you.<br>A clearer kind of care.</h2><p>Your texture, scalp and concerns. Build a shortlist with explanations you can understand.</p><a class="editorial-text-link" href="<?= e($profileLink) ?>">Explore your care <span aria-hidden="true">→</span></a></div>
    <div class="editorial-approach-details">
      <article><h3>A profile, not a guess.</h3><p>Choose your hair details and add an optional description in English or Taglish.</p></article>
      <article><h3>Know why it matches.</h3><p>Simple language rules and catalog suitability tags give each supported match a clear reason.</p></article>
      <article><h3>Keep it together.</h3><p>Save your routine, review current prices and availability, and add only what you need to your bag.</p></article>
      <p class="editorial-disclosure">Avela is a student demonstration store. Matching uses fictional catalog specifications, not verified formulation benefits or medical advice.</p>
    </div>
  </section>
</main>
