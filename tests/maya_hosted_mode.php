<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');ini_set('zend.exception_ignore_args','1');
$root=dirname(__DIR__);require $root.'/app/Core/Env.php';require $root.'/app/Support/helpers.php';
spl_autoload_register(static function(string $class)use($root):void{if(str_starts_with($class,'App\\')){require_once $root.'/app/'.str_replace('\\','/',substr($class,4)).'.php';}});
use App\Services\MayaSandboxConfig as Config;
use App\Services\MayaSandboxClient as Client;
$checks=[];
function checkHosted(string $name,bool $pass):void{global $checks;$checks[]=['name'=>$name,'status'=>$pass?'PASS':'FAIL'];}
function rejectsHosted(callable $f):bool{try{$f();return false;}catch(Throwable){return true;}}
try{
 $_ENV=[...$_ENV,'APP_ENV'=>'development','APP_URL'=>'http://localhost:18080','DB_HOST'=>'127.0.0.1','DB_PORT'=>'13321','DB_DATABASE'=>'avela_hosted_test_mode',
  'MAYA_LOCAL_SIMULATION'=>'false','MAYA_LOCAL_HOSTED_SANDBOX'=>'true','MAYA_SANDBOX_ENABLED'=>'true','MAYA_ENVIRONMENT'=>'sandbox','MAYA_MERCHANT_ORIGIN'=>'http://localhost:18080',
  'MAYA_WEBHOOK_READY'=>'false','MAYA_RECONCILIATION_READY'=>'false','MAYA_PUBLIC_KEY'=>'pk-'.bin2hex(random_bytes(24)),'MAYA_SECRET_KEY'=>'sk-'.bin2hex(random_bytes(24)),
  'MAYA_SANDBOX_PUBLIC_KEY'=>'','MAYA_SANDBOX_SECRET_KEY'=>''];
 checkHosted('No implicit local-hosted enablement',!Config::available()&&rejectsHosted(fn()=>Config::client()));
 define('AVELA_LOCAL_HOSTED_SANDBOX_ACTIVE',true);
 checkHosted('Explicit local-hosted mode available without false webhook claims',Config::available()&&Config::localHosted());
 checkHosted('Default real provider client now usable in guarded hosted mode',Config::client() instanceof Client);
 foreach(['APP_ENV'=>'production','APP_URL'=>'https://attacker.example','DB_HOST'=>'remote.example','DB_PORT'=>'13307','DB_DATABASE'=>'avela_phase1_20261009','MAYA_LOCAL_HOSTED_SANDBOX'=>'unexpected','MAYA_ENVIRONMENT'=>'production','MAYA_LOCAL_SIMULATION'=>'true','MAYA_SANDBOX_ENABLED'=>'false','MAYA_MERCHANT_ORIGIN'=>'http://127.0.0.1:18080'] as $key=>$bad){
  $original=$_ENV[$key];$_ENV[$key]=$bad;checkHosted('Reject local-hosted misuse '.$key,!Config::available()&&rejectsHosted(fn()=>Config::client()));$_ENV[$key]=$original;
 }
 $public=$_ENV['MAYA_PUBLIC_KEY'];$secret=$_ENV['MAYA_SECRET_KEY'];$id='12345678-1234-4234-8234-123456789012';$origin='http://localhost:18080';$seen=[];
 $transport=static function($method,$url,$headers,$body)use(&$seen,$id):array{$seen=compact('method','url','headers','body');return ['status'=>201,'body'=>json_encode(['checkoutId'=>$id,'redirectUrl'=>'https://payments-web-sandbox.paymaya.com/v2/checkout?id='.$id],JSON_THROW_ON_ERROR)];};
 checkHosted('Plain HTTP merchant origin rejected by default',rejectsHosted(fn()=>new Client($public,$secret,$origin,$transport)));
 $client=new Client($public,$secret,$origin,$transport,true);$returns=['success'=>$origin.'/payments/maya/return?order=123&result=success','failure'=>$origin.'/payments/maya/return?order=123&result=failure','cancel'=>$origin.'/payments/maya/return?order=123&result=cancel'];
 $response=$client->createCheckout(12345,str_repeat('a',32),$returns);$body=json_decode($seen['body'],true,32,JSON_THROW_ON_ERROR);
 checkHosted('Only official HTTPS Sandbox create endpoint',$seen['url']==='https://pg-sandbox.paymaya.com/checkout/v1/checkouts'&&$seen['method']==='POST');
 checkHosted('Exact total and currency in create request',$body['totalAmount']['currency']==='PHP'&&(int)round($body['totalAmount']['value']*100)===12345);
 checkHosted('Unique reference and three localhost return URLs preserved',$body['requestReferenceNumber']===str_repeat('a',32)&&$body['redirectUrl']===$returns);
 checkHosted('Public key used only server-side for session creation',in_array('Authorization: Basic '.base64_encode($public.':'),$seen['headers'],true)&&!str_contains($seen['body'],$public)&&!isset($body['buyer']));
 checkHosted('Maya hosted destination returned unchanged',str_starts_with($response['redirect_url'],'https://payments-web-sandbox.paymaya.com/'));
 foreach(['http://evil.example/return','http://localhost:18080.evil.example/return','http://localhost:18081/return','http://127.0.0.1:18080/return',"http://localhost:18080/return\nX-Test:1",'http://localhost:18080/return#fragment',"http://localhost:18080/\\evil.example"] as $url){checkHosted('Reject unsafe browser return '.count($checks),rejectsHosted(fn()=>$client->createCheckout(12345,str_repeat('a',32),[...$returns,'success'=>$url])));}
 foreach(['https://payments-web-sandbox.maya.ph/v2/checkout?id='.$id,'https://payments-web-sandbox.paymaya.com/v2/checkout?id='.$id] as $destination){
  $c=new Client($public,$secret,$origin,fn()=>['status'=>200,'body'=>json_encode(['checkoutId'=>$id,'redirectUrl'=>$destination])],true);
  checkHosted('Exact approved Sandbox destination accepted '.parse_url($destination,PHP_URL_HOST),$c->createCheckout(12345,str_repeat('a',32),$returns)['redirect_url']===$destination);
 }
 foreach(['https://payments.maya.ph/v2/checkout','https://payments-web-sandbox.maya.ph.evil.example/v2/checkout','http://payments-web-sandbox.maya.ph/v2/checkout','https://u@payments-web-sandbox.maya.ph/v2/checkout','https://payments-web-sandbox.maya.ph:444/v2/checkout'] as $destination){
  $c=new Client($public,$secret,$origin,fn()=>['status'=>200,'body'=>json_encode(['checkoutId'=>$id,'redirectUrl'=>$destination])],true);
  checkHosted('Unapproved hosted destination rejected '.count($checks),rejectsHosted(fn()=>$c->createCheckout(12345,str_repeat('a',32),$returns)));
 }
 $e=new App\Services\MayaApiException(401,'PY0019');checkHosted('Safe provider diagnostic fields',$e->httpStatus===401&&$e->providerCode==='PY0019');
 $e=new App\Services\MayaApiException(400,'secret-do-not-copy');checkHosted('Provider diagnostics discard arbitrary payloads',$e->providerCode===null&&!str_contains($e->getMessage(),'secret-do-not-copy'));
 $reference=str_repeat('a',32);$fake=['merchant_reference'=>$reference,'checkout_id'=>'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'];
 checkHosted('Earlier simulator references recognized',App\Services\MayaPaymentService::simulatedReference($fake));
 checkHosted('Genuine unrelated UUID not mislabeled',!App\Services\MayaPaymentService::simulatedReference([...$fake,'checkout_id'=>$id]));
 $_ENV['MAYA_LOCAL_HOSTED_SANDBOX']='false';$_ENV['MAYA_MERCHANT_ORIGIN']='https://merchant.example';
 checkHosted('Normal integration still requires webhook/worker readiness',!Config::available());
 $_ENV['MAYA_WEBHOOK_READY']='true';$_ENV['MAYA_RECONCILIATION_READY']='true';checkHosted('Normal secure hosted integration remains available',Config::available());
 $result=['checks'=>$checks,'pass'=>count(array_filter($checks,fn($c)=>$c['status']==='PASS')),'fail'=>count(array_filter($checks,fn($c)=>$c['status']==='FAIL')),'live_network_requests'=>0];
 $o=getopt('',['output:']);if(isset($o['output']))file_put_contents($o['output'],json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo json_encode($result,JSON_THROW_ON_ERROR),PHP_EOL;exit($result['fail']?1:0);
}catch(Throwable $e){fwrite(STDERR,'Hosted mode tests stopped at line '.$e->getLine().'; private details suppressed.'.PHP_EOL);exit(1);}
