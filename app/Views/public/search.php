<main class="search-page">
  <section class="search-page-head">
    <span class="eyebrow">SEARCH AVELA</span>
    <h1><?= ($query ?? '') !== '' ? 'Results for “' . e($query) . '”' : 'Search Avela.' ?></h1>
    <form class="search-page-form" method="get" action="<?= e(url('/search')) ?>">
      <label>
        <span class="sr-only">Search Avela</span>
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/></svg>
        <input type="text" name="q" maxlength="100" value="<?= e($query ?? '') ?>" placeholder="Search products, categories, concerns, or pages" autocomplete="off">
      </label>
      <button class="btn btn-primary" type="submit">Search</button>
    </form>
  </section>

  <?php if (($query ?? '') === ''): ?>
    <div class="search-empty"><p>Try a product name, category, hair concern, or page.</p></div>
  <?php elseif (empty($productResults) && empty($discoveryResults) && empty($pageResults)): ?>
    <div class="search-empty"><h2>No results found</h2><p>Try a shorter or more general search term.</p></div>
  <?php else: ?>
    <?php if (!empty($productResults)): ?>
      <section class="search-result-section">
        <div class="section-heading-row">
          <div><span class="eyebrow">PRODUCTS</span><h2>Hair care</h2></div>
          <a href="<?= e(url('/shop?q=' . rawurlencode((string) $query))) ?>">View all product results</a>
        </div>
        <div class="product-grid catalog-grid search-product-grid">
          <?php foreach ($productResults as $product): ?>
            <?php $showExcerpt = false; require dirname(__DIR__) . '/partials/product_card.php'; ?>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if (!empty($discoveryResults)): ?>
      <section class="search-result-section">
        <div class="section-heading-row"><div><span class="eyebrow">DISCOVER</span><h2>Categories & hair concerns</h2></div></div>
        <div class="search-link-list">
          <?php foreach ($discoveryResults as $result): ?>
            <a href="<?= e($result['url']) ?>"><span><strong><?= e($result['title']) ?></strong><small><?= e($result['subtitle']) ?></small></span></a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if (!empty($pageResults)): ?>
      <section class="search-result-section">
        <div class="section-heading-row"><div><span class="eyebrow">PAGES</span><h2>Explore Avela</h2></div></div>
        <div class="search-link-list">
          <?php foreach ($pageResults as $result): ?>
            <a href="<?= e($result['url']) ?>"><span><strong><?= e($result['title']) ?></strong><small><?= e($result['subtitle']) ?></small></span></a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  <?php endif; ?>
</main>
