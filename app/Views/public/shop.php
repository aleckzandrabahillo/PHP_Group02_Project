<?php
require_once dirname(__DIR__).'/partials/handoff_art.php';
$filters = $filters ?? [
    'categories' => [], 'concerns' => [], 'textures' => [], 'scalps' => [],
    'routine_steps' => [], 'availability' => '', 'min_price' => null, 'max_price' => null,
];
$filterOptions = $filterOptions ?? ['categories' => [], 'concern' => [], 'texture' => [], 'scalp' => [], 'routine_step' => []];
$sort = $sort ?? 'newest';
$sortLabel = 'Newest';
foreach ($sortLinks ?? [] as $option) if ($option['active']) $sortLabel = $option['label'];
$clearParams = [];
if (($search ?? '') !== '') $clearParams['q'] = $search;
if ($sort !== 'newest') $clearParams['sort'] = $sort;
$clearFilterUrl = url('/shop') . ($clearParams ? '?' . http_build_query($clearParams) : '');
?>

<main id="main-content" tabindex="-1" class="store-page shop-page">
  <section class="shop-banner"><div><p class="eyebrow">THE EVERYDAY ESSENTIALS</p><h1>A little care<br>for your shelf.</h1></div><div class="shop-banner-side"><p>Thoughtfully chosen essentials for every texture and every day.</p></div></section><section class="shop-listing">
  <form class="shop-form" method="get" action="<?= e(url('/shop')) ?>" data-shop-form>
    <input type="hidden" name="sort" value="<?= e($sort) ?>">
    <?php if (($search ?? '') !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>

    <div class="shop-results-toolbar">
      <div class="results-meta">
        <span><?= (int) ($resultCount ?? count($products ?? [])) ?> product<?= (int) ($resultCount ?? count($products ?? [])) === 1 ? '' : 's' ?></span>
        <?php if (($search ?? '') !== ''): ?><span>for “<?= e($search) ?>”</span><?php endif; ?>
      </div>

      <div class="shop-tools">
        <details class="sort-menu" data-sort-menu>
          <summary class="toolbar-text-control">
            <span>Sort by: <?= e($sortLabel) ?></span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 10 4 4 4-4"/></svg>
          </summary>
          <div class="sort-menu-panel" aria-label="Sort products">
            <?php foreach (($sortLinks ?? []) as $option): ?>
              <a class="sort-option <?= !empty($option['active']) ? 'active' : '' ?>" href="<?= e($option['url']) ?>" <?= !empty($option['active']) ? 'aria-current="true"' : '' ?>>
                <span class="sort-radio" aria-hidden="true"></span>
                <span><?= e($option['label']) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </details>
        <button class="toolbar-text-control filter-trigger" type="button" data-filter-open aria-haspopup="dialog" aria-controls="product-filter-drawer" aria-expanded="false">
          <span>Filters<?= ($filterCount ?? 0) > 0 ? ' (' . (int) $filterCount . ')' : '' ?></span>
        </button>
      </div>
    </div>

    <dialog id="product-filter-drawer" class="filter-drawer" data-filter-drawer hidden aria-labelledby="filter-title">
      <div class="filter-drawer-panel">
        <div class="filter-drawer-head">
          <div>
            <span class="eyebrow">FILTER PRODUCTS</span>
            <h2 id="filter-title">Find what fits.</h2>
          </div>
          <button class="drawer-close" type="button" data-filter-close aria-label="Close filters" title="Close filters">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
          </button>
        </div>

        <div class="filter-drawer-body">
          <section class="filter-group">
            <h3>Category</h3>
            <div class="filter-options">
              <?php foreach (($filterOptions['categories'] ?? []) as $category): ?>
                <label class="filter-option">
                  <input type="checkbox" name="category[]" value="<?= (int) $category['id'] ?>" <?= in_array((int) $category['id'], $filters['categories'], true) ? 'checked' : '' ?>>
                  <span><?= e($category['name']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="filter-group">
            <h3>Hair concern</h3>
            <div class="filter-options">
              <?php foreach (($filterOptions['concern'] ?? []) as $option): ?>
                <label class="filter-option">
                  <input type="checkbox" name="concern[]" value="<?= e($option['code']) ?>" <?= in_array($option['code'], $filters['concerns'], true) ? 'checked' : '' ?>>
                  <span><?= e($option['display_name']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="filter-group">
            <h3>Hair texture</h3>
            <div class="filter-options">
              <?php foreach (($filterOptions['texture'] ?? []) as $option): ?>
                <label class="filter-option">
                  <input type="checkbox" name="texture[]" value="<?= e($option['code']) ?>" <?= in_array($option['code'], $filters['textures'], true) ? 'checked' : '' ?>>
                  <span><?= e($option['display_name']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="filter-group">
            <h3>Scalp type</h3>
            <div class="filter-options">
              <?php foreach (($filterOptions['scalp'] ?? []) as $option): ?>
                <label class="filter-option">
                  <input type="checkbox" name="scalp[]" value="<?= e($option['code']) ?>" <?= in_array($option['code'], $filters['scalps'], true) ? 'checked' : '' ?>>
                  <span><?= e($option['display_name']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="filter-group">
            <h3>Routine step</h3>
            <div class="filter-options">
              <?php foreach (($filterOptions['routine_step'] ?? []) as $option): ?>
                <label class="filter-option">
                  <input type="checkbox" name="routine_step[]" value="<?= e($option['code']) ?>" <?= in_array($option['code'], $filters['routine_steps'], true) ? 'checked' : '' ?>>
                  <span><?= e($option['display_name']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="filter-group">
            <h3>Availability</h3>
            <div class="filter-options">
              <label class="filter-option"><input type="radio" name="availability" value="" <?= $filters['availability'] === '' ? 'checked' : '' ?>><span>Any availability</span></label>
              <label class="filter-option"><input type="radio" name="availability" value="in_stock" <?= $filters['availability'] === 'in_stock' ? 'checked' : '' ?>><span>In stock</span></label>
              <label class="filter-option"><input type="radio" name="availability" value="out_of_stock" <?= $filters['availability'] === 'out_of_stock' ? 'checked' : '' ?>><span>Out of stock</span></label>
            </div>
          </section>

          <section class="filter-group">
            <h3>Price range</h3>
            <div class="price-row">
              <label class="price-field"><span>Minimum</span><input type="number" name="min_price" min="0" max="999999.99" step="0.01" value="<?= e($filters['min_price'] !== null ? (string) $filters['min_price'] : '') ?>" placeholder="₱0"></label>
              <label class="price-field"><span>Maximum</span><input type="number" name="max_price" min="0" max="999999.99" step="0.01" value="<?= e($filters['max_price'] !== null ? (string) $filters['max_price'] : '') ?>" placeholder="No limit"></label>
            </div>
          </section>
        </div>

        <div class="filter-drawer-footer">
          <button class="btn btn-secondary" type="button" data-filter-reset>Clear selections</button>
          <button class="btn btn-primary" type="submit">Apply filters</button>
        </div>
      </div>
    </dialog>
  </form>

  <?php if (!empty($products)): ?>
    <div class="shop-grid" data-continuous-list="shop" data-stream-page="<?= (int)$catalogPage ?>" data-next-url="<?= e($pageLinks['Next']??'') ?>">
      <?php foreach ($products as $product): ?>
        <?php $showExcerpt = true; require dirname(__DIR__) . '/partials/product_card.php'; ?>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-store-state">
      <h2>No products found</h2>
      <p>Try changing the active filters or searching for something else.</p>
      <a class="btn btn-secondary" href="<?= e(url('/shop')) ?>">View all products</a>
    </div>
  <?php endif; ?>
  <?php $moreUrl=$pageLinks['Next']??'';require dirname(__DIR__).'/partials/continuous_status.php'; ?>
</section><section class="shop-routine-callout"><div><p class="eyebrow">A LITTLE LESS GUESSWORK</p><h2>Care that starts with you.</h2><p>Get to know your hair, then explore your own routine.</p></div><a class="button primary" href="<?= e(url('/assessment')) ?>">Find my routine </a></section></main>
