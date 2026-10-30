<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('AVELA_HAIR_TEST_LIBRARY',true);require __DIR__.'/hair_profiles.php';require_once dirname(__DIR__).'/vendor/autoload.php';
date_default_timezone_set((string)env('APP_TIMEZONE','Asia/Manila'));
function auditPhoto(array $client,string $image):array {
 global $base;$boundary='Avela'.bin2hex(random_bytes(16));$body='--'.$boundary."\r\nContent-Disposition: form-data; name=\"_token\"\r\n\r\n".$client['csrf']."\r\n--".$boundary."\r\nContent-Disposition: form-data; name=\"profile_image\"; filename=\"synthetic.webp\"\r\nContent-Type: image/webp\r\n\r\n".file_get_contents($image)."\r\n--".$boundary."--\r\n";
 $options=['method'=>'POST','content'=>$body,'ignore_errors'=>true,'follow_location'=>0,'timeout'=>15,'header'=>"Connection: close\r\nCookie: ".$client['cookie']."\r\nContent-Type: multipart/form-data; boundary=".$boundary."\r\n"];
 $response=file_get_contents($base.'/profile/image',false,stream_context_create(['http'=>$options]));preg_match('/ ([0-9]{3}) /',$http_response_header[0]??'',$m);return ['code'=>(int)($m[1]??0),'body'=>$response];
}
function auditFault(callable $operation):bool{
 global $pdo;$pdo->exec("CREATE TRIGGER audit_business_fault BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit fault'");
 try{$operation();return false;}catch(Throwable){return true;}finally{$pdo->exec('DROP TRIGGER audit_business_fault');}
}
try{
 $options=getopt('',['expect-database:','expect-port:','expect-datadir:','base-url:','session-directory:','scratch-directory:','output:']);
 $pdo=App\Core\Database::connection();$target=$pdo->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch();$base=$options['base-url']??'';$url=parse_url($base);$sessions=realpath($options['session-directory']??'');$scratch=realpath($options['scratch-directory']??'');$norm=static fn($p)=>strtolower(rtrim(str_replace('\\','/',$p),'/'));
 if(!preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D',$target['db'])||($options['expect-database']??'')!==$target['db']||env('DB_HOST')!=='127.0.0.1'||(int)($options['expect-port']??0)!==(int)$target['port']||in_array((int)$target['port'],[3306,13307],true)||$norm($options['expect-datadir']??'')!==$norm($target['datadir'])||env('APP_ENV')!=='development'||env('MAIL_DRIVER')!=='log'||!$sessions||!$scratch||str_starts_with($norm($scratch),$norm(dirname(__DIR__)).'/')||($url['host']??'')!=='127.0.0.1'||in_array((int)($url['port']??80),[80,18080],true))throw new RuntimeException('Disposable guard');
 ini_set('session.save_path',$sessions);$checks=[];$users=new App\Models\User();$cart=new App\Models\Cart();$favorites=new App\Models\Favorite();$logs=new App\Services\LogService();$otp=new App\Services\OtpService(new App\Services\MailService(),$logs);$reader=new App\Services\AdminLogReader();$auth=new App\Services\AuthService($users,$otp,$logs);
 $prefix='audit_'.bin2hex(random_bytes(4));$password='Ab1!'.bin2hex(random_bytes(18));$name=$prefix.'_customer';$id=$users->createCustomer(['username'=>$name,'email'=>$name.'@example.invalid','full_name'=>'Synthetic audit customer','contact_no'=>'+639123456789','password_hash'=>password_hash($password,PASSWORD_DEFAULT)]);$users->activate($id);
 $admin=$users->createStaff('admin',$prefix.'_admin@example.invalid',$prefix.'_admin',password_hash($password,PASSWORD_DEFAULT),'Synthetic audit administrator');$manager=$users->createStaff('catalog_manager',$prefix.'_staff@example.invalid',$prefix.'_staff',password_hash($password,PASSWORD_DEFAULT),'Synthetic audit staff');
 $qa=hairClient($admin);$qc=hairClient($id);$qm=hairClient($manager);
 $product=(int)$pdo->query('SELECT p.id FROM products p JOIN categories c ON c.id=p.category_id WHERE p.status="active" AND c.status="active" AND p.stock_qty>=3 ORDER BY p.id LIMIT 1')->fetchColumn();
 $cart->add($id,$product,1);$cart->update($id,$product,2);$cart->remove($id,$product);$favorites->toggle($id,$product);$favorites->toggle($id,$product);
 $q=$pdo->prepare('SELECT action FROM audit_logs WHERE actor_id=? ORDER BY id');$q->execute([$id]);$events=$q->fetchAll(PDO::FETCH_COLUMN);
 foreach(['cart.item_added','cart.quantity_updated','cart.item_removed','favorite.added','favorite.removed'] as $event)verifyHair('Actual business mutation audited / '.$event,in_array($event,$events,true));
 $before=$cart->contentsForUser($id);verifyHair('Cart audit fault rolls back new cart/item',auditFault(fn()=>$cart->add($id,$product,1))&&$cart->contentsForUser($id)===$before);
 $before=$favorites->idsForUser($id);verifyHair('Favorite audit fault rolls back selection',auditFault(fn()=>$favorites->toggle($id,$product))&&$favorites->idsForUser($id)===$before);
 $profile=$users->profileFor($id);verifyHair('Profile audit fault rolls back account/contact fields',auditFault(fn()=>$users->updateCustomerProfile($id,'Changed synthetic',$profile['email'],$profile['contact_no'],'Synthetic address'))&&$users->profileFor($id)===$profile);
 $flow=['user_id'=>$id,'current_email'=>$profile['email'],'email'=>$prefix.'_changed@example.invalid','before_full_name'=>$profile['full_name'],'before_contact_no'=>$profile['contact_no'],'full_name'=>'Verified synthetic change','contact_no'=>$profile['contact_no'],'delivery_address'=>'Synthetic address'];$otp->issue($id,$profile['email'],'profile_update');$code=null;
 foreach(array_reverse(file(dirname(__DIR__).'/storage/logs/dev_mail.log')) as $line)if(str_contains($line,'TO: '.$profile['email'].' | PURPOSE: profile_update')&&preg_match('/OTP: ([0-9]{6})/',$line,$m)){$code=$m[1];break;}
 $confirm=new App\Services\CustomerProfileService($otp);
 verifyHair('Profile audit fault also preserves single-use OTP for retry',auditFault(fn()=>$confirm->confirm($id,$flow,$code))&&$users->profileFor($id)===$profile);
 verifyHair('Atomic profile verification succeeds on retry',$confirm->confirm($id,$flow,$code)['ok']&&$users->profileFor($id)['full_name']===$flow['full_name']);
 $q=$pdo->prepare('SELECT details FROM audit_logs WHERE actor_id=? AND action="profile.updated" ORDER BY id DESC LIMIT 1');$q->execute([$id]);$details=$q->fetchColumn();verifyHair('Profile audit stores changed field names without personal values',!str_contains($details,$flow['full_name'])&&!str_contains($details,$flow['delivery_address'])&&!str_contains($details,$profile['email']));
 $image=dirname(__DIR__).'/public/assets/images/products/demo/avela-ave-smp-002.webp';$folder=dirname(__DIR__).'/public/uploads/profile';$before=glob($folder.'/*')?:[];
 $pdo->exec("CREATE TRIGGER audit_business_fault BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit fault'");
 try{$response=auditPhoto($qc,$image);}finally{$pdo->exec('DROP TRIGGER audit_business_fault');}
 verifyHair('Photo audit fault rolls back path and removes new upload',$response['code']===302&&$users->profileFor($id)['profile_image']===null&&(glob($folder.'/*')?:[])===$before);
 $response=auditPhoto($qc,$image);$oldPhoto=$users->profileFor($id)['profile_image'];verifyHair('Real multipart photo upload audited',$response['code']===302&&is_file(dirname(__DIR__).'/public/'.$oldPhoto));
 auditPhoto($qc,$image);$newPhoto=$users->profileFor($id)['profile_image'];verifyHair('Committed replacement cleans only old generated photograph',$newPhoto!==$oldPhoto&&!file_exists(dirname(__DIR__).'/public/'.$oldPhoto)&&is_file(dirname(__DIR__).'/public/'.$newPhoto));
 for($i=0;$i<25;$i++){session_name((string)env('SESSION_NAME'));session_id('');session_start();$auth->attempt($name,$password);$auth->logout();session_write_close();}
 foreach(['auth','audit'] as $kind){$path='/admin/'.$kind.'-logs';verifyHair('Actual admin '.$kind.' records load',hairRequest($path,$qa)['code']===200&&str_contains(hairRequest($path.'?q='.$prefix,$qa)['body'],$prefix));verifyHair('Customer denied '.$kind.' viewer',hairRequest($path,$qc)['code']===403);verifyHair('Catalog Manager denied '.$kind.' viewer',hairRequest($path,$qm)['code']===403);verifyHair('Guest denied '.$kind.' viewer',hairRequest($path)['code']===302);}
 $expectedAuth=(int)$pdo->query('SELECT COUNT(*) FROM auth_logs WHERE user_id='.$id)->fetchColumn();
 $first=$reader->read('auth',['q'=>$name]);$second=$reader->read('auth',['q'=>$name,'page'=>2]);
 verifyHair('Search count and real pagination agree',$expectedAuth>=50&&$first['total']===$expectedAuth&&count($first['records'])===20&&count($second['records'])===20);
 verifyHair('No duplicate rows across stable pages',!array_intersect(array_column($first['records'],'id'),array_column($second['records'],'id')));
 verifyHair('Excessive page clamps before bounded offset',$reader->read('auth',['q'=>$name,'page'=>'999999999999999999999999'])['page']===1&&$reader->read('auth',['q'=>$name,'page'=>999999])['page']===3);
 verifyHair('Event and result filters are database backed',$reader->read('auth',['q'=>$name,'event'=>'login','result'=>'success'])['total']===25&&$reader->read('auth',['q'=>$name,'result'=>'failure'])['total']===0);
 verifyHair('Literal wildcard and SQL-like search do not expand selection',$reader->read('auth',['q'=>'%'])['total']===0&&$reader->read('auth',['q'=>"' OR 1=1 --"])['total']===0);
 verifyHair('Date range applies consistently',$reader->read('auth',['q'=>$name,'from'=>date('Y-m-d'),'to'=>date('Y-m-d')])['total']===$expectedAuth);
 $oldest=$reader->read('auth',['q'=>$name,'sort'=>'oldest']);verifyHair('Oldest sort reverses ordering',$oldest['records'][0]['id']<$first['records'][0]['id']);
 foreach(['sort=invalid','from=2026-02-31','q[]=bad','from=2026-10-17&to=2026-10-01'] as $query)verifyHair('Malformed log filter returns clear 422',hairRequest('/admin/auth-logs?'.$query,$qa)['code']===422);
 $xss=hairRequest('/admin/auth-logs?q='.rawurlencode('<img src=x onerror=alert(1)>'),$qa);verifyHair('Reflected search safely encoded',str_contains($xss['body'],'&lt;img')&&!str_contains($xss['body'],'value="<img'));
 App\Core\Database::transaction(fn(PDO $pdo)=>App\Services\AuditService::record($pdo,$admin,'test.output','test',$id,['fixture'=>'<script>alert(1)</script>']));
 $stored=hairRequest('/admin/audit-logs?q=test.output',$qa);verifyHair('Stored log text safely encoded',str_contains($stored['body'],'&lt;script&gt;')&&!str_contains($stored['body'],'<script>alert(1)</script>'));
 $pdo->prepare('UPDATE users SET locked_until="2000-01-01",lock_requires_admin=1 WHERE id=?')->execute([$id]);$denied=false;try{$cart->add($id,$product,1);}catch(InvalidArgumentException){$denied=true;}verifyHair('Cart transaction respects persisted lock after review time',$denied);
 $denied=false;try{(new App\Services\CheckoutService())->quote($id);}catch(InvalidArgumentException){$denied=true;}verifyHair('Checkout service respects persisted lock after review time',$denied);
 $pdo->prepare('UPDATE users SET locked_until=NULL,lock_requires_admin=0 WHERE id=?')->execute([$id]);
 $pdo->prepare('UPDATE users SET locked_until="2000-01-01",lock_requires_admin=1 WHERE id=?')->execute([$manager]);
 $server=$pdo->query('SELECT @@hostname hostname,@@server_id server_id')->fetch();$confirm=['host'=>'127.0.0.1','port'=>(int)$target['port'],'database'=>$target['db'],'server'=>$server['hostname'],'server_id'=>(int)$server['server_id'],'datadir'=>$target['datadir']];
 $imageFixture=App\Services\DemoImageAssigner::readManifest(dirname(__DIR__).'/database/demo_images.json');
 $imageFixture['images']=array_values(array_filter($imageFixture['images'],static fn($image)=>$image['sku']==='AVE-TOL-001'));
 if(count($imageFixture['images'])!==1)throw new RuntimeException('Reviewed fresh-schema image fixture missing.');
 $denied=false;try{(new App\Services\DemoImageAssigner($pdo,dirname(__DIR__).'/public'))->run($imageFixture,false,$prefix.'_staff',$confirm);}catch(DomainException $e){$denied=str_contains($e->getMessage(),'unlocked');}verifyHair('Guarded image assignment rejects persisted locked staff',$denied);
 $denied=false;try{(new App\Services\DemoCatalogImporter($pdo))->run(App\Services\DemoCatalogImporter::readManifest(dirname(__DIR__).'/database/demo_catalog.json'),true,$prefix.'_staff',$confirm);}catch(DomainException $e){$denied=str_contains($e->getMessage(),'unlocked');}verifyHair('Guarded catalog import rejects persisted locked staff',$denied);
 $pdo->prepare('UPDATE users SET locked_until=NULL,lock_requires_admin=0 WHERE id=?')->execute([$manager]);
 $pdo->exec("CREATE TRIGGER auth_history_fault BEFORE INSERT ON auth_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic history fault'");
 session_id('');session_start();$denied=false;try{$auth->attempt($name,$password);}catch(RuntimeException){$denied=true;}verifyHair('Authentication-history fault prevents silent successful login',$denied&&App\Core\Session::get('auth_user')===null);$auth->logout();verifyHair('Logout remains available despite history failure',App\Core\Session::get('auth_user')===null);session_write_close();$pdo->exec('DROP TRIGGER auth_history_fault');
 $result=['checks'=>$checks];if(isset($options['output']))file_put_contents($options['output'],json_encode($result,JSON_PRETTY_PRINT));ob_end_flush();exit(count(array_filter($checks,static fn($c)=>$c['status']==='FAIL'))?1:0);
}catch(Throwable $e){fwrite(STDERR,'Audit tests stopped: '.get_class($e).' / '.basename($e->getFile()).':'.$e->getLine()."; private values suppressed.\n");exit(1);}
