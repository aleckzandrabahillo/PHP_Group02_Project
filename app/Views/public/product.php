<?php
require_once dirname(__DIR__).'/partials/handoff_art.php';
$currentUser = auth_user();
$currentRole = $currentUser['role'] ?? null;
$imageUrl = product_image_url($product['image_path'] ?? null);
$inStock = (int) $product['stock_qty'] > 0;
?>
<main id="main-content" tabindex="-1" class="store-page product-page">
  <div class="product-primary-area">
    <nav class="product-breadcrumb" aria-label="Breadcrumb">
      <a href="<?= e(url('/shop')) ?>">Shop</a><span aria-hidden="true">/</span>
      <span><?= e($product['category_name']) ?></span>
    </nav>

    <section class="product-detail">
      <div class="product-detail-photo">
        <?php if ($imageUrl !== null): ?>
          <img class="product-detail-image" src="<?= e($imageUrl) ?>" alt="<?= e($product['name']) ?>" width="1200" height="1500" decoding="async" fetchpriority="high">
        <?php else: ?>
          <?php $visualCategory = $product['category_name'] ?? ''; $placeholderLarge = true; require dirname(__DIR__) . '/partials/product_placeholder.php'; ?>
        <?php endif; ?>
      </div>

      <div class="product-detail-copy">

<p class="eyebrow"><?= e(mb_strtoupper($product['category_name'])) ?></p><div class="product-title-row"><h1><?= e($product['name']) ?></h1><div class="detail-heart"><?php $favoriteProductId = (int) $product['id']; $favoriteProductName = (string) $product['name']; require dirname(__DIR__) . '/partials/favorite_button.php'; unset($favoriteProductName); ?></div></div>
        <strong class="detail-price">₱<?= e(number_format((float) $product['price'], 2)) ?></strong>
        <?php if (trim((string) $product['description']) !== ''): ?>
          <p class="product-description"><?= e((string) $product['description']) ?></p>
        <?php endif; ?>

        <?php if ($currentRole === 'customer'): ?>
          <form id="add-to-bag-form" class="purchase-form detail-add-row" data-quick-add method="post" action="<?= e(url('/cart/add')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <input type="hidden" name="return_to" value="<?= e(current_relative_uri()) ?>">
            <div class="quantity-block">
              <span class="purchase-label visually-hidden">Quantity</span>
              <div class="quantity-control" data-quantity-control>
                <button type="button" data-quantity-minus aria-label="Decrease quantity">−</button>
                <input type="number" name="quantity" min="1" max="<?= max(1, (int) $product['stock_qty']) ?>" value="1" inputmode="numeric" aria-label="Quantity" <?= $inStock ? '' : 'disabled' ?>>
                <button type="button" data-quantity-plus aria-label="Increase quantity">+</button>
              </div>
            </div>
<button class="button primary" type="submit" <?= $inStock ? '' : 'disabled' ?>><?= $inStock ? 'Add to bag' : 'Out of stock' ?> <?= handoff_icon('bag') ?></button>
          </form>

        <?php elseif ($currentRole === null): ?>
          <a class="btn btn-primary product-signin-cta" href="<?= e(url('/login')) ?>">Sign in to add to bag</a>
        <?php endif; ?>
        <div class="product-accordions" data-animate-disclosures>
          <details name="product-care"><summary>How to use</summary><div class="disclosure-body"><p><?php
          $instructions = ['cleanse'=>'Massage into a wet scalp, then rinse thoroughly. Let the rinse pass through the lengths.', 'condition'=>'Apply along the lengths after washing. Detangle gently, then rinse as directed.', 'treat'=>'Use only as directed on the product label, including timing and frequency.', 'finish'=>'Apply only as directed to the lengths or ends. Start with a small amount.'];
          echo e($instructions[$product['routine_step'] ?? ''] ?? 'Follow the instructions supplied with this product.');
          ?></p><p class="product-care-note">General care guidance. Follow the packaging for product-specific amounts, timing and treatment compatibility.</p></div></details>
          <details name="product-care"><summary>What it does</summary><div class="disclosure-body"><p><?= e((string)$product['description']) ?></p>
            <?php foreach(['concern'=>'Listed concerns','texture'=>'Listed textures','scalp'=>'Listed scalp types'] as $key=>$label): if(!empty($product['suitability_tags'][$key])): ?><p><strong><?= e($label) ?>:</strong> <?= e(implode(', ', $product['suitability_tags'][$key])) ?></p><?php endif; endforeach; ?>
            <p class="product-care-note">Catalog suitability supports matching. Benefits and treatment compatibility are not independently verified.</p>
          </div></details>
          <details name="product-care"><summary>Ingredients</summary><div class="disclosure-body">
            <?php if(!empty($productReference['ingredients'])): ?>
              <p class="product-care-note">Reference-based illustrative formula for this fictional academic product. It is not a tested or manufactured Avela formula.</p>
              <p><?= e($productReference['ingredients']) ?></p>
              <p class="product-care-note">Reference: <?= e($productReference['reference_product']) ?>. <a class="text-link" href="<?= e($productReference['source_url']) ?>" target="_blank" rel="noopener noreferrer">Ingredient source</a></p>
            <?php else: ?><p>Ingredient information has not been supplied for this catalog item. Check the product packaging before use, especially if you have sensitivities.</p><?php endif; ?>
          </div></details>
        </div>
        <p class="product-care-note">SKU <?= e($product['sku']) ?> · <?= $inStock ? 'In stock' : 'Out of stock' ?></p>
        <a class="detail-assessment-link" href="<?= e(url('/assessment')) ?>">Find a little clarity for your hair </a>
      </div>
    </section>
  </div>

  <section class="product-information" data-product-tabs>
    <div class="product-tabs" role="tablist" aria-label="Discover more products">
      <?php foreach(['related'=>'You may also like','recent'=>'Recently viewed'] as $key=>$label): ?><button class="product-tab <?= $key==='related'?'active':'' ?>" type="button" role="tab" aria-selected="<?= $key==='related'?'true':'false' ?>" aria-controls="product-panel-<?= $key ?>" id="product-tab-<?= $key ?>" data-product-tab="<?= $key ?>"><?= e($label) ?></button><?php endforeach; ?>
    </div>
    <?php foreach(['related'=>$recommendations,'recent'=>$recentProducts ?? []] as $key=>$items): ?>
      <div class="product-tab-panel <?= $key==='related'?'active':'' ?>" id="product-panel-<?= $key ?>" role="tabpanel" aria-labelledby="product-tab-<?= $key ?>" data-product-panel="<?= $key ?>" <?= $key==='recent'?'hidden':'' ?>>
        <?php if($items): ?><div class="product-grid recommendation-grid"><?php foreach(array_slice($items,0,4) as $productCard): $showExcerpt=true; require dirname(__DIR__).'/partials/product_card.php'; endforeach; unset($productCard); ?></div>
        <?php else: ?><p class="product-tab-empty-message"><?= $key==='recent'?'Products you visit in this browsing session will appear here.':'No related products are available right now.' ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>
</main>
<script src="<?= e(asset('js/reading.js')) ?>" defer></script>
