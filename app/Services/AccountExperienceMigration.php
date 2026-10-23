<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use DomainException;
final class AccountExperienceMigration
{
 public static function addressesInstalled(PDO $pdo):bool{$q=$pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="customer_delivery_addresses" AND ENGINE="InnoDB"');return (int)$q->fetchColumn()===1;}
 public static function referencesInstalled(PDO $pdo):bool{return (bool)$pdo->query('SHOW COLUMNS FROM orders WHERE Field="public_reference"')->fetch();}
 public function run(PDO $pdo,bool $apply=false):array{
  if($pdo->inTransaction())throw new DomainException('Run additive migration outside business transactions.');
  foreach(['orders','users','customer_profiles','audit_logs'] as $table){$q=$pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);if($q->fetchColumn()!=='InnoDB')throw new DomainException('Existing transactional schema required.');}
  $pending=[];$column=$pdo->query('SHOW FULL COLUMNS FROM orders WHERE Field="public_reference"')->fetch();
  if(!$column)$pending[]='ALTER TABLE orders ADD public_reference VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL';
  elseif(strtolower($column['Type'])!=='varchar(32)'||$column['Collation']!=='ascii_bin'||$column['Null']!=='YES')throw new DomainException('Unexpected order-reference column.');
  $indexes=$pdo->query('SHOW INDEX FROM orders WHERE Key_name="uq_orders_public_reference"')->fetchAll();
  if(!$indexes)$pending[]='ALTER TABLE orders ADD UNIQUE KEY uq_orders_public_reference(public_reference)';
  elseif(count($indexes)!==1||(int)$indexes[0]['Non_unique']!==0||$indexes[0]['Column_name']!=='public_reference'||$indexes[0]['Sub_part']!==null)throw new DomainException('Unexpected order-reference index.');
  $ddl=file_get_contents(dirname(__DIR__,2).'/database/account_experience.sql');
  if(!is_string($ddl)||trim($ddl)==='')throw new DomainException('Address schema source unavailable.');
  $exists=$pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="customer_delivery_addresses"')->fetchColumn();
  if(!$exists)$pending[]=$ddl;
  else{
   $columns=$pdo->query('SHOW COLUMNS FROM customer_delivery_addresses')->fetchAll();
   foreach($columns as $extension)if($extension['Field']==='province'&&(strtolower($extension['Type'])!=='varchar(80)'||$extension['Null']!=='YES'))throw new DomainException('Unexpected province extension.');
   $columns=array_values(array_filter($columns,fn($column)=>$column['Field']!=='province'));$expected=['user_id'=>'bigint(20) unsigned','country'=>'char(2)','first_name'=>'varchar(60)','last_name'=>'varchar(60)','address_line'=>'varchar(200)','barangay'=>'varchar(100)','postal_code'=>'char(4)','city'=>'varchar(100)','region'=>'varchar(16)','contact_no'=>'varchar(25)','revision'=>'char(32)','created_at'=>'datetime','updated_at'=>'datetime'];
   if(!self::addressesInstalled($pdo)||array_column($columns,'Field')!==array_keys($expected))throw new DomainException('Unexpected saved-address schema.');
   foreach($columns as $c)if(strtolower($c['Type'])!==$expected[$c['Field']]||$c['Null']!=='NO')throw new DomainException('Unexpected address column.');
   $key=$pdo->query('SHOW INDEX FROM customer_delivery_addresses WHERE Key_name="PRIMARY"')->fetchAll();$create=$pdo->query('SHOW CREATE TABLE customer_delivery_addresses')->fetch(PDO::FETCH_NUM)[1];
   if(count($key)!==1||$key[0]['Column_name']!=='user_id'||!preg_match('/FOREIGN KEY \(`user_id`\) REFERENCES `users` \(`id`\) ON DELETE CASCADE/',$create))throw new DomainException('Unexpected address ownership constraint.');
  }
  if($apply){foreach($pending as $sql)$pdo->exec($sql);$this->run($pdo,false);}
  return ['pending_additions'=>count($pending),'applied'=>$apply?count($pending):0,'rows_updated'=>0];
 }
}
