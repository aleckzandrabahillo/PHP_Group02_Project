<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use Throwable;

final class OrderEmailQueue
{
    public function __construct(private readonly PDO $pdo) {}

    public function pendingCount(): int
    {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM order_email_outbox WHERE status="pending" AND attempts<3')->fetchColumn();
    }

    /** Sending happens after commit. An interrupted sending job requires review, not blind resend. */
    public function deliverNext(callable $send, ?int $orderId = null): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $q=$this->pdo->prepare('SELECT id,order_id,attempts FROM order_email_outbox WHERE status="pending" AND attempts<3'.($orderId!==null?' AND order_id=?':'').' ORDER BY id LIMIT 1 FOR UPDATE');
            $q->execute($orderId!==null?[$orderId]:[]);$job=$q->fetch();
            if(!$job){$this->pdo->commit();return null;}
            $this->pdo->prepare('UPDATE order_email_outbox SET status="sending",attempts=attempts+1,last_error=NULL WHERE id=?')->execute([$job['id']]);
            $this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        try {
            $q=$this->pdo->prepare('SELECT o.id,o.public_reference,o.contact_email_snapshot,o.recipient_name_snapshot,o.payment_method,o.status,o.total,o.created_at,COALESCE(d.shipping_fee,0) AS shipping_fee FROM orders o LEFT JOIN order_delivery_details d ON d.order_id=o.id WHERE o.id=?');$q->execute([$job['order_id']]);$order=$q->fetch();
            if(!$order||!filter_var($order['contact_email_snapshot'],FILTER_VALIDATE_EMAIL))throw new \RuntimeException('Order email is unavailable.');
            if($order['status']==='cancelled')throw new \RuntimeException('Order cancelled.');
            if($order['payment_method']!=='cod'){
                if($order['payment_method']!=='manual'||!MayaMigration::installed($this->pdo))throw new \RuntimeException('Payment confirmation unavailable.');
                $payment=$this->pdo->prepare('SELECT state,verified_at,checkout_id,merchant_reference FROM maya_payments WHERE order_id=?');$payment->execute([$order['id']]);$proof=$payment->fetch();
                if(!$proof||$proof['state']!=='paid'||empty($proof['verified_at'])||MayaPaymentService::simulatedReference($proof))throw new \RuntimeException('Payment confirmation pending.');
                $order['payment_method']='maya_sandbox';$order['payment_verified']=true;
            }
            $q=$this->pdo->prepare('SELECT product_name_snapshot,quantity,unit_price_snapshot FROM order_items WHERE order_id=? ORDER BY id');$q->execute([$job['order_id']]);
            $items=$q->fetchAll();$order['discount']=null;
            if(DiscountMigration::installed($this->pdo)){$q=$this->pdo->prepare('SELECT code_snapshot,amount_cents FROM order_discounts WHERE order_id=?');$q->execute([$order['id']]);$order['discount']=$q->fetch()?:null;}
            $send($order,$items);
        }catch(Throwable){
            // Acceptance may be uncertain after a network interruption. Never automatically duplicate mail.
            $this->pdo->prepare('UPDATE order_email_outbox SET status="failed",last_error="delivery_review_required" WHERE id=? AND status="sending"')->execute([$job['id']]);
            return ['id'=>(int)$job['id'],'status'=>'failed','reason'=>'delivery_review_required'];
        }
        $this->pdo->prepare('UPDATE order_email_outbox SET status="sent",sent_at=NOW() WHERE id=? AND status="sending"')->execute([$job['id']]);
        return ['id'=>(int)$job['id'],'status'=>'sent'];
    }
}
