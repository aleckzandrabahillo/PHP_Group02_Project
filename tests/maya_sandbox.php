<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('AVELA_HAIR_TEST_LIBRARY',true);require __DIR__.'/hair_profiles.php';

use App\Services\MayaSandboxClient;
use App\Services\MayaPaymentService;
use App\Services\MayaMigration;
use App\Services\MayaSandboxConfig;

function mayaFixtureEnv(string $key,string $value): void {
    $file=tempnam(sys_get_temp_dir(),'avela-maya-setting-');
    try{file_put_contents($file,$key.'='.json_encode($value)."\n");App\Core\Env::load($file);}finally{unlink($file);}
}
function mayaReject(callable $call): bool {try{$call();return false;}catch(InvalidArgumentException|RuntimeException|DomainException){return true;}}
function mayaUuid(string $hex): string {return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20,12);}
function mayaRows(PDO $pdo,string $table): array {$rows=$pdo->query('SELECT * FROM '.$table)->fetchAll();$hash=array_map(fn($r)=>hash('sha256',json_encode($r,JSON_THROW_ON_ERROR)),$rows);sort($hash);return $hash;}
function mayaRace(array $jobs,array $options): array
{
    $children=[];
    foreach($jobs as $job){$args=[PHP_BINARY,__FILE__];foreach([...$options,...$job] as $key=>$value)$args[]='--'.$key.'='.$value;
        $p=proc_open($args,[0=>['file','NUL','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);if(!is_resource($p))throw new RuntimeException('Worker unavailable');$children[]=[$p,$pipes];}
    $results=[];foreach($children as [$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);if($code!==0||$err!=='')throw new RuntimeException('Concurrent fixture failed');$results[]=json_decode($out,true,32,JSON_THROW_ON_ERROR);}return $results;
}

try{
    $o=getopt('',['expect-host:','expect-database:','expect-port:','expect-server:','expect-server-id:','expect-datadir:','base-url:','session-directory:','scratch-directory:','output:','worker:','order:','status:','product:','actor:']);
    $pdo=App\Core\Database::connection();$id=$pdo->query('SELECT DATABASE() db,@@port port,@@hostname host,@@server_id sid,@@datadir dir')->fetch();
    $base=$o['base-url']??'';$url=parse_url($base);$sessions=realpath($o['session-directory']??'');$norm=fn($s)=>strtolower(rtrim(str_replace('\\','/',$s),'/'));
    if(!preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D',$id['db'])||($o['expect-database']??'')!==$id['db']||(int)($o['expect-port']??0)!==(int)$id['port']
        ||in_array((int)$id['port'],[3306,13307],true)||$id['host']!==($o['expect-server']??null)||(int)$id['sid']!==(int)($o['expect-server-id']??0)
        ||$norm($o['expect-datadir']??'')!==$norm($id['dir'])||env('DB_HOST')!=='127.0.0.1'||env('MAIL_DRIVER')!=='log'||env('APP_ENV')!=='development'
        ||!$sessions||str_starts_with($norm($sessions),$norm(dirname(__DIR__)).'/')||($url['host']??'')!=='127.0.0.1'||in_array((int)($url['port']??80),[80,18080],true))throw new RuntimeException('Disposable-only guard');
    if(isset($o['worker'])){
        $client=new MayaSandboxClient('pk-'.bin2hex(random_bytes(24)),'sk-'.bin2hex(random_bytes(24)),(string)env('MAYA_MERCHANT_ORIGIN'),static function()use($pdo,$o):array{
            $q=$pdo->prepare('SELECT * FROM maya_payments WHERE order_id=?');$q->execute([$o['order']]);$p=$q->fetch();
            return ['status'=>200,'body'=>json_encode(['id'=>$p['checkout_id'],'requestReferenceNumber'=>$p['merchant_reference'],'totalAmount'=>['value'=>App\Services\ShippingService::decimal((int)$p['amount_cents']),'currency'=>'PHP'],'paymentStatus'=>$o['status']],JSON_THROW_ON_ERROR)];
        });
        $service=new MayaPaymentService($pdo,$client);
        if($o['worker']==='restock')(new App\Models\Product())->changeStock((int)$o['product'],7,true,null,(int)$o['actor'],'Synthetic concurrent shipment');
        elseif($o['worker']==='queue')$service->processNext();else $service->reconcile((int)$o['order']);
        echo '{"completed":true}';ob_end_flush();exit;
    }
    ini_set('session.save_path',$sessions);$checks=[];$orders=[];$prefix='maya_'.bin2hex(random_bytes(5));$users=new App\Models\User();$checkout=new App\Services\CheckoutService();
    $original=[];foreach(['products','product_tags','product_reviews','order_items','orders','security_settings'] as $table)$original[$table]=mayaRows($pdo,$table);
    $category=(int)$pdo->query('SELECT id FROM categories WHERE status="active" ORDER BY id LIMIT 1')->fetchColumn();
    $pdo->prepare('INSERT INTO products(category_id,name,sku,price,stock_qty,status) VALUES (?,"Synthetic Maya product",?,"10.00",1000,"active")')->execute([$category,strtoupper($prefix)]);$product=(int)$pdo->lastInsertId();
    $input=['country'=>'PH','first_name'=>'Synthetic','last_name'=>'Recipient','address_line'=>'1 Test Street','barangay'=>'Test','postal_code'=>'1600','city'=>'Pasig','region'=>'NCR','province'=>'Metro Manila','contact_no'=>'09123456789','shipping_method'=>'standard','payment_method'=>'maya_sandbox'];
    $seq=0;$newCustomer=function()use($users,$prefix,&$seq):int{$name=$prefix.'_'.(++$seq);$uid=$users->createCustomer(['username'=>$name,'email'=>$name.'@example.invalid','full_name'=>'Synthetic Maya customer','contact_no'=>'+639123456789','password_hash'=>password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT)]);$users->activate($uid);return $uid;};
    $make=function(array $override=[])use($newCustomer,$checkout,$input,$product,&$orders):array{$uid=$newCustomer();(new App\Models\Cart())->add($uid,$product,1);$quote=$checkout->quote($uid);$key=bin2hex(random_bytes(32));$order=$checkout->place($uid,[...$input,...$override],$key,$quote['signature']);$orders[]=$order;return ['user'=>$uid,'order'=>$order,'key'=>$key,'quote'=>$quote];};
    $row=function(int $id)use($pdo):array{$q=$pdo->prepare('SELECT * FROM maya_payments WHERE order_id=?');$q->execute([$id]);return $q->fetch();};
    $stock=fn()=>(int)$pdo->query('SELECT stock_qty FROM products WHERE id='.$product)->fetchColumn();
    $public='pk-'.bin2hex(random_bytes(24));$secret='sk-'.bin2hex(random_bytes(24));$origin=(string)env('MAYA_MERCHANT_ORIGIN');$calls=[];$states=[];$failure=null;$empty=false;
    $transport=function(string $method,string $url,array $headers,?string $body)use(&$calls,&$states,&$failure,&$empty,$pdo):array{
        $calls[]=$method; if($failure==='throw')throw new RuntimeException('Synthetic unavailable');
        if($method==='POST'){$request=json_decode($body,true,32,JSON_THROW_ON_ERROR);$ref=$request['requestReferenceNumber'];$data=['checkoutId'=>mayaUuid($ref),'redirectUrl'=>'https://payments-web-sandbox.paymaya.com/v2/checkout?id='.mayaUuid($ref)];}
        else{$ref=basename(parse_url($url,PHP_URL_PATH));$rrn=str_contains($url,'/payment-rrns/');$q=$pdo->prepare('SELECT * FROM maya_payments WHERE '.($rrn?'merchant_reference':'checkout_id').'=?');$q->execute([$ref]);$p=$q->fetch();if(!$p)throw new RuntimeException('Synthetic lookup absent');$ref=$p['merchant_reference'];$data=['id'=>$p['checkout_id']??mayaUuid($ref),'requestReferenceNumber'=>$ref,'totalAmount'=>['value'=>App\Services\ShippingService::decimal((int)$p['amount_cents']),'currency'=>'PHP'],'paymentStatus'=>$states[$ref]??'PENDING_PAYMENT'];if($rrn)$data=$empty?[]:[$data];}
        if($failure==='amount'&&isset($data['totalAmount']))$data['totalAmount']['value']='0.01';
        if($failure==='reference'&&isset($data['requestReferenceNumber']))$data['requestReferenceNumber']=str_repeat('0',32);
        return ['status'=>$failure==='api'?503:200,'body'=>$failure==='json'?'{invalid':json_encode($data,JSON_THROW_ON_ERROR)];
    };
    $client=new MayaSandboxClient($public,$secret,$origin,$transport);$service=new MayaPaymentService($pdo,$client);
    verifyHair('Maya schema repeat-safe with no additions',(new MayaMigration())->run($pdo,false)['pending_additions']===0);
    verifyHair('Explicit sandbox readiness available',MayaSandboxConfig::available());
    foreach(['MAYA_SANDBOX_ENABLED','MAYA_WEBHOOK_READY','MAYA_RECONCILIATION_READY'] as $flag){mayaFixtureEnv($flag,'false');verifyHair('Missing readiness fails closed '.$flag,!MayaSandboxConfig::available());mayaFixtureEnv($flag,'true');}
    mayaFixtureEnv('MAYA_ENVIRONMENT','production');verifyHair('Production mode rejected',!MayaSandboxConfig::available());mayaFixtureEnv('MAYA_ENVIRONMENT','sandbox');
    foreach(['','your-maya-key','pk-'.str_repeat('a',300),"pk-bad\nkey"] as $key)verifyHair('Missing/invalid credentials rejected',mayaReject(fn()=>new MayaSandboxClient($key,$secret,$origin,$transport)));
    $_ENV['MAYA_SANDBOX_PUBLIC_KEY']='pk-'.bin2hex(random_bytes(20));verifyHair('Conflicting legacy key rejected',!MayaSandboxConfig::available());unset($_ENV['MAYA_SANDBOX_PUBLIC_KEY']);

    $pending=$make();$p=$row($pending['order']);verifyHair('Server total includes shipping',$p['amount_cents']===9000||$p['amount_cents']==='9000');
    verifyHair('Unpredictable unique merchant reference',preg_match('/^[a-f0-9]{32}$/D',$p['merchant_reference'])===1);
    verifyHair('Pending reserve deducted once',$stock()===999&&$p['stock_state']==='reserved');
    verifyHair('Unpaid order has no confirmation mail',(int)$pdo->query('SELECT COUNT(*) FROM order_email_outbox WHERE order_id='.$pending['order'])->fetchColumn()===0);
    $before=$stock();$repeat=$checkout->place($pending['user'],$input,$pending['key'],$pending['quote']['signature']);verifyHair('Duplicate submission reuses internal order',$repeat===$pending['order']&&$stock()===$before);
    $destination=$service->start($pending['user'],$pending['order']);$p=$row($pending['order']);$count=count($calls);
    verifyHair('Validated hosted sandbox redirect',str_starts_with($destination??'','https://payments-web-sandbox.paymaya.com/'));
    verifyHair('Repeated start never creates another checkout',$service->start($pending['user'],$pending['order'])===$destination&&count($calls)===$count);
    verifyHair('Unrelated customer cannot start payment',mayaReject(fn()=>$service->start($newCustomer(),$pending['order'])));
    $owner=hairClient($pending['user']);$other=hairClient($newCustomer());$beforeState=$row($pending['order']);
    $return=hairRequest('/payments/maya/return?order='.$pending['order'].'&result=success&paymentStatus=PAYMENT_SUCCESS',$owner);
    verifyHair('Forged success redirect is read-only',$return['code']===302&&$row($pending['order'])===$beforeState);
    verifyHair('Return requires owner',hairRequest('/payments/maya/return?order='.$pending['order'],$other)['code']===404);
    verifyHair('Return requires login',hairRequest('/payments/maya/return?order='.$pending['order'])['code']===302);
    foreach(['/payments/maya/refresh','/payments/maya/continue'] as $path){verifyHair('CSRF required '.$path,hairRequest($path,$owner,['_token'=>'bad','order_id'=>(string)$pending['order']])['code']===419);verifyHair('Guest denied '.$path,hairRequest($path,null,[])['code']===302);}
    $audit=(int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();$before=$stock();
    $event=json_encode(['id'=>$p['checkout_id'],'requestReferenceNumber'=>$p['merchant_reference'],'paymentStatus'=>'PAYMENT_SUCCESS'],JSON_THROW_ON_ERROR);
    foreach([['127.0.0.1',true,443],['13.229.160.234',false,443],['13.229.160.234',true,80]] as [$peer,$tls,$port])verifyHair('Webhook transport/source rejected',mayaReject(fn()=>$service->enqueue($event,$peer,$tls,$port)));
    foreach(['{invalid','[]',str_repeat('x',16385),json_encode(['id'=>[$p['checkout_id']]]),json_encode(['id'=>$p['checkout_id'],'requestReferenceNumber'=>$p['merchant_reference'],'paymentStatus'=>'FAKE_SUCCESS'])] as $invalid)verifyHair('Malformed webhook rejected',mayaReject(fn()=>$service->enqueue($invalid,'13.229.160.234',true,443)));
    $count=count($calls);verifyHair('Authenticated webhook durably queued',$service->enqueue($event,'13.229.160.234',true,443));
    verifyHair('Acknowledgement makes no provider call',count($calls)===$count&&$stock()===$before&&$row($pending['order'])['state']==='pending');
    $service->enqueue($event,'3.1.199.75',true,443);verifyHair('Duplicate event stored once',(int)$pdo->query('SELECT COUNT(*) FROM maya_webhook_inbox WHERE order_id='.$pending['order'])->fetchColumn()===1);
    $states[$p['merchant_reference']]='PAYMENT_SUCCESS';verifyHair('Worker verifies success',$service->processNext()===true&&$row($pending['order'])['state']==='paid');
    verifyHair('Payment success commits reservation without second deduction',$row($pending['order'])['stock_state']==='committed'&&$stock()===$before);
    verifyHair('Success queues exactly one confirmation',(int)$pdo->query('SELECT COUNT(*) FROM order_email_outbox WHERE order_id='.$pending['order'])->fetchColumn()===1);
    verifyHair('Processed duplicate has no second job',$service->processNext()===null);
    $service->reconcile($pending['order']);verifyHair('Repeated verification does not duplicate mail',(int)$pdo->query('SELECT COUNT(*) FROM order_email_outbox WHERE order_id='.$pending['order'])->fetchColumn()===1);
    $late=json_encode(['id'=>$p['checkout_id'],'requestReferenceNumber'=>$p['merchant_reference'],'paymentStatus'=>'PAYMENT_FAILED'],JSON_THROW_ON_ERROR);$service->enqueue($late,'13.229.160.234',true,443);$service->processNext();
    verifyHair('Delayed failure cannot regress verified success',$row($pending['order'])['state']==='paid'&&$stock()===$before);

    foreach(['PAYMENT_FAILED'=>'failed','PAYMENT_CANCELLED'=>'cancelled','PAYMENT_EXPIRED'=>'expired'] as $status=>$expected){
        $f=$make();$service->start($f['user'],$f['order']);$fp=$row($f['order']);$before=$stock();$states[$fp['merchant_reference']]=$status;$service->reconcile($f['order']);
        verifyHair('Definitive payment state '.$status,$row($f['order'])['state']===$expected);
        verifyHair('Failed/cancelled/expired stock released exactly once '.$status,$row($f['order'])['stock_state']==='released'&&$stock()===$before+1);
        $service->reconcile($f['order']);verifyHair('Repeated terminal state cannot restore twice '.$status,$stock()===$before+1);
        verifyHair('Unpaid order cancelled '.$status,$pdo->query('SELECT status FROM orders WHERE id='.$f['order'])->fetchColumn()==='cancelled');
        verifyHair('No paid email for unsuccessful payment '.$status,(int)$pdo->query('SELECT COUNT(*) FROM order_email_outbox WHERE order_id='.$f['order'])->fetchColumn()===0);
        $states[$fp['merchant_reference']]='PAYMENT_SUCCESS';$service->reconcile($f['order']);verifyHair('Late payment after release is review, never automatic fulfillment '.$status,$row($f['order'])['state']==='review'&&$stock()===$before+1);
    }
    $uncertain=$make();$failure='throw';$before=$stock();verifyHair('Transport failure never creates fake payment',$service->start($uncertain['user'],$uncertain['order'])===null&&$row($uncertain['order'])['state']==='pending');$failure=null;
    $count=count(array_filter($calls,fn($m)=>$m==='POST'));$service->start($uncertain['user'],$uncertain['order']);verifyHair('Uncertain creation is never retried blindly',count(array_filter($calls,fn($m)=>$m==='POST'))===$count);
    $up=$row($uncertain['order']);$states[$up['merchant_reference']]='PAYMENT_SUCCESS';$service->reconcile($uncertain['order']);verifyHair('Uncertain creation recovers via authenticated RRN',$row($uncertain['order'])['state']==='paid'&&$stock()===$before);
    $missing=$make();$failure='throw';$service->start($missing['user'],$missing['order']);$failure=null;$empty=true;
    verifyHair('Uncertain young payment retains reservation',mayaReject(fn()=>$service->reconcile($missing['order']))&&$row($missing['order'])['stock_state']==='reserved');
    $pdo->prepare('UPDATE maya_payments SET created_at=DATE_SUB(NOW(),INTERVAL 121 MINUTE) WHERE order_id=?')->execute([$missing['order']]);$before=$stock();$service->reconcile($missing['order']);$empty=false;
    verifyHair('Old authenticated empty RRN expires and releases',$row($missing['order'])['state']==='expired'&&$stock()===$before+1);
    $bad=$make();$service->start($bad['user'],$bad['order']);$bp=$row($bad['order']);$states[$bp['merchant_reference']]='PAYMENT_SUCCESS';
    foreach(['amount','reference','json','api','throw'] as $fault){$failure=$fault;$before=$row($bad['order']);$quantity=$stock();verifyHair('Provider verification fails closed '.$fault,mayaReject(fn()=>$service->reconcile($bad['order']))&&$row($bad['order'])===$before&&$stock()===$quantity);}$failure=null;
    $pdo->exec('CREATE TRIGGER maya_test_audit_fault BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT="Synthetic Maya audit fault"');
    try{$states[$bp['merchant_reference']]='PAYMENT_FAILED';$before=$stock();verifyHair('Audit failure rolls back release and order',mayaReject(fn()=>$service->reconcile($bad['order']))&&$row($bad['order'])['state']==='pending'&&$stock()===$before);}finally{$pdo->exec('DROP TRIGGER maya_test_audit_fault');}
    $cod=$make(['payment_method'=>'cod']);verifyHair('COD remains independent without Maya row',(int)$pdo->query('SELECT COUNT(*) FROM maya_payments WHERE order_id='.$cod['order'])->fetchColumn()===0&&$pdo->query('SELECT payment_method FROM orders WHERE id='.$cod['order'])->fetchColumn()==='cod');
    $httpUser=$newCustomer();(new App\Models\Cart())->add($httpUser,$product,1);$http=hairClient($httpUser);$page=hairRequest('/checkout',$http);
    verifyHair('Card choice uses the guarded Sandbox route without an adjacent environment badge',str_contains($page['body'],'value="maya_sandbox"')&&str_contains($page['body'],'Debit/Credit Card')&&!str_contains($page['body'],'class="payment-environment"')&&!str_contains($page['body'],'data-card-information')&&!str_contains($page['body'],'name="card_number"'));
    preg_match('/name="checkout_key" value="([a-f0-9]{64})"/',$page['body'],$key);$post=['_token'=>$http['csrf'],'checkout_key'=>$key[1],...$input,'total'=>'0.01','shipping_fee'=>'0'];$r=hairRequest('/checkout',$http,$post);
    $q=$pdo->prepare('SELECT id FROM orders WHERE checkout_key=? AND user_id=?');$q->execute([$key[1],$httpUser]);$httpId=(int)$q->fetchColumn();$orders[]=$httpId;
    verifyHair('Actual POST uses simulated hosted sandbox redirect',$r['code']===303&&array_filter($r['headers'],fn($h)=>str_starts_with($h,'Location: https://payments-web-sandbox.paymaya.com/'))!==[]);
    verifyHair('Actual POST ignores forged fee/amount',$row($httpId)['amount_cents']==9000);
    $repeat=hairRequest('/checkout',$http,$post);verifyHair('Actual repeated POST does not create again',$repeat['code']===303&&(int)$pdo->query('SELECT COUNT(*) FROM maya_payments WHERE order_id='.$httpId)->fetchColumn()===1);
    $view=hairRequest('/orders/view?id='.$httpId,$http);verifyHair('Pending order never displays verified success',$view['code']===200&&str_contains($view['body'],'No payment was collected')&&!str_contains($view['body'],'Your payment has been confirmed by the payment provider'));
    $q=$pdo->query('SELECT actor_id FROM audit_logs WHERE action="payment.initiated" AND target_id='.$httpId.' ORDER BY id DESC LIMIT 1');verifyHair('Initiation audit attributes actual customer',(int)$q->fetchColumn()===$httpUser);
    $manager=$users->findByIdentifier('image_catalog_manager');$managerClient=hairClient((int)$manager['id']);
    foreach(['/payments/maya/refresh','/payments/maya/continue'] as $path)verifyHair('Staff cannot use customer payment action '.$path,hairRequest($path,$managerClient,['_token'=>$managerClient['csrf'],'order_id'=>(string)$httpId])['code']===403);
    $quantity=$stock();$account=$users->findById($httpUser);$pdo->prepare('UPDATE users SET status="inactive" WHERE id=?')->execute([$httpUser]);
    verifyHair('Inactive owner cannot resume payment',mayaReject(fn()=>$service->start($httpUser,$httpId))&&$stock()===$quantity);$pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$account['status'],$httpUser]);
    $admin=$users->findByIdentifier('image_admin');
    verifyHair('Unpaid Maya order cannot be fulfilled',mayaReject(fn()=>(new App\Services\OrderWorkflow())->advance($pdo,(int)$admin['id'],$httpId,'pending','processing')));
    verifyHair('Unpaid Maya cannot use COD cancellation path',mayaReject(fn()=>(new App\Services\OrderWorkflow())->advance($pdo,(int)$admin['id'],$httpId,'pending','cancelled')));
    $fakeHeaders="Content-Type: application/json\r\nX-Forwarded-For: 13.229.160.234\r\nX-Forwarded-Proto: https\r\nConnection: close\r\n";
    file_get_contents($base.'/payments/maya/webhook',false,stream_context_create(['http'=>['method'=>'POST','ignore_errors'=>true,'follow_location'=>0,'header'=>$fakeHeaders,'content'=>$event]]));
    verifyHair('Actual HTTP ignores spoofed forwarded headers',str_contains($http_response_header[0]??'','403'));
    $raceOptions=array_intersect_key($o,array_flip(['expect-host','expect-database','expect-port','expect-server','expect-server-id','expect-datadir','base-url','session-directory','scratch-directory']));
    $raceOrder=$make();$service->start($raceOrder['user'],$raceOrder['order']);$before=$stock();
    mayaRace([['worker'=>'reconcile','order'=>$raceOrder['order'],'status'=>'PAYMENT_FAILED'],['worker'=>'reconcile','order'=>$raceOrder['order'],'status'=>'PAYMENT_FAILED']],$raceOptions);
    verifyHair('Concurrent failures release stock once',$stock()===$before+1&&$row($raceOrder['order'])['state']==='failed');
    $raceOrder=$make();$service->start($raceOrder['user'],$raceOrder['order']);$before=$stock();
    mayaRace([['worker'=>'reconcile','order'=>$raceOrder['order'],'status'=>'PAYMENT_SUCCESS'],['worker'=>'reconcile','order'=>$raceOrder['order'],'status'=>'PAYMENT_SUCCESS']],$raceOptions);
    verifyHair('Concurrent success does not deduct again',$stock()===$before&&$row($raceOrder['order'])['state']==='paid');
    verifyHair('Concurrent success schedules one receipt',(int)$pdo->query('SELECT COUNT(*) FROM order_email_outbox WHERE order_id='.$raceOrder['order'])->fetchColumn()===1);
    $raceOrder=$make();$service->start($raceOrder['user'],$raceOrder['order']);$before=$stock();
    mayaRace([['worker'=>'reconcile','order'=>$raceOrder['order'],'status'=>'PAYMENT_FAILED'],['worker'=>'restock','order'=>$raceOrder['order'],'status'=>'PAYMENT_FAILED','product'=>$product,'actor'=>$manager['id']]],$raceOptions);
    verifyHair('Concurrent reservation release and restock both preserved',$stock()===$before+8);
    $raceOrder=$make();$service->start($raceOrder['user'],$raceOrder['order']);$before=$stock();$rp=$row($raceOrder['order']);
    $service->enqueue(json_encode(['id'=>$rp['checkout_id'],'requestReferenceNumber'=>$rp['merchant_reference'],'paymentStatus'=>'PAYMENT_SUCCESS'],JSON_THROW_ON_ERROR),'13.229.160.234',true,443);
    mayaRace([['worker'=>'queue','order'=>$raceOrder['order'],'status'=>'PAYMENT_SUCCESS'],['worker'=>'queue','order'=>$raceOrder['order'],'status'=>'PAYMENT_SUCCESS']],$raceOptions);
    verifyHair('Concurrent workers claim one webhook safely',$stock()===$before&&$row($raceOrder['order'])['state']==='paid'&&(int)$pdo->query('SELECT attempts FROM maya_webhook_inbox WHERE order_id='.$raceOrder['order'])->fetchColumn()===1);
    foreach(['https://payments-web-sandbox.paymaya.com.evil.test/x','http://payments-web-sandbox.paymaya.com/x','https://payments.maya.ph/x',"https://payments-web-sandbox.paymaya.com/\r\nLocation:x",'https://u@payments-web-sandbox.paymaya.com/x'] as $badUrl){$badClient=new MayaSandboxClient($public,$secret,$origin,fn()=>['status'=>200,'body'=>json_encode(['checkoutId'=>mayaUuid(bin2hex(random_bytes(16))),'redirectUrl'=>$badUrl])]);verifyHair('Unsafe provider redirect rejected',mayaReject(fn()=>$badClient->createCheckout(9000,bin2hex(random_bytes(16)),['success'=>$origin.'/success','failure'=>$origin.'/failure','cancel'=>$origin.'/cancel'])));}
    $scalar=['id'=>mayaUuid(bin2hex(random_bytes(16))),'amount'=>'90.00','currency'=>'PHP','status'=>'PAYMENT_SUCCESS','isPaid'=>true,'requestReferenceNumber'=>bin2hex(random_bytes(16))];
    $scalarClient=new MayaSandboxClient($public,$secret,$origin,fn()=>['status'=>200,'body'=>json_encode($scalar)]);
    verifyHair('Official scalar amount/status response supported',$scalarClient->verifiedPayment($scalar['id'],$scalar['requestReferenceNumber'],9000)['paid']);
    foreach(['currency'=>'USD','amount'=>'0.01','id'=>mayaUuid(bin2hex(random_bytes(16))),'isPaid'=>false] as $field=>$badValue){$badClient=new MayaSandboxClient($public,$secret,$origin,fn()=>['status'=>200,'body'=>json_encode([...$scalar,$field=>$badValue])]);verifyHair('Mismatched provider proof rejected '.$field,mayaReject(fn()=>$badClient->verifiedPayment($scalar['id'],$scalar['requestReferenceNumber'],9000)));}
    $payloadValid=false;
    $numeric=new MayaSandboxClient($public,$secret,$origin,function($method,$url,$headers,$body)use(&$payloadValid):array{$payload=json_decode($body,true,32,JSON_THROW_ON_ERROR);$payloadValid=(is_float($payload['totalAmount']['value'])||is_int($payload['totalAmount']['value']))&&(int)round($payload['totalAmount']['value']*100)===9001&&$payload['totalAmount']['currency']==='PHP'&&!isset($payload['buyer']);return ['status'=>201,'body'=>json_encode(['checkoutId'=>mayaUuid(bin2hex(random_bytes(16))),'redirectUrl'=>'https://payments-web-sandbox.paymaya.com/v2/checkout'])];});
    $numeric->createCheckout(9001,bin2hex(random_bytes(16)),['success'=>$origin.'/success','failure'=>$origin.'/failure','cancel'=>$origin.'/cancel']);verifyHair('Create uses numeric amount and sends no customer/card data',$payloadValid);
    $notFound=new MayaSandboxClient($public,$secret,$origin,fn()=>['status'=>404,'body'=>json_encode(['code'=>'PY0009'])]);verifyHair('Documented missing RRN is empty evidence',$notFound->paymentByReference(bin2hex(random_bytes(16)),9000)===null);
    foreach(['PY0017','unknown'] as $code){$badClient=new MayaSandboxClient($public,$secret,$origin,fn()=>['status'=>404,'body'=>json_encode(['code'=>$code])]);verifyHair('Merchant/unknown 404 is not payment expiry '.$code,mayaReject(fn()=>$badClient->paymentByReference(bin2hex(random_bytes(16)),9000)));}
    verifyHair('Missing known payment ID does not assume expiry',mayaReject(fn()=>$notFound->verifiedPayment(mayaUuid(bin2hex(random_bytes(16))),bin2hex(random_bytes(16)),9000)));
    $paidBefore=$stock();(new App\Services\OrderWorkflow())->advance($pdo,(int)$admin['id'],$pending['order'],'pending','processing');verifyHair('Verified paid order can proceed without stock mutation',$pdo->query('SELECT status FROM orders WHERE id='.$pending['order'])->fetchColumn()==='processing'&&$stock()===$paidBefore);
    $pdo->beginTransaction();try{verifyHair('Uncommitted outer transaction cannot acknowledge webhook',mayaReject(fn()=>$service->enqueue($event,'13.229.160.234',true,443)));}finally{$pdo->rollBack();}
    $pdo->exec('ALTER TABLE maya_payments ALTER state SET DEFAULT "paid"');
    try{verifyHair('Migration rejects altered default without repair',mayaReject(fn()=>(new MayaMigration())->run($pdo,false)));}finally{$pdo->exec('ALTER TABLE maya_payments ALTER state SET DEFAULT "initiating"');}
    verifyHair('Migration leaves current schema unchanged',(new MayaMigration())->run($pdo,false)['pending_additions']===0);
    foreach($orders as $order){$pdo->prepare('DELETE FROM maya_webhook_inbox WHERE order_id=?')->execute([$order]);$pdo->prepare('DELETE FROM maya_payments WHERE order_id=?')->execute([$order]);$pdo->prepare('DELETE FROM orders WHERE id=?')->execute([$order]);}
    $pdo->prepare('DELETE FROM cart_items WHERE product_id=?')->execute([$product]);$pdo->prepare('DELETE FROM products WHERE id=? AND sku=?')->execute([$product,strtoupper($prefix)]);
    foreach($original as $table=>$before)verifyHair('Original rows preserved '.$table,mayaRows($pdo,$table)===$before);
    $result=['checks'=>$checks,'pass'=>count(array_filter($checks,fn($c)=>$c['status']==='PASS')),'fail'=>count(array_filter($checks,fn($c)=>$c['status']==='FAIL'))];
    if(isset($o['output']))file_put_contents($o['output'],json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));ob_end_flush();exit($result['fail']?1:0);
}catch(Throwable $e){fwrite(STDERR,'Maya tests stopped: '.get_class($e).' at line '.$e->getLine().'; private details suppressed.'.PHP_EOL);exit(1);}
