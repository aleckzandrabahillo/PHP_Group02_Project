<?php foreach($cart['items'] as $item):
$cartImage=product_image_url($item['image_path']??null);$itemCanAdjust=(bool)($item['can_adjust']??false);
?>
<article class="bag-line" data-cart-item data-product-id="<?= (int)$item['product_id'] ?>">
 <a class="bag-item-image" href="<?= e(url('/product?id='.(int)$item['product_id'])) ?>" aria-label="View <?= e($item['name']) ?>"><?php if($cartImage): ?><img src="<?= e($cartImage) ?>" alt="<?= e($item['name']) ?>" width="100" height="114"><?php else:$visualCategory=$item['category_name']??'';$placeholderLarge=false;require __DIR__.'/product_placeholder.php';endif; ?></a>
 <div class="bag-item-info"><a href="<?= e(url('/product?id='.(int)$item['product_id'])) ?>"><?= e($item['name']) ?></a><span><?= e($item['category_name']) ?> · ₱<?= e(number_format((float)$item['price'],2)) ?> each</span>
 <p class="cart-stock-warning" data-cart-stock-warning <?= $item['is_available']?'hidden':'' ?>><?= e($item['availability_message']) ?></p>
 <div class="bag-item-controls">
  <form method="post" action="<?= e(url('/cart/update')) ?>" data-cart-auto-update><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>"><div class="quantity-stepper" data-quantity-control><button type="button" data-quantity-minus aria-label="Decrease quantity for <?= e($item['name']) ?>" <?= $itemCanAdjust?'':'disabled' ?>>−</button><input type="number" name="quantity" min="1" max="<?= max(1,(int)$item['stock_qty']) ?>" value="<?= (int)$item['quantity'] ?>" data-confirmed-quantity="<?= (int)$item['quantity'] ?>" inputmode="numeric" aria-label="Quantity for <?= e($item['name']) ?>" <?= $itemCanAdjust?'':'disabled' ?>><button type="button" data-quantity-plus aria-label="Increase quantity for <?= e($item['name']) ?>" <?= $itemCanAdjust?'':'disabled' ?>>+</button></div><span class="cart-inline-feedback" data-cart-feedback hidden aria-live="polite"></span><noscript><button type="submit">Update</button></noscript></form>
  <form method="post" action="<?= e(url('/cart/remove')) ?>"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>"><button class="bag-remove" type="submit">Remove</button></form>
 </div></div><strong data-cart-line-total>₱<?= e(number_format((float)$item['line_total'],2)) ?></strong>
</article>
<?php endforeach; ?>
