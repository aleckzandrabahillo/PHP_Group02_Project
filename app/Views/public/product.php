<?php
$currentUser = auth_user();
$currentRole = $currentUser['role'] ?? null;
$imageUrl = product_image_url($product['image_path'] ?? null);
$inStock = (int) $product['stock_qty'] > 0;
$reviewSummary = $reviewSummary ?? ['count' => 0, 'average' => 0.0];
?>
<main class="store-page product-page">
  <div class="product-primary-area">
    <a class="back-link" href="<?= e(url('/shop')) ?>">Back to shop</a>

    <section class="product-detail-shell">
      <div class="product-detail-art">
        <?php if ($imageUrl !== null): ?>
          <img class="product-detail-image" src="<?= e($imageUrl) ?>" alt="<?= e($product['name']) ?>">
        <?php else: ?>
          <?php $visualCategory = $product['category_name'] ?? ''; $placeholderLarge = true; require dirname(__DIR__) . '/partials/product_placeholder.php'; ?>
        <?php endif; ?>
      </div>

      <div class="product-detail-copy">
        <span class="eyebrow"><?= e(strtoupper((string) $product['category_name'])) ?></span>
        <h1><?= e($product['name']) ?></h1>
        <p class="sku">SKU <?= e($product['sku']) ?></p>
        <strong class="product-price">₱<?= e(number_format((float) $product['price'], 2)) ?></strong>
        <?php if (trim((string) $product['description']) !== ''): ?>
          <p class="product-summary"><?= e((string) $product['description']) ?></p>
        <?php endif; ?>

        <div class="detail-meta">
          <span>Routine step <strong><?= e($product['routine_step'] ? ucfirst((string) $product['routine_step']) : 'General care') ?></strong></span>
          <span>Availability <strong><?= $inStock ? 'In stock' : 'Out of stock' ?></strong></span>
        </div>

        <?php
        $tagGroups = [
            'concern' => 'Hair concerns',
            'texture' => 'Hair texture',
            'scalp' => 'Scalp type',
        ];
        ?>
        <div class="product-suitability-overview">
          <?php foreach ($tagGroups as $key => $label): ?>
            <?php if (!empty($product['suitability_tags'][$key])): ?>
              <div class="suitability-block">
                <span><?= e($label) ?></span>
                <div class="suitability-chips">
                  <?php foreach ($product['suitability_tags'][$key] as $tag): ?><span><?= e($tag) ?></span><?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>

        <?php if ($currentRole === 'customer'): ?>
          <form id="add-to-bag-form" class="purchase-form" method="post" action="<?= e(url('/cart/add')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <input type="hidden" name="return_to" value="<?= e(current_relative_uri()) ?>">
            <div class="quantity-block">
              <span class="purchase-label">Quantity</span>
              <div class="quantity-control" data-quantity-control>
                <button type="button" data-quantity-minus aria-label="Decrease quantity">−</button>
                <input type="number" name="quantity" min="1" max="<?= max(1, (int) $product['stock_qty']) ?>" value="1" inputmode="numeric" aria-label="Quantity" <?= $inStock ? '' : 'disabled' ?>>
                <button type="button" data-quantity-plus aria-label="Increase quantity">+</button>
              </div>
            </div>
          </form>
          <div class="purchase-actions">
            <button class="btn btn-primary add-to-bag" type="submit" form="add-to-bag-form" <?= $inStock ? '' : 'disabled' ?>><?= $inStock ? 'Add to bag' : 'Out of stock' ?></button>
            <?php $favoriteProductId = (int) $product['id']; $favoriteProductName = (string) $product['name']; require dirname(__DIR__) . '/partials/favorite_button.php'; unset($favoriteProductName); ?>
          </div>
        <?php elseif ($currentRole === null): ?>
          <a class="btn btn-primary product-signin-cta" href="<?= e(url('/login')) ?>">Sign in to add to bag</a>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <section class="product-information" data-product-tabs>
    <div class="product-tabs" role="tablist" aria-label="Product information">
      <button class="product-tab active" type="button" role="tab" aria-selected="true" aria-controls="product-panel-description" id="product-tab-description" data-product-tab="description">Description</button>
      <button class="product-tab" type="button" role="tab" aria-selected="false" aria-controls="product-panel-reviews" id="product-tab-reviews" data-product-tab="reviews">Reviews<?= (int) $reviewSummary['count'] > 0 ? ' (' . (int) $reviewSummary['count'] . ')' : '' ?></button>
      <button class="product-tab" type="button" role="tab" aria-selected="false" aria-controls="product-panel-recommendations" id="product-tab-recommendations" data-product-tab="recommendations">Recommendations</button>
    </div>

    <div class="product-tab-panel active" id="product-panel-description" role="tabpanel" aria-labelledby="product-tab-description" data-product-panel="description">
      <div class="product-tab-content product-tab-content--copy">
        <span class="eyebrow">ABOUT THIS PRODUCT</span>
        <p class="product-long-description"><?= e((string) $product['description']) ?></p>
      </div>
    </div>

    <div class="product-tab-panel" id="product-panel-reviews" role="tabpanel" aria-labelledby="product-tab-reviews" data-product-panel="reviews" hidden>
      <div class="product-tab-content">
        <span class="eyebrow">REVIEWS</span>
        <?php if (!empty($reviews)): ?>
          <div class="review-summary-line">
            <strong><?= e(number_format((float) $reviewSummary['average'], 1)) ?>/5</strong>
            <span><?= (int) $reviewSummary['count'] ?> review<?= (int) $reviewSummary['count'] === 1 ? '' : 's' ?></span>
          </div>
          <div class="review-list">
            <?php foreach ($reviews as $review): ?>
              <article class="review-card">
                <div><strong><?= e($review['reviewer_name']) ?></strong><span><?= (int) $review['rating'] ?>/5</span></div>
                <?php if (trim((string) $review['review_text']) !== ''): ?><p><?= e($review['review_text']) ?></p><?php endif; ?>
                <time datetime="<?= e(date('Y-m-d', strtotime((string) $review['created_at']))) ?>"><?= e(date('M j, Y', strtotime((string) $review['created_at']))) ?></time>
              </article>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="product-tab-empty-message">No reviews yet.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="product-tab-panel" id="product-panel-recommendations" role="tabpanel" aria-labelledby="product-tab-recommendations" data-product-panel="recommendations" hidden>
      <div class="product-tab-content">
        <span class="eyebrow">RECOMMENDED FOR YOU</span>
        <?php if (!empty($recommendations)): ?>
          <div class="product-grid recommendation-grid">
            <?php foreach ($recommendations as $recommendation): ?>
              <?php $productCard = $recommendation; $showExcerpt = false; require dirname(__DIR__) . '/partials/product_card.php'; unset($productCard); ?>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="product-tab-empty-message">No related products found.</p>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>
