<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\User;
use InvalidArgumentException;
use PDO;
use Throwable;

final class OrderWorkflow
{
    public const NEXT = ['pending'=>'processing','processing'=>'shipped','shipped'=>'delivered'];

    /** Called inside Checkout's transaction: history and mail intent commit with the order. */
    public function created(PDO $pdo, int $orderId, int $customerId, bool $queueEmail = true): void
    {
        $pdo->prepare('INSERT INTO order_status_events (order_id,actor_id,from_status,to_status) VALUES (?,?,NULL,"pending")')->execute([$orderId,$customerId]);
        if ($queueEmail) $pdo->prepare('INSERT INTO order_email_outbox (order_id) VALUES (?)')->execute([$orderId]);
    }

    public function advance(PDO $pdo, int $actorId, int $orderId, string $expected, string $next): void
    {
        $cancel=$next==='cancelled'&&in_array($expected,['pending','processing'],true);
        if (!$cancel && (self::NEXT[$expected] ?? null) !== $next) throw new InvalidArgumentException('Choose the next available order status.');
        $pdo->beginTransaction();
        try {
            $actor=$pdo->prepare('SELECT id,role,status,locked_until,lock_requires_admin FROM users WHERE id=? FOR UPDATE');$actor->execute([$actorId]);$user=$actor->fetch();
            if (!User::canAuthenticate($user ?: null) || $user['role'] !== 'admin') throw new InvalidArgumentException('An active Administrator is required.');
            $query=$pdo->prepare('SELECT status,checkout_key,payment_method,user_id FROM orders WHERE id=? FOR UPDATE');$query->execute([$orderId]);$order=$query->fetch();
            if (!$order || $order['status'] !== $expected) throw new InvalidArgumentException('This order changed. Refresh the list before updating it.');
            if($order['payment_method']==='manual' && MayaMigration::installed($pdo)){
                $payment=$pdo->prepare('SELECT state FROM maya_payments WHERE order_id=? FOR UPDATE');$payment->execute([$orderId]);$state=$payment->fetchColumn();
                if($state!==false&&($state!=='paid'||$cancel))throw new InvalidArgumentException('This Maya payment must be verified or reconciled before changing the order.');
            }
            if($cancel){$this->restoreStock($pdo,$actorId,$orderId,$order);DiscountService::settle($pdo,$orderId,true);}
            $pdo->prepare('UPDATE orders SET status=?,updated_at=NOW() WHERE id=?')->execute([$next,$orderId]);
            $pdo->prepare('INSERT INTO order_status_events (order_id,actor_id,from_status,to_status) VALUES (?,?,?,?)')->execute([$orderId,$actorId,$expected,$next]);
            AuditService::record($pdo,$actorId,$cancel?'order.cancelled':'order.status','order',$orderId,['from'=>$expected,'to'=>$next]);
            $pdo->commit();
        } catch (Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
    }

    private function restoreStock(PDO $pdo,int $actorId,int $orderId,array $order): void
    {
        // Only this application's stock-deducting COD checkout can be reversed automatically.
        if($order['payment_method']!=='cod'||!is_string($order['checkout_key'])||!preg_match('/^[a-f0-9]{64}$/D',$order['checkout_key']))
            throw new InvalidArgumentException('This order needs manual payment or inventory reconciliation before cancellation.');
        $proof=$pdo->prepare('SELECT id FROM audit_logs WHERE action="order.create" AND target_type="order" AND target_id=? AND actor_id=? LIMIT 1');
        $proof->execute([$orderId,$order['user_id']]);
        if(!$proof->fetchColumn())throw new InvalidArgumentException('The original stock deduction could not be confirmed. No stock was changed.');
        $items=$pdo->prepare('SELECT product_id,quantity FROM order_items WHERE order_id=? ORDER BY product_id,id FOR UPDATE');$items->execute([$orderId]);$quantities=[];
        foreach($items->fetchAll() as $item){
            if($item['product_id']===null||(int)$item['quantity']<1)throw new InvalidArgumentException('An ordered product is unavailable for stock reconciliation.');
            $id=(int)$item['product_id'];$quantities[$id]=($quantities[$id]??0)+(int)$item['quantity'];
            if($quantities[$id]>4294967295)throw new InvalidArgumentException('The stock return exceeds the supported limit.');
        }
        if(!$quantities)throw new InvalidArgumentException('This order has no items to reconcile.');
        ksort($quantities);
        foreach($quantities as $id=>$quantity){
            $q=$pdo->prepare('SELECT stock_qty FROM products WHERE id=? FOR UPDATE');$q->execute([$id]);$before=$q->fetchColumn();
            if($before===false||(int)$before>4294967295-$quantity)throw new InvalidArgumentException('Stock cannot be restored safely. No order changes were saved.');
            $pdo->prepare('UPDATE products SET stock_qty=stock_qty+?,updated_at=NOW() WHERE id=?')->execute([$quantity,$id]);
            AuditService::record($pdo,$actorId,'order.stock_restored','product',$id,['order_id'=>$orderId,'quantity'=>$quantity,'before'=>(int)$before,'after'=>(int)$before+$quantity]);
        }
        // Suppress still-pending confirmations; already-claimed/sent mail cannot be recalled.
        $pdo->prepare('UPDATE order_email_outbox SET status="failed",last_error="order_cancelled" WHERE order_id=? AND status="pending"')->execute([$orderId]);
    }
}
