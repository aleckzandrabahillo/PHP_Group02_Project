<section class="page-head"><div><span class="eyebrow">MY ORDERS</span><h1 class="sr-only">My Orders</h1></div></section>
<?php
$emptyTitle = 'No orders yet';
$emptyMessage = 'Your orders will appear here after your first purchase.';
$emptyActionLabel = 'Browse products';
$emptyActionUrl = url('/shop');
require dirname(__DIR__) . '/partials/empty_state.php';
?>
