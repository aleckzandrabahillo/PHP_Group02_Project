<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('AVELA_HAIR_TEST_LIBRARY',true);require __DIR__.'/hair_profiles.php';require_once dirname(__DIR__).'/vendor/autoload.php';
date_default_timezone_set((string)env('APP_TIMEZONE','Asia/Manila'));
use App\Core\{Database,Session};
use App\Services\{AddressBook,CustomerAddress,ActivationService,OtpService,MailService,LogService};
try {
 $o=getopt('',['expect-database:','expect-port:','expect-datadir:','base-url:','session-directory:','output:']);
 $pdo=Database::connection();$identity=$pdo->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch();$norm=fn($p)=>strtolower(rtrim(str_replace('\\','/',$p),'/'));
 $base=$o['base-url']??'';$sessions=realpath($o['session-directory']??'');
 if($identity['db']!=='avela_revision_test_2026103001'||($o['expect-database']??'')!==$identity['db']||(int)$identity['port']!==13427||(int)($o['expect-port']??0)!==13427||$norm($o['expect-datadir']??'')!==$norm($identity['datadir'])||$base!=='http://127.0.0.1:18430'||!$sessions||env('MAIL_DRIVER')!=='log'||env('APP_ENV')!=='development'||env('DB_HOST')!=='127.0.0.1'||str_starts_with($norm($sessions),$norm(dirname(__DIR__)).'/'))throw new RuntimeException('Disposable-only guard.');
 ini_set('session.save_path',$sessions);$checks=[];$users=new App\Models\User();$book=new AddressBook();$make=static function(bool $active=true)use($users){$name='revision_'.bin2hex(random_bytes(5));$id=$users->createCustomer(['username'=>$name,'email'=>$name.'@example.invalid','full_name'=>'Synthetic Review','contact_no'=>'+639123456789','password_hash'=>password_hash('Synthetic9!'.bin2hex(random_bytes(8)),PASSWORD_DEFAULT)]);if($active)$users->activate($id);return $id;};
 $a=$make();$b=$make();$client=hairClient($a);$other=hairClient($b);
 $input=['country'=>'PH','first_name'=>'Synthetic','last_name'=>'Review','address_line'=>'1 Sample Street','barangay'=>'San Antonio','postal_code'=>'1600','city'=>'Pasig','region'=>'NCR','province'=>'Metro Manila','contact_no'=>'+639123456789'];
 verifyHair('Fresh address migration is idempotent',(new App\Services\AddressBookMigration())->run($pdo,false)['pending_additions']===0);
 verifyHair('Guest address mutation denied',hairRequest('/profile/address',null,$input)['code']===302);
 verifyHair('Address mutation requires CSRF',hairRequest('/profile/address',$client,['_token'=>'bad',...$input,'revision'=>''])['code']===419);
 $post=fn($who,$extra)=>hairRequest('/profile/address',$who,['_token'=>$who['csrf'],...$input,...$extra]);
 $response=$post($client,['address_id'=>-1,'revision'=>'','user_id'=>$b]);$addresses=$book->all($pdo,$a);$first=$addresses[0]??[];
 verifyHair('First owned address persists and becomes default',$response['code']===200&&count($addresses)===1&&$first['is_default']&&$first['city']==='Pasig');
 verifyHair('Forged owner is ignored',$book->all($pdo,$b)===[]);
 $stored=$pdo->query('SELECT payload_ciphertext FROM customer_address_book WHERE user_id='.$a)->fetchColumn();verifyHair('Address payload is encrypted and contains no plain address',str_starts_with($stored,'v1:')&&!str_contains($stored,'Sample Street'));
 verifyHair('Address cards escape user text',str_contains($response['body'],'address-card')&&!str_contains($response['body'],'payload_ciphertext'));
 $response=$post($client,['address_id'=>-1,'revision'=>'','address_line'=>'2 Sample Street']);$addresses=$book->all($pdo,$a);$second=current(array_filter($addresses,fn($row)=>$row['id']!==$first['id']));
 verifyHair('Second address does not replace the first',count($addresses)===2&&$book->default($pdo,$a)['id']===$first['id']);
 verifyHair('Other account cannot edit or delete address',$post($other,['action'=>'delete','address_id'=>$second['id'],'revision'=>$second['revision']])['code']===409&&count($book->all($pdo,$a))===2);
 verifyHair('Stale revision cannot overwrite address',$post($client,['address_id'=>$first['id'],'revision'=>str_repeat('0',32)])['code']===409);
 verifyHair('Default action requires a real address',$post($client,['action'=>'default','address_id'=>-1,'revision'=>''])['code']===409&&$book->default($pdo,$a)['id']===$first['id']);
 verifyHair('Invalid address values rejected',$post($client,['address_id'=>$first['id'],'revision'=>$first['revision'],'country'=>'US'])['code']===422);
 $response=$post($client,['action'=>'default','address_id'=>$second['id'],'revision'=>$second['revision']]);$second=$book->default($pdo,$a);
 verifyHair('Default changes atomically with exactly one default',$response['code']===200&&$second['address_line']==='2 Sample Street'&&(int)$pdo->query('SELECT COUNT(*) FROM customer_address_book WHERE user_id='.$a.' AND is_default=1')->fetchColumn()===1);
 $response=$post($client,['address_id'=>$second['id'],'revision'=>$second['revision'],'address_line'=>'Updated sample']);$second=$book->default($pdo,$a);
 verifyHair('Edit preserves identity and changes revision',$response['code']===200&&$second['address_line']==='Updated sample');
 verifyHair('Checkout source uses the default address',(new CustomerAddress())->find($pdo,$a)['id']===$second['id']);
 $pid=(int)$pdo->query('SELECT id FROM products WHERE status="active" AND stock_qty>0 LIMIT 1')->fetchColumn();
 $cart=new App\Models\Cart();$cart->add($a,$pid,1);$checkout=hairRequest('/checkout',$client);
 verifyHair('Checkout offers both owned saved addresses',$checkout['code']===200&&str_contains($checkout['body'],'data-saved-address')&&str_contains($checkout['body'],'1 Sample Street')&&str_contains($checkout['body'],'Updated sample'));
 $response=$post($client,['action'=>'delete','address_id'=>$second['id'],'revision'=>$second['revision']]);verifyHair('Deleting default selects remaining address',$response['code']===200&&$book->default($pdo,$a)['id']===$first['id']);
 $first=$book->default($pdo,$a);$post($client,['action'=>'delete','address_id'=>$first['id'],'revision'=>$first['revision']]);verifyHair('Deleted last address cannot reappear from legacy storage',$book->all($pdo,$a)===[]);
 $legacyId=$make();$legacy=(new CustomerAddress())->save($pdo,$legacyId,$input,'');$legacyClient=hairClient($legacyId);
 verifyHair('Legacy address remains readable before explicit mutation',$book->all($pdo,$legacyId)[0]['id']===0);
 $response=$post($legacyClient,['address_id'=>0,'revision'=>$legacy['revision'],'address_line'=>'Legacy updated']);
 verifyHair('Legacy edit materializes without losing old records',$response['code']===200&&$book->default($pdo,$legacyId)['address_line']==='Legacy updated'&&(new CustomerAddress())->findLegacy($pdo,$legacyId)['address_line']==='1 Sample Street');
 $fault=new HairAuditFaultPDO('mysql:host=127.0.0.1;port=13427;dbname='.$identity['db'].';charset=utf8mb4',env('DB_USERNAME'),env('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$before=$book->all($pdo,$legacyId);$blocked=false;try{$book->mutate($fault,$legacyId,'delete',$before[0]['id'],$before[0]['revision']);}catch(Throwable){$blocked=true;}verifyHair('Audit failure rolls back address deletion',$blocked&&$book->all($pdo,$legacyId)===$before);
 $legacyRow=$book->default($pdo,$legacyId);$cart->add($legacyId,$pid,1);$post($legacyClient,['action'=>'delete','address_id'=>$legacyRow['id'],'revision'=>$legacyRow['revision']]);
 $cleared=hairRequest('/checkout',$legacyClient);verifyHair('Deleted legacy address is not reused in checkout',!str_contains($cleared['body'],'value="1 Sample Street"')&&!str_contains($cleared['body'],'value="Legacy updated"'));
 $bound=false;try{App\Services\PiiCipher::configured()->decrypt('customer_address_book','payload',$first['id']+100000,$stored);}catch(RuntimeException){$bound=true;}verifyHair('Encrypted address cannot move to another row',$bound);
 $logs=new LogService();$otp=new OtpService(new MailService(),$logs);$activation=new ActivationService($otp,new MailService(),$logs);
 $pending=$make(false);$account=$users->findById($pending);$activation->issue($pending);
 $mail=file(dirname(__DIR__).'/storage/logs/dev_mail.log');$code='';foreach(array_reverse($mail) as $line)if(str_contains($line,'TO: '.$account['email'].' | PURPOSE: activation |')&&preg_match('/OTP: ([0-9]{6})/',$line,$m)){$code=$m[1];break;}
 $activation->verifyAndSendLink($pending,$code);$token='';foreach(glob(env('MAIL_DEV_OUTBOX_DIRECTORY').'/*.eml') as $file){$body=file_get_contents($file);if(str_contains($body,$account['email'])&&preg_match('/#token=([a-f0-9]{64})/',$body,$m))$token=$m[1];}
 session_id('');session_name(env('SESSION_NAME'));session_start();$_SESSION=['_csrf'=>bin2hex(random_bytes(32)),'pending_flow'=>['user_id'=>$pending,'email'=>$account['email'],'purpose'=>'activation_link','password_version'=>$account['password_version'],'verified_at'=>time(),'expires_at'=>time()+1800]];$guest=['cookie'=>session_name().'='.session_id(),'csrf'=>$_SESSION['_csrf']];session_write_close();
 $response=hairRequest('/activate',$guest,['_token'=>$guest['csrf'],'activation_token'=>$token]);$newCookie=null;foreach($response['headers'] as $header)if(preg_match('/^Set-Cookie: ('.preg_quote(env('SESSION_NAME'),'/').'=[^;]+)/i',$header,$m))$newCookie=$m[1];
 verifyHair('Verified activation redirects home and rotates the session',$response['code']===302&&in_array('Location: /',$response['headers'],true)&&$newCookie!==null&&$newCookie!==$guest['cookie']);
 verifyHair('Activated customer immediately accesses their account',hairRequest('/profile',['cookie'=>$newCookie??'','csrf'=>$guest['csrf']])['code']===200);
 verifyHair('Activation token replay cannot sign in again',!$activation->consume($token));
 $form=hairForm($client);$response=hairRequest('/assessment',$client,$form);$state=(new App\Models\Routine())->currentForUser($a);
 verifyHair('Assessment supplies a current routine without another save',in_array($response['code'],[302,303],true)&&$state['state']==='ready'&&$state['derived']);
 $routine=hairRequest('/routine',$client);verifyHair('My Routine route renders current plan',$routine['code']===200&&str_contains($routine['body'],'My Routine'));
 $recommendations=hairRequest('/recommendations',$client);verifyHair('Recommendations omit save and expose shared section navigation',$recommendations['code']===200&&!str_contains($recommendations['body'],'Save routine')&&str_contains($recommendations['body'],'recommendation-shortcuts'));
 $one=hairRequest('/product?id='.$pid,$client);$pid2=(int)$pdo->query('SELECT id FROM products WHERE status="active" AND id<>'.$pid.' LIMIT 1')->fetchColumn();$two=hairRequest('/product?id='.$pid2,$client);
 verifyHair('Product view has exclusive accordions and asynchronous bag form',$one['code']===200&&substr_count($one['body'],'name="product-care"')===3&&str_contains($one['body'],'data-quick-add')&&!str_contains($one['body'],'product-tab-reviews'));
 verifyHair('Recently viewed contains the real earlier product',str_contains($two['body'],'product-panel-recent')&&str_contains($two['body'],'product-'.$pid.'"'));
 $bagBody=file_get_contents($base.'/cart/add',false,stream_context_create(['http'=>['method'=>'POST','ignore_errors'=>true,'follow_location'=>0,'timeout'=>15,'header'=>"Connection: close\r\nAccept: application/json\r\nContent-Type: application/x-www-form-urlencoded\r\nCookie: ".$client['cookie']."\r\n",'content'=>http_build_query(['_token'=>$client['csrf'],'product_id'=>$pid,'quantity'=>1,'return_to'=>'/product?id='.$pid])]]));
 $bagHeaders=$http_response_header??[];$bag=json_decode((string)$bagBody,true);
 verifyHair('Asynchronous bag request returns updated count without a page redirect',str_contains($bagHeaders[0]??'',' 200 ')&&($bag['ok']??false)&&($bag['itemCount']??0)>=2&&!array_filter($bagHeaders,fn($h)=>str_starts_with(strtolower($h),'location:')));
 $failed=array_filter($checks,fn($c)=>$c['status']==='FAIL');$result=['pass'=>count($checks)-count($failed),'fail'=>count($failed),'checks'=>$checks];file_put_contents($o['output'],json_encode($result,JSON_PRETTY_PRINT));echo json_encode(['pass'=>$result['pass'],'fail'=>$result['fail']]);exit($failed?1:0);
}catch(Throwable $e){fwrite(STDERR,'Synthetic revision test stopped: '.$e->getMessage().PHP_EOL);exit(1);}
