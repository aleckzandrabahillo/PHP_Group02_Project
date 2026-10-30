<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/scripts/assign_demo_images.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
ob_start(); // CLI session factories must run before output is flushed.
ini_set('session.use_cookies', '0');
ini_set('session.cache_limiter', '');
$checks = [];
function verify(string $test, bool $ok): void {
    global $checks; $checks[] = ['test'=>$test,'status'=>$ok?'PASS':'FAIL'];
    echo ($ok?'PASS: ':'FAIL: ') . $test . PHP_EOL;
}
function state(PDO $pdo): array {
    $out=[];foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table){
        if(!preg_match('/^[a-z_]+$/D',$table))throw new RuntimeException();
        $rows=[];foreach($pdo->query('SELECT * FROM '.$table)->fetchAll() as $row)$rows[]=hash('sha256',json_encode($row));
        sort($rows);$out[$table]=hash('sha256',implode('',$rows));
    }return $out;
}
function testClient(int $id): array {
    session_name((string)env('SESSION_NAME'));session_id('');session_start();
    $u=(new App\Models\User())->findById($id);
    $_SESSION=['auth_user'=>['id'=>$id,'role'=>$u['role'],'email'=>$u['email'],'username'=>$u['username']],'last_activity'=>time(),'_csrf'=>bin2hex(random_bytes(32))];
    $client=['cookie'=>session_name().'='.session_id(),'csrf'=>$_SESSION['_csrf']];
    session_write_close();return $client;
}
function request(string $path, ?array $client=null, ?array $data=null): array {
    global $base;
    $headers="Connection: close\r\n";if($client)$headers.='Cookie: '.$client['cookie']."\r\n";
    $opts=['method'=>$data===null?'GET':'POST','timeout'=>15,'ignore_errors'=>true,'follow_location'=>0,'header'=>$headers];
    if($data!==null){$opts['content']=http_build_query($data);$opts['header'].="Content-Type: application/x-www-form-urlencoded\r\n";}
    $body=file_get_contents($base.$path,false,stream_context_create(['http'=>$opts]));
    $h=$http_response_header??[];preg_match('/\s([0-9]{3})\s/',$h[0]??'',$m);
    $location='';foreach($h as $line)if(str_starts_with(strtolower($line),'location:'))$location=trim(substr($line,9));
    return ['code'=>(int)($m[1]??0),'body'=>(string)$body,'location'=>$location,
        'no_store'=>(bool)array_filter($h,fn($line)=>str_starts_with(strtolower($line),'cache-control:')&&str_contains($line,'no-store'))];
}
function form(array $client): array {
    $r=request('/checkout',$client);if($r['code']!==200)throw new RuntimeException('Checkout form fixture not ready.');
    preg_match('/name="checkout_key" value="([a-f0-9]{64})"/',$r['body'],$m);
    return ['_token'=>$client['csrf'],'checkout_key'=>$m[1]??'','country'=>'PH','first_name'=>'Synthetic','last_name'=>'Recipient','contact_no'=>'09123456789','address_line'=>'12 Test Street','barangay'=>'Test Barangay','postal_code'=>'1600','city'=>'Pasig','region'=>'NCR','province'=>'Metro Manila','shipping_method'=>'standard','payment_method'=>'cod'];
}
function reject(string $name, callable $fn, PDO $pdo): void {
    $before=state($pdo);$denied=false;try{$fn();}catch(InvalidArgumentException){$denied=true;}
    verify($name,$denied&&state($pdo)===$before);
}
final class CheckoutFaultPDO extends PDO {
    public string $failAt='audit';
    public function prepare(string $query,array $options=[]): PDOStatement|false {
        if(($this->failAt==='audit'&&str_starts_with($query,'INSERT INTO audit_logs'))
            ||($this->failAt==='items'&&str_starts_with($query,'INSERT INTO order_items'))
            ||($this->failAt==='convert'&&str_starts_with($query,'UPDATE carts SET status="converted"')))throw new PDOException('Synthetic transactional fault.');
        return parent::prepare($query,$options);
    }
}
try {
    $o=getopt('',['expect-database:','expect-port:','expect-datadir:','base-url:','session-directory:','scratch-directory:','output:','worker:','fixture:']);
    $pdo=App\Core\Database::connection();$s=$pdo->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch();
    $norm=static fn($x)=>strtolower(rtrim(str_replace('\\','/',$x),'/'));
    $base=$o['base-url']??'';$url=parse_url($base);
    $scratch=realpath($o['scratch-directory']??'');$sessions=realpath($o['session-directory']??'');
    if(!preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D',$s['db'])||($o['expect-database']??'')!==$s['db']
        ||(int)($o['expect-port']??0)!==(int)$s['port']||in_array((int)$s['port'],[3306,13307],true)
        ||$norm($o['expect-datadir']??'')!==$norm($s['datadir'])||env('DB_HOST')!=='127.0.0.1'||env('APP_ENV')!=='development'
        ||!$scratch||!$sessions||str_starts_with($norm($scratch),$norm(dirname(__DIR__)).'/')
        ||str_starts_with($norm($sessions),$norm(dirname(__DIR__)).'/')||($url['host']??'')!=='127.0.0.1'
        ||($url['scheme']??'')!=='http'||in_array((int)($url['port']??80),[80,18080],true))throw new RuntimeException('Disposable-only guard stopped.');
    ini_set('session.save_path',$sessions);
    $service=new App\Services\CheckoutService();$cart=new App\Models\Cart();$users=new App\Models\User();
    if(isset($o['worker'])){
        $file=realpath($o['worker']);if(!$file||!str_starts_with($norm($file),$norm($scratch).'/'))throw new RuntimeException();
        $job=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
        try{
            if(($job['operation']??'place')==='add') { $quantity=$cart->add($job['user'],$job['product'],1);echo json_encode(['ok'=>true,'quantity'=>$quantity]); }
            else {$id=$service->place($job['user'],$job['input'],$job['key'],$job['signature']);echo json_encode(['ok'=>true,'order'=>$id]);}
        }
        catch(InvalidArgumentException){echo json_encode(['ok'=>false]);}
        exit;
    }
    $fixture=$o['fixture']??'demo20';
    if(!in_array($fixture,['demo20','fresh6'],true))throw new RuntimeException('Unknown catalog fixture.');
    $expectedCount=$fixture==='fresh6'?6:20;
    verify('Disposable target guard and expected catalog fixture',(int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn()===$expectedCount);
    $demoBefore=$pdo->query('SELECT * FROM products ORDER BY id')->fetchAll();
    $identity=[];$suffix='_'.bin2hex(random_bytes(4));foreach(['checkout_a','checkout_b','checkout_other','checkout_browser'] as $name){
        $name .= $suffix;
        $id=$users->createCustomer(['username'=>$name,'email'=>$name.'@example.invalid','full_name'=>'Synthetic Checkout Customer','contact_no'=>'+639123456789','password_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);
        $users->activate($id);$identity[$name]=$id;
    }
    [$a,$b,$other,$browser]=array_values($identity);
    $qa=testClient($a);$qb=testClient($b);$qo=testClient($other);
    $cm=(int)$users->findByIdentifier('image_catalog_manager')['id'];$admin=(int)$users->findByIdentifier('image_admin')['id'];
    foreach(['/checkout','/orders','/orders/view?id=1'] as $path)verify('Guest denied '.$path,request($path)['location']==='/login');
    verify('Guest direct checkout POST denied',request('/checkout',null,['payment_method'=>'cod'])['location']==='/login');
    foreach([$cm,$admin] as $staff){
        $c=testClient($staff);
        foreach(['/checkout','/orders','/orders/view?id=1'] as $path)verify('Staff role '.$users->findById($staff)['role'].' denied '.$path,request($path,$c)['code']===403);
        verify('Staff direct checkout POST denied',request('/checkout',$c,['_token'=>$c['csrf']])['code']===403);
    }
    verify('Empty bag redirects to bag',request('/checkout',$qa)['location']==='/cart');
    $productSku=$fixture==='fresh6'?'AVE-SMP-001':'AVE-SMP-002';
    $q=$pdo->prepare('SELECT id FROM products WHERE sku=?');$q->execute([$productSku]);$p=(int)$q->fetchColumn();
    $p2=(int)$pdo->query("SELECT id FROM products WHERE sku='AVE-TOL-001'")->fetchColumn();
    $cart->add($a,$p,2);$cart->add($a,$p2,1);$f=form($qa);
    verify('Checkout form has CSRF, submission key and COD',strlen($f['checkout_key'])===64&&str_contains(request('/checkout',$qa)['body'],'Cash on delivery'));
    verify('Checkout response prevents private page caching',request('/checkout',$qa)['no_store']);
    $before=state($pdo);
    $cartUpdate=request('/cart/update',$qa,['_token'=>$qa['csrf'],'product_id'=>$p,'quantity'=>'3']);
    verify('Existing cart HTTP update still works',$cartUpdate['location']==='/cart'&&$cart->contentsForUser($a)['item_count']===4);
    $cart->update($a,$p,2);
    verify('Cart update invalid quantities rejected',request('/cart/update',$qa,['_token'=>$qa['csrf'],'product_id'=>$p,'quantity'=>'100'])['location']==='/cart'&&$cart->contentsForUser($a)['item_count']===3);
    $unauthorized=request('/cart/remove',$qo,['_token'=>$qo['csrf'],'product_id'=>$p]);
    verify('Other customer cannot mutate bag',$cart->contentsForUser($a)['item_count']===3);
    $cartAdd=request('/cart/add',$qa,['_token'=>$qa['csrf'],'product_id'=>$p2,'quantity'=>'1']);
    verify('Existing cart HTTP add still works',$cartAdd['location']==='/cart'&&$cart->contentsForUser($a)['item_count']===4);
    request('/cart/remove',$qa,['_token'=>$qa['csrf'],'product_id'=>$p2]);
    verify('Existing cart HTTP remove still works',$cart->contentsForUser($a)['item_count']===2);
    $cart->add($a,$p2,1);$f=form($qa);
    foreach([null,'wrong',['bad']] as $token){
        $before=state($pdo);$bad=$f;if($token===null)unset($bad['_token']);else $bad['_token']=$token;
        verify('Missing / invalid / array CSRF rejected',request('/checkout',$qa,$bad)['code']===419&&state($pdo)===$before);
    }
    $cases=['empty name'=>['first_name'=>''],'long name'=>['first_name'=>str_repeat('a',61)],'array name'=>['first_name'=>['bad']],'invalid UTF8'=>['first_name'=>chr(255)],
        'control name'=>['first_name'=>"Test\x00Name"],'blank unicode name'=>['first_name'=>"\u{00A0}"],
        'empty phone'=>['contact_no'=>''],'bad phone'=>['contact_no'=>'not a phone'],'long phone'=>['contact_no'=>str_repeat('9',26)],
        'empty address'=>['address_line'=>''],'long address'=>['address_line'=>str_repeat('x',201)],
        'array address'=>['address_line'=>['bad']],'control address'=>['address_line'=>"Bad\x00address"],
        'gateway'=>['payment_method'=>'card'],'manual not offered'=>['payment_method'=>'manual'],'array payment'=>['payment_method'=>['cod']],
        'missing key'=>['checkout_key'=>''],'array key'=>['checkout_key'=>['bad']],'wrong key'=>['checkout_key'=>str_repeat('f',64)]];
    foreach($cases as $label=>$bad){$before=state($pdo);$r=request('/checkout',$qa,array_replace($f,$bad));verify('Invalid '.$label.' rejected with no writes',$r['code']===422&&state($pdo)===$before);}
    $escaped=request('/checkout',$qa,array_replace($f,['first_name'=>'<script>alert(1)</script>','contact_no'=>'invalid']));
    verify('Invalid submitted text is encoded',str_contains($escaped['body'],'&lt;script&gt;')&&!str_contains($escaped['body'],'<script>alert(1)</script>'));
    $limits=App\Core\CheckoutValidator::validate([...$f,'first_name'=>str_repeat('é',59),'last_name'=>str_repeat('é',60),'address_line'=>str_repeat('界',200),'barangay'=>str_repeat('界',100),'city'=>str_repeat('界',100),'postal_code'=>'2500','region'=>'R1','province'=>'La Union']);
    verify('Schema character limits accept valid multi-byte input',$limits['errors']===[]);
    // Only private synthetic session state is aged; application authentication is unchanged.
    session_id(substr($qa['cookie'],strpos($qa['cookie'],'=')+1));session_start();$_SESSION['checkout_intent']['issued_at']=time()-1801;session_write_close();
    $before=state($pdo);
    verify('Expired checkout intent rejected without writes',request('/checkout',$qa,$f)['code']===422&&state($pdo)===$before);
    $f=form($qa);
    $quote=$service->quote($a);$origP=$pdo->query('SELECT * FROM products WHERE id='.$p)->fetch();
    $cart->update($a,$p,1);
    reject('Changed cart invalidates displayed quote',fn()=>$service->place($a,$f,$f['checkout_key'],$quote['signature']),$pdo);
    $cart->update($a,$p,2);
    $pdo->exec('UPDATE cart_items SET quantity=100 WHERE cart_id IN (SELECT id FROM carts WHERE user_id='.$a.' AND status="active") AND product_id='.$p);
    reject('Oversized stored quantity rejected safely',fn()=>$service->quote($a),$pdo);
    $cart->update($a,$p,2);
    $priceTwo=$pdo->query('SELECT price,updated_at FROM products WHERE id='.$p2)->fetch();
    $pdo->exec('UPDATE products SET price=0.10 WHERE id='.$p);$pdo->exec('UPDATE products SET price=0.20 WHERE id='.$p2);
    verify('Exact integer-cent arithmetic',$service->quote($a)['subtotal']==='0.40');
    $pdo->exec('UPDATE products SET price=99999999.99 WHERE id='.$p);
    reject('Order total exceeding DECIMAL limit rejected',fn()=>$service->quote($a),$pdo);
    $pdo->prepare('UPDATE products SET price=?,updated_at=? WHERE id=?')->execute([$origP['price'],$origP['updated_at'],$p]);
    $pdo->prepare('UPDATE products SET price=?,updated_at=? WHERE id=?')->execute([$priceTwo['price'],$priceTwo['updated_at'],$p2]);
    $pdo->prepare('UPDATE products SET price=price+1 WHERE id=?')->execute([$p]);
    reject('Changed price invalidates displayed quote',fn()=>$service->place($a,$f,$f['checkout_key'],$quote['signature']),$pdo);
    $changed=request('/checkout',$qa,$f);verify('Stale browser quote returns updated summary',$changed['code']===409&&str_contains($changed['body'],'changed'));
    $pdo->prepare('UPDATE products SET price=?,updated_at=? WHERE id=?')->execute([$origP['price'],$origP['updated_at'],$p]);$f=form($qa);$quote=$service->quote($a);
    foreach(['product inactive','category inactive','insufficient stock'] as $case){
        if($case==='product inactive')$pdo->exec('UPDATE products SET status="inactive" WHERE id='.$p);
        elseif($case==='category inactive')$pdo->exec('UPDATE categories SET status="inactive" WHERE id='.(int)$origP['category_id']);
        else $pdo->exec('UPDATE products SET stock_qty=1 WHERE id='.$p);
        reject($case.' blocks order',fn()=>$service->place($a,$f,$f['checkout_key'],$quote['signature']),$pdo);
        $pdo->prepare('UPDATE products SET status=?,stock_qty=?,updated_at=? WHERE id=?')->execute([$origP['status'],$origP['stock_qty'],$origP['updated_at'],$p]);
        $pdo->exec('UPDATE categories SET status="active" WHERE id='.(int)$origP['category_id']);
    }
    foreach(['inactive','locked','staff'] as $case){
        $pdo->prepare('UPDATE users SET status=?,role=?,locked_until=? WHERE id=?')->execute([$case==='inactive'?'inactive':'active',$case==='staff'?'catalog_manager':'customer',$case==='locked'?date('Y-m-d H:i:s',time()+3600):null,$a]);
        reject('Current '.$case.' customer rejected in transaction',fn()=>$service->place($a,$f,$f['checkout_key'],$quote['signature']),$pdo);
        $pdo->prepare('UPDATE users SET status="active",role="customer",locked_until=NULL WHERE id=?')->execute([$a]);
    }
    $fault=new CheckoutFaultPDO('mysql:host=127.0.0.1;port='.$s['port'].';dbname='.$s['db'].';charset=utf8mb4',(string)env('DB_USERNAME'),(string)env('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    foreach(['items','convert','audit'] as $stage){
        $fault->failAt=$stage;$before=state($pdo);$failed=false;
        try{(new App\Services\CheckoutService($fault))->place($a,$f,$f['checkout_key'],$quote['signature']);}catch(PDOException){$failed=true;}
        verify('Injected '.$stage.' failure rolls back order, items, stock, cart and audit',$failed&&state($pdo)===$before);
    }
    $f=form($qa);$beforeOrder=state($pdo);$profileBefore=$users->profileFor($a);
    $f['first_name']='<b>Literal';$f['last_name']='Recipient</b>';$f['address_line']='<script>literal</script>';
    $placed=request('/checkout',$qa,[...$f,'total'=>'0.01','subtotal'=>'0.01','price'=>'0.01','user_id'=>$other,'product_id'=>$p2,'quantity'=>'99']);
    preg_match('/id=([0-9]+)/',$placed['location'],$m);$orderId=(int)($m[1]??0);
    verify('Valid real POST creates order and redirects 303',$placed['code']===303&&$orderId>0);
    $order=(new App\Models\Order())->owned($orderId,$a);
    verify('Server totals ignore submitted price/quantity/customer',is_array($order)&&$order['total']===number_format((float)$quote['subtotal']+80,2,'.','')&&$order['delivery']['shipping_fee']==='80.00'&&$order['user_id']==$a&&count($order['items'])===2);
    verify('Recipient and contact snapshots stored, profile unchanged',$order['recipient_name_snapshot']===$f['first_name'].' '.$f['last_name']&&$order['contact_no_snapshot']==='+639123456789'&&$order['contact_email_snapshot']===$profileBefore['email']&&$users->profileFor($a)===$profileBefore);
    verify('Pending COD order and exact price snapshots',$order['status']==='pending'&&$order['payment_method']==='cod'&&$order['items'][0]['unit_price_snapshot']===$quote['items'][0]['price']);
    verify('Stock deducted exactly once',(int)$pdo->query('SELECT stock_qty FROM products WHERE id='.$p)->fetchColumn()===(int)$origP['stock_qty']-2);
    verify('Active bag converted and emptied',$cart->contentsForUser($a)['items']===[]);
    verify('Exactly one transactional non-PII audit',(int)$pdo->query('SELECT COUNT(*) FROM audit_logs WHERE action="order.create" AND target_id='.$orderId)->fetchColumn()===1
        &&!str_contains((string)$pdo->query('SELECT details FROM audit_logs WHERE action="order.create" AND target_id='.$orderId)->fetchColumn(),$f['address_line']));
    $after=state($pdo);$repeat=request('/checkout',$qa,$f);
    verify('Repeated POST returns same order without writes',$repeat['location']===$placed['location']&&state($pdo)===$after);
    $displayReference=\App\Services\OrderReference::display((new \App\Models\Order())->owned($orderId,$a));
    verify('Order history displays own order reference',str_contains(request('/orders',$qa)['body'],$displayReference));
    verify('Other customer cannot read order',request('/orders/view?id='.$orderId,$qo)['code']===404);
    verify('Unknown/malformed order IDs return 404',request('/orders/view?id[]=1',$qa)['code']===404&&request('/orders/view?id=99999999',$qa)['code']===404);
    verify('Other history does not reveal order',!str_contains(request('/orders',$qo)['body'],$displayReference));
    $detail=request('/orders/view?id='.$orderId,$qa);
    verify('Order details prevent private page caching',$detail['no_store']);
    verify('Stored order text is safely encoded',str_contains($detail['body'],'&lt;b&gt;Literal Recipient&lt;/b&gt;')&&str_contains($detail['body'],'&lt;script&gt;literal&lt;/script&gt;')&&!str_contains($detail['body'],'<script>literal</script>'));
    $pdo->prepare('UPDATE products SET name="Changed catalog name",price=price+9 WHERE id=?')->execute([$p]);
    verify('Order retains original name/price despite catalog changes',(new App\Models\Order())->owned($orderId,$a)['items']===$order['items']);
    $pdo->prepare('UPDATE products SET name=?,price=?,updated_at=? WHERE id=?')->execute([$origP['name'],$origP['price'],$origP['updated_at'],$p]);
    $cart->add($a,$p,1);$newForm=form($qa);$after=state($pdo);
    verify('Old successful submission cannot consume new bag',request('/checkout',$qa,$f)['location']===$placed['location']&&state($pdo)===$after);
    // Concurrent processes bypass PHP's session-file serialization and exercise real InnoDB locks.
    $guards=['--expect-database='.$s['db'],'--expect-port='.$s['port'],'--expect-datadir='.$s['datadir'],'--base-url='.$base,'--session-directory='.$sessions,'--scratch-directory='.$scratch];
    $launch=static function(string $file)use($guards):array{$p=proc_open([PHP_BINARY,__FILE__,...$guards,'--worker='.$file],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);return[$p,$pipes];};
    $finish=static function(array $job):array{[$p,$pipes]=$job;$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);if($exit||$err)throw new RuntimeException('Concurrent worker failed privately.');return json_decode($out,true,512,JSON_THROW_ON_ERROR);};
    $job=['user'=>$a,'input'=>$newForm,'key'=>$newForm['checkout_key'],'signature'=>$service->quote($a)['signature']];
    file_put_contents($scratch.'/same.json',json_encode($job));$startOrders=(int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();$stockBefore=(int)$pdo->query('SELECT stock_qty FROM products WHERE id='.$p)->fetchColumn();
    $j1=$launch($scratch.'/same.json');$j2=$launch($scratch.'/same.json');$r1=$finish($j1);$r2=$finish($j2);
    verify('Concurrent same-key submits return one order',$r1['ok']&&$r2['ok']&&$r1['order']===$r2['order']&&(int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn()===$startOrders+1&&(int)$pdo->query('SELECT stock_qty FROM products WHERE id='.$p)->fetchColumn()===$stockBefore-1);
    $cart->add($a,$p2,1);$cart->add($b,$p2,1);$qaQuote=$service->quote($a);$qbQuote=$service->quote($b);
    $pdo->exec('UPDATE products SET stock_qty=1 WHERE id='.$p2);
    foreach([[$a,$qaQuote,'one'],[$b,$qbQuote,'two']] as [$who,$q,$file])file_put_contents($scratch.'/'.$file.'.json',json_encode(['user'=>$who,'input'=>$newForm,'key'=>bin2hex(random_bytes(32)),'signature'=>$q['signature']]));
    $startOrders=(int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $j1=$launch($scratch.'/one.json');$j2=$launch($scratch.'/two.json');$r1=$finish($j1);$r2=$finish($j2);
    verify('Concurrent customers cannot oversell final stock',((int)$r1['ok']+(int)$r2['ok'])===1&&(int)$pdo->query('SELECT stock_qty FROM products WHERE id='.$p2)->fetchColumn()===0&&(int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn()===$startOrders+1);
    $pdo->exec('UPDATE products SET stock_qty=200 WHERE id='.$p);
    $cart->add($other,$p,99);
    reject('Cumulative bag additions cannot exceed 99',fn()=>$cart->add($other,$p,1),$pdo);
    $cart->remove($other,$p);
    foreach([1,1] as $qty){
        $pdo->prepare('INSERT INTO carts (user_id,status) VALUES (?,"active")')->execute([$other]);$cid=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO cart_items (cart_id,product_id,quantity) VALUES (?,?,?)')->execute([$cid,$p,$qty]);
    }
    $legacyQuote=$service->quote($other);
    verify('Legacy multiple active carts aggregate safely',count($legacyQuote['items'])===1&&$legacyQuote['items'][0]['quantity']===2);
    $legacyOrder=$service->place($other,$newForm,bin2hex(random_bytes(32)),$legacyQuote['signature']);
    verify('Legacy carts convert together with one item snapshot',count((new App\Models\Order())->owned($legacyOrder,$other)['items'])===1&&$cart->contentsForUser($other)['items']===[]);
    $cart->add($other,$p,1);$raceQuote=$service->quote($other);
    file_put_contents($scratch.'/place-race.json',json_encode(['user'=>$other,'input'=>$newForm,'key'=>bin2hex(random_bytes(32)),'signature'=>$raceQuote['signature']]));
    file_put_contents($scratch.'/add-race.json',json_encode(['operation'=>'add','user'=>$other,'product'=>$p2]));
    $pdo->exec('UPDATE products SET stock_qty=20 WHERE id='.$p2);
    $beforeRace=(int)$pdo->query('SELECT COUNT(*) FROM orders WHERE user_id='.$other)->fetchColumn();
    $j1=$launch($scratch.'/place-race.json');$j2=$launch($scratch.'/add-race.json');$racePlace=$finish($j1);$raceAdd=$finish($j2);
    $bagAfter=$cart->contentsForUser($other);
    $newItems=0;
    if($racePlace['ok'])foreach((new App\Models\Order())->owned($racePlace['order'],$other)['items'] as $item)$newItems+=(int)$item['quantity'];
    verify('Concurrent cart addition and checkout lose no items',$raceAdd['ok']&&$newItems+$bagAfter['item_count']===2);
    // Restore original demonstration catalog values; only synthetic customer orders remain.
    foreach($demoBefore as $row)$pdo->prepare('UPDATE products SET name=?,price=?,stock_qty=?,updated_at=? WHERE id=?')->execute([$row['name'],$row['price'],$row['stock_qty'],$row['updated_at'],$row['id']]);
    verify('All twenty demonstration product rows restored',$pdo->query('SELECT * FROM products ORDER BY id')->fetchAll()===$demoBefore);
    $cart->add($browser,$p,2);$cart->add($browser,$p2,1);
    file_put_contents($scratch.'/browser-user.json',json_encode(['id'=>$browser,'username'=>$users->findById($browser)['username']],JSON_THROW_ON_ERROR));
    verify('Public shop and details remain available',request('/shop')['code']===200&&request('/product?id='.$p)['code']===200);
    $staffClient=testClient($cm);
    verify('Catalog Manager product/inventory/category pages remain available',request('/catalog/products',$staffClient)['code']===200&&request('/catalog/inventory',$staffClient)['code']===200&&request('/catalog/categories',$staffClient)['code']===200);
    verify('Catalog Manager admin restriction preserved',request('/admin/security',$staffClient)['code']===403);
    for($i=0;$i<12;$i++)$pdo->prepare('INSERT INTO orders(user_id,delivery_address_snapshot,payment_method,subtotal,total,status) VALUES (?,"Synthetic legacy delivery","manual",0,0,"shipped")')->execute([$other]);
    $pages=(new App\Models\Order())->forCustomer($other,99999999);
    verify('History pagination clamps to populated last page',$pages['page']===$pages['pages']&&count($pages['items'])>0&&count($pages['items'])<=10);
    verify('History page zero/array inputs are safe',request('/orders?page=0',$qo)['code']===200&&request('/orders?page[]=1',$qo)['code']===200);
    $legacyId=(int)$pdo->query('SELECT MAX(id) FROM orders WHERE user_id='.$other)->fetchColumn();
    $legacyPage=request('/orders/view?id='.$legacyId,$qo);
    verify('Legacy orders retain status and explain absent snapshots',$legacyPage['code']===200&&str_contains($legacyPage['body'],'Order shipped')&&str_contains($legacyPage['body'],'Recipient was not recorded'));
    $beforeStatus=state($pdo);
    verify('Customers cannot submit order status changes',request('/orders',$qo,['_token'=>$qo['csrf'],'status'=>'delivered'])['code']===405&&state($pdo)===$beforeStatus);
    $logs=new App\Services\LogService();$otp=new App\Services\OtpService(new App\Services\MailService(),$logs);
    $auth=new App\Services\AuthService($users,$otp,$logs);
    foreach(['customer','catalog_manager','admin'] as $role){
        $username='qa_auth_'.$role.$suffix;$email=$username.'@example.invalid';$password='Qa1!'.bin2hex(random_bytes(20));
        if($role==='customer'){
            $uid=$users->createCustomer(['username'=>$username,'email'=>$email,'full_name'=>'Synthetic Auth Customer','contact_no'=>'+639123456789','password_hash'=>password_hash($password,PASSWORD_DEFAULT)]);
            $users->activate($uid);
        }else $uid=$users->createStaff($role,$email,$username,password_hash($password,PASSWORD_DEFAULT),'Synthetic Auth Staff');
        session_id('');App\Core\Session::start();$oldSession=session_id();
        $mailFile=dirname(__DIR__).'/storage/logs/dev_mail.log';$offset=is_file($mailFile)?filesize($mailFile):0;
        $login=$auth->attempt($username,$password);
        verify($role.' unenrolled login does not issue an email challenge',($login['next']??'')==='dashboard'&&(int)$pdo->query('SELECT COUNT(*) FROM otp_codes WHERE user_id='.$uid)->fetchColumn()===0);
        verify('Normal '.$role.' service login completes',($login['ok']??false)&&App\Core\Session::get('auth_user')['id']===$uid&&session_id()!==$oldSession);
        session_write_close();unset($password);
    }
    $fail=count(array_filter($checks,fn($x)=>$x['status']==='FAIL'));
    $report=['type'=>'Actual guarded disposable PDO, HTTP and concurrent CLI checks; HTTP uses synthetic sessions, not browser password/CAPTCHA login. Fault tests inject prepare failures.','pass'=>count($checks)-$fail,'fail'=>$fail,'checks'=>$checks];
    if(isset($o['output']))file_put_contents($o['output'],json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo json_encode(['pass'=>$report['pass'],'fail'=>$fail]),PHP_EOL;exit($fail?1:0);
} catch(Throwable $e){fwrite(STDERR,'Checkout harness stopped; private diagnostics suppressed. '.get_class($e).' line '.$e->getLine().PHP_EOL);exit(1);}
