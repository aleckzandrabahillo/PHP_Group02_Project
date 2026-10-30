<?php

namespace App\Models;

use App\Core\Database;
use PDO;

final class Order
{
    public function __construct(private readonly ?PDO $connection = null) {}

    public function forCustomer(int $userId,int $page=1,array $filters=[]): array
    {
        $pdo=$this->connection??Database::connection();$status=$filters['status']??'';$search=$filters['q']??'';
        if(!is_string($status)||!in_array($status,['','pending','processing','shipped','delivered','cancelled'],true)||!is_string($search)||!mb_check_encoding($search,'UTF-8')||mb_strlen($search)>60||preg_match('/[\x00-\x1f\x7f]/u',$search))throw new \InvalidArgumentException('Choose valid order filters.');
        $where=['o.user_id=?'];$params=[$userId];if($status!==''){$where[]='o.status=?';$params[]=$status;}
        $search=trim($search);
        if($search!==''){
            $needle=preg_replace('/^#\s*/u','',$search);
            $displayId=\App\Services\OrderReference::displayId($needle);if($displayId!==null)$needle=(string)$displayId;
            $where[]="(CAST(o.id AS CHAR) LIKE ? ESCAPE '!' OR UPPER(o.public_reference) LIKE UPPER(?) ESCAPE '!' OR CONCAT('AVE-',DATE_FORMAT(o.created_at,'%Y%m%d'),'-L',UPPER(CONV(o.id,10,36))) LIKE UPPER(?) ESCAPE '!' OR EXISTS(SELECT 1 FROM order_items search_items WHERE search_items.order_id=o.id AND search_items.product_name_snapshot LIKE ? ESCAPE '!'))";
            $term='%'.strtr($needle,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';array_push($params,$term,$term,$term,$term);
        }
        $filter=' WHERE '.implode(' AND ',$where);$q=$pdo->prepare('SELECT COUNT(*) FROM orders o'.$filter);$q->execute($params);$total=(int)$q->fetchColumn();$pages=max(1,(int)ceil($total/10));$page=max(1,min($page,$pages));$maya=\App\Services\MayaMigration::installed($pdo);
        $query='SELECT o.id,o.public_reference,o.total,o.status,o.payment_method,o.created_at,'.($maya?'mp.state,mp.verified_at,mp.checkout_id,mp.merchant_reference':'NULL AS state,NULL AS verified_at,NULL AS checkout_id,NULL AS merchant_reference').' FROM orders o'.($maya?' LEFT JOIN maya_payments mp ON mp.order_id=o.id':'').$filter.' ORDER BY o.id DESC LIMIT 10 OFFSET '.(($page-1)*10);
        $q=$pdo->prepare($query);$q->execute($params);$rows=$q->fetchAll();$byId=[];
        if($rows){$ids=array_column($rows,'id');$q=$pdo->prepare('SELECT oi.order_id,oi.product_id,oi.product_name_snapshot,oi.quantity,oi.unit_price_snapshot,p.image_path FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY oi.id');$q->execute($ids);foreach($q->fetchAll() as $item)$byId[$item['order_id']][]=$item;}
        $deliveryEvents=$this->deliveryEvents($pdo,$rows);foreach($rows as &$row){$row['delivery_event_id']=$deliveryEvents[(int)$row['id']]??0;$row['maya_state']=$row['state'];$row['items']=$byId[$row['id']]??[];$row['simulated_payment']=\App\Services\MayaPaymentService::simulatedReference($row);$row['item_count']=array_sum(array_column($row['items'],'quantity'));}unset($row);
        return ['items'=>$rows,'total'=>$total,'page'=>$page,'pages'=>$pages,'status'=>$status,'q'=>$search];
    }

    public function owned(int $id, int $userId): ?array
    {
        $pdo = $this->connection ?? Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id=? AND user_id=?');
        $stmt->execute([$id, $userId]);
        $order = $stmt->fetch();
        if (!$order) return null;
        $order=\App\Services\PiiStorage::read('orders',$id,$order);
        $items = $pdo->prepare('SELECT oi.product_id,oi.product_name_snapshot,oi.quantity,oi.unit_price_snapshot,p.image_path FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=? ORDER BY oi.id');
        $items->execute([$id]);
        $order['items'] = $items->fetchAll();
        $events=$pdo->prepare('SELECT from_status,to_status,created_at FROM order_status_events WHERE order_id=? ORDER BY id');
        $events->execute([$id]);$order['events']=$events->fetchAll();
        $delivery=$pdo->prepare('SELECT * FROM order_delivery_details WHERE order_id=?');$delivery->execute([$id]);$order['delivery']=$delivery->fetch()?:null;
        $order['maya_payment']=null;
        if($order['payment_method']==='manual'&&\App\Services\MayaMigration::installed($pdo)){
            $payment=$pdo->prepare('SELECT state,stock_state,verified_at,checkout_id,merchant_reference FROM maya_payments WHERE order_id=?');$payment->execute([$id]);
            $order['maya_payment']=$payment->fetch()?:null;
        }
        $q=$pdo->prepare('SELECT status,sent_at FROM order_email_outbox WHERE order_id=?');$q->execute([$id]);$order['email_confirmation']=$q->fetch()?:null;
        $dispatch=$pdo->prepare('SELECT id,created_at FROM audit_logs WHERE action="order.out_for_delivery" AND target_type="order" AND target_id=? ORDER BY id DESC LIMIT 1');$dispatch->execute([$id]);$event=$dispatch->fetch();
        $order['delivery_event_id']=$event?(int)$event['id']:0;$order['out_for_delivery_at']=$event['created_at']??null;
        $order['discount']=null;
        if(\App\Services\DiscountMigration::installed($pdo)){$q=$pdo->prepare('SELECT code_snapshot,amount_cents,state FROM order_discounts WHERE order_id=?');$q->execute([$id]);$order['discount']=$q->fetch()?:null;}
        return $order;
    }

    public function ownedReference(mixed $reference,int $userId):?array
    {
        if(($displayId=\App\Services\OrderReference::displayId($reference))!==null)return $this->owned($displayId,$userId);
        if(!is_string($reference)||!preg_match('/^AVE-[0-9]{8}-[A-F0-9]{14}$/D',$reference))return null;
        $pdo=$this->connection??Database::connection();$q=$pdo->prepare('SELECT id FROM orders WHERE public_reference=? AND user_id=?');$q->execute([$reference,$userId]);$id=$q->fetchColumn();return $id===false?null:$this->owned((int)$id,$userId);
    }

    public function forAdministrator(int $id): ?array
    {
        $pdo=$this->connection??Database::connection();
        $q=$pdo->prepare('SELECT user_id FROM orders WHERE id=?');$q->execute([$id]);$owner=$q->fetchColumn();
        return $owner===false?null:$this->owned($id,(int)$owner);
    }

    public function submitted(int $userId, string $key): ?int
    {
        $stmt = ($this->connection ?? Database::connection())->prepare('SELECT id FROM orders WHERE user_id=? AND checkout_key=?');
        $stmt->execute([$userId, $key]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function forAdmin(array $input): array
    {
        foreach(['status','q'] as $key)if(isset($input[$key])&&(!is_string($input[$key])||!mb_check_encoding($input[$key],'UTF-8')||preg_match('/[\x00-\x1f\x7f]/',$input[$key])))throw new \InvalidArgumentException('Use valid text for order filters.');
        $status=is_string($input['status']??null)?$input['status']:'';
        if(!in_array($status,['','pending','processing','shipped','delivered','cancelled'],true))throw new \InvalidArgumentException('Choose a valid order status.');
        $search=is_string($input['q']??null)?trim($input['q']):'';
        if(mb_strlen($search)>100)throw new \InvalidArgumentException('Keep order search within 100 characters.');
        $where=[];$params=[];
        if($status!==''){$where[]='o.status=?';$params[]=$status;}
        if($search!==''){$needle=(string)(\App\Services\OrderReference::displayId($search)??$search);$where[]="(CAST(o.id AS CHAR) LIKE ? ESCAPE '!' OR COALESCE(o.recipient_name_snapshot,'') LIKE ? ESCAPE '!' OR o.public_reference LIKE ? ESCAPE '!')";$q='%'.strtr($needle,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$params[]=$q;$params[]=$q;$params[]=$q;}
        $sql=' FROM orders o'.($where?' WHERE '.implode(' AND ',$where):'');$pdo=$this->connection??Database::connection();
        $count=$pdo->prepare('SELECT COUNT(*)'.$sql);$count->execute($params);$total=(int)$count->fetchColumn();$pages=max(1,(int)ceil($total/10));
        $raw=$input['page']??1;$page=(is_int($raw)||is_string($raw))?filter_var($raw,FILTER_VALIDATE_INT):false;$page=max(1,min($pages,$page===false?1:$page));
        $payment=\App\Services\MayaMigration::installed($pdo)?'(SELECT mp.state FROM maya_payments mp WHERE mp.order_id=o.id) AS maya_state':'NULL AS maya_state';
        $query=$pdo->prepare('SELECT o.id,o.public_reference,o.recipient_name_snapshot,o.total,o.status,o.created_at,o.payment_method,'.$payment.$sql.' ORDER BY o.id DESC LIMIT 10 OFFSET '.(($page-1)*10));$query->execute($params);$rows=$query->fetchAll();$deliveryEvents=$this->deliveryEvents($pdo,$rows);foreach($rows as &$row){$row['delivery_event_id']=$deliveryEvents[(int)$row['id']]??0;}unset($row);
        return ['items'=>$rows,'total'=>$total,'page'=>$page,'pages'=>$pages,'status'=>$status,'q'=>$search];
    }

    /** One bounded lookup per result page, preserving the original order-query filters. */
    private function deliveryEvents(PDO $pdo,array $orders):array
    {
        if(!$orders)return [];
        $ids=array_column($orders,'id');
        $q=$pdo->prepare('SELECT target_id,MAX(id) AS event_id FROM audit_logs WHERE action="order.out_for_delivery" AND target_type="order" AND target_id IN ('.implode(',',array_fill(0,count($ids),'?')).') GROUP BY target_id');
        $q->execute($ids);$events=[];
        foreach($q->fetchAll() as $event)$events[(int)$event['target_id']]=(int)$event['event_id'];
        return $events;
    }

}
