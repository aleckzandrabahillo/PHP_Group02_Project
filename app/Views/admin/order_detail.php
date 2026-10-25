<?php
// From AdminController::orderView(): $order (with ['items']), $nextStatuses, $history.
// $nextStatuses is Order::FLOW[current status], so the dropdown can only offer legal moves.
$nextStatuses = $nextStatuses ?? [];
$history = $history ?? [];
$paymentLabels = ['cod' => 'Cash on delivery', 'manual' => 'Manual payment', 'paymongo' => 'Online (PayMongo)'];
$paymentStatusLabels = ['paid' => 'Paid', 'pending' => 'Awaiting payment', 'failed' => 'Failed', 'expired' => 'Not completed'];
?>
<section class="page-head">
  <div>
    <span class="eyebrow">ORDER</span>
    <h1>Order #<?= (int) $order['id'] ?></h1>
    <p>Placed <?= e(date('M j, Y g:i A', strtotime((string) $order['created_at']))) ?></p>
  </div>
  <a class="btn btn-secondary" href="<?= e(url('/admin/orders')) ?>">Back to orders</a>
</section>

<div class="panel-grid">
  <div class="panel">
    <div class="panel-head"><h2>Customer &amp; delivery</h2></div>
    <p><strong><?= e($order['customer']) ?></strong></p>
    <p class="muted"><?= e($order['email']) ?><?= !empty($order['contact_no']) ? ' · ' . e($order['contact_no']) : '' ?></p>
    <p><?= e($order['delivery_address_snapshot']) ?></p>
    <p class="muted small">Payment: <?= e($paymentLabels[$order['payment_method']] ?? $order['payment_method']) ?><?php if ($order['payment_method'] === 'paymongo'): ?> — <strong><?= e($paymentStatusLabels[$order['payment_status'] ?? ''] ?? ($order['payment_status'] ?? '')) ?></strong><?php if (!empty($order['paid_at'])): ?> on <?= e($order['paid_at']) ?><?php endif; ?><?php endif; ?></p>
    <?php if ($order['payment_method'] === 'paymongo' && !empty($order['paymongo_checkout_session_id'])): ?>
      <p class="muted small">PayMongo checkout session: <?= e($order['paymongo_checkout_session_id']) ?><?= !empty($order['paymongo_payment_intent_id']) ? ' · payment intent: ' . e($order['paymongo_payment_intent_id']) : '' ?></p>
    <?php endif; ?>
    <?php if ($order['payment_method'] === 'paymongo' && ($order['payment_status'] ?? '') === 'paid' && $order['status'] === 'cancelled'): ?>
      <p class="alert alert-warning" role="alert">This order is cancelled but was paid. Refund it from the PayMongo dashboard.</p>
    <?php endif; ?>
    <?php if ($order['payment_method'] === 'paymongo' && ($order['payment_status'] ?? '') === 'pending'): ?>
      <p class="muted small">Waiting for online payment: this order cannot be processed or shipped until PayMongo confirms it.</p>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Status</h2></div>
    <p>Current status: <strong><?= e(ucfirst((string) $order['status'])) ?></strong></p>

    <?php if ($nextStatuses): ?>
      <form method="post" action="<?= e(url('/admin/orders/status')) ?>"
            onsubmit="return this.status.value !== 'cancelled' || confirm('Cancel this order? This cannot be undone.');">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
        <label class="field">
          <span>Move to</span>
          <select name="status" required>
            <?php foreach ($nextStatuses as $next): ?>
              <option value="<?= e($next) ?>"><?= e(ucfirst($next)) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button class="btn btn-primary" type="submit" style="margin-top:12px">Update status</button>
      </form>
    <?php else: ?>
      <p class="muted">This order is <?= e((string) $order['status']) ?>, so its status can no longer be changed.</p>
    <?php endif; ?>
  </div>
</div>

<div class="table-card admin-list" style="margin-top:20px">
  <div class="data-table">
    <div class="data-row header">
      <span>Product</span>
      <span>Quantity</span>
      <span>Unit price</span>
      <span>Line total</span>
    </div>

    <?php foreach ($order['items'] as $item): ?>
      <div class="data-row">
        <span>
          <?= e($item['product_name_snapshot']) ?>
          <?php if ($item['product_id'] === null): ?><small class="muted">Product no longer in catalog</small><?php endif; ?>
        </span>
        <span><?= (int) $item['quantity'] ?></span>
        <span>₱<?= e(number_format((float) $item['unit_price_snapshot'], 2)) ?></span>
        <span>₱<?= e(number_format((float) $item['line_total'], 2)) ?></span>
      </div>
    <?php endforeach; ?>

    <div class="data-row">
      <span></span>
      <span></span>
      <span><strong>Total</strong></span>
      <span><strong>₱<?= e(number_format((float) $order['total'], 2)) ?></strong></span>
    </div>
  </div>

  <?php if (empty($order['items'])): ?>
    <div class="table-empty"><p>This order has no line items.</p></div>
  <?php endif; ?>
</div>

<div class="table-card admin-list" style="margin-top:20px">
  <div class="panel-head" style="padding:18px 20px 0"><h2>Status history</h2></div>
  <div class="data-table">
    <div class="data-row header">
      <span>When</span>
      <span>Changed by</span>
      <span>Change</span>
    </div>
    <?php foreach ($history as $entry): ?>
      <div class="data-row">
        <span><?= e(date('M j, Y g:i A', strtotime((string) $entry['created_at']))) ?></span>
        <span><?= e($entry['actor_email'] ?? 'System / deleted user') ?></span>
        <span><?= e($entry['details'] ?? '') ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (empty($history)): ?>
    <div class="table-empty"><p>No status changes recorded yet.</p></div>
  <?php endif; ?>
</div>
