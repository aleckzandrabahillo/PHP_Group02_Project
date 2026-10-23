<?php
declare(strict_types=1);
namespace App\Services;
use DomainException;
use PDO;
final class DeliveryLocationMigration
{
 public function run(PDO $pdo,bool $apply=false):array
 {
  if($pdo->inTransaction())throw new DomainException('Run additive DDL outside business transactions.');
  $pending=[];
  foreach(['customer_delivery_addresses','order_delivery_details'] as $table){
   $q=$pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);
   if($q->fetchColumn()!=='InnoDB')throw new DomainException('Existing transactional delivery tables required.');
   $column=$pdo->query('SHOW COLUMNS FROM `'.$table.'` WHERE Field="province"')->fetch();
   if(!$column)$pending[]='ALTER TABLE `'.$table.'` ADD province VARCHAR(80) NULL DEFAULT NULL';
   elseif(strtolower($column['Type'])!=='varchar(80)'||$column['Null']!=='YES'||$column['Default']!==null)throw new DomainException('Unexpected province column.');
  }
  if($apply){foreach($pending as $sql)$pdo->exec($sql);$this->run($pdo,false);}
  return ['pending_additions'=>count($pending),'applied'=>$apply?count($pending):0,'rows_updated'=>0];
 }
 public static function installed(PDO $pdo):bool
 {
  $q=$pdo->query('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ("customer_delivery_addresses","order_delivery_details") AND COLUMN_NAME="province" AND DATA_TYPE="varchar" AND CHARACTER_MAXIMUM_LENGTH=80 AND IS_NULLABLE="YES"');return (int)$q->fetchColumn()===2;
 }
}
