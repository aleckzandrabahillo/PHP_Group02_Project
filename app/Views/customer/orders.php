<section class="page-head"><div><span class="eyebrow">MY ORDERS</span><h1>My orders</h1></div></section>
<?php if (!$history['items']): ?>
<?php
$emptyTitle = 'No orders yet';
$emptyMessage = 'Your orders will appear here after your first purchase.';
$emptyActionLabel = 'Browse products';
$emptyActionUrl = url('/shop');
require dirname(__DIR__) . '/partials/empty_state.php';
?>
<?php else: ?>
  <div class="order-table-wrap"><table>
    <thead><tr><th>Order</th><th>Placed</th><th>Status</th><th>Total</th><th>Details</th></tr></thead>
    <tbody><?php foreach ($history['items'] as $item): ?><tr>
      <td>#<?= (int) $item['id'] ?></td><td><?= e($item['created_at']) ?></td>
      <td><?= e(ucfirst($item['status'])) ?><?php if (($item['payment_method'] ?? '') === 'paymongo'): ?><br><small><?= e(['paid' => 'Paid online', 'pending' => 'Awaiting payment', 'failed' => 'Payment failed', 'expired' => 'Unpaid'][$item['payment_status'] ?? 'pending'] ?? '') ?></small><?php endif; ?></td><td>₱<?= e(number_format((float) $item['total'], 2)) ?></td>
      <td><a href="<?= e(url('/orders/view?id=' . (int) $item['id'])) ?>">View order #<?= (int) $item['id'] ?></a></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <nav class="order-pagination" aria-label="Order history pages">
    <?php if ($history['page'] > 1): ?><a href="<?= e(url('/orders?page=' . ($history['page'] - 1))) ?>">Previous</a><?php endif; ?>
    <span>Page <?= (int) $history['page'] ?> of <?= (int) $history['pages'] ?></span>
    <?php if ($history['page'] < $history['pages']): ?><a href="<?= e(url('/orders?page=' . ($history['page'] + 1))) ?>">Next</a><?php endif; ?>
  </nav>
<?php endif; ?>
