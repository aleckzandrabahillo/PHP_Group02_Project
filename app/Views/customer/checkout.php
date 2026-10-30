<?php
$profile=$profile??[];$shippingQuote=null;
$eligibleSubtotal=(int)round((float)$cart['subtotal']*100)-(int)($discountPreview['amount_cents']??0);
try{$shippingQuote=\App\Services\ShippingService::quote($values['postal_code']??'',$values['shipping_method']??'standard',$values['city']??'',$values['region']??'',$eligibleSubtotal);}catch(\InvalidArgumentException){}
?>
<div class="checkout-shell">
  <section class="checkout-details" aria-labelledby="checkout-title">
    <div class="checkout-section checkout-contact-section">

      <div class="checkout-account"><div class="checkout-account-email"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/></svg><p><?= e($profile['email']??'') ?></p></div>
        <details class="checkout-account-menu"><summary aria-label="Account options"><span aria-hidden="true">⋮</span></summary>
          <div><form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button type="submit">Sign out</button></form></div>
        </details>
      </div>
    </div>
    <a class="v2-summary-jump" href="#order-summary-title">View order summary ↓</a>
    <h1 id="checkout-title">Contact and delivery.</h1>
    <form id="checkout-form" method="post" action="<?= e(url('/checkout')) ?>" data-checkout-ready="<?= $cart['checkout_ready']?'true':'false' ?>" data-pricing-url="<?= e(url('/checkout/pricing')) ?>" data-checkout-delivery data-shipping-url="<?= e(url('/checkout/shipping')) ?>">
      <?= csrf_field() ?><input type="hidden" name="checkout_key" value="<?= e($checkoutKey) ?>">
      <input type="hidden" name="discount_code" value="<?= e($discountPreview['code']??'') ?>"><input type="hidden" name="discount_revision" value="<?= e($discountPreview['revision']??'') ?>">
      <?php if($errors): ?><div class="checkout-errors" role="alert" tabindex="-1" data-error-summary><strong>Please review your order.</strong><ul>
        <?php foreach($errors as $field=>$error): ?><li><?php if(isset(\App\Core\DeliveryValidator::LIMITS[$field])||$field==='contact_no'): ?><a href="#checkout-<?= e($field) ?>"><?= e($error) ?></a><?php else: ?><?= e($error) ?><?php endif; ?></li><?php endforeach; ?>
      </ul></div><?php endif; ?>
      <div class="checkout-section"><div class="checkout-section-heading"><h2>Delivery</h2></div>
        <?php if(!empty($deliveryAddresses)): ?><label class="checkout-field checkout-field--full checkout-saved-address"><span>Saved delivery addresses</span><select data-saved-address><option value="">Choose a saved address</option><?php foreach($deliveryAddresses as $address): ?><option value="<?= (int)$address['id'] ?>" data-delivery="<?= e(json_encode(array_intersect_key($address,array_flip(\App\Services\AddressBook::FIELDS)),JSON_THROW_ON_ERROR)) ?>"><?= e(($address['is_default']?'Default · ':'').$address['first_name'].' — '.$address['address_line'].', '.$address['city']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
        <div class="checkout-field-grid">
          <label class="checkout-field checkout-field--full"><span>Country</span><select id="checkout-country" name="country" autocomplete="country" required><option value="PH" selected>Philippines</option></select>
            <?php if(isset($errors['country'])): ?><small class="field-error"><?= e($errors['country']) ?></small><?php endif; ?></label>
          <?php foreach(['first_name'=>['First name','given-name',60],'last_name'=>['Last name','family-name',60],
              'address_line'=>['Address','address-line1',200],'barangay'=>['Barangay','address-line2',100],
              'postal_code'=>['Postal code','postal-code',4],'city'=>['City / Municipality','address-level2',100]] as $field=>[$label,$autocomplete,$maximum]): ?>
            <label class="checkout-field <?= in_array($field,['address_line','barangay'],true)?'checkout-field--full':'' ?>"><span><?= e($label) ?></span>
              <input id="checkout-<?= e($field) ?>" name="<?= e($field) ?>" value="<?= e($values[$field]??'') ?>" autocomplete="<?= e($autocomplete) ?>" maxlength="<?= $maximum ?>" required <?= $field==='postal_code'?'inputmode="numeric" pattern="[1-9][0-9]{3}"':'' ?> aria-invalid="<?= isset($errors[$field])?'true':'false' ?>" <?= isset($errors[$field])?'aria-describedby="checkout-'.e($field).'-error"':'' ?>>
              <?php if(isset($errors[$field])): ?><small class="checkout-field-error" id="checkout-<?= e($field) ?>-error"><?= e($errors[$field]) ?></small><?php endif; ?>
            </label>
          <?php endforeach; ?>
          <?php $locationValues=$values;$locationPrefix='checkout';require dirname(__DIR__).'/partials/region_field.php'; ?>
          <label class="checkout-field checkout-field--full"><span>Phone</span><input id="checkout-contact_no" type="tel" name="contact_no" value="<?= e($values['contact_no']??'') ?>" maxlength="25" required autocomplete="tel" aria-invalid="<?= isset($errors['contact_no'])?'true':'false' ?>">
            <?php if(isset($errors['contact_no'])): ?><small class="checkout-field-error"><?= e($errors['contact_no']) ?></small><?php endif; ?></label>
        </div>
      </div>
      <fieldset class="checkout-section checkout-shipping-options" id="checkout-shipping_method"><legend>Shipping method</legend>
        <?php foreach(['standard'=>'Standard delivery','priority'=>'Priority delivery'] as $method=>$label): ?>
          <label class="checkout-option"><span><input type="radio" name="shipping_method" value="<?= e($method) ?>" <?= ($values['shipping_method']??'standard')===$method?'checked':'' ?> required> <?= e($label) ?></span><strong data-shipping-price="<?= e($method) ?>"><?php if($shippingQuote): ?>₱<?= e(\App\Services\ShippingService::decimal(\App\Services\ShippingService::quote($values['postal_code'],$method,$values['city'],$values['region'],$eligibleSubtotal)['calculated_cents'])) ?><?= !empty($shippingQuote['free_delivery'])?' · Free':'' ?><?php else: ?>—<?php endif; ?></strong></label>
        <?php endforeach; ?><p class="field-error" data-shipping-feedback role="status"><?= e($errors['shipping_method']??'') ?></p>
      </fieldset>
      <fieldset class="checkout-section checkout-payment-section"><legend>Payment</legend>
        <label class="checkout-option"><span><input type="radio" name="payment_method" value="cod" <?= ($values['payment_method']??'cod')==='cod'?'checked':'' ?> required> Cash on delivery</span></label>
        <?php if($mayaAvailable??false): ?>
          <label class="checkout-option"><span><input type="radio" name="payment_method" value="maya_sandbox" <?= ($values['payment_method']??'')==='maya_sandbox'?'checked':'' ?> required> Debit/Credit Card </span></label>
        <?php endif; ?>
        <?php if(isset($errors['payment_method'])): ?><p class="field-error"><?= e($errors['payment_method']) ?></p><?php endif; ?>
      </fieldset>
      <div class="checkout-inline-total"><span>Order total</span><strong data-inline-grand>₱<?= e(number_format(($eligibleSubtotal+($shippingQuote['cents']??0))/100,2)) ?></strong></div>
      <a class="btn btn-secondary checkout-return" href="<?= e(url('/cart')) ?>"><span aria-hidden="true">←</span> Return to your bag</a>
    </form>
    <nav class="checkout-policy-links" aria-label="Checkout policies"><a href="<?= e(url('/refund-policy')) ?>">Refund policy</a><a href="<?= e(url('/privacy')) ?>">Privacy policy</a><a href="<?= e(url('/terms')) ?>">Terms of service</a></nav>
  </section>
  <?php require dirname(__DIR__).'/partials/checkout_summary.php'; ?>
</div>
