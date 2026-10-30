<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/Core/Env.php';require dirname(__DIR__).'/app/Core/Session.php';require dirname(__DIR__).'/app/Core/Csrf.php';require dirname(__DIR__).'/app/Support/helpers.php';
require dirname(__DIR__).'/app/Services/ImageUploadPolicy.php';
$checks=[];$verify=static function(string $name,bool $ok)use(&$checks):void{$checks[]=['test'=>$name,'status'=>$ok?'PASS':'FAIL'];};
$_SERVER['SCRIPT_NAME']='/index.php';$_SERVER['REQUEST_METHOD']='GET';
$render=static function(string $file,array $data=[]):string{extract($data,EXTR_SKIP);ob_start();require dirname(__DIR__).'/app/Views/'.$file.'.php';return ob_get_clean();};
foreach(['admin','catalog_manager'] as $role){
 $_SESSION=['auth_user'=>['id'=>1,'role'=>$role,'username'=>'synthetic_staff']];$_SERVER['REQUEST_URI']=$role==='admin'?'/admin/orders/view?id=2':'/catalog/inventory';
 $html=$render('partials/staff_sidebar');$dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
 $orders=$xpath->query('//nav//a[@href="/admin/orders"]');
 $verify($role.' keeps permission-appropriate Orders navigation',$orders->length===($role==='admin'?1:0));
 if($role==='admin'){
  $verify('Orders is a direct link with no expandable parent',$xpath->query('//a[@href="/admin/orders"]/ancestor::details')->length===0);
  $verify('Order detail keeps Orders selected',$orders->item(0)->getAttribute('aria-current')==='page');
 }
 $verify($role.' keeps explicit CSRF-protected sign out',$xpath->query('//form[@action="/logout" and @method="post"]//input[@name="_token"]')->length===1);
 $verify($role.' uses familiar 2-step navigation',str_contains($html,'2-step verification')&&!str_contains($html,'>Authenticator<'));
 $verify($role.' does not force every sidebar group open',$xpath->query('//details[@open]')->length<=1);
}
foreach(['customer','admin','catalog_manager'] as $role){
 $_SESSION=['auth_user'=>['id'=>1,'role'=>$role,'username'=>'synthetic_account']];
 $data=['enabledAt'=>'2026-10-24','remaining'=>4,'configured'=>true,'setupSecret'=>null,'setupUri'=>null,'setupExpiresAt'=>0,'setupStep'=>1,'accountProfile'=>['id'=>1,'username'=>'synthetic_account','full_name'=>'Synthetic Account Owner','profile_image'=>null]];
 $html=$render('account/mfa',$data);
 $verify($role.' has the same 2-step page title',str_contains($html,'<h1>2-step verification</h1>'));
 $verify($role.' security page uses its role-appropriate account layout',str_contains($html,'account-security-main')===($role==='customer'));
 if($role==='customer'){
  $verify('Customer security identity uses the account name rather than the login handle',str_contains($html,'<h2>Synthetic Account Owner</h2>'));
  $verify('Customer security navigation preserves POST and CSRF sign out',str_contains($html,'action="/logout"')&&str_contains($html,'name="_token"'));
 }
 $verify($role.' explains its actual sign-in steps',str_contains($html,'After your password, enter the code from your phone.')&&!str_contains($html,'After your password and email code'));
 $verify($role.' retains the current password and proof fields',str_contains($html,'name="password"')&&str_contains($html,'name="authenticator_code"')&&str_contains($html,'name="_token"'));
 $html=$render('account/mfa',[...$data,'enabledAt'=>null]);
 $verify($role.' setup starts with the familiar name',str_contains($html,'Set up 2-step verification')&&!str_contains($html,'Start authenticator setup'));
 $recovery=$render('account/mfa_recovery',['codes'=>[]]);$verify($role.' recovery navigation is consistent',str_contains($recovery,'Return to 2-step verification'));
}
$_SESSION=[];$html=$render('auth/verify-authenticator');
$verify('Sign-in challenge does not invent a completed email step',str_contains($html,'<h1>2-step verification</h1>')&&!str_contains($html,'Your email code is confirmed'));
$verify('Sign-in route and proof field stay compatible',str_contains($html,'action="/verify-authenticator"')&&str_contains($html,'name="authenticator_code"'));
$bad=array_values(array_filter($checks,static fn($c)=>$c['status']!=='PASS'));$options=getopt('',['output:']);if(isset($options['output']))file_put_contents($options['output'],json_encode($checks,JSON_PRETTY_PRINT));
echo json_encode(['suite'=>'staff_ui','pass'=>count($checks)-count($bad),'fail'=>count($bad),'failed'=>$bad]),PHP_EOL;exit($bad?1:0);
