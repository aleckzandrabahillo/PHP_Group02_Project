<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;
use App\Core\Validator;
use App\Models\User;
use DomainException;
use PDO;
final class StaffProfileService
{
 public static function validate(array $input):array
 {
  $v=[];$errors=[];foreach(['full_name'=>100,'email'=>190,'contact_no'=>25] as $field=>$limit){$raw=$input[$field]??'';$v[$field]=is_string($raw)&&mb_check_encoding($raw,'UTF-8')?trim($raw):'';if(!is_string($raw)||!mb_check_encoding($raw,'UTF-8')||mb_strlen($raw)>$limit||preg_match('/[\x00-\x1f\x7f]/u',$raw))$errors[$field]='Enter a valid '.$field.'.';}
  if($v['full_name']==='')$errors['full_name']='Enter your full name, up to 100 characters.';
  $v['email']=strtolower($v['email']);if(!filter_var($v['email'],FILTER_VALIDATE_EMAIL))$errors['email']='Enter a valid email address.';
  if($v['contact_no']!==''){$phone=Validator::normalizePhilippineMobile($v['contact_no']);if(!$phone)$errors['contact_no']='Enter a valid Philippine mobile number.';else $v['contact_no']=$phone;}
  return ['values'=>$v,'errors'=>$errors];
 }
 private function actor(PDO $pdo,int $id):array
 {
  $u=(new User())->findById($id,true);if(!User::canAuthenticate($u)||!in_array($u['role'],['admin','catalog_manager'],true))throw new DomainException('An active staff account is required.');
  if(!StaffProfileMigration::installed($pdo))throw new DomainException('Profile editing is temporarily unavailable.');
  $q=$pdo->prepare('SELECT user_id FROM staff_profiles WHERE user_id=? FOR UPDATE');$q->execute([$id]);if($q->fetchColumn()===false)throw new DomainException('Staff profile unavailable.');return $u;
 }
 private function write(PDO $pdo,array $user,array $values):void
 {
  $old=(new User())->profileFor((int)$user['id']);$fields=[];foreach($values as $key=>$value)if((string)($old[$key]??'')!==$value)$fields[]=$key;if(!$fields)return;
  $q=$pdo->prepare('SELECT id FROM users WHERE email=? AND id<>?');$q->execute([$values['email'],$user['id']]);if($q->fetchColumn()!==false)throw new DomainException('That email address is already in use.');
  if($values['email']!==$user['email']){
   $pdo->prepare('UPDATE otp_codes SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$user['id']]);
   $pdo->prepare('UPDATE activation_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$user['id']]);
  }
  $pdo->prepare('UPDATE users SET email=?,updated_at=NOW() WHERE id=?')->execute([$values['email'],$user['id']]);
  $pdo->prepare('UPDATE staff_profiles SET full_name=?,contact_no=?,updated_at=NOW() WHERE user_id=?')->execute([$values['full_name'],$values['contact_no']?:null,$user['id']]);
  AuditService::record($pdo,(int)$user['id'],'profile.updated','user',(int)$user['id'],['fields'=>$fields]);
 }
 public function update(int $id,array $input,string $expectedEmail):void
 {
  $c=self::validate($input);if($c['errors'])throw new DomainException('Check your profile fields.');
  Database::transaction(function(PDO $pdo)use($id,$c,$expectedEmail):void{$u=$this->actor($pdo,$id);if($u['email']!==$expectedEmail||$c['values']['email']!==$u['email'])throw new DomainException('Email changes require verification.');$this->write($pdo,$u,$c['values']);});
 }
 public function confirm(int $id,array $flow,#[\SensitiveParameter] string $code,OtpService $otp):array
 {
  return Database::transaction(function(PDO $pdo)use($id,$flow,$code,$otp):array{
   $u=$this->actor($pdo,$id);if(($flow['user_id']??0)!==$id||($flow['role']??'')!==$u['role']||($flow['current_email']??'')!==$u['email']||($flow['sent_at']??0)<time()-900||!hash_equals($u['password_version']??'',$flow['password_version']??''))return ['ok'=>false];
   $c=self::validate($flow['values']??[]);if($c['errors'])return ['ok'=>false];
   $verified=$otp->verify($id,'profile_update',$code);if(!$verified['ok'])return $verified;
   $latest=(new User())->profileFor($id);foreach(['full_name','contact_no'] as $field)if((string)($latest[$field]??'')!==($flow['before'][$field]??''))$c['values'][$field]=(string)($latest[$field]??'');
   $this->write($pdo,$u,$c['values']);return $verified;
  });
 }
 public function image(int $id,string $path):?string
 {
  if(!preg_match('~^uploads/profile/[a-f0-9]{32}\.(jpg|png|webp)$~D',$path))throw new DomainException('Invalid profile photograph.');
  return Database::transaction(function(PDO $pdo)use($id,$path):?string{$this->actor($pdo,$id);$old=(new User())->profileFor($id)['profile_image']??null;$pdo->prepare('UPDATE staff_profiles SET profile_image=?,updated_at=NOW() WHERE user_id=?')->execute([$path,$id]);AuditService::record($pdo,$id,'profile.image_updated','user',$id);return $old;});
 }
}
