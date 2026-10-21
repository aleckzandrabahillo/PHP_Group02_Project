<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('AVELA_HAIR_TEST_LIBRARY',true);
require __DIR__.'/hair_profiles.php';

function catalogRejected(callable $operation): bool {
    try{$operation();return false;}catch(InvalidArgumentException|DomainException){return true;}
}
function catalogSnapshot(PDO $pdo): string {
    $out=[];
    foreach(['products','product_tags','product_reviews','order_items','favorites','routine_items','audit_logs'] as $table){
        $rows=$pdo->query('SELECT * FROM '.$table)->fetchAll();
        $hashes=array_map(static fn($r)=>hash('sha256',json_encode($r,JSON_THROW_ON_ERROR)),$rows);sort($hashes);$out[$table]=$hashes;
    }
    return hash('sha256',json_encode($out,JSON_THROW_ON_ERROR));
}
function catalogUpload(array $client,array $fields,string $name,string $bytes): array {
    global $base;$boundary='Avela'.bin2hex(random_bytes(16));$body='';
    foreach($fields as $key=>$value){
        foreach(is_array($value)?$value:[$value] as $part)$body.='--'.$boundary."\r\nContent-Disposition: form-data; name=\"".$key.(is_array($value)?'[]':'')."\"\r\n\r\n".$part."\r\n";
    }
    $body.='--'.$boundary."\r\nContent-Disposition: form-data; name=\"image\"; filename=\"".$name."\"\r\nContent-Type: application/octet-stream\r\n\r\n".$bytes."\r\n--".$boundary."--\r\n";
    $html=file_get_contents($base.'/catalog/products',false,stream_context_create(['http'=>['method'=>'POST','ignore_errors'=>true,'follow_location'=>0,'timeout'=>20,'header'=>'Cookie: '.$client['cookie']."\r\nConnection: close\r\nContent-Type: multipart/form-data; boundary=".$boundary."\r\n",'content'=>$body]]));
    preg_match('/ ([0-9]{3}) /',$http_response_header[0]??'',$m);return ['code'=>(int)($m[1]??0),'body'=>(string)$html];
}
function catalogWorker(array $o): void {
    $model=new App\Models\Product();$pdo=App\Core\Database::connection();$target=(int)$o['target'];$actor=(int)$o['actor'];
    try {
        switch($o['worker']){
            case 'add':$model->changeStock($target,(int)$o['quantity'],true,null,$actor,'Synthetic concurrent shipment');break;
            case 'set':$model->changeStock($target,(int)$o['quantity'],false,(int)$o['expected'],$actor,'Synthetic concurrent count');break;
            case 'edit':$data=$model->managementFind($target);$data['stock_qty']=$o['quantity'];$data['expected_stock']=$o['expected'];$data['stock_reason']='Synthetic concurrent edit';$model->saveProduct($target,$data,null,$actor);break;
            case 'create':$data=App\Core\ProductValidator::defaults()+[];$data=array_replace($data,['name'=>'Synthetic concurrent product','sku'=>$o['sku'],'category_id'=>(string)$target,'price'=>'1.00','stock_qty'=>'0']);$model->saveProduct(null,$data,null,$actor);break;
            case 'review':$pdo->prepare('INSERT INTO product_reviews(product_id,user_id,rating,review_text) VALUES (?,?,5,"Synthetic concurrent review")')->execute([$target,$actor]);break;
            case 'delete':$model->removeProduct($target,$actor,false);break;
            default:throw new RuntimeException('Worker mode');
        }
        echo '{"result":"applied"}';exit;
    }catch(InvalidArgumentException|DomainException){echo '{"result":"rejected"}';exit;}
    catch(PDOException $e){if(in_array((int)($e->errorInfo[1]??0),[1062,1205,1213,1451,1452],true)){echo '{"result":"rejected"}';exit;}throw $e;}
}
function catalogRace(array $jobs,array $options): array {
    $children=[];
    foreach($jobs as $job){
        $args=[PHP_BINARY,__FILE__];
        foreach($job as $key=>$value)$args[]='--'.$key.'='.$value;
        foreach(['expect-database','expect-port','expect-datadir','base-url','session-directory'] as $key)$args[]='--'.$key.'='.$options[$key];
        $p=proc_open($args,[0=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
        if(!is_resource($p))throw new RuntimeException('Concurrency fixture');
        $children[]=[$p,$pipes];
    }
    $out=[];foreach($children as [$p,$pipes]){$body=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);if($exit!==0||$err!=='')throw new RuntimeException('Worker failure');$out[]=json_decode($body,true,512,JSON_THROW_ON_ERROR)['result'];}return $out;
}
function catalogCleanup(PDO $pdo,string $prefix,array $orders,int $customer): void {
    foreach($orders as $order)$pdo->prepare('DELETE FROM orders WHERE id=? AND user_id=?')->execute([$order,$customer]);
    $q=$pdo->prepare("SELECT id,image_path FROM products WHERE sku LIKE ? ESCAPE '!'");
    $q->execute([strtr(strtoupper($prefix),['!'=>'!!','%'=>'!%','_'=>'!_']).'%']);$rows=$q->fetchAll();
    foreach($rows as $row){
        foreach(['product_reviews','favorites','cart_items','routine_items'] as $table)$pdo->prepare('DELETE FROM '.$table.' WHERE product_id=?')->execute([$row['id']]);
        $pdo->prepare('DELETE FROM products WHERE id=? AND sku LIKE ?')->execute([$row['id'],strtoupper($prefix).'%']);
        if($row['image_path']!==null&&!(new App\Models\Product())->imageIsReferenced($row['image_path']))(new App\Services\ProductImageService())->discard($row['image_path']);
    }
}

try {
    $o=getopt('',['expect-database:','expect-port:','expect-datadir:','base-url:','session-directory:','scratch-directory:','output:','worker:','actor:','target:','quantity:','expected:','sku:']);
    $pdo=App\Core\Database::connection();$id=$pdo->query('SELECT DATABASE() db,@@port port,@@hostname host,@@server_id sid,@@datadir dir')->fetch();
    $base=$o['base-url']??'';$url=parse_url($base);$sessions=realpath($o['session-directory']??'');
    $norm=static fn($s)=>strtolower(rtrim(str_replace('\\','/',$s),'/'));
    if(!preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D',$id['db'])||($o['expect-database']??'')!==$id['db']||(int)($o['expect-port']??0)!==(int)$id['port']
        ||in_array((int)$id['port'],[3306,13307],true)||$id['host']!=='Zee'||(int)$id['sid']!==1||$norm($o['expect-datadir']??'')!==$norm($id['dir'])
        ||env('DB_HOST')!=='127.0.0.1'||env('APP_ENV')!=='development'||env('MAIL_DRIVER')!=='log'||!$sessions
        ||str_starts_with($norm($sessions),$norm(dirname(__DIR__)).'/')||($url['host']??'')!=='127.0.0.1'||in_array((int)($url['port']??80),[80,18080],true))throw new RuntimeException('Disposable-only guard');
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=5');
    if(isset($o['worker']))catalogWorker($o);
    ini_set('session.save_path',$sessions);$checks=[];$model=new App\Models\Product();$users=new App\Models\User();$prefix='cat_'.bin2hex(random_bytes(5));$orders=[];
    $productsBefore=$pdo->query('SELECT * FROM products ORDER BY id')->fetchAll();
    verifyHair('Verified isolated twenty-product starting catalog',count($productsBefore)===20);
    $originalOrders=$pdo->query('SELECT * FROM orders ORDER BY id')->fetchAll();
    $originalReviews=$pdo->query('SELECT * FROM product_reviews ORDER BY id')->fetchAll();
    $originalTags=$pdo->query('SELECT * FROM product_tags ORDER BY product_id,tag_id')->fetchAll();
    $policyBefore=$pdo->query('SELECT * FROM security_settings ORDER BY setting_key')->fetchAll();
    $secret='ABCDabcd1234!@#$'.bin2hex(random_bytes(24));$hash=password_hash($secret,PASSWORD_DEFAULT);
    $manager=$users->createStaff('catalog_manager',$prefix.'_manager@example.invalid',$prefix.'_manager',$hash,'Synthetic Catalog Manager');
    $adminId=$users->createStaff('admin',$prefix.'_admin@example.invalid',$prefix.'_admin',$hash,'Synthetic Catalog Admin');
    $customer=$users->createCustomer(['username'=>$prefix.'_customer','email'=>$prefix.'_customer@example.invalid','password_hash'=>$hash,'full_name'=>'Synthetic Catalog Customer','contact_no'=>'+639123456789']);$users->activate($customer);
    $staff=hairClient($manager);$admin=hairClient($adminId);$client=hairClient($customer);
    $category=(int)$pdo->query('SELECT id FROM categories WHERE status="active" ORDER BY id LIMIT 1')->fetchColumn();
    $tagIds=array_map('strval',array_column(array_slice($model->suitabilityTags(),0,3),'id'));
    sort($tagIds,SORT_NUMERIC);
    $sequence=0;$form=function(array $extra=[])use(&$sequence,$prefix,$category,$tagIds):array{return array_replace(App\Core\ProductValidator::defaults(),['action'=>'create','name'=>'Synthetic catalog product','sku'=>strtoupper($prefix).'_'.(++$sequence),'category_id'=>(string)$category,'price'=>'10.00','stock_qty'=>'20','status'=>'active','tag_ids'=>$tagIds],$extra);};
    $post=function(array $data,array $actor)use($pdo):array{return hairRequest('/catalog/products',$actor,['_token'=>$actor['csrf'],...$data]);};
    $lookup=function(string $sku)use($pdo):?array{$q=$pdo->prepare('SELECT * FROM products WHERE sku=?');$q->execute([$sku]);return $q->fetch()?:null;};
    $create=function(array $extra=[])use($form,$post,$staff,$lookup):array{$data=$form($extra);$r=$post($data,$staff);$row=$lookup($data['sku']);if($r['code']!==302||!$row)throw new RuntimeException('Product fixture creation');return $row;};
    $edit=function(int $id,array $extra=[])use($model):array{$row=$model->managementFind($id);return array_replace($row,['action'=>'update','id'=>(string)$id,'category_id'=>(string)$row['category_id'],'stock_qty'=>(string)$row['stock_qty'],'price'=>(string)$row['price'],'expected_stock'=>(string)$row['stock_qty'],'routine_step'=>$row['routine_step']??'','stock_reason'=>''],$extra);};
    foreach(['/catalog','/catalog/products','/catalog/inventory','/catalog/categories'] as $path){
        verifyHair('Guest denied '.$path,hairRequest($path)['code']===302);
        verifyHair('Customer denied '.$path,hairRequest($path,$client)['code']===403);
        verifyHair('Catalog staff page works '.$path,hairRequest($path,$staff)['code']===200);
    }
    foreach(['/catalog/products','/catalog/inventory'] as $path){
        verifyHair('Guest mutation denied '.$path,hairRequest($path,null,[])['code']===302);
        verifyHair('Customer mutation denied '.$path,hairRequest($path,$client,['_token'=>$client['csrf']])['code']===403);
        foreach([$staff,$admin] as $actor)foreach(['bad',['bad']] as $token)verifyHair('Invalid CSRF rejected '.$path,hairRequest($path,$actor,['_token'=>$token])['code']===419);
    }
    foreach(['/admin/users','/admin/staff','/admin/security','/admin/audit-logs','/admin/auth-logs'] as $path)verifyHair('Catalog role has no Admin access '.$path,hairRequest($path,$staff)['code']===403);
    $entry=file_get_contents(dirname(__DIR__).'/public/index.php');
    foreach(['products','categories','inventory'] as $path)foreach(['get','post'] as $method)verifyHair('One route contract '.$method.' '.$path,substr_count($entry,'$router->'.$method."('/catalog/".$path."'")===1);
    foreach(['saveManagedProduct','managerRows','updateManagedStock','deleteManagedProduct'] as $method)verifyHair('No competing legacy product implementation '.$method,!method_exists($model,$method));

    $p=$create();$pid=(int)$p['id'];
    verifyHair('Product created with manager-attributed audit',(int)$pdo->query('SELECT actor_id FROM audit_logs WHERE action="product.create" AND target_id='.$pid.' ORDER BY id DESC LIMIT 1')->fetchColumn()===$manager);
    verifyHair('Flat tag IDs persisted',$model->managementFind($pid)['tag_ids']===$tagIds);
    $get=hairRequest('/catalog/products?edit='.$pid,$staff);
    verifyHair('Edit round-trips checked tags',$get['code']===200&&substr_count($get['body'],'name="tag_ids[]"')>0&&substr_count($get['body'],' checked')===count($tagIds)&&!str_contains($get['body'],'name="tags['));
    $before=catalogSnapshot($pdo);
    foreach(['/catalog/products?action=delete&id='.$pid,'/catalog/inventory?stock='.$pid.'&mode=set&stock_qty=999&reason=invalid&_token=bad','/catalog/inventory?action=create&sku=unwanted'] as $path){
        $r=hairRequest($path,$staff);verifyHair('GET is read-only '.$path,$r['code']===200&&catalogSnapshot($pdo)===$before);
    }
    foreach(['name'=>['array'],'sku'=>['array'],'stock_qty'=>['array'],'category_id'=>['array'],'price'=>['array'],'tag_ids'=>[['nested']]] as $key=>$value){
        $data=$form([$key=>$value]);$r=$post($data,$staff);
        verifyHair('Malformed product field rejected '.$key,$r['code']===422&&!str_contains($r['body'],'Array to string conversion')&&!$lookup($data['sku']===['array']?'not-created':(is_string($data['sku'])?$data['sku']:'not-created')));
    }
    foreach(['name'=>str_repeat('x',161),'sku'=>str_repeat('S',51),'description'=>str_repeat('x',65536),'price'=>'-1','stock_qty'=>'4294967296','status'=>'unknown','routine_step'=>'unknown','category_id'=>'999999999'] as $key=>$value){
        $data=$form([$key=>$value]);verifyHair('Schema/enum bound enforced '.$key,$post($data,$staff)['code']===422);
    }
    $data=$form(['name'=>'']);verifyHair('Required product name enforced',$post($data,$staff)['code']===422);
    foreach(['1e2','0.001','100000000.00'] as $value)verifyHair('Invalid price format rejected',$post($form(['price'=>$value]),$staff)['code']===422);
    verifyHair('Duplicate SKU rejected under DB collation',$post($form(['sku'=>strtolower($p['sku'])]),$staff)['code']===422);
    $r=$post($edit($pid,['tags'=>['concern'=>['dryness']]]),$staff);
    verifyHair('Legacy grouped tag payload cannot erase current tags',$r['code']===422&&$model->managementFind($pid)['tag_ids']===$tagIds);
    verifyHair('Associative tag payload rejected',$post($edit($pid,['tag_ids'=>['texture'=>$tagIds[0]]]),$staff)['code']===422);
    verifyHair('Unknown numeric tag rejected',$post($edit($pid,['tag_ids'=>['999999999']]),$staff)['code']===422);
    $newTags=array_slice($tagIds,0,1);$r=$post($edit($pid,['name'=>'Synthetic updated <script>care</script>','tag_ids'=>$newTags]),$admin);
    verifyHair('Administrator updates same implementation',$r['code']===302&&$model->managementFind($pid)['tag_ids']===$newTags);
    $public=hairRequest('/product?id='.$pid)['body'];verifyHair('Product output encoded',str_contains($public,'&lt;script&gt;care&lt;/script&gt;')&&!str_contains($public,'<script>care</script>'));
    $r=$post($edit($pid,['tag_ids'=>[]]),$staff);verifyHair('Explicitly clearing all tags works',$r['code']===302&&$model->managementFind($pid)['tag_ids']===[]);
    $stockPost=['_token'=>$staff['csrf'],'id'=>(string)$pid,'mode'=>'add','stock_qty'=>'3','expected_stock'=>'20','reason'=>'Received new shipment'];
    foreach(['',[],str_repeat('x',201),"bad\nreason","\xff"] as $reason){
        $before=catalogSnapshot($pdo);$r=hairRequest('/catalog/inventory',$staff,[...$stockPost,'reason'=>$reason]);
        verifyHair('Missing/malformed stock reason rejected',$r['code']===422&&catalogSnapshot($pdo)===$before);
    }
    $r=hairRequest('/catalog/inventory',$staff,$stockPost);
    verifyHair('Reasoned restock adds quantity',$r['code']===302&&(int)$model->managementFind($pid)['stock_qty']===23);
    $audit=$pdo->query('SELECT actor_id,details FROM audit_logs WHERE action="inventory.restock" AND target_id='.$pid.' ORDER BY id DESC LIMIT 1')->fetch();
    verifyHair('Stock audit records actual actor and reason',(int)$audit['actor_id']===$manager&&str_contains($audit['details'],'Received new shipment')&&str_contains($audit['details'],'20 to 23'));
    $before=catalogSnapshot($pdo);$r=hairRequest('/catalog/inventory',$staff,[...$stockPost,'mode'=>'set','stock_qty'=>'0']);
    verifyHair('Stale absolute form rejected',$r['code']===422&&catalogSnapshot($pdo)===$before);
    $r=hairRequest('/catalog/inventory',$staff,[...$stockPost,'mode'=>'set','stock_qty'=>'0','expected_stock'=>'23','reason'=>'Physical count correction']);
    verifyHair('Valid absolute count sets zero stock',$r['code']===302&&(int)$model->managementFind($pid)['stock_qty']===0);
    verifyHair('Out-of-stock product remains public',$model->findActive($pid)!==null&&str_contains(hairRequest('/product?id='.$pid)['body'],'Out of stock'));
    verifyHair('Zero additive restock rejected',hairRequest('/catalog/inventory',$staff,[...$stockPost,'stock_qty'=>'0','expected_stock'=>'0'])['code']===422);
    $r=$post($edit($pid,['stock_qty'=>'10']),$staff);
    verifyHair('Product edit cannot bypass stock reason',$r['code']===422&&(int)$model->managementFind($pid)['stock_qty']===0);
    $r=$post($edit($pid,['stock_qty'=>'10','stock_reason'=>'Counted unopened stock']),$staff);
    verifyHair('Product edit with reason updates stock',$r['code']===302&&(int)$model->managementFind($pid)['stock_qty']===10);
    verifyHair('Product edit stock movement has reasoned audit',str_contains((string)$pdo->query('SELECT details FROM audit_logs WHERE action="inventory.set" AND target_id='.$pid.' ORDER BY id DESC LIMIT 1')->fetchColumn(),'Counted unopened stock'));
    $stale=$edit($pid);$model->changeStock($pid,2,true,null,$manager,'Synthetic shipment');
    $before=catalogSnapshot($pdo);$r=$post($stale,$staff);
    verifyHair('Product edit cannot overwrite concurrent stock',$r['code']===422&&catalogSnapshot($pdo)===$before);
    $model->changeStock($pid,App\Models\Product::MAX_STOCK,false,12,$manager,'Synthetic boundary count');
    verifyHair('Maximum stock overflow rejected',catalogRejected(fn()=>$model->changeStock($pid,1,true,null,$manager,'Received stock')));
    $model->changeStock($pid,100,false,App\Models\Product::MAX_STOCK,$manager,'Reset synthetic count');
    verifyHair('Model also requires a nonblank reason',catalogRejected(fn()=>$model->changeStock($pid,1,true,null,$manager,'')));
    $race=catalogRace([['worker'=>'add','actor'=>$manager,'target'=>$pid,'quantity'=>7],['worker'=>'add','actor'=>$adminId,'target'=>$pid,'quantity'=>11]],$o);
    verifyHair('Concurrent restocks both preserved',$race===['applied','applied']&&(int)$model->managementFind($pid)['stock_qty']===118);
    $race=catalogRace([['worker'=>'set','actor'=>$manager,'target'=>$pid,'quantity'=>90,'expected'=>118],['worker'=>'add','actor'=>$adminId,'target'=>$pid,'quantity'=>7]],$o);
    $quantity=(int)$model->managementFind($pid)['stock_qty'];
    verifyHair('Concurrent count and restock cannot lose an update',in_array($quantity,[97,125],true)&&$race[1]==='applied'&&($quantity===97?$race[0]==='applied':$race[0]==='rejected'));
    $race=catalogRace([['worker'=>'edit','actor'=>$manager,'target'=>$pid,'quantity'=>50,'expected'=>$quantity],['worker'=>'edit','actor'=>$adminId,'target'=>$pid,'quantity'=>60,'expected'=>$quantity]],$o);
    verifyHair('Concurrent stale product edits have one winner',count(array_filter($race,fn($x)=>$x==='applied'))===1&&in_array((int)$model->managementFind($pid)['stock_qty'],[50,60],true));
    $dupe=strtoupper($prefix).'_RACE';$race=catalogRace([['worker'=>'create','actor'=>$manager,'target'=>$category,'sku'=>$dupe],['worker'=>'create','actor'=>$adminId,'target'=>$category,'sku'=>$dupe]],$o);
    verifyHair('Concurrent duplicate SKU has one record',count(array_filter($race,fn($x)=>$x==='applied'))===1&&$lookup($dupe)!==null);
    $pdo->prepare('UPDATE users SET status="inactive" WHERE id=?')->execute([$manager]);
    foreach([fn()=>$model->changeStock($pid,1,true,null,$manager,'Denied synthetic action'),fn()=>$model->removeProduct($pid,$manager,true),fn()=>$model->reactivateProduct($pid,$manager),fn()=>$model->saveProduct($pid,$edit($pid),null,$manager)] as $op)
        verifyHair('Inactive actor rejected under model lock',catalogRejected($op));
    $pdo->prepare('UPDATE users SET status="active" WHERE id=?')->execute([$manager]);
    verifyHair('Customer actor denied by stock model',catalogRejected(fn()=>$model->changeStock($pid,1,true,null,$customer,'Denied')));
    $model->removeProduct($pid,$manager,true);
    verifyHair('Archive hides without deleting',$model->managementFind($pid)['status']==='inactive'&&$model->findActive($pid)===null);
    verifyHair('Inactive duplicate SKU still reserved',$post($form(['sku'=>$p['sku']]),$staff)['code']===422);
    $r=$post(['action'=>'reactivate','id'=>(string)$pid],$staff);
    verifyHair('Quick reactivation uses same guarded route',$r['code']===302&&$model->findActive($pid)!==null);
    $n=(int)$pdo->query('SELECT COUNT(*) FROM audit_logs WHERE action="product.reactivate" AND target_id='.$pid)->fetchColumn();
    $post(['action'=>'reactivate','id'=>(string)$pid],$staff);
    verifyHair('Repeated reactivation does not duplicate audit',(int)$pdo->query('SELECT COUNT(*) FROM audit_logs WHERE action="product.reactivate" AND target_id='.$pid)->fetchColumn()===$n);

    $reviewed=$create(['stock_qty'=>'10']);$rid=(int)$reviewed['id'];
    $pdo->prepare('INSERT INTO product_reviews(product_id,user_id,rating,review_text,status) VALUES (?,?,5,"Synthetic review history","hidden")')->execute([$rid,$customer]);
    $review=$pdo->query('SELECT * FROM product_reviews WHERE product_id='.$rid)->fetchAll();
    $r=$post(['action'=>'delete','id'=>(string)$rid],$staff);
    verifyHair('Hidden review prevents cascading deletion',$r['code']===422&&$model->managementFind($rid)!==null&&$review===$pdo->query('SELECT * FROM product_reviews WHERE product_id='.$rid)->fetchAll());
    $model->removeProduct($rid,$manager,true);$model->reactivateProduct($rid,$manager);
    verifyHair('Archive/reactivation preserves review history',$review===$pdo->query('SELECT * FROM product_reviews WHERE product_id='.$rid)->fetchAll());
    $pdo->prepare('INSERT INTO carts(user_id,status) VALUES (?,"active")')->execute([$customer]);$cart=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO cart_items(cart_id,product_id,quantity) VALUES (?,?,1)')->execute([$cart,$rid]);
    $checkout=new App\Services\CheckoutService();$quote=$checkout->quote($customer);
    $order=$checkout->place($customer,['country'=>'PH','first_name'=>'Synthetic','last_name'=>'Recipient','address_line'=>'1 Test Street','barangay'=>'Test','postal_code'=>'1600','city'=>'Pasig','region'=>'NCR','contact_no'=>'09123456789','shipping_method'=>'standard','payment_method'=>'cod'],bin2hex(random_bytes(32)),$quote['signature']);$orders[]=$order;
    $itemsBefore=$pdo->query('SELECT * FROM order_items WHERE order_id='.$order)->fetchAll();
    $r=$post(['action'=>'delete','id'=>(string)$rid],$staff);
    verifyHair('Ordered product deletion preserves references',$r['code']===422&&$itemsBefore===$pdo->query('SELECT * FROM order_items WHERE order_id='.$order)->fetchAll());
    foreach(['cart','favorite','routine'] as $type){
        $linked=$create();$linkedId=(int)$linked['id'];
        if($type==='cart'){$pdo->prepare('INSERT INTO carts(user_id,status) VALUES (?,"active")')->execute([$customer]);$cid=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO cart_items(cart_id,product_id,quantity) VALUES (?,?,1)')->execute([$cid,$linkedId]);}
        if($type==='favorite')$pdo->prepare('INSERT INTO favorites(user_id,product_id) VALUES (?,?)')->execute([$customer,$linkedId]);
        if($type==='routine'){$pdo->prepare('INSERT INTO hair_profiles(user_id) VALUES (?)')->execute([$customer]);$profile=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO routines(user_id,hair_profile_id) VALUES (?,?)')->execute([$customer,$profile]);$routine=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO routine_items(routine_id,product_id,step_order,routine_step) VALUES (?,?,1,"cleanse")')->execute([$routine,$linkedId]);}
        verifyHair('Referenced '.$type.' product cannot be deleted',$post(['action'=>'delete','id'=>(string)$linkedId],$staff)['code']===422&&$model->managementFind($linkedId)!==null);
    }
    $unused=$create();$uid=(int)$unused['id'];$r=$post(['action'=>'delete','id'=>(string)$uid],$staff);
    verifyHair('Catalog Manager deletes unused product without Admin review',$r['code']===302&&$model->managementFind($uid)===null);
    $racing=$create();$raceId=(int)$racing['id'];$race=catalogRace([['worker'=>'review','actor'=>$customer,'target'=>$raceId],['worker'=>'delete','actor'=>$manager,'target'=>$raceId]],$o);
    $reviewCount=(int)$pdo->query('SELECT COUNT(*) FROM product_reviews WHERE product_id='.$raceId)->fetchColumn();
    verifyHair('Concurrent review/delete never erases a saved review',$race[0]==='applied'?($race[1]==='rejected'&&$reviewCount===1&&$model->managementFind($raceId)!==null):($race[1]==='applied'&&$reviewCount===0&&$model->managementFind($raceId)===null));

    $png=file_get_contents(dirname(__DIR__).'/public/assets/images/avela-logo.png');
    verifyHair('Both form and server advertise five megabytes',App\Services\ProductImageService::MAX_BYTES===5242880&&str_contains(hairRequest('/catalog/products?form=add',$staff)['body'],'name="MAX_FILE_SIZE" value="5242880"')&&str_contains(file_get_contents(dirname(__DIR__).'/public/assets/js/app.js'),'file.size > 5 * 1024 * 1024'));
    foreach([3*1024*1024,5*1024*1024,5*1024*1024+1] as $size){
        $data=$form();$bytes=$png.str_repeat(' ',max(0,$size-strlen($png)));$r=catalogUpload($staff,['_token'=>$staff['csrf'],...$data],'product.png',$bytes);$row=$lookup($data['sku']);
        verifyHair('Actual multipart upload boundary '.$size,$size<=5*1024*1024?$r['code']===302&&$row&&is_file(dirname(__DIR__).'/public/'.$row['image_path']):$r['code']===422&&!$row);
        if($row){$path=$row['image_path'];$deleted=$post(['action'=>'delete','id'=>(string)$row['id']],$staff);clearstatcache(true,dirname(__DIR__).'/public/'.$path);verifyHair('Unused uploaded fixture deleted and file retired',$deleted['code']===302&&$model->managementFind((int)$row['id'])===null&&!is_file(dirname(__DIR__).'/public/'.$path));}
    }
    foreach(['fake.jpg'=>['not an image'],'mismatch.jpg'=>[$png],'payload.php'=>[$png],'empty.png'=>[''],'vector.svg'=>['<svg xmlns="http://www.w3.org/2000/svg"></svg>']] as $name=>[$bytes]){
        $data=$form();$r=catalogUpload($staff,['_token'=>$staff['csrf'],...$data],$name,$bytes);verifyHair('Upload type/origin validation '.$name,$r['code']===422&&!$lookup($data['sku']));
    }
    $data=$form();$r=catalogUpload($staff,['_token'=>$staff['csrf'],...$data],'first.png',$png);$uploaded=$lookup($data['sku']);$imageId=(int)$uploaded['id'];$old=$uploaded['image_path'];
    $r=catalogUpload($staff,['_token'=>$staff['csrf'],...$edit($imageId)],'replacement.png',$png);$new=$model->managementFind($imageId)['image_path'];
    verifyHair('Replacement commits new file and retires unreferenced old file',$r['code']===302&&$new!==$old&&is_file(dirname(__DIR__).'/public/'.$new)&&!is_file(dirname(__DIR__).'/public/'.$old));
    $shared=$create();$pdo->prepare('UPDATE products SET image_path=? WHERE id=?')->execute([$new,$shared['id']]);
    $r=catalogUpload($staff,['_token'=>$staff['csrf'],...$edit($imageId)],'again.png',$png);
    verifyHair('Shared generated image is preserved on replacement',$r['code']===302&&is_file(dirname(__DIR__).'/public/'.$new));
    $fileList=glob(dirname(__DIR__).'/public/uploads/products/*');sort($fileList);$before=catalogSnapshot($pdo);
    $pdo->exec('CREATE TRIGGER catalog_module_audit_fault BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT="Synthetic audit fault"');
    try{
        $r=catalogUpload($staff,['_token'=>$staff['csrf'],...$edit($imageId)],'fault.png',$png);$afterFiles=glob(dirname(__DIR__).'/public/uploads/products/*');sort($afterFiles);
        verifyHair('Failed dependent image save rolls back and removes new upload',$r['code']===422&&$before===catalogSnapshot($pdo)&&$fileList===$afterFiles);
        verifyHair('Stock and audit fail atomically',hairRequest('/catalog/inventory',$staff,['_token'=>$staff['csrf'],'id'=>(string)$pid,'mode'=>'add','stock_qty'=>'1','expected_stock'=>(string)$model->managementFind($pid)['stock_qty'],'reason'=>'Synthetic failure'])['code']===422);
        verifyHair('Audit-failed stock leaves original rows intact',$before===catalogSnapshot($pdo));
    }finally{$pdo->exec('DROP TRIGGER catalog_module_audit_fault');}
    App\Core\Database::transaction(function(PDO $p)use($adminId):void{App\Services\AuditService::record($p,$adminId,'security.synthetic_secret','user',$adminId,['note'=>'PRIVATE_EVENT_SHOULD_NOT_APPEAR']);});
    $activity=$model->recentCatalogActivity(999);
    verifyHair('Catalog activity is bounded and contains no raw details',count($activity)<=20&&!array_filter($activity,fn($e)=>array_key_exists('details',$e)||str_starts_with($e['action'],'security.')||str_starts_with($e['action'],'order.')));
    $dashboard=hairRequest('/catalog',$staff);
    verifyHair('Dashboard shows operational events without general audit access',$dashboard['code']===200&&str_contains($dashboard['body'],'Recent catalog activity')&&!str_contains($dashboard['body'],'PRIVATE_EVENT_SHOULD_NOT_APPEAR'));
    verifyHair('One exact active staff navigation label',substr_count(hairRequest('/catalog/inventory',$staff)['body'],'aria-current="page"')===1);

    catalogCleanup($pdo,$prefix,$orders,$customer);
    verifyHair('Original twenty products, stock and image paths preserved',$productsBefore===$pdo->query('SELECT * FROM products ORDER BY id')->fetchAll());
    verifyHair('Original order history preserved',$originalOrders===$pdo->query('SELECT * FROM orders ORDER BY id')->fetchAll());
    verifyHair('Original reviews preserved',$originalReviews===$pdo->query('SELECT * FROM product_reviews ORDER BY id')->fetchAll());
    verifyHair('Original suitability links preserved',$originalTags===$pdo->query('SELECT * FROM product_tags ORDER BY product_id,tag_id')->fetchAll());
    verifyHair('Security policy preserved',$policyBefore===$pdo->query('SELECT * FROM security_settings ORDER BY setting_key')->fetchAll());
    $result=['checks'=>$checks,'pass'=>count(array_filter($checks,fn($r)=>$r['status']==='PASS')),'fail'=>count(array_filter($checks,fn($r)=>$r['status']==='FAIL'))];
    if(isset($o['output']))file_put_contents($o['output'],json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    ob_end_flush();exit($result['fail']?1:0);
}catch(Throwable $e){
    if(isset($pdo,$prefix,$orders,$customer))catalogCleanup($pdo,$prefix,$orders,$customer);
    fwrite(STDERR,'Catalog tests stopped: '.get_class($e).' at line '.$e->getLine().'; private values suppressed.'.PHP_EOL);exit(1);
}
