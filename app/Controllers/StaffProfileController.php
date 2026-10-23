<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,Response,Session,View};
use App\Middleware\AuthGuard;
use App\Models\User;
use App\Services\{StaffProfileService,ProfileImageService,OtpService,MailService,LogService};
final class StaffProfileController
{
 private function path(array $user):string{return $user['role']==='admin'?'/admin/profile':'/catalog/profile';}
 private function otp():OtpService{return new OtpService(new MailService(),new LogService());}
 public function update():void
 {
  $u=AuthGuard::roles(['admin','catalog_manager']);Csrf::enforce($_POST['_token']??null);$path=$this->path($u);$c=StaffProfileService::validate($_POST);
  if($c['errors']){Session::keepOld($c['values']);Session::put('errors',$c['errors']);Response::redirect($path.'?edit=1');}
  $before=(new User())->profileFor((int)$u['id']);
  try{
   if($c['values']['email']===$u['email']){(new StaffProfileService())->update((int)$u['id'],$c['values'],$u['email']);Session::forget('errors');Session::flash('success','Your profile has been updated.');Response::redirect($path);}
   if((new User())->emailExistsForOtherUser($c['values']['email'],(int)$u['id']))throw new \DomainException('That email address is already in use.');
   $current=(new User())->findById((int)$u['id']);Session::put('staff_profile_update_flow',['user_id'=>(int)$u['id'],'role'=>$u['role'],'current_email'=>$u['email'],'password_version'=>$current['password_version']??'','before'=>array_map(static fn($v)=>(string)$v,array_intersect_key($before,array_flip(['full_name','contact_no']))),'values'=>$c['values'],'sent_at'=>time()]);
   $issued=$this->otp()->issue((int)$u['id'],$u['email'],'profile_update');if(!$issued['issued'])throw new \DomainException('Please wait before requesting another verification code.');
   Response::redirect('/account/profile/verify-otp');
  }catch(\Throwable $e){Session::forget('staff_profile_update_flow');Session::flash('warning',$e instanceof \DomainException?$e->getMessage():'Your profile could not be saved. Please try again.');Response::redirect($path.'?edit=1');}
 }
 public function verify():void
 {
  $u=AuthGuard::roles(['admin','catalog_manager']);$flow=Session::get('staff_profile_update_flow');if(!is_array($flow)||($flow['user_id']??0)!==(int)$u['id'])Response::redirect($this->path($u));
  if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
   Csrf::enforce($_POST['_token']??null);$code=$_POST['code']??null;
   try{$result=is_string($code)&&preg_match('/^[0-9]{6}$/D',$code)?(new StaffProfileService())->confirm((int)$u['id'],$flow,$code,$this->otp()):['ok'=>false];}catch(\Throwable){$result=['ok'=>false];}
   if($result['ok']){Session::forget('staff_profile_update_flow');Session::flash('success','Your profile has been updated.');Response::redirect($this->path($u));}
   Session::flash('warning','The code could not be verified. Check it or return to your profile.');Response::redirect('/account/profile/verify-otp');
  }
  $email=$flow['current_email'];$parts=explode('@',$email);$masked=mb_substr($parts[0],0,1).'•••@'.($parts[1]??'');View::render('account/profile-verify',['pageTitle'=>'Confirm email change','maskedEmail'=>$masked,'backPath'=>$this->path($u)]);
 }
 public function image():void
 {
  $u=AuthGuard::roles(['admin','catalog_manager']);Csrf::enforce($_POST['_token']??null);$images=new ProfileImageService();$path=null;
  try{$path=$images->store($_FILES['profile_image']??null);$old=(new StaffProfileService())->image((int)$u['id'],$path);$images->discard($old);Session::flash('success','Your profile photograph has been updated.');}
  catch(\Throwable $e){$images->discard($path);Session::flash('warning',$e instanceof \InvalidArgumentException?$e->getMessage():'Your photograph could not be saved. Please try again.');}
  Response::redirect($this->path($u));
 }
}
