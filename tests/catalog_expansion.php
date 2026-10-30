<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('AVELA_HAIR_TEST_LIBRARY',true);require __DIR__.'/hair_profiles.php';
require_once dirname(__DIR__).'/vendor/autoload.php';
try {
    $o=getopt('',['expect-database:','expect-port:','expect-datadir:','base-url:','session-directory:','output:']);
    $pdo=App\Core\Database::connection();$id=$pdo->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch();
    $root=dirname(__DIR__);$base=$o['base-url']??'';$sessions=realpath($o['session-directory']??'');
    $norm=fn($v)=>strtolower(rtrim(str_replace('\\','/',(string)$v),'/'));
    if($id['db']!=='avela_release_test_2026103102'||($o['expect-database']??'')!==$id['db']||(int)$id['port']!==13427||(int)($o['expect-port']??0)!==13427
        ||$norm($id['datadir'])!==$norm($o['expect-datadir']??'')||env('DB_HOST')!=='127.0.0.1'||env('MAIL_DRIVER')!=='log'
        ||$base!=='http://127.0.0.1:18432'||!$sessions||str_starts_with($norm($sessions),$norm($root).'/'))throw new RuntimeException('Disposable-only target required.');
    ini_set('session.save_path',$sessions);$checks=[];
    $expected=['host'=>'127.0.0.1','database'=>$id['db'],'port'=>13427,'datadir'=>$id['datadir']];
    $refresh=new App\Services\CatalogRefresh($pdo,$root);$plan=$refresh->run(false,null,[]);
    $refresh->run(true,'image_catalog_manager',[...$expected,'source_sha256'=>$plan['source_sha256'],'catalog_sha256'=>$plan['catalog_sha256']]);
    $users=new App\Models\User();$name='expansion_'.bin2hex(random_bytes(4));
    $uid=$users->createCustomer(['username'=>$name,'email'=>$name.'@example.invalid','full_name'=>'Synthetic Catalog Customer','contact_no'=>'+639123456789','password_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);$users->activate($uid);
    $cart=new App\Models\Cart();$checkout=new App\Services\CheckoutService();$old=$pdo->query('SELECT * FROM products WHERE sku="AVE-26-0001"')->fetch();
    $input=App\Core\CheckoutValidator::validate(['country'=>'PH','first_name'=>'Synthetic','last_name'=>'Customer','contact_no'=>'+639123456789','address_line'=>'12 Test Street','barangay'=>'Test Barangay','postal_code'=>'1600','city'=>'Pasig','region'=>'NCR','province'=>'Metro Manila','shipping_method'=>'standard','payment_method'=>'cod'],$users->profileFor($uid));
    if($input['errors'])throw new RuntimeException('Invalid checkout fixture.');
    $cart->add($uid,(int)$old['id'],1);$quote=$checkout->quote($uid);$oid=$checkout->place($uid,$input['values'],bin2hex(random_bytes(32)),$quote['signature']);
    $before=$pdo->query('SELECT * FROM products ORDER BY id')->fetchAll();$related=[];
    foreach(['users','customer_profiles','orders','order_items','order_status_events','product_tags','routines','routine_items','customer_address_book'] as $table)$related[$table]=$pdo->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll();
    $service=new App\Services\CatalogExpansion($pdo,$root);$plan=$service->run(false,null,[]);
    verifyHair('Dry-run plans 113 additions and five missing photographs',$plan['inserts']===113&&$plan['image_repairs']===5);
    verifyHair('Dry-run does not mutate catalog',$before===$pdo->query('SELECT * FROM products ORDER BY id')->fetchAll());
    $expected=[...$expected,'source_sha256'=>$plan['source_sha256'],'catalog_sha256'=>$plan['catalog_sha256']];
    foreach(['host'=>'different','database'=>'avela','port'=>13321,'datadir'=>'C:/wrong','source_sha256'=>str_repeat('0',64),'catalog_sha256'=>str_repeat('0',64)] as $key=>$bad){$blocked=false;try{$service->run(true,'image_admin',[...$expected,$key=>$bad]);}catch(DomainException){$blocked=true;}verifyHair('Exact confirmation rejects '.$key,$blocked);}
    $blocked=false;try{$service->run(true,$name,$expected);}catch(DomainException){$blocked=true;}verifyHair('Customer cannot act as catalog importer',$blocked);
    $fault=new HairAuditFaultPDO('mysql:host=127.0.0.1;port=13427;dbname='.$id['db'].';charset=utf8mb4',env('DB_USERNAME'),env('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $blocked=false;try{(new App\Services\CatalogExpansion($fault,$root))->run(true,'image_admin',$expected);}catch(PDOException){$blocked=true;}
    verifyHair('Audit failure rolls back all product inserts and image repairs',$blocked&&$before===$pdo->query('SELECT * FROM products ORDER BY id')->fetchAll());
    $result=$service->run(true,'image_catalog_manager',$expected);
    verifyHair('Approved batch creates 113 products and repairs five images',$result['inserts']===113&&$result['image_repairs']===5&&$result['archives']===0);
    $after=array_column($pdo->query('SELECT * FROM products ORDER BY id')->fetchAll(),null,'id');$preserved=true;$imageChanges=0;
    foreach($before as $row){$new=$after[$row['id']];if($new['image_path']!==$row['image_path'])$imageChanges++;unset($new['image_path'],$new['updated_at'],$row['image_path'],$row['updated_at']);if($new!==$row)$preserved=false;}
    verifyHair('Existing prices, stock, IDs and metadata preserved',$preserved&&$imageChanges===5);
    foreach($related as $table=>$rows)verifyHair('Existing business data preserved: '.$table,$rows===$pdo->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll());
    $active=$pdo->query('SELECT * FROM products WHERE status="active" ORDER BY sku')->fetchAll();
    verifyHair('Catalog has 213 active products with real images',count($active)===213&&!array_filter($active,fn($p)=>!$p['image_path']||!is_file($root.'/public/'.$p['image_path'])));
    $references=new App\Services\ProductReference();$known=0;$missing=0;
    foreach($active as $product){$r=$references->forProduct($product);if(!empty($r['ingredients']))$known++;else $missing++;}
    verifyHair('Exactly 113 ingredient lists and 100 honest unavailable states',$known===113&&$missing===100);
    $p=$active[0];verifyHair('Changed product identity cannot inherit formula',$references->forProduct([...$p,'name'=>'Different product'])===null);
    $client=hairClient($uid);$with=current(array_filter($active,fn($p)=>!empty($references->forProduct($p)['ingredients'])));$without=current(array_filter($active,fn($p)=>empty($references->forProduct($p)['ingredients'])));
    $html=hairRequest('/product?id='.$with['id'],$client);$r=$references->forProduct($with);
    verifyHair('Product HTTP response escapes exact formula and states illustrative provenance',$html['code']===200&&str_contains($html['body'],e($r['ingredients']))&&str_contains($html['body'],'not a tested or manufactured Avela formula'));
    $html=hairRequest('/product?id='.$without['id'],$client);verifyHair('Unavailable ingredients never fabricated',$html['code']===200&&str_contains($html['body'],'Ingredient information has not been supplied'));
    $candidates=(new App\Services\SuitabilityRepository())->candidates($pdo);verifyHair('Existing 76 reviewed candidates preserved; unreviewed new products excluded',count(array_filter($candidates,fn($p)=>str_starts_with($p['sku'],'AVE-26-')))===76);
    $new=$pdo->query('SELECT * FROM products WHERE sku="AVE-26-0101"')->fetch();$cart->add($uid,(int)$new['id'],1);$quote=$checkout->quote($uid);$newOrder=$checkout->place($uid,$input['values'],bin2hex(random_bytes(32)),$quote['signature']);
    verifyHair('New SKU checkout uses real price and decrements stock',(new App\Models\Order())->owned($newOrder,$uid)['items'][0]['product_id']===$new['id']&&(int)$pdo->query('SELECT stock_qty FROM products WHERE id='.$new['id'])->fetchColumn()===19);
    $repeatPlan=$service->run(false,null,[]);$repeat=$service->run(true,'image_admin',[...$expected,'catalog_sha256'=>$repeatPlan['catalog_sha256']]);
    verifyHair('Repeat application is idempotent and never replenishes sold stock',$repeat['inserts']===0&&$repeat['image_repairs']===0&&(int)$pdo->query('SELECT stock_qty FROM products WHERE id='.$new['id'])->fetchColumn()===19);
    $cart->add($uid,(int)$new['id'],1);$html=hairRequest('/checkout',$client);
    verifyHair('Checkout removes obsolete saved-address shortcut',$html['code']===200&&!str_contains($html['body'],'data-use-default'));
    $failed=array_filter($checks,fn($c)=>$c['status']==='FAIL');$result=['pass'=>count($checks)-count($failed),'fail'=>count($failed),'checks'=>$checks];file_put_contents($o['output'],json_encode($result,JSON_PRETTY_PRINT));ob_end_flush();exit($failed?1:0);
}catch(Throwable $e){fwrite(STDERR,'Expansion test stopped: '.get_class($e).' at '.basename($e->getFile()).':'.$e->getLine().PHP_EOL);exit(1);}
