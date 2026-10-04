<?php
$currentUser = auth_user();
$isCustomer = ($currentUser['role'] ?? null) === 'customer';
$assessmentUrl = $isCustomer ? url('/assessment') : ($currentUser ? url('/shop') : url('/register'));
?>
<main class="store-home storefront-home">
  <section class="storefront-hero" aria-labelledby="home-hero-title">
    <div class="storefront-hero-media" aria-hidden="true">
      <img src="<?= e(asset('images/avela-hero-studio.png')) ?>" alt="" fetchpriority="high">
    </div>
    <div class="storefront-hero-scrim" aria-hidden="true"></div>
    <div class="storefront-hero-inner">
      <div class="storefront-hero-copy">
        <span class="eyebrow hero-eyebrow">CURATED HAIR CARE</span>
        <h1 id="home-hero-title">Find the right care for your hair.</h1>
        <p>Explore hair care for every routine, then narrow your choices by what your hair actually needs.</p>
        <div class="hero-actions">
          <a class="btn btn-primary" href="<?= e(url('/shop')) ?>">Shop hair care</a>
          <a class="btn btn-hero-secondary" href="<?= e($assessmentUrl) ?>">Take hair assessment</a>
        </div>
      </div>
    </div>
  </section>

  <section class="store-section home-section category-section">
    <div class="section-heading-row">
      <div>
        <span class="eyebrow">SHOP BY CATEGORY</span>
        <h2>Start with what your routine needs.</h2>
      </div>
      <a href="<?= e(url('/shop')) ?>">View all products</a>
    </div>
    <div class="category-grid">
      <?php foreach (($categories ?? []) as $index => $category): ?>
        <a class="category-card" href="<?= e(url('/shop?category[]=' . (int) $category['id'])) ?>">
          <span class="category-art" aria-hidden="true"><?php $visualCategory = $category['name']; $placeholderLarge = false; require dirname(__DIR__) . '/partials/product_placeholder.php'; ?></span>
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
        <h2>Everyday picks from the catalog.</h2>
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
      <span class="eyebrow">PERSONALIZATION, WHEN YOU WANT IT</span>
      <h2>Not sure what fits? Start with your hair profile.</h2>
      <p>A short assessment helps Avela narrow the catalog using your hair texture, scalp type, and concerns. You can still shop normally without taking it.</p>
    </div>
    <a class="btn btn-primary" href="<?= e($assessmentUrl) ?>">Take hair assessment</a>
  </section>
</main>
