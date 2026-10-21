<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
final class OrderReference
{
 public static function create(PDO $pdo,int $id):string{
  if(!$pdo->inTransaction())throw new \LogicException('Order reference belongs to the order transaction.');
  for($attempt=0;$attempt<5;$attempt++){$ref='AVE-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(7)));try{$q=$pdo->prepare('UPDATE orders SET public_reference=? WHERE id=? AND public_reference IS NULL');$q->execute([$ref,$id]);if($q->rowCount()!==1)throw new \LogicException('Order reference already assigned.');return $ref;}catch(\PDOException $e){if(($e->errorInfo[1]??0)!==1062)throw $e;}}
  throw new \RuntimeException('Order reference unavailable.');
 }
 public static function display(array $order):string{
  if(is_string($order['public_reference']??null)&&preg_match('/^AVE-[0-9]{8}-[A-F0-9]{14}$/D',$order['public_reference']))return $order['public_reference'];
  // Stable, unique legacy display only. Existing numeric URLs/provider references remain unchanged.
  return 'AVE-'.date('Ymd',strtotime($order['created_at']??'now')).'-L'.strtoupper(base_convert((string)$order['id'],10,36));
 }
}
