<?php
$payment=$order['maya_payment']??null;
$simulated=$payment&&\App\Services\MayaPaymentService::simulatedReference($payment);
$journey=\App\Services\OrderProgress::journey($order);
?>
<section class="order-detail-page">
 <header class="order-detail-heading"><h1>Order details</h1></header>
 <dl class="order-detail-meta">
  <div><dt>Order date</dt><dd><time datetime="<?= e(str_replace(' ','T',$order['created_at'])) ?>"><?= e(date('j F Y, g:i a',strtotime($order['created_at']))) ?></time></dd></div>
  <div><dt>Order number</dt><dd><?= e(\App\Services\OrderReference::display($order)) ?></dd></div>
  <div><dt>Payment method</dt><dd><?= $order['payment_method']==='cod'?'Cash on delivery':($payment?'Debit/Credit Card':'Payment on arrangement') ?></dd></div>
  <div><dt>Order status</dt><dd><span class="order-status-badge"><?= e(\App\Services\OrderProgress::label($order)) ?></span></dd></div>
 </dl>
 <?php require dirname(__DIR__).'/partials/order_progress.php'; ?>
 <div class="order-detail-layout">
  <section class="order-detail-summary"><h2>Item</h2><div class="order-items-list">
   <?php foreach($order['items'] as $item):$image=product_image_url($item['image_path']??null); ?>
   <article class="order-line"><img class="order-item-image" src="<?= e($image??product_placeholder_url('')) ?>" alt="<?= e($item['product_name_snapshot']) ?>" width="112" height="112"><div><h3><?php if($item['product_id']!==null): ?><a href="<?= e(url('/product?id='.(int)$item['product_id'])) ?>"><?= e($item['product_name_snapshot']) ?></a><?php else: ?><?= e($item['product_name_snapshot']) ?><?php endif; ?></h3><p>Qty <?= (int)$item['quantity'] ?> · ₱<?= e(number_format((float)$item['unit_price_snapshot'],2)) ?> each</p></div><strong>₱<?= e(number_format((float)$item['unit_price_snapshot']*(int)$item['quantity'],2)) ?></strong></article>
   <?php endforeach; ?>
  </div></section>
  <div class="order-detail-secondary">
   <section class="order-summary-section"><h2>Order Summary</h2><div class="order-cost-summary">
    <dl class="order-detail-totals"><div><dt>Subtotal</dt><dd>₱<?= e(number_format((float)$order['subtotal'],2)) ?></dd></div>
    <?php if(!empty($order['discount'])): ?><div><dt>Discount · <?= e($order['discount']['code_snapshot']) ?></dt><dd>−₱<?= e(\App\Services\ShippingService::decimal((int)$order['discount']['amount_cents'])) ?></dd></div><?php endif; ?>
    <?php if($order['delivery']): ?><div><dt>Shipping · <?= e(ucfirst($order['delivery']['shipping_method'])) ?></dt><dd>₱<?= e(number_format((float)$order['delivery']['shipping_fee'],2)) ?></dd></div><?php endif; ?>
    <div class="order-total"><dt>Total</dt><dd>₱<?= e(number_format((float)$order['total'],2)) ?></dd></div></dl>
    <p class="order-payment-state" data-payment-label><?= $simulated?'No payment was collected for this order.':e(\App\Services\OrderPaymentStatus::label($order)) ?></p>
    <?php if($payment&&!$simulated): ?>
     <p data-payment-feedback role="status" aria-live="polite"><?= !empty($payment['verified_at'])?'Last checked '.e(date('j M, g:i a',strtotime($payment['verified_at']))).'.':'Payment has not been confirmed yet.' ?></p>
     <?php if(in_array($payment['state'],['initiating','pending'],true)): ?><div class="order-payment-actions" data-payment-actions>
      <form method="post" action="<?= e(url('/payments/maya/refresh')) ?>" data-payment-check data-auto-check="true"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>"><button class="btn btn-secondary" type="submit">Check payment status</button></form>
      <form method="post" action="<?= e(url('/payments/maya/continue')) ?>"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>"><button class="btn btn-primary" type="submit">Continue to card payment</button></form>
     </div><?php elseif(in_array($payment['state'],['failed','cancelled','expired'],true)): ?><a class="text-link" href="<?= e(url('/shop')) ?>">Start a new order</a><?php endif; ?>
    <?php endif; ?>
    <?php if(($order['email_confirmation']['status']??'')==='pending'&&!$simulated): ?><form class="order-email-action" method="post" action="<?= e(url('/orders/confirmation')) ?>"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>"><button class="text-link" type="submit">Send order email</button></form><?php endif; ?>
   </div></section>
   <section class="order-detail-delivery"><h2>Delivery details</h2><div class="order-delivery-card"><p><strong><?= e($order['recipient_name_snapshot']??'Recipient was not recorded') ?></strong></p><p><?= nl2br(e($order['delivery_address_snapshot'])) ?></p><p><?= e($order['contact_email_snapshot']??'') ?><br><?= e($order['contact_no_snapshot']??'') ?></p></div></section>
  </div>
 </div>
</section>
