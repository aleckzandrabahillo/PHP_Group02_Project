<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('AVELA_HAIR_TEST_LIBRARY',true);require __DIR__.'/hair_profiles.php';
try {
    $o=getopt('',['expect-database:','expect-port:','expect-datadir:','base-url:','session-directory:','output:']);
    $pdo=App\Core\Database::connection();$identity=$pdo->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch();
    $base=$o['base-url']??'';$target=parse_url($base);$sessions=realpath($o['session-directory']??'');
    $norm=static fn($v)=>strtolower(rtrim(str_replace('\\','/',$v),'/'));
    if(($o['expect-database']??'')!==$identity['db'] || !preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D',$identity['db'])
        || (int)$identity['port']!==13427 || (int)($o['expect-port']??0)!==13427 || env('DB_HOST')!=='127.0.0.1'
        || $norm($identity['datadir'])!==$norm($o['expect-datadir']??'') || env('APP_ENV')!=='development'
        || env('MAIL_DRIVER')!=='log' || ($target['host']??'')!=='127.0.0.1' || ($target['scheme']??'')!=='http'
        || in_array((int)($target['port']??0),[0,80,18080],true) || !$sessions
        || str_starts_with($norm($sessions),$norm(dirname(__DIR__)).'/'))throw new RuntimeException('Disposable-only identity guard stopped.');
    ini_set('session.save_path',$sessions);$checks=[];
    $product=$pdo->query('SELECT p.* FROM products p JOIN categories c ON c.id=p.category_id WHERE p.status="active" AND c.status="active" AND p.stock_qty>=2 ORDER BY p.id LIMIT 1')->fetch();
    if(!$product)throw new RuntimeException('Synthetic product fixture unavailable.');
    $name='availability_'.bin2hex(random_bytes(6));$users=new App\Models\User();
    $uid=$users->createCustomer(['username'=>$name,'email'=>$name.'@example.invalid','full_name'=>'Synthetic Availability Customer','contact_no'=>'+639123456789','password_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);$users->activate($uid);
    $client=hairClient($uid);$cartModel=new App\Models\Cart();$pid=(int)$product['id'];$cartModel->add($uid,$pid,2);
    $change=$pdo->prepare('UPDATE products SET stock_qty=?,status=? WHERE id=?');
    try {
        verifyHair('Initial valid bag can proceed',$cartModel->contentsForUser($uid)['checkout_ready']);
        $change->execute([0,'active',$pid]);$cart=$cartModel->contentsForUser($uid);$fragment=hairRequest('/cart?panel=1',$client);
        verifyHair('Zero stock blocks checkout without deleting items',!$cart['checkout_ready']&&count($cart['items'])===1&&$cart['items'][0]['quantity']===2);
        verifyHair('Zero stock explains removal and preserves the original quantity',str_contains($fragment['body'],'Out of stock. Remove this item to continue to checkout.')&&str_contains($fragment['body'],'value="2" data-confirmed-quantity="2"'));
        verifyHair('Bag notifies that other items are preserved',str_contains($fragment['body'],'data-cart-availability role="status" >')&&str_contains($fragment['body'],'Your other items stay in your bag.'));
        verifyHair('Direct checkout still refuses unavailable inventory',in_array(hairRequest('/checkout',$client)['code'],[302,303],true));
        $headers="Connection: close\r\nAccept: application/json\r\nCookie: ".$client['cookie']."\r\nContent-Type: application/x-www-form-urlencoded\r\n";
        $post=['_token'=>$client['csrf'],'product_id'=>$pid,'quantity'=>1];
        $json=file_get_contents($base.'/cart/update',false,stream_context_create(['http'=>['method'=>'POST','ignore_errors'=>true,'follow_location'=>0,'header'=>$headers,'content'=>http_build_query($post),'timeout'=>15]]));$data=json_decode($json,true);
        verifyHair('Rejected stock update returns authoritative stock and a visible explanation',($data['ok']??true)===false&&$data['checkoutReady']===false&&$data['availability'][0]['stock']===0&&$data['availability'][0]['canAdjust']===false&&str_contains($data['availability'][0]['message'],'Out of stock'));
        verifyHair('Rejected quantity update does not silently remove or clamp the bag',$cartModel->contentsForUser($uid)['items'][0]['quantity']===2);
        $change->execute([1,'active',$pid]);$fragment=hairRequest('/cart?panel=1',$client);$cart=$cartModel->contentsForUser($uid);
        verifyHair('Partial stock has actionable quantity warning',str_contains($fragment['body'],'Only 1 left in stock. Reduce the quantity or remove this item')&&!$cart['checkout_ready']&&$cart['items'][0]['can_adjust']);
        $cartModel->update($uid,$pid,1);verifyHair('Explicit reduction restores checkout eligibility',$cartModel->contentsForUser($uid)['checkout_ready']);
        $change->execute([2,'inactive',$pid]);$fragment=hairRequest('/cart?panel=1',$client);
        verifyHair('Archived item remains removable with a distinct explanation',str_contains($fragment['body'],'This item is no longer available. Remove it to continue to checkout.')&&str_contains($fragment['body'],'action="/cart/remove"'));
        verifyHair('Removal still enforces CSRF',hairRequest('/cart/remove',$client,['_token'=>'invalid','product_id'=>$pid])['code']===419&&$cartModel->countForUser($uid)===1);
        verifyHair('Owner can explicitly remove the unavailable item',in_array(hairRequest('/cart/remove',$client,['_token'=>$client['csrf'],'product_id'=>$pid])['code'],[302,303],true)&&$cartModel->countForUser($uid)===0);
    } finally { $change->execute([(int)$product['stock_qty'],$product['status'],$pid]); }
    verifyHair('Synthetic inventory is restored after scenarios',$pdo->query('SELECT stock_qty FROM products WHERE id='.$pid)->fetchColumn()==$product['stock_qty']);
    $failed=array_filter($checks,static fn($c)=>$c['status']==='FAIL');if(isset($o['output']))file_put_contents($o['output'],json_encode(['checks'=>$checks],JSON_PRETTY_PRINT));
    echo count($checks)-count($failed).' PASS, '.count($failed).' FAIL'.PHP_EOL;exit($failed?1:0);
} catch(Throwable $e){fwrite(STDERR,'Bag availability harness stopped: '.get_class($e).PHP_EOL);exit(1);}
