<?php
declare(strict_types=1);
namespace App\Services;
use PDO;

final class OrderNotification
{
    /** Target one committed order. SMTP trouble must never undo a successful checkout. */
    public static function deliver(PDO $pdo,int $orderId,?callable $sender=null): ?array
    {
        if($pdo->inTransaction())return ['status'=>'pending'];
        try{return (new OrderEmailQueue($pdo))->deliverNext($sender??static fn(array $order,array $items)=>(new MailService())->sendOrderConfirmation($order,$items),$orderId);}
        catch(\Throwable){return ['status'=>'pending'];}
    }
}
