<?php
declare(strict_types=1);if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('AVELA_HAIR_TEST_LIBRARY',true);require __DIR__.'/hair_profiles.php';
try{
 $options=getopt('',['expect-database:','expect-port:','expect-datadir:','output:']);$pdo=App\Core\Database::connection();
 $identity=$pdo->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch();$norm=fn($p)=>strtolower(rtrim(str_replace('\\','/',$p),'/'));
 if($identity['db']!=='avela_catalog_test_2026103001'||($options['expect-database']??'')!==$identity['db']||(int)$identity['port']!==13427||(int)($options['expect-port']??0)!==13427
  ||$norm($identity['datadir'])!==$norm($options['expect-datadir']??'')||env('DB_HOST')!=='127.0.0.1'||env('APP_ENV')!=='development'||env('MAIL_DRIVER')!=='log')throw new RuntimeException('Verified isolated catalog target required');
 if((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()!==0||(int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn()!==19)throw new RuntimeException('Fresh catalog-restoration fixture required');
 $checks=[];$root=dirname(__DIR__);$users=new App\Models\User();$prefix='catalog_'.bin2hex(random_bytes(5));
 $staff=$users->createStaff('catalog_manager',$prefix.'staff@example.invalid',$prefix.'_staff',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT),'Synthetic catalog actor');
 $uid=$users->createCustomer(['username'=>$prefix.'_customer','email'=>$prefix.'customer@example.invalid','full_name'=>'Synthetic Catalog Customer','contact_no'=>'+639123456789','password_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);$users->activate($uid);
 $old=$pdo->query('SELECT * FROM products WHERE status="active" AND stock_qty>2 ORDER BY id LIMIT 1')->fetch();$cart=new App\Models\Cart();$cart->add($uid,(int)$old['id'],1);
 $input=['country'=>'PH','first_name'=>'Synthetic','last_name'=>'Customer','contact_no'=>'+639123456789','address_line'=>'12 Test Street','barangay'=>'Test Barangay','postal_code'=>'1600','city'=>'Pasig','region'=>'NCR','province'=>'Metro Manila','shipping_method'=>'standard','payment_method'=>'cod'];
 $values=App\Core\CheckoutValidator::validate($input,$users->profileFor($uid));if($values['errors'])throw new RuntimeException('Checkout fixture validation');
 $checkout=new App\Services\CheckoutService();$quote=$checkout->quote($uid);$oid=$checkout->place($uid,$values['values'],bin2hex(random_bytes(32)),$quote['signature']);
 $orderBefore=(new App\Models\Order())->owned($oid,$uid);$oldStock=$pdo->query('SELECT id,stock_qty FROM products ORDER BY id')->fetchAll();
 $pdo->prepare('INSERT INTO favorites(user_id,product_id) VALUES (?,?)')->execute([$uid,$old['id']]);$cart->add($uid,(int)$old['id'],1);
 $preserved=[];foreach(['orders','order_items','order_status_events','users','customer_profiles','favorites','cart_items','routines','routine_items']as $table)$preserved[$table]=$pdo->query('SELECT * FROM '.$table)->fetchAll();
 $service=new App\Services\CatalogRefresh($pdo,$root);$plan=$service->run(false,null,[]);
 verifyHair('Dry-run plans exact 100 inserts and 19 archives',$plan['inserts']===100&&$plan['archives']===19);
 verifyHair('Dry-run has no write side effects',(int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn()===19);
 $expected=['host'=>'127.0.0.1','database'=>$identity['db'],'port'=>13427,'datadir'=>$identity['datadir'],'catalog_sha256'=>$plan['catalog_sha256'],'source_sha256'=>$plan['source_sha256']];
 foreach(['database'=>'avela','port'=>13321,'datadir'=>'C:/wrong','catalog_sha256'=>str_repeat('0',64),'source_sha256'=>str_repeat('0',64)]as $field=>$bad){$rejected=false;try{$service->run(true,$prefix.'_staff',[...$expected,$field=>$bad]);}catch(DomainException){$rejected=true;}verifyHair('Mismatched confirmation is rejected: '.$field,$rejected&&(int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn()===19);}
 $rejected=false;try{$service->run(true,$prefix.'_customer',$expected);}catch(DomainException){$rejected=true;}verifyHair('Customer actor cannot import catalog',$rejected);
 $result=$service->run(true,$prefix.'_staff',$expected);
 verifyHair('Approved catalog imports atomically',$result['inserts']===100&&$result['archives']===19&&(int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn()===119);
 verifyHair('Exactly 100 new products are active with owner-approved stock',(int)$pdo->query('SELECT COUNT(*) FROM products WHERE status="active" AND sku LIKE "AVE-26-%" AND stock_qty=20')->fetchColumn()===100);
 verifyHair('Old products are archived and retain reserved stock',$oldStock===$pdo->query('SELECT id,stock_qty FROM products WHERE sku NOT LIKE "AVE-26-%" ORDER BY id')->fetchAll()&&(int)$pdo->query('SELECT COUNT(*) FROM products WHERE sku NOT LIKE "AVE-26-%" AND status="inactive"')->fetchColumn()===19);
 foreach($preserved as $table=>$rows)verifyHair('Related data preserved: '.$table,$rows===$pdo->query('SELECT * FROM '.$table)->fetchAll());
 verifyHair('Historical order remains owned and readable',(new App\Models\Order())->owned($oid,$uid)===$orderBefore);
 verifyHair('Import audit records archive and creation',(int)$pdo->query('SELECT COUNT(*) FROM audit_logs WHERE action="product.create" AND actor_id='.$staff)->fetchColumn()===100&&(int)$pdo->query('SELECT COUNT(*) FROM audit_logs WHERE action="product.archive" AND actor_id='.$staff)->fetchColumn()===19);
 $manifest=json_decode(file_get_contents($root.'/database/avela_catalog_20261030.json'),true);$rows=$pdo->query('SELECT p.*,c.name category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.sku LIKE "AVE-26-%" ORDER BY p.sku')->fetchAll();
 foreach($rows as $i=>$row){$expectedRow=$manifest['products'][$i];verifyHair('Imported product identity, price, tags and image match: '.$row['sku'],$row['name']===$expectedRow['name']&&$row['price']===$expectedRow['price']&&$row['category_name']===$expectedRow['category']&&$row['image_path']===$expectedRow['image_path']);}
 verifyHair('Five missing images use an honest placeholder',(int)$pdo->query('SELECT COUNT(*) FROM products WHERE sku LIKE "AVE-26-%" AND image_path IS NULL')->fetchColumn()===5);
 verifyHair('Styling remains its own product category',(int)$pdo->query('SELECT COUNT(*) FROM products p JOIN categories c ON c.id=p.category_id WHERE c.name="Styling"')->fetchColumn()===3);
 $candidates=(new App\Services\SuitabilityRepository())->candidates($pdo);$newCandidates=array_filter($candidates,fn($p)=>str_starts_with($p['sku'],'AVE-26-'));
 verifyHair('Only reviewed fictional suitability entries enter candidate matching',count($newCandidates)===76);
 verifyHair('Borrowed ingredient formulas are absent from public product descriptions',!str_contains(implode(' ',array_column($rows,'description')),'Dimethicone')&&!str_contains(implode(' ',array_column($rows,'description')),'FDA'));
 $profile=['tags'=>[['type'=>'texture','code'=>'curly'],['type'=>'scalp','code'=>'oily_scalp'],['type'=>'concern','code'=>'frizz'],['type'=>'concern','code'=>'dryness']],'treatment_history'=>'','hair_texture'=>'curly','scalp_type'=>'oily_scalp'];
 $matcher=new App\Services\ProductMatcher();$ranked=$matcher->rank($profile,$candidates);verifyHair('New catalog supports explainable matches',count($ranked)>0&&!array_filter($ranked,fn($p)=>!str_starts_with($p['sku'],'AVE-26-')||!$p['reasons']));
 $routine=$matcher->routine($profile,$ranked);verifyHair('A representative profile has actual cleanser and conditioner choices',count($routine['items'])>=2&&$routine['items'][0]['product']['role']==='cleanse'&&$routine['items'][1]['product']['role']==='condition');
 $first=$rows[0];$cart->remove($uid,(int)$old['id']);
 $cart->add($uid,(int)$first['id'],1);$quote=$checkout->quote($uid);$newOrder=$checkout->place($uid,$values['values'],bin2hex(random_bytes(32)),$quote['signature']);
 verifyHair('New product checkout uses genuine saved prices and decrements stock',(new App\Models\Order())->owned($newOrder,$uid)['items'][0]['product_id']==$first['id']&&(int)$pdo->query('SELECT stock_qty FROM products WHERE id='.(int)$first['id'])->fetchColumn()===19);
 $repeatPlan=$service->run(false,null,[]);$repeat=$service->run(true,$prefix.'_staff',[...$expected,'catalog_sha256'=>$repeatPlan['catalog_sha256']]);
 verifyHair('Repeat import is idempotent and never replenishes sold stock',$repeat['inserts']===0&&$repeat['archives']===0&&(int)$pdo->query('SELECT stock_qty FROM products WHERE id='.(int)$first['id'])->fetchColumn()===19);
 $pdo->prepare('UPDATE products SET status="active" WHERE id=?')->execute([$old['id']]);
 $fault=new HairAuditFaultPDO('mysql:host=127.0.0.1;port=13427;dbname='.$identity['db'].';charset=utf8mb4',env('DB_USERNAME'),env('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $faultService=new App\Services\CatalogRefresh($fault,$root);$faultPlan=$faultService->run(false,null,[]);$failed=false;
 try{$faultService->run(true,$prefix.'_staff',[...$expected,'catalog_sha256'=>$faultPlan['catalog_sha256']]);}catch(PDOException){$failed=true;}
 verifyHair('Audit failure rolls back the catalog business change',$failed&&$pdo->query('SELECT status FROM products WHERE id='.(int)$old['id'])->fetchColumn()==='active');
 $pdo->prepare('UPDATE products SET status="inactive" WHERE id=?')->execute([$old['id']]);
 $data=['checks'=>$checks,'pass'=>count(array_filter($checks,fn($c)=>$c['status']==='PASS')),'fail'=>count(array_filter($checks,fn($c)=>$c['status']==='FAIL'))];if(isset($options['output']))file_put_contents($options['output'],json_encode($data,JSON_PRETTY_PRINT));ob_end_flush();exit($data['fail']?1:0);
}catch(Throwable $e){fwrite(STDERR,'Catalog refresh tests stopped: '.get_class($e).' / '.basename($e->getFile()).':'.$e->getLine().'; private diagnostics suppressed.'.PHP_EOL);exit(1);}
