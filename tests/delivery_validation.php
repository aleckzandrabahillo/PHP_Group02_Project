<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);require $root.'/app/Core/Env.php';require $root.'/app/Support/helpers.php';
spl_autoload_register(static function(string $class)use($root):void{if(str_starts_with($class,'App\\'))require $root.'/app/'.str_replace('\\','/',substr($class,4)).'.php';});
$checks=[];
$check=static function(string $name,bool $passed)use(&$checks):void{$checks[]=['test'=>$name,'status'=>$passed?'PASS':'FAIL'];};
$reject=static function(string $name,callable $call)use($check):void{$failed=false;try{$call();}catch(\InvalidArgumentException|\RuntimeException){$failed=true;}$check($name,$failed);};
$valid=['country'=>'PH','first_name'=>'Synthetic','last_name'=>'Recipient','address_line'=>'12 Test Street','barangay'=>'Test Barangay','postal_code'=>'1600','city'=>'Pasig','region'=>'NCR','province'=>'Metro Manila','shipping_method'=>'standard','contact_no'=>'09123456789','payment_method'=>'cod'];
$v=App\Core\CheckoutValidator::validate($valid);$check('Complete delivery validates with normalized mobile',!$v['errors']&&$v['values']['contact_no']==='+639123456789');
foreach(App\Core\DeliveryValidator::LIMITS as $field=>$max){
 foreach(['',str_repeat('a',$max+1),['nested'],"bad\0value","\xff","\u{00A0}"] as $bad)$check('Reject malformed '.$field,isset(App\Core\DeliveryValidator::validate([...$valid,$field=>$bad])['errors'][$field]));
}
$check('All eighteen Philippine regions available',count(App\Services\ShippingService::REGIONS)===18&&isset(App\Services\ShippingService::REGIONS['NIR']));
$check('Reject foreign country',isset(App\Core\DeliveryValidator::validate([...$valid,'country'=>'US'])['errors']['country']));
foreach(['0000','123','12345','12a4','-123'] as $bad)$reject('Reject invalid postal code',fn()=>App\Services\ShippingService::options($bad));
foreach([['1600','Pasig','NCR',8000],['1550','Mandaluyong City','NCR',9000],['1000','Manila','NCR',10000],['2000','San Fernando','R3',12000],['4500','Legazpi','R5',15000],['6000','Cebu City','R7',18000],['8000','Davao City','R11',20000]] as [$postal,$city,$region,$cents]){foreach(['standard'=>0,'priority'=>6000] as $method=>$extra)$check('Configured area tier '.$city.' '.$method,App\Services\ShippingService::quote($postal,$method,$city,$region)['cents']===$cents+$extra);}
foreach([['9999','',''],['1600','Unknown City','NCR'],['1600','Pasig','unknown'],['8000','Pasig','NCR'],['1000','Pasig','NCR']] as [$postal,$city,$region])$reject('Reject incomplete or unsupported destination',fn()=>App\Services\ShippingService::quote($postal,'standard',$city,$region));
$check('Pasig city spelling normalizes',App\Services\ShippingService::quote('1600','standard',' City of PASIG ','NCR')['cents']===8000);
$reject('Reject forged shipping method',fn()=>App\Services\ShippingService::quote('1600','free'));
$forged=App\Core\CheckoutValidator::validate([...$valid,'shipping_fee'=>'0','total'=>'0.01']);$check('Submitted fee and total ignored',$forged['values']['shipping_fee']==='80.00');
$blank=App\Core\DeliveryValidator::prefill([]);$check('Missing profile stays blank except country and choices',$blank['country']==='PH'&&$blank['first_name']===''&&$blank['last_name']===''&&$blank['address_line']===''&&$blank['postal_code']===''&&$blank['region']==='');
$prefill=App\Core\DeliveryValidator::prefill(['full_name'=>'Synthetic Person','delivery_address'=>'Known saved address','contact_no'=>'+639123456789']);$check('Existing recorded values prefill without guessed location',$prefill['first_name']==='Synthetic'&&$prefill['last_name']==='Person'&&$prefill['address_line']==='Known saved address'&&$prefill['region']===''&&$prefill['postal_code']==='');
$public='pk-'.bin2hex(random_bytes(16));$secret='sk-'.bin2hex(random_bytes(16));$origin='https://avela.example.test';$id='12345678-1234-1234-1234-123456789abc';$reference='avela-test-1';
$urls=['success'=>$origin.'/success','failure'=>$origin.'/failure','cancel'=>$origin.'/cancel'];
$fake=static fn(array $body,int $status=200):Closure=>static fn()=>['status'=>$status,'body'=>json_encode($body,JSON_THROW_ON_ERROR)];
$create=['checkoutId'=>$id,'redirectUrl'=>'https://payments-web-sandbox.paymaya.com/v2/checkout?id='.$id];
$client=new App\Services\MayaSandboxClient($public,$secret,$origin,$fake($create));
$check('Simulated sandbox checkout response validates',$client->createCheckout(12345,$reference,$urls)['checkout_id']===$id);
foreach([0,-1,10000000000] as $amount)$reject('Reject invalid payment total',fn()=>$client->createCheckout($amount,$reference,$urls));
$reject('Reject external return origin',fn()=>$client->createCheckout(100,$reference,[...$urls,'success'=>'https://other.example.test/success']));
$reject('Reject HTTP merchant origin',fn()=>new App\Services\MayaSandboxClient($public,$secret,'http://avela.example.test',$fake($create)));
$reject('Reject credentials in merchant origin',fn()=>new App\Services\MayaSandboxClient($public,$secret,'https://user@avela.example.test',$fake($create)));
foreach(['http://payments-web-sandbox.paymaya.com/x','https://payments.maya.ph/x','https://payments-web-sandbox.paymaya.com.attacker.test/x'] as $url){$bad=new App\Services\MayaSandboxClient($public,$secret,$origin,$fake([...$create,'redirectUrl'=>$url]));$reject('Reject unsafe provider redirect',fn()=>$bad->createCheckout(100,$reference,$urls));}
$payment=['id'=>$id,'requestReferenceNumber'=>$reference,'totalAmount'=>['value'=>'123.45','currency'=>'PHP'],'paymentStatus'=>'PAYMENT_SUCCESS'];
$verified=new App\Services\MayaSandboxClient($public,$secret,$origin,$fake($payment));$check('Simulated authenticated payment lookup matches amount reference and currency',$verified->verifiedPayment($id,$reference,12345)['paid']);
foreach(['reference'=>$reference.'x','amount'=>12346] as $type=>$bad)$reject('Reject payment mismatch '.$type,fn()=>$verified->verifiedPayment($id,$type==='reference'?$bad:$reference,$type==='amount'?$bad:12345));
foreach(['PAYMENT_FAILED','PAYMENT_EXPIRED','PAYMENT_CANCELLED','PENDING_PAYMENT'] as $state){$c=new App\Services\MayaSandboxClient($public,$secret,$origin,$fake([...$payment,'paymentStatus'=>$state]));$check('Non-success state never marked paid '.$state,!$c->verifiedPayment($id,$reference,12345)['paid']);}
$bad=new App\Services\MayaSandboxClient($public,$secret,$origin,static fn()=>['status'=>200,'body'=>'not JSON']);$reject('Reject invalid provider JSON',fn()=>$bad->createCheckout(100,$reference,$urls));
$bad=new App\Services\MayaSandboxClient($public,$secret,$origin,static fn()=>['status'=>200,'body'=>str_repeat('x',65537)]);$reject('Reject oversized provider response',fn()=>$bad->createCheckout(100,$reference,$urls));
$bad=new App\Services\MayaSandboxClient($public,$secret,$origin,$fake($create,302));$reject('Reject provider redirects',fn()=>$bad->createCheckout(100,$reference,$urls));
$o=getopt('',['output:']);if(isset($o['output']))file_put_contents($o['output'],json_encode($checks,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));$fails=count(array_filter($checks,static fn($c)=>$c['status']==='FAIL'));echo (count($checks)-$fails).' PASS, '.$fails.' FAIL (Maya transport simulated; no network or real payments).',PHP_EOL;exit($fails?1:0);
