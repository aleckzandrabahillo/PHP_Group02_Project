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
 /** Collision-free display alias: the complete decimal primary key, never truncated. */
 public static function display(array $order):string{
  $id=(string)($order['id']??'');
  if(!preg_match('/^[1-9][0-9]{0,19}$/D',$id))throw new \InvalidArgumentException('Valid order identity required.');
  return 'AVE-'.str_pad($id,6,'0',STR_PAD_LEFT);
 }
 public static function displayId(mixed $reference):?int{
  if(!is_string($reference)||!preg_match('/^AVE-([0-9]{6,20})$/D',$reference,$m))return null;
  $id=filter_var(ltrim($m[1],'0'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  return $id===false?null:$id;
 }
}
