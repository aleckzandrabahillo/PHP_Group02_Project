<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('AVELA_HAIR_TEST_LIBRARY',true);
require __DIR__.'/hair_profiles.php';
require dirname(__DIR__).'/vendor/autoload.php';

function followupUpload(array $client,string $bytes,string $path,string $field,array $fields=[]): array {
 global $base;$boundary='Avela'.bin2hex(random_bytes(16));$fields=['_token'=>$client['csrf'],...$fields];$body='';
 foreach($fields as $key=>$value)$body.='--'.$boundary."\r\nContent-Disposition: form-data; name=\"".$key."\"\r\n\r\n".$value."\r\n";
 $body.='--'.$boundary."\r\nContent-Disposition: form-data; name=\"".$field."\"; filename=\"photo.jpg\"\r\nContent-Type: image/jpeg\r\n\r\n".$bytes."\r\n--".$boundary."--\r\n";
 $result=file_get_contents($base.$path,false,stream_context_create(['http'=>['method'=>'POST','content'=>$body,'header'=>'Cookie: '.$client['cookie']."\r\nConnection: close\r\nContent-Type: multipart/form-data; boundary=".$boundary."\r\n",'ignore_errors'=>true,'follow_location'=>0,'timeout'=>20]]));
 preg_match('/ ([0-9]{3}) /',$http_response_header[0]??'',$m);return ['code'=>(int)($m[1]??0),'body'=>(string)$result];
}
try {
 $o=getopt('', ['expect-database:','expect-port:','expect-datadir:','base-url:','session-directory:','scratch-directory:','output:']);$pdo=App\Core\Database::connection();
 $id=$pdo->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch();$base=$o['base-url']??'';$url=parse_url($base);$sessions=realpath($o['session-directory']??'');
 $norm=static fn($p)=>strtolower(rtrim(str_replace('\\','/',$p),'/'));
 if(!preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D',$id['db'])||($o['expect-database']??'')!==$id['db']||(int)($o['expect-port']??0)!==(int)$id['port']||in_array((int)$id['port'],[3306,13307],true)||$norm($o['expect-datadir']??'')!==$norm($id['datadir'])||env('DB_HOST')!=='127.0.0.1'||env('APP_ENV')!=='development'||!$sessions||str_starts_with($norm($sessions),$norm(dirname(__DIR__)).'/')||($url['host']??'')!=='127.0.0.1'||in_array((int)($url['port']??80),[80,18080],true))throw new RuntimeException('Disposable-only guard');
 ini_set('session.save_path',$sessions);$checks=[];$users=new App\Models\User();
 $name='follow_'.bin2hex(random_bytes(5));$uid=$users->createCustomer(['username'=>$name,'email'=>$name.'@example.invalid','full_name'=>'Synthetic Followup','contact_no'=>'+639123456789','password_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);$users->activate($uid);$client=hairClient($uid);
 $admin=$users->findByIdentifier('image_admin');$manager=$users->findByIdentifier('image_catalog_manager');if(!$admin||!$manager)throw new RuntimeException('Staff fixtures missing');$staff=hairClient((int)$admin['id']);$catalog=hairClient((int)$manager['id']);
 $form=hairForm($client);$form['treatments']=[];hairRequest('/assessment',$client,$form);
 $profileBefore=(new App\Models\HairProfile())->forUser($uid);$entry=hairRequest('/assessment',$client);$retake=hairRequest('/assessment?edit=1',$client);
 verifyHair('Saved assessment offers results and retake',$entry['code']===200&&str_contains($entry['body'],'Explore my recommendations')&&str_contains($entry['body'],'Retake assessment')&&!str_contains($entry['body'],'data-assessment-form'));
 verifyHair('Saved profile has one clear heading and one primary result action',substr_count($entry['body'],'<h1')===1&&str_contains($entry['body'],'Hair Profile')&&str_contains($entry['body'],'Retake assessment'));
 verifyHair('Saved profile uses handoff texture illustration and recorded facts',str_contains($entry['body'],'class="profile-texture"')&&str_contains($entry['body'],'viewBox="0 0 145 225"')&&str_contains($entry['body'],'Lengths &amp; ends'));
 foreach(['hair_texture','scalp_type','hair_condition'] as $field)verifyHair('Saved profile displays recorded '.$field,str_contains($entry['body'],e(App\Core\HairProfileValidator::CHOICES[$field][$profileBefore[$field]])));
 verifyHair('Saved entry does not repeat raw concern description',!str_contains($entry['body'],e($form['concern_text'])));
 verifyHair('Absent treatments do not create empty detail panels',!str_contains($entry['body'],'class="saved-profile-details"'));
 $hairProfile=$profileBefore;$choices=App\Core\HairProfileValidator::CHOICES;$choices['hair_texture'][$hairProfile['hair_texture']]='<img src=x onerror=alert(1)>';
 ob_start();require dirname(__DIR__).'/app/Views/partials/saved_hair_profile.php';$encoded=ob_get_clean();verifyHair('Saved answer labels are HTML encoded',str_contains($encoded,'&lt;img src=x onerror=alert(1)&gt;')&&!str_contains($encoded,'<img src=x'));
 $hairProfile['hair_texture']='unknown';ob_start();require dirname(__DIR__).'/app/Views/partials/saved_hair_profile.php';$unknown=ob_get_clean();verifyHair('Unknown historical answer is shown honestly',str_contains($unknown,'Not specified'));
 verifyHair('Retake displays all inputs without changing saved profile',str_contains($retake['body'],'data-assessment-form')&&(new App\Models\HairProfile())->forUser($uid)===$profileBefore);
 verifyHair('Assessment section menu is optional and helper paragraphs are removed',!str_contains($retake['body'],'Jump to a section')&&str_contains($retake['body'],'data-quiz-step')&&!str_contains($retake['body'],'Think about your roots')&&!str_contains($retake['body'],'Optional. Select any'));
 $matches=hairRequest('/recommendations',$client);verifyHair('Results show profile summary and expandable matching explanations',$matches['code']===200&&str_contains($matches['body'],'Your saved hair profile')&&str_contains($matches['body'],'Matching details')&&str_contains($matches['body'],'Step details'));
 $shop=hairRequest('/shop?sort=price_desc&q=shampoo');verifyHair('Shop retains its own query and sort while header search stays general',str_contains($shop['body'],'name="q" value="shampoo"')&&str_contains($shop['body'],'name="sort" value="price_desc"')&&preg_match('~<form[^>]*action="/search"[^>]*class="nav-search-field"~',$shop['body'])&&!str_contains($shop['body'],'data-catalog-sort='));
 $page=(new App\Models\Product())->catalogPage('',[],'price_asc',1);verifyHair('SQL pagination clamps and bounds results',count($page['items'])===min(12,$page['total'])&&(new App\Models\Product())->catalogPage('',[],'price_asc',999)['page']===$page['pages']);
 $about=hairRequest('/about');verifyHair('Approved handoff About photography is used',$about['code']===200&&str_contains($about['body'],'editorial-v2/approach-textures.webp')&&str_contains($about['body'],'editorial-v2/approach-detail.webp')&&!str_contains($about['body'],'images/products/demo/avela-ave-smp-002.webp'));
 $permanent=trim(str_repeat('Saved address ',30));$pdo->prepare('UPDATE customer_profiles SET delivery_address=? WHERE user_id=?')->execute([$permanent,$uid]);
 $input=['country'=>'PH','first_name'=>'Another','last_name'=>'recipient','contact_no'=>'+639123456789','address_line'=>'12 Test Street','barangay'=>'Test Barangay','postal_code'=>'1600','city'=>'Pasig','region'=>'NCR','province'=>'Metro Manila','shipping_method'=>'standard','payment_method'=>'cod'];
 $checked=App\Core\CheckoutValidator::validate($input,$users->profileFor($uid));verifyHair('Editable delivery is independent of permanent profile',!$checked['errors']&&$checked['values']['delivery_address']!==$permanent);
 verifyHair('Malformed address source rejected',isset(App\Core\CheckoutValidator::validate([...$input,'address_source'=>['saved']])['errors']['address_source']));
 verifyHair('Blank profile still permits a complete delivery form',!App\Core\CheckoutValidator::validate($input,[])['errors']);
 $product=$pdo->query('SELECT * FROM products WHERE status="active" AND stock_qty>5 ORDER BY id LIMIT 1')->fetch();$pid=(int)$product['id'];
 hairRequest('/cart/add',$client,['_token'=>$client['csrf'],'product_id'=>$pid,'quantity'=>1]);$quote=(new App\Services\CheckoutService())->quote($uid);$key=bin2hex(random_bytes(32));
 $service=new App\Services\CheckoutService();$orderId=$service->place($uid,$input,$key,$quote['signature']);$order=(new App\Models\Order())->owned($orderId,$uid);
 verifyHair('Recipient and structured delivery snapshot recorded',$order['recipient_name_snapshot']==='Another recipient'&&$order['delivery_address_snapshot']===$checked['values']['delivery_address']);
 verifyHair('Checkout preserves permanent address',$users->profileFor($uid)['delivery_address']===$permanent);
 verifyHair('Creation records actual pending timeline',count($order['events'])===1&&$order['events'][0]['to_status']==='pending');
 verifyHair('Order replay does not duplicate order or email intent',$service->place($uid,$input,$key,$quote['signature'])===$orderId&&(int)$pdo->query('SELECT COUNT(*) FROM order_email_outbox WHERE order_id='.$orderId)->fetchColumn()===1);
 verifyHair('Other customer cannot read order',(new App\Models\Order())->owned($orderId,(int)$admin['id'])===null);
 $statusPost=['_token'=>$client['csrf'],'order_id'=>$orderId,'expected_status'=>'pending','status'=>'processing'];
 verifyHair('Customer cannot advance order',hairRequest('/admin/orders/status',$client,$statusPost)['code']===403);
 verifyHair('Catalog Manager cannot advance order',hairRequest('/admin/orders/status',$catalog,[...$statusPost,'_token'=>$catalog['csrf']])['code']===403);
 verifyHair('Administrator status POST enforces CSRF',hairRequest('/admin/orders/status',$staff,[...$statusPost,'_token'=>'bad'])['code']===419);
 $workflow=new App\Services\OrderWorkflow();$before=$order['status'];$rejected=false;try{$workflow->advance($pdo,(int)$admin['id'],$orderId,'pending','delivered');}catch(InvalidArgumentException){$rejected=true;}
 verifyHair('Status skips rejected without mutation',$rejected&&(new App\Models\Order())->owned($orderId,$uid)['status']===$before);
 foreach(['pending'=>'processing','processing'=>'shipped','shipped'=>'delivered'] as $from=>$to){$workflow->advance($pdo,(int)$admin['id'],$orderId,$from,$to);verifyHair('Valid recorded transition '.$from.' to '.$to,(new App\Models\Order())->owned($orderId,$uid)['status']===$to);}
 $stale=false;try{$workflow->advance($pdo,(int)$admin['id'],$orderId,'pending','processing');}catch(InvalidArgumentException){$stale=true;}verifyHair('Stale transition rejected',$stale);
 $order=(new App\Models\Order())->owned($orderId,$uid);verifyHair('Each actual transition has one timeline event',count($order['events'])===4);
 verifyHair('Timeline and status changes do not deduct inventory again',(int)$pdo->query('SELECT stock_qty FROM products WHERE id='.$pid)->fetchColumn()===(int)$product['stock_qty']-1);
 $detail=hairRequest('/orders/view?id='.$orderId,$client);verifyHair('Actual customer page displays timeline',$detail['code']===200&&str_contains($detail['body'],'Delivery progress')&&str_contains($detail['body'],'Order confirmed')&&str_contains($detail['body'],'Order delivered')&&str_contains($detail['body'],'<time datetime='));
 $queue=new App\Services\OrderEmailQueue($pdo);$messages=0;$result=$queue->deliverNext(function($mailOrder,$items)use(&$messages,$orderId){if((int)$mailOrder['id']!==$orderId||!$items)throw new RuntimeException('Wrong queued order');$messages++;(new App\Services\MailService())->sendOrderConfirmation($mailOrder,$items);},$orderId);
 verifyHair('Development confirmation generated through existing private mail transport',$result['status']==='sent'&&$messages===1);
 verifyHair('Repeated worker does not resend completed confirmation',$queue->deliverNext(function()use(&$messages){$messages++;},$orderId)===null&&$messages===1);
 $pdo->prepare('UPDATE order_email_outbox SET status="pending" WHERE order_id=?')->execute([$orderId]);
 $failure=$queue->deliverNext(static function(){throw new RuntimeException('Synthetic transport failure');},$orderId);
 verifyHair('Transport failure preserves order and requires review',$failure['status']==='failed'&&(new App\Models\Order())->owned($orderId,$uid)['status']==='delivered');
 verifyHair('Uncertain failure is not automatically retried',$queue->deliverNext(static function(){throw new RuntimeException('Must not send');},$orderId)===null);
 $jpg=file_get_contents(dirname(__DIR__).'/public/assets/images/editorial/about-portrait.jpg');
 foreach([2621439,2621440,2621441] as $size){$bytes=$jpg.str_repeat(' ',max(0,$size-strlen($jpg)));$old=$users->profileFor($uid)['profile_image'];$response=followupUpload($client,$bytes,'/profile/image','profile_image');$new=$users->profileFor($uid)['profile_image'];verifyHair('Profile upload size boundary '.$size,$response['code']===302&&($size<=2621440?$new!==$old:$new===$old));}
 verifyHair('Both image flows share 2.5 MB limit',App\Services\ProductImageService::MAX_BYTES===2621440);
 foreach([2621439,2621440,2621441] as $size){
  $sku='FOLLOW-UP-'.bin2hex(random_bytes(5));$bytes=$jpg.str_repeat(' ',max(0,$size-strlen($jpg)));
  $data=['action'=>'create','name'=>'Synthetic upload boundary','sku'=>$sku,'category_id'=>(string)$product['category_id'],'description'=>'Disposable image validation','price'=>'1.00','stock_qty'=>'1','status'=>'active','routine_step'=>''];
  $response=followupUpload($catalog,$bytes,'/catalog/products','image',$data);$query=$pdo->prepare('SELECT id,image_path FROM products WHERE sku=?');$query->execute([$sku]);$created=$query->fetch();
  verifyHair('Product upload size boundary '.$size,$size<=2621440?(bool)$created&&!empty($created['image_path']):!$created);
  if($created){$remove=hairRequest('/catalog/products',$catalog,['_token'=>$catalog['csrf'],'action'=>'delete','id'=>(string)$created['id']]);$query->execute([$sku]);verifyHair('Manager deletes unused image fixture without admin review',$query->fetch()===false&&!is_file(dirname(__DIR__).'/public/'.$created['image_path']));}
 }
 $badOrder=$pdo->prepare('SELECT COUNT(*) FROM orders WHERE id=?');$badOrder->execute([$orderId]);verifyHair('Failed delivery leaves exactly one real order',(int)$badOrder->fetchColumn()===1);
 $result=['checks'=>$checks,'pass'=>count(array_filter($checks,static fn($c)=>$c['status']==='PASS')),'fail'=>count(array_filter($checks,static fn($c)=>$c['status']==='FAIL'))];if(isset($o['output']))file_put_contents($o['output'],json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));ob_end_flush();exit($result['fail']?1:0);
}catch(Throwable $e){fwrite(STDERR,'Followup checks stopped at line '.$e->getLine().'; confidential values suppressed.'.PHP_EOL);exit(1);}
