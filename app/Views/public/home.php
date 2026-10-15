<?php
$heroProduct = null;
foreach (($featuredProducts ?? []) as $candidate) {
    if ($candidate['sku'] === 'AVE-SMP-001') {
        $heroProduct = $candidate;
        break;
    }
}
?>
<main id="main-content" tabindex="-1" class="store-home storefront-home">
  <section class="campaign-hero" aria-labelledby="home-hero-title">
    <div class="campaign-copy">
      <span class="eyebrow">THE EVERYDAY COLLECTION</span>
      <h1 id="home-hero-title">Hair care,<br><em>at your pace.</em></h1>
      <p>From your first wash to the finishing touch. Find the essentials that fit your everyday routine.</p>
      <div class="hero-actions">
        <a class="btn btn-primary" href="<?= e(url('/shop')) ?>">Shop products <span aria-hidden="true">↗</span></a>
        <a class="campaign-text-link" href="#featured">Explore the collection</a>
      </div>
      <p class="campaign-note">Cleanse. Condition. Finish.<br>A little time for yourself, every day.</p>
    </div>
    <figure class="campaign-image">
      <a href="<?= e($heroProduct ? url('/product?id=' . (int) $heroProduct['id']) : url('/shop')) ?>" aria-label="<?= e($heroProduct ? 'View ' . $heroProduct['name'] : 'Explore Avela products') ?>">
        <img src="<?= e(asset('images/products/demo/avela-ave-smp-001.webp')) ?>" alt="Avela Gentle Hydrating Shampoo" width="1200" height="1500" fetchpriority="high" decoding="async">
      </a>
      <figcaption><span><?= e($heroProduct['name'] ?? 'The Avela collection') ?></span><?php if ($heroProduct): ?><strong>₱<?= e(number_format((float) $heroProduct['price'], 2)) ?></strong><?php endif; ?></figcaption>
    </figure>
  </section>

  <section class="store-section home-section category-section">
    <div class="section-heading-row">
      <div>
        <span class="eyebrow">SHOP BY CATEGORY</span>
        <h2>Your routine, step by step.</h2>
      </div>
      <a href="<?= e(url('/shop')) ?>">View all products</a>
    </div>
    <div class="category-grid">
      <?php foreach (($categories ?? []) as $index => $category): ?>
        <a class="category-card" href="<?= e(url('/shop?category[]=' . (int) $category['id'])) ?>">
          <span class="category-copy">
            <small><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></small>
            <strong><?= e($category['name']) ?></strong>
            <span>Explore <?= e(strtolower($category['name'])) ?></span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="store-section home-section" id="featured">
    <div class="section-heading-row">
      <div>
        <span class="eyebrow">FROM THE CATALOG</span>
        <h2>The everyday essentials.</h2>
      </div>
      <a href="<?= e(url('/shop')) ?>">Shop the catalog</a>
    </div>
    <?php if (!empty($featuredProducts)): ?>
      <div class="product-grid">
        <?php foreach ($featuredProducts as $product): ?>
          <?php $showExcerpt = false; require dirname(__DIR__) . '/partials/product_card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-store-state"><p>The catalog is being prepared. Products added by the Catalog Manager will appear here.</p></div>
    <?php endif; ?>
  </section>

  <?php if (!empty($concerns)): ?>
    <section class="store-section home-section concern-section">
      <div class="section-heading-row">
        <div>
          <span class="eyebrow">SHOP BY HAIR CONCERN</span>
          <h2>Browse around what matters to your hair.</h2>
        </div>
        <a href="<?= e(url('/shop')) ?>">Browse all hair care</a>
      </div>
      <div class="concern-grid">
        <?php foreach ($concerns as $index => $concern): ?>
          <a class="concern-card" href="<?= e(url('/shop?concern[]=' . rawurlencode((string) $concern['code']))) ?>">
            <span class="concern-card-index"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
            <span class="concern-card-copy"><strong><?= e($concern['display_name']) ?></strong><span>View matching products</span></span>
            <span class="concern-card-arrow" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12h13M14 7l5 5-5 5"/></svg></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="assessment-promo home-section">
    <div>
      <span class="eyebrow">FIND YOUR EVERYDAY CARE</span>
      <h2>Start with what your hair needs.</h2>
      <p>Browse the catalog by hair concern, texture or scalp type. Saved hair profiles and personalized routines are planned for a future update.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('/shop')) ?>">Explore hair care</a>
  </section>
</main>
