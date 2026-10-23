<?php
declare(strict_types=1);
/** Reconcile and retire only this suite's uniquely named synthetic products/orders. */
function cleanupRefinementFixture(PDO $pdo,string $prefix):void
{
 if(!preg_match('/^refine_[a-f0-9]{10}$/D',$prefix)||$pdo->query('SELECT DATABASE()')->fetchColumn()!=='avela_demo_images_test_personalization_20261016'||(int)$pdo->query('SELECT @@port')->fetchColumn()!==13321)throw new RuntimeException('Fixture cleanup identity');
 $pdo->beginTransaction();try{
  $q=$pdo->prepare('SELECT o.id,o.payment_method,o.status FROM orders o JOIN users u ON u.id=o.user_id WHERE u.username=? ORDER BY o.id FOR UPDATE');$q->execute([$prefix]);
  foreach($q->fetchAll() as $order){if($order['payment_method']!=='cod')throw new RuntimeException('Unexpected synthetic payment');
   if($order['status']!=='cancelled'){$items=$pdo->prepare('SELECT product_id,quantity FROM order_items WHERE order_id=?');$items->execute([$order['id']]);foreach($items->fetchAll() as $item)$pdo->prepare('UPDATE products SET stock_qty=stock_qty+? WHERE id=?')->execute([$item['quantity'],$item['product_id']]);}
   $payment=$pdo->prepare('SELECT COUNT(*) FROM maya_payments WHERE order_id=?');$payment->execute([$order['id']]);if((int)$payment->fetchColumn()!==0)throw new RuntimeException('Preserve payment fixture');
   foreach(['order_email_outbox','order_status_events','order_discounts','order_delivery_details','order_items'] as $table)$pdo->prepare('DELETE FROM '.$table.' WHERE order_id=?')->execute([$order['id']]);
   $pdo->prepare('DELETE FROM orders WHERE id=?')->execute([$order['id']]);
  }
  $pdo->prepare('DELETE ci FROM cart_items ci JOIN carts c ON c.id=ci.cart_id JOIN users u ON u.id=c.user_id WHERE u.username=?')->execute([$prefix]);
  $q=$pdo->prepare('SELECT id,image_path FROM products WHERE sku=? OR sku LIKE ?');$q->execute([strtoupper($prefix),strtoupper($prefix).'U%']);$products=$q->fetchAll();
  foreach($products as $product){foreach(['order_items','product_reviews','favorites','routine_items','cart_items'] as $table){$q=$pdo->prepare('SELECT COUNT(*) FROM '.$table.' WHERE product_id=?');$q->execute([$product['id']]);if((int)$q->fetchColumn()!==0)throw new RuntimeException('Unexpected fixture reference');}$pdo->prepare('DELETE FROM products WHERE id=?')->execute([$product['id']]);}
  $pdo->commit();foreach($products as $product)(new App\Services\ProductImageService())->discard($product['image_path']);
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
