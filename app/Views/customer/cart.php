<?php $cart = $cart ?? ['items' => [], 'subtotal' => 0.0, 'item_count' => 0, 'checkout_ready' => false]; ?>
<section class="page-head"><div><span class="eyebrow">SHOPPING BAG</span><h1 class="sr-only">Shopping Bag</h1></div></section>

<?php if (empty($cart['items'])): ?>
  <?php
  $emptyTitle = 'Your bag is empty';
  $emptyMessage = "Browse the catalog when you're ready to add something.";
  $emptyActionLabel = 'Browse products';
  $emptyActionUrl = url('/shop');
  require dirname(__DIR__) . '/partials/empty_state.php';
  ?>
<?php else: ?>
  <div class="cart-layout" data-cart-root>
    <div class="cart-items">
      <?php foreach ($cart['items'] as $item): ?>
        <?php
        $cartImage = product_image_url($item['image_path'] ?? null);
        $itemAvailable = (bool) ($item['is_available'] ?? false);
        $itemCanAdjust = (bool) ($item['can_adjust'] ?? false);
        ?>
        <article class="cart-item" data-cart-item data-product-id="<?= (int) $item['product_id'] ?>">
          <a class="cart-item-art" href="<?= e(url('/product?id=' . (int) $item['product_id'])) ?>" aria-label="View <?= e($item['name']) ?>">
            <?php if ($cartImage !== null): ?>
              <img src="<?= e($cartImage) ?>" alt="<?= e($item['name']) ?>">
            <?php else: ?>
              <?php $visualCategory = $item['category_name'] ?? ''; $placeholderLarge = false; require dirname(__DIR__) . '/partials/product_placeholder.php'; ?>
            <?php endif; ?>
          </a>
          <div class="cart-item-copy">
            <span class="eyebrow"><?= e(strtoupper((string) $item['category_name'])) ?></span>
            <a class="cart-item-name" href="<?= e(url('/product?id=' . (int) $item['product_id'])) ?>"><?= e($item['name']) ?></a>
            <span class="cart-item-price">₱<?= e(number_format((float) $item['price'], 2)) ?> each</span>
            <?php if ((string) $item['status'] !== 'active' || (string) ($item['category_status'] ?? 'active') !== 'active' || (int) $item['stock_qty'] < 1): ?>
              <span class="cart-stock-warning">Currently unavailable</span>
            <?php elseif ((int) $item['quantity'] > (int) $item['stock_qty']): ?>
              <span class="cart-stock-warning">Only <?= (int) $item['stock_qty'] ?> left in stock</span>
            <?php endif; ?>
          </div>
          <div class="cart-item-controls">
            <form class="cart-quantity-form" method="post" action="<?= e(url('/cart/update')) ?>" data-cart-auto-update>
              <?= csrf_field() ?>
              <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
              <div class="quantity-control" data-quantity-control>
                <button type="button" data-quantity-minus aria-label="Decrease quantity" <?= $itemCanAdjust ? '' : 'disabled' ?>>−</button>
                <input type="number" name="quantity" min="1" max="<?= max(1, (int) $item['stock_qty']) ?>" value="<?= (int) $item['quantity'] ?>" data-confirmed-quantity="<?= (int) $item['quantity'] ?>" inputmode="numeric" aria-label="Quantity for <?= e($item['name']) ?>" <?= $itemCanAdjust ? '' : 'disabled' ?>>
                <button type="button" data-quantity-plus aria-label="Increase quantity" <?= $itemCanAdjust ? '' : 'disabled' ?>>+</button>
              </div>
              <span class="cart-inline-feedback" data-cart-feedback hidden aria-live="polite"></span>
            </form>
            <strong class="cart-line-total" data-cart-line-total>₱<?= e(number_format((float) $item['line_total'], 2)) ?></strong>
            <form method="post" action="<?= e(url('/cart/remove')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
              <button class="text-button cart-remove-button" type="submit">Remove</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <aside class="cart-summary">
      <span class="eyebrow">ORDER SUMMARY</span>
      <div class="cart-summary-row"><span>Items</span><strong data-cart-summary-count><?= (int) $cart['item_count'] ?></strong></div>
      <div class="cart-summary-row cart-summary-total"><span>Subtotal</span><strong data-cart-summary-subtotal>₱<?= e(number_format((float) $cart['subtotal'], 2)) ?></strong></div>
      <a class="btn btn-primary cart-checkout-button <?= !empty($cart['checkout_ready']) ? '' : 'is-disabled' ?>"
         data-cart-checkout data-checkout-url="<?= e(url('/checkout')) ?>"
         <?= !empty($cart['checkout_ready']) ? 'href="' . e(url('/checkout')) . '"' : 'aria-disabled="true" tabindex="-1"' ?>>Checkout</a>
    </aside>
  </div>
<?php endif; ?>
