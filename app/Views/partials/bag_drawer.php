<?php require_once __DIR__.'/handoff_art.php'; ?>
<dialog id="avela-bag" class="commerce-dialog side-drawer bag-drawer" aria-labelledby="avela-bag-title" data-bag-url="<?= e(url('/cart?panel=1')) ?>">
 <header class="drawer-header"><div><p class="eyebrow">A LITTLE CARE, KEPT CLOSE</p><h2 id="avela-bag-title">Your bag <span data-bag-count></span></h2></div><button class="icon-button" type="button" data-bag-close aria-label="Close bag"><?= handoff_icon('close') ?></button></header>
 <div class="drawer-body" data-bag-content aria-live="polite"></div>
 <footer class="drawer-footer"><div class="bag-subtotal"><span>Subtotal</span><strong data-bag-subtotal>—</strong></div><a class="button primary" data-cart-checkout data-checkout-url="<?= e(url('/checkout')) ?>" aria-disabled="true" tabindex="-1">Checkout</a></footer>
</dialog>
