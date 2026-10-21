<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use InvalidArgumentException;

/** Bounded aggregates; catalog reporting never returns customer or security records. */
final class DashboardAnalytics
{
 public function __construct(private readonly PDO $pdo){}
 public static function days(mixed $value):int{
  if(!is_string($value)&&!is_int($value))throw new InvalidArgumentException('Choose a valid reporting period.');
  if(!in_array((string)$value,['7','30','90'],true))throw new InvalidArgumentException('Choose a valid reporting period.');return (int)$value;
 }
 private function rows(string $sql,array $parameters=[]):array{$q=$this->pdo->prepare($sql);$q->execute($parameters);return $q->fetchAll();}
 public function report(int $days,bool $admin=false):array{
  $days=self::days($days);$today=new \DateTimeImmutable('today');$since=$today->modify('-'.($days-1).' days')->format('Y-m-d 00:00:00');$until=$today->modify('+1 day')->format('Y-m-d 00:00:00');
  $stock=$this->rows('SELECT COUNT(*) total,SUM(status="active") active,SUM(status<>"active") inactive,SUM(status="active" AND stock_qty BETWEEN 1 AND 5) low_stock,SUM(status="active" AND stock_qty=0) out_of_stock FROM products')[0];
  $attention=$this->rows('SELECT id,name,sku,stock_qty FROM products WHERE status="active" AND stock_qty<=5 ORDER BY stock_qty,id LIMIT 6');
  $categories=$this->rows('SELECT c.name,COUNT(p.id) products FROM categories c LEFT JOIN products p ON p.category_id=c.id GROUP BY c.id,c.name ORDER BY products DESC,c.name LIMIT 10');
  $maya=MayaMigration::installed($this->pdo);
  // Provider verification is evidence of a Sandbox payment state, not bank settlement.
  $verified=$maya?'EXISTS(SELECT 1 FROM maya_payments mp WHERE mp.order_id=o.id AND mp.state="paid" AND mp.stock_state="committed" AND mp.verified_at IS NOT NULL AND mp.checkout_id IS NOT NULL AND REPLACE(mp.checkout_id,"-","")<>mp.merchant_reference)':'0=1';
  $top=$this->rows('SELECT oi.product_id,MAX(oi.product_name_snapshot) name,SUM(oi.quantity) units FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.status="delivered" AND o.created_at>=? AND o.created_at<? AND (o.payment_method="cod" OR ('.$verified.')) GROUP BY oi.product_id ORDER BY units DESC,oi.product_id LIMIT 5',[$since,$until]);
  $result=['days'=>$days,'since'=>substr($since,0,10),'until'=>$today->format('Y-m-d'),'stock'=>$stock,'attention'=>$attention,'categories'=>$categories,'top'=>$top];
  if(!$admin)return $result;
  $statuses=array_fill_keys(['pending','processing','shipped','delivered','cancelled'],0);
  foreach($this->rows('SELECT status,COUNT(*) count FROM orders WHERE created_at>=? AND created_at<? GROUP BY status',[$since,$until]) as $row)$statuses[$row['status']]=(int)$row['count'];
  $series=[];for($i=0;$i<$days;$i++)$series[$today->modify('-'.($days-1-$i).' days')->format('Y-m-d')]=0;
  foreach($this->rows('SELECT DATE(created_at) day,COUNT(*) count FROM orders WHERE created_at>=? AND created_at<? GROUP BY DATE(created_at)',[$since,$until]) as $row)$series[$row['day']]=(int)$row['count'];
  $value=$this->rows('SELECT COALESCE(SUM(total),0) total FROM orders o WHERE o.status<>"cancelled" AND o.created_at>=? AND o.created_at<? AND ('.$verified.')',[$since,$until])[0]['total'];
  $ready=(int)$this->rows('SELECT COUNT(*) count FROM orders o WHERE o.status IN ("pending","processing") AND o.created_at>=? AND o.created_at<? AND (o.payment_method="cod" OR ('.$verified.'))',[$since,$until])[0]['count'];
  $recent=$this->rows('SELECT a.action,a.target_type,a.target_id,a.created_at,COALESCE(u.username,"System") actor FROM audit_logs a LEFT JOIN users u ON u.id=a.actor_id ORDER BY a.id DESC LIMIT 6');
  return $result+['statuses'=>$statuses,'series'=>$series,'orders'=>array_sum($statuses),'verified_card_value'=>$value,'ready_to_prepare'=>$ready,'recent'=>$recent];
 }
}
