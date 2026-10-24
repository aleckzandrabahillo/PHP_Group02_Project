<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('AVELA_HAIR_TEST_LIBRARY',true);require __DIR__.'/hair_profiles.php';require dirname(__DIR__).'/vendor/autoload.php';
try{
 $o=getopt('',['expect-database:','expect-port:','expect-datadir:','base-url:','session-directory:','scratch-directory:','output:']);
 $pdo=App\Core\Database::connection();$id=$pdo->query('SELECT DATABASE() db,@@port port,@@datadir dir')->fetch();$base=$o['base-url']??'';$url=parse_url($base);$sessions=realpath($o['session-directory']??'');
 $norm=static fn($p)=>strtolower(rtrim(str_replace('\\','/',$p),'/'));
 if(!preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D',$id['db'])||($o['expect-database']??'')!==$id['db']||(int)($o['expect-port']??0)!==(int)$id['port']||in_array((int)$id['port'],[3306,13307],true)||$norm($o['expect-datadir']??'')!==$norm($id['dir'])||env('DB_HOST')!=='127.0.0.1'||env('APP_ENV')!=='development'||!$sessions||str_starts_with($norm($sessions),$norm(dirname(__DIR__)).'/')||($url['host']??'')!=='127.0.0.1'||in_array((int)($url['port']??80),[80,18080],true))throw new RuntimeException('Disposable-only guard');
 ini_set('session.save_path',$sessions);$checks=[];$users=new App\Models\User();$name='delivery_'.bin2hex(random_bytes(5));
 $uid=$users->createCustomer(['username'=>$name,'email'=>$name.'@example.invalid','full_name'=>'Synthetic Customer','contact_no'=>'+639123456789','password_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);$users->activate($uid);$client=hairClient($uid);
 $manager=$users->findByIdentifier('image_catalog_manager');$catalog=hairClient((int)$manager['id']);
 verifyHair('Guest cannot quote shipping',hairRequest('/checkout/shipping',null,[])['code']===302);
 verifyHair('Catalog Manager cannot quote customer shipping',hairRequest('/checkout/shipping',$catalog,['_token'=>$catalog['csrf'],'postal_code'=>'1600'])['code']===403);
 verifyHair('Shipping quote enforces CSRF',hairRequest('/checkout/shipping',$client,['_token'=>'bad','postal_code'=>'1600'])['code']===419);
 foreach(['1600'=>8000,'8000'=>20000] as $code=>$cents){$r=hairRequest('/checkout/shipping',$client,['_token'=>$client['csrf'],'postal_code'=>(string)$code,'city'=>(string)$code==='1600'?'Pasig':'Davao City','region'=>(string)$code==='1600'?'NCR':'R11']);$json=json_decode($r['body'],true);verifyHair('Actual shipping quote '.$code,$r['code']===200&&$json['options']['standard']['cents']===$cents&&$json['options']['priority']['cents']===$cents+6000);}
 foreach(['0000','12345',['bad']] as $postal)verifyHair('Shipping rejects malformed postal code',hairRequest('/checkout/shipping',$client,['_token'=>$client['csrf'],'postal_code'=>$postal])['code']===422);
 $profile=$users->profileFor($uid);$p=$pdo->query('SELECT * FROM products WHERE status="active" AND stock_qty>5 ORDER BY id LIMIT 1')->fetch();$cart=new App\Models\Cart();$cart->add($uid,(int)$p['id'],1);
 try{
  $page=hairRequest('/checkout',$client);$html=$page['body'];preg_match('/name="checkout_key" value="([a-f0-9]{64})"/',$html,$m);
  verifyHair('New checkout renders without account email textbox',$page['code']===200&&str_contains($html,e($profile['email']))&&!str_contains($html,'type="email"'));
  verifyHair('Checkout contact menu uses POST sign-out',str_contains($html,'checkout-account-menu')&&str_contains($html,'action="/logout"')&&str_contains($html,'Sign out'));
  foreach(['country','first_name','last_name','address_line','barangay','postal_code','city','region','contact_no'] as $field)verifyHair('Editable delivery field '.$field,str_contains($html,'name="'.$field.'"'));
  verifyHair('Missing address fields stay blank',str_contains($html,'name="address_line" value=""')&&str_contains($html,'name="postal_code" value=""'));
  verifyHair('Account name and phone prefilled',str_contains($html,'name="first_name" value="Synthetic"')&&str_contains($html,'name="last_name" value="Customer"')&&str_contains($html,'value="+639123456789"'));
  verifyHair('Country preselected and complete single area list',str_contains($html,'value="PH" selected')&&str_contains($html,'value="Abra"')&&str_contains($html,'value="Zamboanga del Sur"')&&str_contains($html,'value="Siquijor"')&&!str_contains($html,'name="province"'));
  verifyHair('Place order follows totals; policies stay in the left delivery panel',strpos($html,'>Place order</button>')>strpos($html,'<aside class="checkout-summary"')&&str_contains($html,'form="checkout-form"')&&strpos($html,'aria-label="Checkout policies"')<strpos($html,'<aside class="checkout-summary"')&&str_contains($html,'/refund-policy')&&str_contains($html,'/privacy')&&str_contains($html,'/terms'));
  foreach(['Include house/building','Phone numbers are not verified','Do not enter card or bank details','Stock and prices are checked again','student demonstration','demonstration)'] as $copy)verifyHair('Checkout requested copy removed '.$copy,!str_contains($html,$copy));
  $input=['_token'=>$client['csrf'],'checkout_key'=>$m[1]??'','country'=>'PH','first_name'=>'Gift','last_name'=>'Recipient','address_line'=>'12 Test Street','barangay'=>'Test Barangay','postal_code'=>'1600','city'=>'Pasig','region'=>'NCR','province'=>'Metro Manila','shipping_method'=>'priority','contact_no'=>'09123456789','payment_method'=>'cod'];
  $r=hairRequest('/checkout',$client,[...$input,'shipping_fee'=>'0.00','total'=>'0.01']);$q=$pdo->prepare('SELECT id FROM orders WHERE user_id=? AND checkout_key=?');$q->execute([$uid,$input['checkout_key']]);$orderId=(int)$q->fetchColumn();$order=$orderId?(new App\Models\Order())->owned($orderId,$uid):null;
  verifyHair('Structured priority order succeeds',$r['code']===303&&$order!==null);
  verifyHair('Fee and total recomputed on server',$order&&$order['delivery']['shipping_fee']==='140.00'&&$order['total']===number_format((float)$order['subtotal']+140,2,'.',''));
  verifyHair('Other recipient and delivery fields snapshotted',$order&&$order['recipient_name_snapshot']==='Gift Recipient'&&$order['delivery']['postal_code']==='1600'&&$order['delivery']['barangay']==='Test Barangay');
  verifyHair('Checkout does not overwrite the saved profile',$profile===$users->profileFor($uid));
  $repeat=hairRequest('/checkout',$client,$input);$q->execute([$uid,$input['checkout_key']]);verifyHair('Duplicate structured submission returns same order',$repeat['code']===303&&(int)$q->fetchColumn()===$orderId);
  $detail=hairRequest('/orders/view?id='.$orderId,$client);verifyHair('Order history shows actual shipping fee',$detail['code']===200&&str_contains($detail['body'],'140.00')&&str_contains($detail['body'],'Priority'));
 }finally{$pdo->prepare('UPDATE products SET stock_qty=?,updated_at=? WHERE id=?')->execute([$p['stock_qty'],$p['updated_at'],$p['id']]);}
 foreach(['/refund-policy','/terms','/privacy'] as $path){$page=hairRequest($path);verifyHair('Policy route available with review notice '.$path,$page['code']===200&&str_contains($page['body'],'Policy for review'));}
 $sessionId=substr($client['cookie'],strpos($client['cookie'],'=')+1);
 $writeAge=static function(int $age)use($sessionId):void{session_id($sessionId);session_start();$_SESSION['last_activity']=time()-$age;session_write_close();};
 $readAge=static function()use($sessionId):?int{session_id($sessionId);session_start();$last=$_SESSION['last_activity']??null;session_write_close();return $last;};
 $writeAge(45);$last=$readAge();$status=hairRequest('/account/session',$client);verifyHair('Passive status returns expiry without extending activity',$status['code']===200&&$readAge()===$last&&isset(json_decode($status['body'],true)['expires_at']));
 $bad=hairRequest('/account/session/activity',$client,['_token'=>'bad']);verifyHair('Session activity requires CSRF and does not extend on failure',$bad['code']===419&&$readAge()===$last);
 $touch=hairRequest('/account/session/activity',$client,['_token'=>$client['csrf']]);verifyHair('Authenticated interaction extends session after CSRF',$touch['code']===200&&$readAge()>$last);
 $writeAge(3600);$expired=hairRequest('/account/session',$client);verifyHair('Expired session returns machine-readable login redirect',$expired['code']===401&&json_decode($expired['body'],true)['redirect']==='/login');
 verifyHair('Expired protected page access remains denied',hairRequest('/profile',$client)['code']===302);
 $q=$pdo->prepare('SELECT setting_value FROM security_settings WHERE setting_key="max_login_attempts"');$q->execute();$before=$q->fetchColumn();
 try{$pdo->exec('UPDATE security_settings SET setting_value="1" WHERE setting_key="max_login_attempts"');verifyHair('Explicit Administrator threshold is honored',App\Services\SecurityPolicy::values()['max_login_attempts']===1);}finally{$pdo->prepare('UPDATE security_settings SET setting_value=? WHERE setting_key="max_login_attempts"')->execute([$before]);}
 $registration=hairRequest('/register');verifyHair('Registration byte jargon removed',!str_contains($registration['body'],'72 UTF-8 bytes')&&!str_contains($registration['body'],'data-password-rule="bytes"'));
 $home=hairRequest('/');verifyHair('Public footer has no student demo statement',!str_contains($home['body'],'student demonstration')&&!str_contains($home['body'],'Student demonstration'));
 $fail=count(array_filter($checks,static fn($c)=>$c['status']==='FAIL'));if(isset($o['output']))file_put_contents($o['output'],json_encode($checks,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo (count($checks)-$fail).' PASS, '.$fail.' FAIL',PHP_EOL;exit($fail?1:0);
}catch(Throwable $e){fwrite(STDERR,'Refinement test stopped: '.get_class($e).' at line '.$e->getLine().PHP_EOL);exit(1);}
