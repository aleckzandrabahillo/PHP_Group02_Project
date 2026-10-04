<?php
$profile = $profile ?? [];
$cart = $cart ?? ['items' => [], 'subtotal' => 0.0, 'item_count' => 0];
?>
<main class="checkout-page">
  <section class="checkout-details" aria-labelledby="checkout-title">
    <div class="checkout-section checkout-contact-section">
      <span class="eyebrow">CHECKOUT</span>
      <h1 id="checkout-title">Contact and delivery.</h1>

      <div class="checkout-section-heading">
        <h2>Contact</h2>
      </div>
      <div class="checkout-field-grid checkout-field-grid--single">
        <label class="checkout-field">
          <span>Email</span>
          <input type="email" value="<?= e($profile['email'] ?? '') ?>" autocomplete="email">
        </label>
        <label class="checkout-field">
          <span>Phone</span>
          <input type="tel" value="<?= e($profile['contact_no'] ?? '') ?>" autocomplete="tel">
        </label>
      </div>
    </div>

    <div class="checkout-section">
      <div class="checkout-section-heading">
        <h2>Delivery</h2>
      </div>
      <div class="checkout-field-grid">
        <label class="checkout-field checkout-field--full">
          <span>Country / Region</span>
          <input type="text" value="Philippines" readonly aria-readonly="true">
        </label>
        <label class="checkout-field checkout-field--full">
          <span>Full name</span>
          <input type="text" value="<?= e($profile['full_name'] ?? '') ?>" autocomplete="name">
        </label>
        <label class="checkout-field checkout-field--full">
          <span>Address</span>
          <input type="text" value="<?= e($profile['delivery_address'] ?? '') ?>" autocomplete="street-address">
        </label>
        <label class="checkout-field checkout-field--full">
          <span>Barangay</span>
          <input type="text" autocomplete="address-level3">
        </label>
        <label class="checkout-field">
          <span>City / Municipality</span>
          <input type="text" autocomplete="address-level2">
        </label>
        <label class="checkout-field">
          <span>Postal code</span>
          <input type="text" inputmode="numeric" autocomplete="postal-code">
        </label>
        <label class="checkout-field checkout-field--full">
          <span>Region / Province</span>
          <input type="text" autocomplete="address-level1">
        </label>
      </div>
    </div>

    <div class="checkout-section">
      <div class="checkout-section-heading"><h2>Shipping method</h2></div>
      <div class="checkout-pending-panel">Shipping options will be connected with order processing.</div>
    </div>

    <div class="checkout-section checkout-payment-section">
      <div class="checkout-section-heading"><h2>Payment</h2></div>
      <div class="checkout-pending-panel">Payment and final order placement will be implemented in the next checkout phase.</div>
    </div>
  </section>

  <aside class="checkout-summary" aria-label="Order summary">
    <div class="checkout-summary-items">
      <?php foreach ($cart['items'] as $item): ?>
        <?php $checkoutImage = product_image_url($item['image_path'] ?? null); ?>
        <article class="checkout-summary-item">
          <div class="checkout-summary-art">
            <?php if ($checkoutImage !== null): ?>
              <img src="<?= e($checkoutImage) ?>" alt="<?= e($item['name']) ?>">
            <?php else: ?>
              <?php $visualCategory = $item['category_name'] ?? ''; $placeholderLarge = false; require dirname(__DIR__) . '/partials/product_placeholder.php'; ?>
            <?php endif; ?>
            <span class="checkout-item-quantity"><?= (int) $item['quantity'] ?></span>
          </div>
          <div class="checkout-summary-copy">
            <strong><?= e($item['name']) ?></strong>
            <span><?= e($item['category_name']) ?></span>
          </div>
          <strong class="checkout-summary-price">₱<?= e(number_format((float) $item['line_total'], 2)) ?></strong>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="checkout-summary-totals">
      <div><span>Subtotal</span><strong>₱<?= e(number_format((float) $cart['subtotal'], 2)) ?></strong></div>
      <div><span>Shipping</span><span>To be added</span></div>
      <div class="checkout-summary-grand"><span>Estimated total</span><strong><small>PHP</small> ₱<?= e(number_format((float) $cart['subtotal'], 2)) ?></strong></div>
    </div>
  </aside>
</main>
