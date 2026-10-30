<?php require_once __DIR__.'/handoff_art.php'; ?>
<div data-checkout-ready="<?= $cart['checkout_ready']?'true':'false' ?>" data-bag-fragment data-item-count="<?= (int)$cart['item_count'] ?>" data-subtotal="<?= e(number_format((float)$cart['subtotal'],2)) ?>">
<p class="cart-availability-notice" data-cart-availability role="status" <?= empty($cart['items'])||$cart['checkout_ready']?'hidden':'' ?>>Some items are no longer available in your requested quantity. Update or remove the marked items before checkout. Your other items stay in your bag.</p>
<?php if(empty($cart['items'])): ?><div class="drawer-empty"><?= handoff_icon('bag') ?><h3>A little space for care.</h3><p>Your bag is waiting for its first essential.</p><a class="button primary" href="<?= e(url('/shop')) ?>">Explore the collection </a></div>
<?php else: require __DIR__.'/bag_lines.php'; endif; ?></div>
