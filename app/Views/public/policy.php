<main id="main-content" tabindex="-1" class="store-page privacy-page">
  <article class="privacy-content" aria-labelledby="policy-title"><span class="eyebrow">SHOPPING WITH AVELA</span>
    <h1 id="policy-title"><?= e($pageTitle) ?></h1>
    <p class="muted"><strong>Policy for review.</strong> Final terms and contact details require owner approval.</p>
    <?php if($policyKind==='refund'): ?>
      <p>Keep your order number and product details when requesting help with a purchase.</p>
      <h2>Incorrect, damaged or missing items</h2><p>Retain the packaging and record the problem clearly. Include the order number, affected items and relevant photographs in your request. Avoid sending passwords or payment-card details.</p>
      <h2>Before returning an item</h2><p>Contact Avela before sending anything back. Return eligibility and instructions must be confirmed after reviewing the item, its condition and the reason for the request, subject to applicable consumer rights.</p>
      <h2>Refunds and cancellations</h2><p>A request does not automatically cancel an order. Where a refund is approved, the amount and payment method must be confirmed with you. Online order status alone is not proof that a refund has been issued.</p>
    <?php else: ?>
      <p>These terms describe the proposed use of Avela's account and shopping services.</p>
      <h2>Your account</h2><p>Provide accurate account and delivery information. Keep sign-in details private and use only your own account. Never share verification or recovery codes.</p>
      <h2>Products and ordering</h2><p>Prices, available stock and delivery charges are shown before an order is submitted. Product suggestions reflect your recorded hair preferences and listed product attributes; they are not medical advice or a guarantee of results.</p>
      <h2>Payment and fulfillment</h2><p>Use only the payment options offered at checkout. Review your recipient, delivery address, shipping option and order total before placing an order. Recorded order updates indicate Avela's fulfillment status.</p>
      <h2>Returns and information</h2><p>See the <a href="<?= e(url('/refund-policy')) ?>">Refund policy</a> for purchase issues and the <a href="<?= e(url('/privacy')) ?>">Privacy policy</a> for information handling. These terms do not exclude rights that cannot lawfully be excluded.</p>
    <?php endif; ?>
    <?php if($privacyEmail): ?><h2>Contact Avela</h2><p><a href="mailto:<?= e(rawurlencode($privacyEmail)) ?>"><?= e($privacyEmail) ?></a></p><?php else: ?><p>Contact details require owner confirmation before these terms are finalized.</p><?php endif; ?>
  </article>
</main>
