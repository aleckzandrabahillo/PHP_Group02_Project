<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\User;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/** Hosted SANDBOX payments only. API calls are outside database transactions. */
final class MayaPaymentService
{
    public const EVENTS = ['PAYMENT_SUCCESS','PAYMENT_FAILED','PAYMENT_CANCELLED','PAYMENT_EXPIRED'];

    public function __construct(private readonly PDO $pdo,private readonly MayaSandboxClient $client) {}

    /** Called in the existing stock-deducting checkout transaction. */
    public static function reserve(PDO $pdo,int $orderId,int $cents): void
    {
        $pdo->prepare('INSERT INTO maya_payments(order_id,merchant_reference,amount_cents) VALUES (?,?,?)')
            ->execute([$orderId,bin2hex(random_bytes(16)),$cents]);
    }

    public function start(int $userId,int $orderId): ?string
    {
        $payment=$this->transaction(function()use($userId,$orderId):array{
            $actor=$this->pdo->prepare('SELECT id,role,status,locked_until,lock_requires_admin FROM users WHERE id=? FOR UPDATE');$actor->execute([$userId]);$user=$actor->fetch();
            if(!User::canAuthenticate($user?:null)||$user['role']!=='customer')throw new InvalidArgumentException('Sign in to continue payment.');
            $order=$this->lockedOrder($orderId);
            if((int)$order['user_id']!==$userId)throw new InvalidArgumentException('Order not found.');
            $payment=$this->lockedPayment($orderId);
            if(MayaSandboxConfig::localHosted()&&self::simulatedReference($payment))throw new InvalidArgumentException('Start a fresh order for hosted Sandbox checkout.');
            if($payment['state']==='pending'&&$payment['redirect_url']!==null&&(int)$payment['age_seconds']<3600)return $payment;
            if((int)$payment['age_seconds']>=3600)return $payment+['skip'=>true];
            if($payment['state']!=='initiating'||$payment['initiation_started_at']!==null)return $payment+['skip'=>true];
            $this->pdo->prepare('UPDATE maya_payments SET state="pending",initiation_started_at=NOW() WHERE order_id=?')->execute([$orderId]);
            return $payment;
        });
        if(isset($payment['skip']))return null;
        if($payment['redirect_url']!==null)return $payment['redirect_url'];
        $origin=(string)env('MAYA_MERCHANT_ORIGIN','');$return=$origin.'/payments/maya/return?order='.$orderId;
        try {
            $result=$this->client->createCheckout((int)$payment['amount_cents'],$payment['merchant_reference'],
                ['success'=>$return.'&result=success','failure'=>$return.'&result=failure','cancel'=>$return.'&result=cancel']);
            return $this->transaction(function()use($orderId,$result,$userId):?string{
                $this->lockedOrder($orderId);$payment=$this->lockedPayment($orderId);
                if($payment['state']!=='pending'||$payment['stock_state']!=='reserved')return null;
                if($payment['checkout_id']!==null&&$payment['checkout_id']!==$result['checkout_id'])throw new RuntimeException('Payment reference requires review.');
                $this->pdo->prepare('UPDATE maya_payments SET checkout_id=?,redirect_url=? WHERE order_id=?')->execute([$result['checkout_id'],$result['redirect_url'],$orderId]);
                AuditService::record($this->pdo,$userId,'payment.initiated','order',$orderId,['provider'=>'maya_sandbox']);
                return $result['redirect_url'];
            });
        } catch(Throwable) {
            // A timeout or malformed response may hide a created payment. Never create another blindly
            // or release stock until a subsequent authenticated retrieval resolves the uncertainty.
            return null;
        }
    }

    public function refresh(int $userId,int $orderId): void
    {
        $q=$this->pdo->prepare('SELECT id,role,status,locked_until,lock_requires_admin FROM users WHERE id=?');$q->execute([$userId]);$user=$q->fetch();
        $q=$this->pdo->prepare('SELECT user_id FROM orders WHERE id=?');$q->execute([$orderId]);
        if(!User::canAuthenticate($user?:null)||$user['role']!=='customer'||(int)$q->fetchColumn()!==$userId)throw new InvalidArgumentException('Order not found.');
        $this->reconcile($orderId);
    }

    /** Safe scheduled fallback also handles missing/delayed webhooks and uncertain creates. */
    public function reconcile(int $orderId,?int $eventId=null): void
    {
        $q=$this->pdo->prepare('SELECT *,TIMESTAMPDIFF(SECOND,created_at,NOW()) age_seconds FROM maya_payments WHERE order_id=?');$q->execute([$orderId]);$payment=$q->fetch();
        if(!$payment)throw new InvalidArgumentException('Payment not found.');
        if(MayaSandboxConfig::localHosted()&&self::simulatedReference($payment))throw new InvalidArgumentException('An earlier local simulation is not a Maya transaction.');
        $proof=$payment['checkout_id']!==null
            ?$this->client->verifiedPayment($payment['checkout_id'],$payment['merchant_reference'],(int)$payment['amount_cents'])
            :$this->client->paymentByReference($payment['merchant_reference'],(int)$payment['amount_cents']);
        // Maya checkout validity is one hour. Two hours plus an authenticated empty RRN lookup
        // safely closes an initiation that never produced a retrievable provider transaction.
        if($proof===null&&(int)$payment['age_seconds']<7200)throw new RuntimeException('Payment confirmation is pending.');
        $this->transaction(function()use($orderId,$eventId,$proof):void{
            $order=$this->lockedOrder($orderId);$payment=$this->lockedPayment($orderId);
            if($eventId!==null){
                $event=$this->pdo->prepare('SELECT payment_id FROM maya_webhook_inbox WHERE id=? AND order_id=? FOR UPDATE');$event->execute([$eventId,$orderId]);
                if($proof===null||$event->fetchColumn()!==$proof['payment_id'])throw new RuntimeException('Webhook payment differs.');
            }
            if($proof===null&&(int)$payment['age_seconds']<7200)throw new RuntimeException('Payment confirmation is pending.');
            if($proof!==null&&$payment['checkout_id']!==null&&$payment['checkout_id']!==$proof['payment_id'])throw new RuntimeException('Payment reference differs.');
            $provider=$proof['status']??'PAYMENT_EXPIRED';
            $next=match($provider){
                'PAYMENT_SUCCESS','CAPTURED'=>'paid',
                'PAYMENT_FAILED','AUTH_FAILED'=>'failed',
                'PAYMENT_CANCELLED'=>'cancelled',
                'PAYMENT_EXPIRED','CAPTURE_HOLD_EXPIRED'=>'expired',
                'VOIDED','REFUNDED','AUTHORIZED'=>'review',
                default=>'pending',
            };
            $current=$payment['state'];
            if($current==='review'||($next==='pending'&&$current!=='initiating'))$next=$current;
            if($current==='paid'&&in_array($next,['failed','cancelled','expired'],true))$next='review';
            if($payment['stock_state']==='released'&&$next==='paid')$next='review';
            if(in_array($current,['failed','cancelled','expired'],true)&&in_array($next,['failed','cancelled','expired'],true))$next=$current;
            if($next!==$current){
                $stock=$payment['stock_state'];
                if(in_array($next,['failed','cancelled','expired'],true)&&$stock==='reserved'){
                    $this->releaseStock($orderId);$stock='released';
                    DiscountService::settle($this->pdo,$orderId,true);
                    $this->pdo->prepare('UPDATE orders SET status="cancelled",updated_at=NOW() WHERE id=? AND status="pending"')->execute([$orderId]);
                    $this->pdo->prepare('INSERT INTO order_status_events(order_id,actor_id,from_status,to_status) VALUES (?,NULL,"pending","cancelled")')->execute([$orderId]);
                }
                if($next==='paid'){
                    if($order['status']!=='pending'||$stock!=='reserved')throw new RuntimeException('Order requires payment review.');
                    $stock='committed';
                    DiscountService::settle($this->pdo,$orderId,false);
                    $this->pdo->prepare('INSERT INTO order_email_outbox(order_id) VALUES (?) ON DUPLICATE KEY UPDATE order_id=VALUES(order_id)')->execute([$orderId]);
                }
                $this->pdo->prepare('UPDATE maya_payments SET state=?,stock_state=?,checkout_id=COALESCE(checkout_id,?),verified_at=NOW() WHERE order_id=?')
                    ->execute([$next,$stock,$proof['payment_id']??null,$orderId]);
                AuditService::record($this->pdo,null,'payment.'.$next,'order',$orderId,['provider'=>'maya_sandbox','from'=>$current,'to'=>$next]);
            } elseif($proof!==null){
                $this->pdo->prepare('UPDATE maya_payments SET checkout_id=COALESCE(checkout_id,?),verified_at=NOW() WHERE order_id=?')->execute([$proof['payment_id'],$orderId]);
            }
            if($eventId!==null)$this->pdo->prepare('UPDATE maya_webhook_inbox SET processed_at=NOW(),lease_until=NULL,last_error=NULL WHERE id=? AND order_id=?')->execute([$eventId,$orderId]);
        });
    }

    /** Authenticated ingress only queues minimal identifiers; no raw payload/card/buyer storage. */
    public function enqueue(string $body,string $peer,bool $https,int $port): bool
    {
        if($this->pdo->inTransaction())throw new RuntimeException('Webhook receipt requires a committed inbox.');
        if(!$https||$port!==443||!in_array($peer,['13.229.160.234','3.1.199.75'],true))throw new InvalidArgumentException('Webhook source rejected.',403);
        if(strlen($body)>16384)throw new InvalidArgumentException('Webhook too large.',413);
        try{$event=json_decode($body,true,16,JSON_THROW_ON_ERROR);}catch(Throwable){throw new InvalidArgumentException('Invalid webhook.',400);}
        if(!is_array($event)||array_is_list($event)||!MayaSandboxClient::validId($event['id']??null)
            ||!is_string($event['requestReferenceNumber']??null)||!preg_match('/^[a-f0-9]{32}$/D',$event['requestReferenceNumber'])
            ||!in_array($event['paymentStatus']??null,self::EVENTS,true))throw new InvalidArgumentException('Invalid webhook.',400);
        $q=$this->pdo->prepare('SELECT order_id,checkout_id FROM maya_payments WHERE merchant_reference=?');$q->execute([$event['requestReferenceNumber']]);$payment=$q->fetch();
        // Shared sandbox merchants receive unrelated events. Acknowledge without storing or exposing them.
        if(!$payment)return false;
        if($payment['checkout_id']!==null&&!hash_equals($payment['checkout_id'],$event['id']))throw new InvalidArgumentException('Webhook reference rejected.',400);
        $wait=(int)$this->pdo->query('SELECT @@innodb_lock_wait_timeout')->fetchColumn();
        try {
            // Bound a duplicate/worker row-lock wait so Maya can retry an unavailable inbox.
            $this->pdo->exec('SET SESSION innodb_lock_wait_timeout=2');
            $this->pdo->prepare('INSERT INTO maya_webhook_inbox(order_id,payment_id,event_status) VALUES (?,?,?) ON DUPLICATE KEY UPDATE id=id')
                ->execute([$payment['order_id'],$event['id'],$event['paymentStatus']]);
        } finally {$this->pdo->exec('SET SESSION innodb_lock_wait_timeout='.$wait);}
        return true;
    }

    public function processNext(): ?bool
    {
        $job=$this->transaction(function():array|false{
            $q=$this->pdo->query('SELECT id,order_id FROM maya_webhook_inbox WHERE processed_at IS NULL AND attempts<8 AND (lease_until IS NULL OR lease_until<NOW()) ORDER BY id LIMIT 1 FOR UPDATE');$job=$q->fetch();
            if($job)$this->pdo->prepare('UPDATE maya_webhook_inbox SET lease_until=DATE_ADD(NOW(),INTERVAL 60 SECOND),attempts=attempts+1 WHERE id=?')->execute([$job['id']]);
            return $job;
        });
        if(!$job)return null;
        try{$this->reconcile((int)$job['order_id'],(int)$job['id']);OrderNotification::deliver($this->pdo,(int)$job['order_id']);return true;}
        catch(Throwable){$this->pdo->prepare('UPDATE maya_webhook_inbox SET last_error="verification_unavailable" WHERE id=? AND processed_at IS NULL')->execute([$job['id']]);return false;}
    }

    /** Recognizes only the deterministic IDs used by the explicit local test adapter. */
    public static function simulatedReference(array $payment): bool
    {
        $id=$payment['checkout_id']??null;$reference=$payment['merchant_reference']??null;
        return MayaSandboxClient::validId($id)&&is_string($reference)
            && hash_equals($reference,str_replace('-','',strtolower($id)));
    }

    private function releaseStock(int $orderId): void
    {
        $q=$this->pdo->prepare('SELECT product_id,quantity FROM order_items WHERE order_id=? ORDER BY product_id,id FOR UPDATE');$q->execute([$orderId]);$quantities=[];
        foreach($q->fetchAll() as $item){if($item['product_id']===null||(int)$item['quantity']<1)throw new RuntimeException('Stock reconciliation needs review.');$id=(int)$item['product_id'];$quantities[$id]=($quantities[$id]??0)+(int)$item['quantity'];}
        if(!$quantities)throw new RuntimeException('Stock reconciliation needs review.');
        ksort($quantities);
        foreach($quantities as $id=>$quantity){
            $q=$this->pdo->prepare('SELECT stock_qty FROM products WHERE id=? FOR UPDATE');$q->execute([$id]);$before=$q->fetchColumn();
            if($before===false||$quantity>4294967295||(int)$before>4294967295-$quantity)throw new RuntimeException('Stock reconciliation needs review.');
            $this->pdo->prepare('UPDATE products SET stock_qty=stock_qty+?,updated_at=NOW() WHERE id=?')->execute([$quantity,$id]);
            AuditService::record($this->pdo,null,'payment.stock_released','product',$id,['order_id'=>$orderId,'quantity'=>$quantity]);
        }
    }

    private function lockedOrder(int $id): array
    {
        $q=$this->pdo->prepare('SELECT id,user_id,status FROM orders WHERE id=? FOR UPDATE');$q->execute([$id]);return $q->fetch()?:throw new InvalidArgumentException('Order not found.');
    }
    private function lockedPayment(int $id): array
    {
        $q=$this->pdo->prepare('SELECT *,TIMESTAMPDIFF(SECOND,created_at,NOW()) age_seconds FROM maya_payments WHERE order_id=? FOR UPDATE');$q->execute([$id]);return $q->fetch()?:throw new InvalidArgumentException('Payment not found.');
    }
    private function transaction(callable $operation): mixed
    {
        if($this->pdo->inTransaction())throw new RuntimeException('Unexpected payment transaction.');
        $this->pdo->beginTransaction();
        try{$result=$operation();$this->pdo->commit();return $result;}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}
