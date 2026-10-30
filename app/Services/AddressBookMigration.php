<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
final class AddressBookMigration
{
 public function run(PDO $pdo,bool $apply=false):array
 {
  if($pdo->inTransaction())throw new \DomainException('Run this additive migration outside business transactions.');
  $q=$pdo->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="users"');
  if($q->fetchColumn()!=='InnoDB')throw new \DomainException('Transactional accounts are required.');
  $exists=$pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="customer_address_book"')->fetchColumn();
  if($exists){
   $fields=$pdo->query('SHOW COLUMNS FROM customer_address_book')->fetchAll();
   if(!AddressBook::installed($pdo)||array_column($fields,'Field')!==['id','user_id','payload_ciphertext','revision','is_default','created_at','updated_at','deleted_at'])throw new \DomainException('Unexpected address-book schema.');
   $expected=['id'=>'bigint(20) unsigned','user_id'=>'bigint(20) unsigned','payload_ciphertext'=>'text','revision'=>'char(32)','is_default'=>'tinyint(3) unsigned','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];
   foreach($fields as $field){if(strtolower($field['Type'])!==$expected[$field['Field']]||$field['Null']!==(in_array($field['Field'],['is_default','deleted_at'],true)?'YES':'NO'))throw new \DomainException('Unexpected address-book column definition.');}
   $ddl=$pdo->query('SHOW CREATE TABLE customer_address_book')->fetch(PDO::FETCH_NUM)[1];
   if(!str_contains($ddl,'UNIQUE KEY `uq_address_book_default` (`user_id`,`is_default`)')||!str_contains($ddl,'FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE'))throw new \DomainException('Address ownership/default constraints are required.');
  }elseif($apply){$pdo->exec(file_get_contents(dirname(__DIR__,2).'/database/address_book.sql'));$this->run($pdo,false);}
  return ['pending_additions'=>$exists?0:1,'applied'=>!$exists&&$apply?1:0,'rows_updated'=>0];
 }
}
