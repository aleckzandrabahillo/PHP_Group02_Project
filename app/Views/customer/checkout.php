<?php
$profile = $profile ?? [];
$cart = $cart ?? ['items' => [], 'subtotal' => 0.0, 'item_count' => 0];
?>
<form class="checkout-page" method="post" action="<?= e(url('/checkout')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="checkout_key" value="<?= e($checkoutKey) ?>">
  <section class="checkout-details" aria-labelledby="checkout-title">
    <div class="checkout-section checkout-contact-section">
      <span class="eyebrow">CHECKOUT</span>
      <h1 id="checkout-title">Contact and delivery.</h1>
      <?php if ($errors): ?><div class="checkout-errors" role="alert" tabindex="-1" data-error-summary><strong>Please review your order.</strong>
        <ul><?php foreach ($errors as $key => $error): ?><li><?php if (in_array($key, ['full_name', 'contact_no', 'delivery_address'], true)): ?><a href="#checkout-<?= e($key) ?>"><?= e($error) ?></a><?php else: ?><?= e($error) ?><?php endif; ?></li><?php endforeach; ?></ul>
      </div><?php endif; ?>

      <div class="checkout-section-heading">
        <h2>Contact</h2>
      </div>
      <div class="checkout-field-grid checkout-field-grid--single">
        <label class="checkout-field">
          <span>Account email</span>
          <input type="email" value="<?= e($profile['email'] ?? '') ?>" readonly aria-readonly="true" autocomplete="email">
        </label>
        <label class="checkout-field">
          <span>Mobile number</span>
          <input id="checkout-contact_no" type="tel" name="contact_no" value="<?= e($values['contact_no']) ?>" maxlength="25" required autocomplete="tel" aria-invalid="<?= isset($errors['contact_no']) ? 'true' : 'false' ?>" <?= isset($errors['contact_no']) ? 'aria-describedby="checkout-contact-error"' : '' ?>>
          <?php if (isset($errors['contact_no'])): ?><small class="checkout-field-error" id="checkout-contact-error"><?= e($errors['contact_no']) ?></small><?php endif; ?>
        </label>
      </div>
      <p class="muted small">Used for delivery contact. Phone numbers are not verified.</p>
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
          <span>Recipient full name</span>
          <input id="checkout-full_name" type="text" name="full_name" value="<?= e($values['full_name']) ?>" maxlength="120" required autocomplete="name" aria-invalid="<?= isset($errors['full_name']) ? 'true' : 'false' ?>" <?= isset($errors['full_name']) ? 'aria-describedby="checkout-name-error"' : '' ?>>
          <?php if (isset($errors['full_name'])): ?><small class="checkout-field-error" id="checkout-name-error"><?= e($errors['full_name']) ?></small><?php endif; ?>
        </label>
        <label class="checkout-field checkout-field--full">
          <span>Complete delivery address</span>
          <textarea id="checkout-delivery_address" name="delivery_address" rows="4" maxlength="500" required autocomplete="street-address" aria-describedby="delivery-help<?= isset($errors['delivery_address']) ? ' checkout-address-error' : '' ?>" aria-invalid="<?= isset($errors['delivery_address']) ? 'true' : 'false' ?>"><?= e($values['delivery_address']) ?></textarea>
          <?php if (isset($errors['delivery_address'])): ?><small class="checkout-field-error" id="checkout-address-error"><?= e($errors['delivery_address']) ?></small><?php endif; ?>
        </label>
        <p id="delivery-help" class="checkout-field--full muted small">Include house/building, street, barangay, city, province and postal code. This order's delivery details do not change your profile.</p>
      </div>
    </div>

    <div class="checkout-section">
      <div class="checkout-section-heading"><h2>Shipping method</h2></div>
      <div class="checkout-pending-panel">Demonstration delivery: no shipping charge is added. No courier booking is performed.</div>
    </div>

    <div class="checkout-section checkout-payment-section">
      <div class="checkout-section-heading"><h2>Payment</h2></div>
      <label class="checkout-payment-choice"><input type="radio" name="payment_method" value="cod" checked required> Cash on delivery (demonstration)</label>
      <p class="muted small">No online payment is collected. Do not enter card or bank details.</p>
      <a class="back-link" href="<?= e(url('/cart')) ?>">Return to your bag</a>
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
            <span><?= (int) $item['quantity'] ?> × ₱<?= e(number_format((float) $item['price'], 2)) ?></span>
            <?php if (!$item['is_available']): ?><span>Review quantity or availability in your bag.</span><?php endif; ?>
          </div>
          <strong class="checkout-summary-price">₱<?= e(number_format((float) $item['line_total'], 2)) ?></strong>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="checkout-summary-totals">
      <div><span>Subtotal</span><strong>₱<?= e(number_format((float) $cart['subtotal'], 2)) ?></strong></div>
      <div><span>Shipping (demonstration)</span><strong>₱0.00</strong></div>
      <div class="checkout-summary-grand"><span>Total</span><strong><small>PHP</small> ₱<?= e(number_format((float) $cart['subtotal'], 2)) ?></strong></div>
      <button class="btn btn-primary" type="submit" <?= !$cart['checkout_ready'] ? 'disabled' : '' ?>>Place order — cash on delivery</button>
      <p class="muted small">Stock and prices are checked again when you place the order.</p>
    </div>
  </aside>
</form>
