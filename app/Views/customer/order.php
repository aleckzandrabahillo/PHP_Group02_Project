<section class="page-head"><div><span class="eyebrow">ORDER DETAILS</span><h1>Order #<?= (int) $order['id'] ?></h1>
  <p>Status: <strong><?= e(ucfirst($order['status'])) ?></strong> · Placed <?= e($order['created_at']) ?></p></div></section>
<section class="order-delivery" aria-label="Delivery and payment">
  <?php if ($order['checkout_key'] !== null && $order['status'] === 'pending' && $order['payment_method'] !== 'paymongo'): ?><p class="order-next-step">Your order is saved. Return to My orders to review its status. This demonstration does not book delivery.</p><?php endif; ?>
  <h2>Contact and delivery</h2>
  <p><?= e($order['recipient_name_snapshot'] ?? 'Recipient was not recorded for this older order.') ?></p>
  <p><?= e($order['contact_email_snapshot'] ?? 'Email was not recorded.') ?> · <?= e($order['contact_no_snapshot'] ?? 'Phone was not recorded.') ?></p>
  <p class="order-address"><?= e($order['delivery_address_snapshot']) ?></p>
  <?php if ($order['payment_method'] === 'paymongo'): ?>
    <?php
    $paymentStatus = $order['payment_status'] ?? 'pending';
    $paymentLabels = ['paid' => 'Paid', 'pending' => 'Awaiting payment', 'failed' => 'Payment failed', 'expired' => 'Payment not completed (cancelled)'];
    ?>
    <p>Online payment (PayMongo) — <strong><?= e($paymentLabels[$paymentStatus] ?? $paymentStatus) ?></strong><?php if ($paymentStatus === 'paid' && !empty($order['paid_at'])): ?> on <?= e($order['paid_at']) ?><?php endif; ?></p>
    <?php if ($paymentStatus === 'pending' && $order['status'] === 'pending'): ?>
      <p class="order-next-step">This order is saved but not paid yet. Your items are held for you while you complete payment.</p>
      <form method="post" action="<?= e(url('/orders/pay')) ?>" style="display:inline">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
        <button class="btn btn-primary" type="submit">Pay now</button>
      </form>
      <form method="post" action="<?= e(url('/orders/cancel-unpaid')) ?>" style="display:inline" onsubmit="return confirm('Cancel this unpaid order?');">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
        <button class="btn btn-secondary" type="submit">Cancel order</button>
      </form>
    <?php endif; ?>
  <?php else: ?>
  <p><?= $order['payment_method'] === 'cod' ? 'Cash on delivery' : 'Manual payment' ?> — <?= $order['checkout_key'] !== null ? 'demonstration order; no online payment was collected.' : 'payment details recorded with the original order.' ?></p>
  <?php endif; ?>
</section>
<div class="order-table-wrap"><table>
  <thead><tr><th>Product</th><th>Quantity</th><th>Unit price</th><th>Subtotal</th></tr></thead>
  <tbody><?php foreach ($order['items'] as $item): ?><tr>
    <td><?= e($item['product_name_snapshot']) ?></td><td><?= (int) $item['quantity'] ?></td>
    <td>₱<?= e(number_format((float) $item['unit_price_snapshot'], 2)) ?></td>
    <td>₱<?= e(number_format((float) $item['unit_price_snapshot'] * (int) $item['quantity'], 2)) ?></td>
  </tr><?php endforeach; ?></tbody>
  <tfoot><tr><th colspan="3">Order total</th><td>₱<?= e(number_format((float) $order['total'], 2)) ?></td></tr></tfoot>
</table></div>
<p><a class="btn btn-secondary" href="<?= e(url('/orders')) ?>">My orders</a> <a class="btn btn-primary" href="<?= e(url('/shop')) ?>">Continue shopping</a></p>
