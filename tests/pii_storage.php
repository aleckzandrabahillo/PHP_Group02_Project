<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/scripts/assign_demo_images.php';
use App\Core\Database;
use App\Models\User;
use App\Services\PiiMigration;
use App\Services\PiiStorage;
$checks=[];$check=static function(string $name,bool $ok)use(&$checks):void{$checks[]=['test'=>$name,'status'=>$ok?'PASS':'FAIL'];};
$denied=static function(callable $call):bool{try{$call();return false;}catch(Throwable){return true;}};
try{
 $args=getopt('',['expect-database:','expect-port:','expect-datadir:','output:']);$pdo=Database::connection();$id=$pdo->query('SELECT DATABASE() db,@@port port,@@datadir dir')->fetch();
 $norm=static fn($p)=>strtolower(rtrim(str_replace('\\','/',$p),'/'));
 if(!preg_match('/^avela_pii_test_[0-9]+$/D',$id['db'])||($args['expect-database']??'')!==$id['db']||(int)($args['expect-port']??0)!==(int)$id['port']||in_array((int)$id['port'],[3306,13307],true)||$norm($args['expect-datadir']??'')!==$norm($id['dir'])||env('DB_HOST')!=='127.0.0.1'||env('APP_ENV')!=='development'||env('MAIL_DRIVER')!=='log'||!PiiStorage::enabled())throw new RuntimeException('Disposable encryption guard');
 $migration=new PiiMigration();$before=$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();$plan=$migration->run($pdo,false);
 $check('Dry-run writes no rows',$plan['rows_updated']===0&&$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn()===$before);
 $migration->run($pdo,true);$check('Additive migration repeat needs zero additions',$migration->run($pdo,true)['pending_additions']===0);
 $users=new User();$prefix='pii_'.bin2hex(random_bytes(5));$admin=$users->createStaff('admin',$prefix.'a@example.invalid',$prefix.'a',password_hash('LongerPassword1!',PASSWORD_DEFAULT),'Encryption administrator');
 $user=$users->createCustomer(['username'=>$prefix,'email'=>$prefix.'@example.invalid','full_name'=>'Encryption fixture','contact_no'=>'+639123456789','password_hash'=>password_hash('LongerPassword1!',PASSWORD_DEFAULT)]);$users->activate($user);
 $raw=$pdo->query('SELECT * FROM customer_profiles WHERE user_id='.$user)->fetch();
 $check('Registration stores ciphertext instead of selected plaintext',$raw['contact_no']===''&&$raw['delivery_address']===null&&(int)$raw['pii_encrypted']===1&&$raw['contact_no_encrypted']!==null);
 $profile=$users->profileFor($user);$check('Profile reads selected encrypted fields',$profile['contact_no']==='+639123456789'&&$profile['delivery_address']===null&&!isset($profile['contact_no_encrypted']));
 $users->updateCustomerProfile($user,'Encryption fixture',$prefix.'@example.invalid','+639123456780','A synthetic delivery address');
 $profile=$users->profileFor($user);$check('Profile writes decrypt exactly',$profile['contact_no']==='+639123456780'&&$profile['delivery_address']==='A synthetic delivery address');
 $address=['country'=>'PH','first_name'=>'Fixture','last_name'=>'Customer','address_line'=>'1 Synthetic Street','barangay'=>'Test Barangay','postal_code'=>'1600','city'=>'Pasig','region'=>'NCR','province'=>'Metro Manila','contact_no'=>'+639123456780'];
 $saved=(new App\Services\CustomerAddress())->save($pdo,$user,$address,'');
 $raw=$pdo->query('SELECT * FROM customer_profiles WHERE user_id='.$user)->fetch();
 $check('Address save preserves encrypted phone and clears selected plaintext',$raw['delivery_address']===null&&$users->profileFor($user)['delivery_address']===$saved['delivery_address']&&$users->profileFor($user)['contact_no']==='+639123456780');
 $pdo->prepare('INSERT INTO categories(name,status) VALUES (?,"active")')->execute([$prefix]);$category=(int)$pdo->lastInsertId();
 $pdo->prepare('INSERT INTO products(category_id,sku,name,price,stock_qty,status) VALUES (?,?,?,100.00,10,"active")')->execute([$category,strtoupper($prefix),$prefix]);$product=(int)$pdo->lastInsertId();
 (new App\Models\Cart())->add($user,$product,1);$checkout=new App\Services\CheckoutService();$quote=$checkout->quote($user);
 $order=$checkout->place($user,$address+['full_name'=>'Fixture Customer','delivery_address'=>$saved['delivery_address'],'payment_method'=>'cod','shipping_method'=>'standard'],bin2hex(random_bytes(32)),$quote['signature']);
 $rawOrder=$pdo->query('SELECT * FROM orders WHERE id='.$order)->fetch();$model=new App\Models\Order();$owned=$model->owned($order,$user);
 $check('Checkout snapshot fields encrypted atomically',$rawOrder['contact_no_snapshot']===null&&$rawOrder['delivery_address_snapshot']===''&&(int)$rawOrder['pii_encrypted']===1);
 $check('Customer and staff order details recover snapshots',$owned['contact_no_snapshot']==='+639123456780'&&$owned['delivery_address_snapshot']===$saved['delivery_address']&&$model->forAdministrator($order)['delivery_address_snapshot']===$owned['delivery_address_snapshot']);
 $check('Order ownership remains enforced',$model->owned($order,$admin)===null);
 $check('Stock and order transaction preserved',(int)$pdo->query('SELECT stock_qty FROM products WHERE id='.$product)->fetchColumn()===9);
 $original=$pdo->query('SELECT contact_no_encrypted FROM customer_profiles WHERE user_id='.$user)->fetchColumn();
 $pdo->prepare('UPDATE customer_profiles SET contact_no_encrypted=? WHERE user_id=?')->execute(['v1:broken:AAAA',$user]);
 $check('Tampered profile never falls back to plaintext',$denied(fn()=>$users->profileFor($user)));
 $pdo->prepare('UPDATE customer_profiles SET contact_no_encrypted=? WHERE user_id=?')->execute([$original,$user]);
 $before=$pdo->query('SELECT * FROM customer_profiles WHERE user_id='.$user)->fetch();
 $pdo->exec("CREATE TRIGGER pii_test_audit_failure BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit failure'");
 try{$failed=$denied(fn()=>$users->updateCustomerProfile($user,'Changed name',$prefix.'@example.invalid','+639123456781','Changed address'));}finally{$pdo->exec('DROP TRIGGER pii_test_audit_failure');}
 $check('Audit failure rolls back plaintext and ciphertext',$failed&&$pdo->query('SELECT * FROM customer_profiles WHERE user_id='.$user)->fetch()===$before);
 $keyring=str_replace('\\','/',(string)env('PII_KEYRING_FILE'));$envFile=tempnam(sys_get_temp_dir(),'pii-test-');
 try{
  file_put_contents($envFile,"PII_KEYRING_FILE=missing\n");App\Core\Env::load($envFile);
  $check('Missing key blocks protected reads',$denied(fn()=>$users->profileFor($user)));
  file_put_contents($envFile,'PII_KEYRING_FILE="'.$keyring.'"'."\nPII_ENCRYPTION_ENABLED=false\n");App\Core\Env::load($envFile);
  $check('Disabled encryption cannot write plaintext over a protected row',$denied(fn()=>$users->updateCustomerProfile($user,'Changed name',$prefix.'@example.invalid','+639123456781','Changed address'))&&$pdo->query('SELECT * FROM customer_profiles WHERE user_id='.$user)->fetch()===$before);
  $legacy=$users->createCustomer(['username'=>$prefix.'_legacy','email'=>$prefix.'_legacy@example.invalid','full_name'=>'Legacy encryption fixture','contact_no'=>'+639123456789','password_hash'=>password_hash('LongerPassword1!',PASSWORD_DEFAULT)]);
  $pdo->prepare('INSERT INTO orders(user_id,delivery_address_snapshot,payment_method,subtotal,total,status,updated_at) VALUES (?,"Synthetic legacy address","manual",0,0,"pending","2000-01-01 00:00:00")')->execute([$legacy]);$legacyOrder=(int)$pdo->lastInsertId();
  file_put_contents($envFile,"PII_ENCRYPTION_ENABLED=true\n");App\Core\Env::load($envFile);
  $check('Recovered key restores exact protected values',$users->profileFor($user)['contact_no']==='+639123456780');
 }finally{if(is_file($envFile))unlink($envFile);}
 $check('Customer cannot execute a backfill',$denied(fn()=>PiiStorage::backfill($pdo,$user,'customer_profiles',1)));
 $backfilled=0;foreach(['customer_profiles','orders'] as $table){do{$n=PiiStorage::backfill($pdo,$admin,$table);$backfilled+=$n;}while($n===100);$check('Backfill repeat skips protected rows / '.$table,PiiStorage::backfill($pdo,$admin,$table)===0);}
 $check('Encryption backfill preserves historical order update time',$pdo->query('SELECT updated_at FROM orders WHERE id='.$legacyOrder)->fetchColumn()==='2000-01-01 00:00:00');
 $check('All selected profile/order fields cut over in disposable clone',(int)$pdo->query('SELECT COUNT(*) FROM customer_profiles WHERE pii_encrypted<>1 OR contact_no<>"" OR delivery_address IS NOT NULL')->fetchColumn()===0&&(int)$pdo->query('SELECT COUNT(*) FROM orders WHERE pii_encrypted<>1 OR contact_no_snapshot IS NOT NULL OR delivery_address_snapshot<>""')->fetchColumn()===0);
 $audit=$pdo->query('SELECT actor_id,details FROM audit_logs WHERE action="pii.backfilled" ORDER BY id DESC LIMIT 1')->fetch();$check('Backfill audit attributes actor without personal values',(int)$audit['actor_id']===$admin&&!str_contains($audit['details'],'Synthetic')&&!str_contains($audit['details'],'639'));
 $check('Structured address copies deliberately outside initial scope',(int)$pdo->query('SELECT COUNT(*) FROM customer_delivery_addresses WHERE user_id='.$user.' AND address_line="1 Synthetic Street"')->fetchColumn()===1);
 if(isset($args['output']))file_put_contents($args['output'],json_encode(['checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
 echo json_encode(['pass'=>count(array_filter($checks,fn($c)=>$c['status']==='PASS')),'fail'=>count(array_filter($checks,fn($c)=>$c['status']==='FAIL')),'failed'=>array_values(array_filter($checks,fn($c)=>$c['status']==='FAIL'))],JSON_THROW_ON_ERROR),PHP_EOL;
 exit(count(array_filter($checks,fn($c)=>$c['status']==='FAIL'))?1:0);
}catch(Throwable $e){fwrite(STDERR,'Encryption tests stopped: '.get_class($e).' in '.basename($e->getFile()).' at line '.$e->getLine().'; details suppressed.'.PHP_EOL);exit(1);}
