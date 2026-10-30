<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\DeliveryValidator;
use App\Core\Validator;
use App\Models\User;
use InvalidArgumentException;
use PDO;
use Throwable;
final class CustomerAddress
{
 public static function validate(array $input):array{
  $result=DeliveryValidator::validate([...$input,'shipping_method'=>'standard']);$raw=$input['contact_no']??null;$phone=is_string($raw)?Validator::normalizePhilippineMobile($raw):null;
  if(!$phone||strlen(is_string($raw)?$raw:'')>25)$result['errors']['contact_no']='Enter a valid Philippine mobile number.';$result['values']['contact_no']=$phone??'';return $result;
 }
 public function find(PDO $pdo,int $user):?array{return (new AddressBook())->default($pdo,$user);}
 public function findLegacy(PDO $pdo,int $user):?array{if(!AccountExperienceMigration::addressesInstalled($pdo))return null;$q=$pdo->prepare('SELECT * FROM customer_delivery_addresses WHERE user_id=?');$q->execute([$user]);return $q->fetch()?:null;}
 public function save(PDO $pdo,int $user,array $input,string $expected):array{
  if(($input['location_version']??'')==='2'&&!DeliveryLocationMigration::installed($pdo))throw new \RuntimeException('Address updates are temporarily unavailable.');
  $checked=self::validate($input);if($checked['errors'])throw new InvalidArgumentException('Check your address fields.');$v=$checked['values'];
  if(!AccountExperienceMigration::addressesInstalled($pdo))throw new \RuntimeException('Address management is temporarily unavailable.');
  $pdo->beginTransaction();try{
   $q=$pdo->prepare('SELECT id,role,status,locked_until,lock_requires_admin FROM users WHERE id=? FOR UPDATE');$q->execute([$user]);$actor=$q->fetch();if(!User::canAuthenticate($actor?:null)||$actor['role']!=='customer')throw new InvalidArgumentException('An active customer account is required.');
   $q=$pdo->prepare('SELECT user_id FROM customer_profiles WHERE user_id=?');$q->execute([$user]);if($q->fetchColumn()===false)throw new InvalidArgumentException('Your profile is unavailable. Please contact support.');
   $current=$this->findLegacy($pdo,$user);if($expected!==($current['revision']??''))throw new InvalidArgumentException('Your address changed. Reload your profile before saving.');
   $fields=['country','first_name','last_name','address_line','barangay','postal_code','city','region','contact_no'];$values=array_map(fn($f)=>$v[$f],$fields);
   if($current&&($current['province']??'')===($v['province']??'')&&array_map(fn($f)=>$current[$f],$fields)===$values){$pdo->commit();return [...$current,'delivery_address'=>$v['delivery_address']];}
   $revision=bin2hex(random_bytes(16));$pdo->prepare('INSERT INTO customer_delivery_addresses(user_id,country,first_name,last_name,address_line,barangay,postal_code,city,region,contact_no,revision,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE country=VALUES(country),first_name=VALUES(first_name),last_name=VALUES(last_name),address_line=VALUES(address_line),barangay=VALUES(barangay),postal_code=VALUES(postal_code),city=VALUES(city),region=VALUES(region),contact_no=VALUES(contact_no),revision=VALUES(revision),updated_at=NOW()')->execute([$user,...$values,$revision]);
   if(DeliveryLocationMigration::installed($pdo))$pdo->prepare('UPDATE customer_delivery_addresses SET province=? WHERE user_id=?')->execute([$v['province']?:null,$user]);
   $pdo->prepare('UPDATE customer_profiles SET delivery_address=?,updated_at=NOW() WHERE user_id=?')->execute([$v['delivery_address'],$user]);
   PiiStorage::write($pdo,'customer_profiles',$user,['delivery_address'=>$v['delivery_address']]);
   AuditService::record($pdo,$user,$current?'address.update':'address.create','user',$user,['fields'=>$fields]);$pdo->commit();return [...$v,'revision'=>$revision];
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
}
