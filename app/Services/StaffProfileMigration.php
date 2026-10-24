<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use DomainException;
final class StaffProfileMigration
{
 public function run(PDO $pdo,bool $apply=false):array
 {
  if($pdo->inTransaction())throw new DomainException('Run additive DDL outside a transaction.');
  $engine=$pdo->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="staff_profiles"')->fetchColumn();if($engine!=='InnoDB')throw new DomainException('Existing transactional staff profiles required.');
  $pending=[];foreach(['contact_no'=>25,'profile_image'=>255] as $column=>$length){
   $q=$pdo->query('SHOW COLUMNS FROM staff_profiles WHERE Field="'.$column.'"');$row=$q->fetch();
   if(!$row)$pending[]='ALTER TABLE staff_profiles ADD `'.$column.'` VARCHAR('.$length.') NULL DEFAULT NULL';
   elseif(strtolower($row['Type'])!=='varchar('.$length.')'||$row['Null']!=='YES'||$row['Default']!==null)throw new DomainException('Unexpected staff profile column.');
  }
  if($apply)foreach($pending as $sql)$pdo->exec($sql);
  return ['pending_additions'=>count($pending),'applied'=>$apply?count($pending):0,'rows_updated'=>0];
 }
 public static function installed(PDO $pdo):bool
 {
  $q=$pdo->query('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="staff_profiles" AND ((COLUMN_NAME="contact_no" AND CHARACTER_MAXIMUM_LENGTH=25) OR (COLUMN_NAME="profile_image" AND CHARACTER_MAXIMUM_LENGTH=255)) AND DATA_TYPE="varchar" AND IS_NULLABLE="YES"');return (int)$q->fetchColumn()===2;
 }
}
