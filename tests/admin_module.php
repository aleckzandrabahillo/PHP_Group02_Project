<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('AVELA_HAIR_TEST_LIBRARY',true);
require __DIR__.'/hair_profiles.php';
require_once dirname(__DIR__).'/vendor/autoload.php';

function adminClient(int $id): array {
    $u=(new App\Models\User())->findById($id);
    session_name((string)env('SESSION_NAME'));session_id('');session_start();
    $_SESSION=['auth_user'=>['id'=>$id,'role'=>$u['role'],'username'=>$u['username'],'email'=>$u['email']],
        'last_activity'=>time(),'_csrf'=>bin2hex(random_bytes(32)),'auth_password_version'=>$u['password_version'],
        'auth_mfa_version'=>App\Services\MfaService::sessionVersion($id)];
    $client=['cookie'=>session_name().'='.session_id(),'csrf'=>$_SESSION['_csrf']];session_write_close();return $client;
}
function adminDenied(callable $operation): bool {
    try{$operation();return false;}catch(DomainException|InvalidArgumentException){return true;}
}
function adminWorker(array $o): void {
    $pdo=App\Core\Database::connection();$actor=(int)$o['actor'];$target=(int)$o['target'];
    try {
        if($o['worker']==='role')(new App\Services\AdminAccounts())->change($actor,$target,'role','admin','catalog_manager');
        elseif($o['worker']==='cancel')(new App\Services\OrderWorkflow())->advance($pdo,$actor,$target,'pending','cancelled');
        elseif($o['worker']==='advance')(new App\Services\OrderWorkflow())->advance($pdo,$actor,$target,'pending','processing');
        elseif($o['worker']==='restock')(new App\Models\Product())->changeStock($target,7,true,null,$actor,'Synthetic received shipment');
        else throw new RuntimeException('Worker mode');
        echo json_encode(['result'=>'applied']);exit;
    }catch(DomainException|InvalidArgumentException){echo json_encode(['result'=>'rejected']);exit;}
}
function adminRace(array $jobs,array $options): array {
    $children=[];
    foreach($jobs as [$kind,$actor,$target]){
        $args=[PHP_BINARY,__FILE__,'--worker='.$kind,'--actor='.$actor,'--target='.$target];
        foreach(['expect-database','expect-port','expect-datadir','base-url','session-directory'] as $key)$args[]='--'.$key.'='.$options[$key];
        $p=proc_open($args,[0=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
        if(!is_resource($p))throw new RuntimeException('Concurrency fixture');
        $children[]=[$p,$pipes];
    }
    $out=[];
    foreach($children as [$p,$pipes]){$text=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);if($exit!==0||$err!=='')throw new RuntimeException('Concurrency worker failed');$out[]=json_decode($text,true,512,JSON_THROW_ON_ERROR)['result'];}
    return $out;
}
try {
    $o=getopt('',['expect-database:','expect-port:','expect-datadir:','base-url:','session-directory:','scratch-directory:','output:','worker:','actor:','target:']);
    $pdo=App\Core\Database::connection();$id=$pdo->query('SELECT DATABASE() db,@@port port,@@datadir dir')->fetch();
    $base=$o['base-url']??'';$url=parse_url($base);$sessions=realpath($o['session-directory']??'');
    $norm=static fn($v)=>strtolower(rtrim(str_replace('\\','/',$v),'/'));
    if(!preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D',$id['db'])||($o['expect-database']??'')!==$id['db']
        ||(int)($o['expect-port']??0)!==(int)$id['port']||in_array((int)$id['port'],[3306,13307],true)
        ||$norm($o['expect-datadir']??'')!==$norm($id['dir'])||env('DB_HOST')!=='127.0.0.1'||env('APP_ENV')!=='development'||env('MAIL_DRIVER')!=='log'
        ||!$sessions||str_starts_with($norm($sessions),$norm(dirname(__DIR__)).'/')||($url['host']??'')!=='127.0.0.1'||in_array((int)($url['port']??80),[80,18080],true))throw new RuntimeException('Disposable-only guard');
    if(isset($o['worker']))adminWorker($o);
    ini_set('session.save_path',$sessions);$checks=[];$users=new App\Models\User();$accounts=new App\Services\AdminAccounts();
    $prefix='adm_'.bin2hex(random_bytes(5));$pass='ABCDabcd1234!@#$'.bin2hex(random_bytes(24));$hash=password_hash($pass,PASSWORD_DEFAULT);
    $staff=[];foreach(['admin','second','catalog_manager'] as $role)$staff[$role]=$users->createStaff($role==='second'?'admin':$role,$prefix.'_'.$role.'@example.invalid',$prefix.'_'.$role,$hash,'Synthetic Administrator');
    $a=$staff['admin'];$b=$staff['second'];$manager=$staff['catalog_manager'];
    $customer=$users->createCustomer(['username'=>$prefix.'_customer','email'=>$prefix.'_customer@example.invalid','password_hash'=>$hash,'full_name'=>'Synthetic <script>Account</script>','contact_no'=>'+639123456789']);$users->activate($customer);
    $admin=adminClient($a);$other=adminClient($b);$catalog=adminClient($manager);$client=adminClient($customer);
    $valid=['full_name'=>'Synthetic New Staff','username'=>$prefix.'_new','email'=>$prefix.'_new@example.invalid','role'=>'catalog_manager','password'=>$pass];
    foreach(['/admin/users','/admin/staff','/admin/orders','/admin/orders/view?id=1','/admin/auth-logs','/admin/audit-logs'] as $path) {
        verifyHair('Guest denied '.$path,hairRequest($path)['code']===302);
        verifyHair('Customer denied '.$path,hairRequest($path,$client)['code']===403);
        verifyHair('Catalog Manager denied '.$path,hairRequest($path,$catalog)['code']===403);
    }
    foreach(['/admin/staff/create','/admin/staff/role','/admin/users/status','/admin/users/unlock','/admin/orders/status'] as $path) {
        verifyHair('Guest POST denied '.$path,hairRequest($path,null,[])['code']===302);
        verifyHair('Customer POST denied '.$path,hairRequest($path,$client,['_token'=>$client['csrf']])['code']===403);
        verifyHair('Catalog POST denied '.$path,hairRequest($path,$catalog,['_token'=>$catalog['csrf']])['code']===403);
        foreach(['bad',['bad']] as $token)verifyHair('Invalid CSRF rejected '.$path,hairRequest($path,$admin,['_token'=>$token])['code']===419);
    }
    $page=hairRequest('/admin/users?q='.$prefix,$admin);
    verifyHair('Users route loads real customer records',$page['code']===200&&str_contains($page['body'],$prefix.'_customer')&&!str_contains($page['body'],$prefix.'_catalog_manager'));
    verifyHair('Customer profile output encoded',str_contains($page['body'],'&lt;script&gt;Account&lt;/script&gt;')&&!str_contains($page['body'],'<script>Account</script>'));
    verifyHair('Staff route loads current staff and creation form',str_contains(hairRequest('/admin/staff?q='.$prefix,$admin)['body'],'Create staff account'));
    foreach(['q%5B%5D=bad','status%5B%5D=active','role%5B%5D=admin','status=invalid','q='.str_repeat('x',101)] as $query)
        verifyHair('Malformed account filter rejected '.$query,hairRequest('/admin/staff?'.$query,$admin)['code']===422);
    verifyHair('Literal SQL search does not expose records',$accounts->listing(false,['q'=>"' OR 1=1 --"])['total']===0);
    foreach(['full_name','email','username','role','password'] as $field){
        $post=$valid;$post[$field]=['malformed'];
        $r=hairRequest('/admin/staff/create',$admin,['_token'=>$admin['csrf'],...$post]);
        verifyHair('Malformed staff field rejected: '.$field,$r['code']===422&&!str_contains($r['body'],'Array to string conversion')&&!$users->usernameExists($valid['username']));
    }
    foreach(['weak'=>'lowercaseonly12345','bytes'=>str_repeat('A',73).'a1!','invalid_utf8'=>"\xff",'null'=>"ABcd12!\0abcdefghijkl"] as $label=>$password){
        $post=[...$valid,'password'=>$password];$r=hairRequest('/admin/staff/create',$admin,['_token'=>$admin['csrf'],...$post]);
        verifyHair('Password policy enforced: '.$label,$r['code']===422&&!$users->usernameExists($valid['username']));
    }
    $r=hairRequest('/admin/staff/create',$admin,['_token'=>$admin['csrf'],...$valid]);$new=$users->findByIdentifier($valid['username']);
    verifyHair('Staff creation succeeds with required profile',$r['code']===302&&$new&&$users->profileFor((int)$new['id'])['full_name']===$valid['full_name']);
    verifyHair('Staff password hashed and MFA preserved',password_verify($pass,$new['password_hash'])&&$new['password_hash']!==$pass&&(int)$new['mfa_enabled']===1&&$new['status']==='active'&&$new['activated_at']!==null);
    foreach(['full_name'=>str_repeat('x',121),'username'=>str_repeat('x',41),'email'=>str_repeat('x',185).'@example.invalid','role'=>'customer'] as $field=>$value)
        verifyHair('Schema/role bound enforced: '.$field,isset(\App\Core\StaffValidator::validate([...$valid,$field=>$value])['errors'][$field]));
    $policyBeforeCreation=App\Services\SecurityPolicy::values();
    try{
        (new App\Services\SecurityManagement())->savePolicy($a,array_replace($policyBeforeCreation,['password_min_uppercase'=>4]));
        verifyHair('Staff creation follows customized password complexity',isset(\App\Core\StaffValidator::validate([...$valid,'password'=>'Abcd1234!'.str_repeat('a',55)])['errors']['password']));
    }finally{(new App\Services\SecurityManagement())->savePolicy($a,$policyBeforeCreation);}
    $audit=$pdo->query('SELECT actor_id,details FROM audit_logs WHERE action="staff.created" AND target_id='.(int)$new['id'].' ORDER BY id DESC LIMIT 1')->fetch();
    verifyHair('Staff creation audit identifies actor without credentials',(int)$audit['actor_id']===$a&&!str_contains($audit['details'],$pass)&&!str_contains($audit['details'],$new['password_hash']));
    verifyHair('Duplicate identifiers rejected',hairRequest('/admin/staff/create',$admin,['_token'=>$admin['csrf'],...$valid])['code']===422);
    $r=hairRequest('/admin/staff/create',$admin,['_token'=>$admin['csrf'],...$valid,'username'=>$prefix.'_other','password'=>'weak']);
    verifyHair('Rejected form never reflects password',$r['code']===422&&!preg_match('/name="password"[^>]*value=/',$r['body']));
    $logs=new App\Services\LogService();$otp=new App\Services\OtpService(new App\Services\MailService(),$logs);$auth=new App\Services\AuthService($users,$otp,$logs);
    $login=$auth->attempt($valid['username'],$pass);
    verifyHair('Unenrolled staff can sign in before authenticator setup without email OTP',
        $login['ok']&&$login['next']==='dashboard'
        &&(int)$pdo->query('SELECT COUNT(*) FROM otp_codes WHERE user_id='.(int)$new['id'])->fetchColumn()===0);

    $state=$users->findById($customer);
    verifyHair('Malformed status target has no write',hairRequest('/admin/users/status',$admin,['_token'=>$admin['csrf'],'id'=>['bad'],'expected'=>'active','status'=>'inactive'])['code']===302&&$users->findById($customer)===$state);
    verifyHair('Malformed status array has no write',hairRequest('/admin/users/status',$admin,['_token'=>$admin['csrf'],'id'=>(string)$customer,'expected'=>'active','status'=>['inactive']])['code']===302&&$users->findById($customer)===$state);
    $accounts->change($a,$customer,'status','active','inactive');
    verifyHair('Customer disabled and existing session revoked',hairRequest('/profile',$client)['code']===302&&$users->findById($customer)['status']==='inactive');
    verifyHair('Stale account form rejected',adminDenied(fn()=>$accounts->change($a,$customer,'status','active','inactive')));
    $accounts->change($a,$customer,'status','inactive','active');
    verifyHair('Activation preserves credentials and revokes old session',password_verify($pass,$users->findById($customer)['password_hash'])&&hairRequest('/profile',$client)['code']===302);
    $client=adminClient($customer);
    $roleSession=adminClient((int)$new['id']);
    $accounts->change($a,(int)$new['id'],'role','catalog_manager','admin');
    verifyHair('Role promotion requires a fresh authenticated MFA flow',$users->findById((int)$new['id'])['role']==='admin'&&hairRequest('/admin',$roleSession)['code']===302&&(int)$users->findById((int)$new['id'])['mfa_enabled']===1);
    verifyHair('Customer cannot become staff through role action',adminDenied(fn()=>$accounts->change($a,$customer,'role','customer','admin')));
    foreach(['status','role'] as $kind)verifyHair('Self access change blocked: '.$kind,adminDenied(fn()=>$accounts->change($a,$a,$kind,$kind==='status'?'active':'admin',$kind==='status'?'inactive':'catalog_manager')));
    verifyHair('Manual locking is not an account-status action',adminDenied(fn()=>$accounts->change($a,$b,'status','active','locked')));
    $pdo->prepare('UPDATE users SET status="inactive" WHERE id=?')->execute([$b]);
    verifyHair('Inactive actor rejected inside service',adminDenied(fn()=>$accounts->change($b,$customer,'status','active','inactive')));
    verifyHair('Inactive actor cannot create staff',adminDenied(fn()=>$accounts->create($b,[...$valid,'username'=>$prefix.'_blocked','email'=>$prefix.'_blocked@example.invalid'])));
    $pdo->prepare('UPDATE users SET status="active",lock_requires_admin=1,locked_until=DATE_ADD(NOW(),INTERVAL 15 MINUTE) WHERE id=?')->execute([$b]);
    verifyHair('Locked actor rejected inside service',adminDenied(fn()=>$accounts->change($b,$customer,'status','active','inactive')));
    $pdo->prepare('UPDATE users SET lock_requires_admin=0,locked_until=NULL WHERE id=?')->execute([$b]);
    verifyHair('Catalog actor rejected inside service',adminDenied(fn()=>$accounts->change($manager,$customer,'status','active','inactive')));
    $beforeAdmins=$pdo->query('SELECT id,status FROM users WHERE role="admin" ORDER BY id')->fetchAll();
    try {
        $pdo->prepare('UPDATE users SET status="inactive" WHERE role="admin" AND id<>?')->execute([$a]);
        verifyHair('Last available admin cannot disable itself',adminDenied(fn()=>$accounts->change($a,$a,'status','active','inactive')));
        verifyHair('Last available admin cannot demote itself',adminDenied(fn()=>$accounts->change($a,$a,'role','admin','catalog_manager')));
        verifyHair('Inactive admin cannot remove last available admin',adminDenied(fn()=>$accounts->change($b,$a,'status','active','inactive'))&&App\Models\User::canAuthenticate($users->findById($a)));
    }finally{foreach($beforeAdmins as $row)$pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$row['status'],$row['id']]);}
    $race=adminRace([['role',$a,$b],['role',$b,$a]],$o);
    verifyHair('Concurrent cross-demotions allow only one mutation',count(array_filter($race,fn($r)=>$r==='applied'))===1&&count(array_filter([$users->findById($a),$users->findById($b)],fn($u)=>$u['role']==='admin'))===1);
    $pdo->prepare('UPDATE users SET role="admin" WHERE id IN (?,?)')->execute([$a,$b]);$admin=adminClient($a);$other=adminClient($b);
    for($n=0;$n<3;$n++)$users->recordFailure($customer);
    $lock=$users->findById($customer)['locked_until'];
    verifyHair('Persistent lock visible in customer filters',$accounts->listing(false,['q'=>$prefix,'status'=>'locked'])['total']===1);
    $r=hairRequest('/admin/users/unlock',$admin,['_token'=>$admin['csrf'],'id'=>(string)$customer,'expected_lock'=>$lock]);
    verifyHair('Existing Administrator unlock workflow reused',$r['code']===302&&!App\Models\User::isLocked($users->findById($customer)));
    verifyHair('Unlock audit attributed to Administrator',(int)$pdo->query('SELECT actor_id FROM audit_logs WHERE action="account_unlocked" AND target_id='.$customer.' ORDER BY id DESC LIMIT 1')->fetchColumn()===$a);
    for($n=0;$n<3;$n++)$users->recordFailure($customer);
    $prior=$users->findById($customer);
    hairRequest('/admin/users/unlock',$admin,['_token'=>$admin['csrf'],'id'=>(string)$customer,'expected_lock'=>'2000-01-01 00:00:00']);
    verifyHair('Stale unlock cannot release a new lock',$users->findById($customer)===$prior);
    (new App\Services\SecurityManagement())->unlock($a,$customer,$prior['locked_until']);

    for($n=0;$n<23;$n++)$users->createCustomer(['username'=>$prefix.'_p'.$n,'email'=>$prefix.'_p'.$n.'@example.invalid','password_hash'=>$hash,'full_name'=>'Pagination fixture','contact_no'=>'+639123456789']);
    $listing=$accounts->listing(false,['q'=>$prefix]);
    verifyHair('Account list enforces page size',$listing['total']===24&&count($listing['items'])===20&&$listing['pages']===2);
    foreach(['9999999999999999999999999999',PHP_INT_MAX,-99,['bad']] as $raw){
        $page=$accounts->listing(false,['q'=>$prefix,'page'=>$raw]);
        verifyHair('Account pagination bounded for malformed/large page',$page['page']>=1&&$page['page']<=$page['pages']&&count($page['items'])<=20);
    }
    verifyHair('Past-last page clamps before SQL offset',$accounts->listing(false,['q'=>$prefix,'page'=>999])['page']===2);
    $next=hairRequest('/admin/users?q='.$prefix.'&status=pending',$admin);
    verifyHair('Pagination links preserve allowed filters',str_contains($next['body'],'status=pending')&&str_contains($next['body'],'page=2'));
    foreach(['audit','auth'] as $kind){
        $reader=new App\Services\AdminLogReader();$data=$reader->read($kind,['q'=>$prefix,'from'=>date('Y-m-d'),'to'=>date('Y-m-d'),'sort'=>'oldest','page'=>PHP_INT_MAX]);
        verifyHair('Existing date/search/sort log filters retained: '.$kind,$data['filters']['sort']==='oldest'&&$data['filters']['from']===date('Y-m-d')&&$data['page']<=$data['pages']);
        verifyHair('Malformed log filters still rejected: '.$kind,adminDenied(fn()=>$reader->read($kind,['q'=>['bad']])));
    }
    $policyBefore=$pdo->query('SELECT * FROM security_settings ORDER BY setting_key')->fetchAll();
    $pdo->exec('CREATE TRIGGER admin_module_audit_fault BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT="Synthetic audit fault"');
    try{
        $before=$users->findById($manager);$failed=false;
        try{$accounts->change($a,$manager,'status','active','inactive');}catch(Throwable){$failed=true;}
        verifyHair('Audit failure rolls account mutation back',$failed&&$users->findById($manager)===$before);
        $bad=[...$valid,'username'=>$prefix.'_fault','email'=>$prefix.'_fault@example.invalid'];$failed=false;
        try{$accounts->create($a,$bad);}catch(Throwable){$failed=true;}
        verifyHair('Staff and profile creation roll back on audit fault',$failed&&!$users->usernameExists($bad['username']));
    }finally{$pdo->exec('DROP TRIGGER admin_module_audit_fault');}

    $category=(int)$pdo->query('SELECT id FROM categories WHERE status="active" ORDER BY id LIMIT 1')->fetchColumn();
    $pdo->prepare('INSERT INTO products(category_id,name,sku,description,price,stock_qty,status) VALUES (?,"Synthetic cancellation product",?,"Disposable",10,100,"active")')->execute([$category,strtoupper($prefix)]);
    $product=(int)$pdo->lastInsertId();$workflow=new App\Services\OrderWorkflow();$checkout=new App\Services\CheckoutService();
    $makeOrder=function(int $quantity=2)use($pdo,$customer,$product,$checkout):int{
        $pdo->prepare('INSERT INTO carts(user_id,status) VALUES (?,"active")')->execute([$customer]);$cart=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO cart_items(cart_id,product_id,quantity) VALUES (?,?,?)')->execute([$cart,$product,$quantity]);
        $quote=$checkout->quote($customer);
        return $checkout->place($customer,['country'=>'PH','first_name'=>'Synthetic','last_name'=>'Recipient','address_line'=>'1 Test Street','barangay'=>'Test','postal_code'=>'1600','city'=>'Pasig','region'=>'NCR','province'=>'Metro Manila','contact_no'=>'09123456789','shipping_method'=>'standard','payment_method'=>'cod'],bin2hex(random_bytes(32)),$quote['signature']);
    };
    $stock=fn()=>(int)$pdo->query('SELECT stock_qty FROM products WHERE id='.$product)->fetchColumn();
    $status=fn($order)=>$pdo->query('SELECT status FROM orders WHERE id='.(int)$order)->fetchColumn();
    $order=$makeOrder();$afterCheckout=$stock();$cust=adminClient($customer);
    foreach([$cust,$catalog] as $unauthorized)verifyHair('Unauthorized cancellation denied',hairRequest('/admin/orders/status',$unauthorized,['_token'=>$unauthorized['csrf'],'order_id'=>(string)$order,'expected_status'=>'pending','status'=>'cancelled'])['code']===403&&$stock()===$afterCheckout);
    verifyHair('Admin order detail loads actual snapshot',hairRequest('/admin/orders/view?id='.$order,$admin)['code']===200&&str_contains(hairRequest('/admin/orders/view?id='.$order,$admin)['body'],'Synthetic cancellation product'));
    verifyHair('Admin missing/malformed order returns 404',hairRequest('/admin/orders/view?id%5B%5D=bad',$admin)['code']===404);
    $r=hairRequest('/admin/orders/status',$admin,['_token'=>$admin['csrf'],'order_id'=>(string)$order,'expected_status'=>'pending','status'=>'cancelled']);
    verifyHair('Cancellation restores exactly the deducted quantity',$r['code']===302&&$status($order)==='cancelled'&&$stock()===$afterCheckout+2);
    verifyHair('Cancellation preserves items and delivery snapshots',(int)$pdo->query('SELECT COUNT(*) FROM order_items WHERE order_id='.$order)->fetchColumn()===1&&(int)$pdo->query('SELECT COUNT(*) FROM order_delivery_details WHERE order_id='.$order)->fetchColumn()===1);
    verifyHair('Cancellation leaves converted cart converted',(int)$pdo->query('SELECT COUNT(*) FROM carts WHERE user_id='.$customer.' AND status="active"')->fetchColumn()===0);
    verifyHair('Cancellation appends timeline and attributed stock/order audits',(int)$pdo->query('SELECT COUNT(*) FROM order_status_events WHERE order_id='.$order.' AND to_status="cancelled" AND actor_id='.$a)->fetchColumn()===1&&(int)$pdo->query('SELECT COUNT(*) FROM audit_logs WHERE action="order.cancelled" AND target_id='.$order.' AND actor_id='.$a)->fetchColumn()===1);
    verifyHair('Cancelled pending email is not sent',$pdo->query('SELECT last_error FROM order_email_outbox WHERE order_id='.$order)->fetchColumn()==='order_cancelled');
    verifyHair('Duplicate cancellation cannot restock twice',adminDenied(fn()=>$workflow->advance($pdo,$a,$order,'pending','cancelled'))&&$stock()===$afterCheckout+2);
    $order=$makeOrder();$workflow->advance($pdo,$a,$order,'pending','processing');$n=$stock();$workflow->advance($pdo,$a,$order,'processing','cancelled');
    verifyHair('Processing order cancellation reconciles stock',$stock()===$n+2&&$status($order)==='cancelled');
    $order=$makeOrder();$workflow->advance($pdo,$a,$order,'pending','processing');$workflow->advance($pdo,$a,$order,'processing','shipped');$n=$stock();
    verifyHair('Shipped cancellation requires separate return process',adminDenied(fn()=>$workflow->advance($pdo,$a,$order,'shipped','cancelled'))&&$stock()===$n);
    verifyHair('Only an Administrator can record dispatch',adminDenied(fn()=>$workflow->advance($pdo,$staff['catalog_manager'],$order,'shipped','out_for_delivery',0)));
    $workflow->advance($pdo,$a,$order,'shipped','out_for_delivery',0);
    $dispatch=(new App\Models\Order())->forAdministrator($order);
    verifyHair('Dispatch is actual saved evidence without changing shipped enum',$status($order)==='shipped'&&!empty($dispatch['out_for_delivery_at'])&&$dispatch['delivery_event_id']>0);
    verifyHair('Dispatch timeline maps real current stage',App\Services\OrderProgress::journey($dispatch)['stages'][2]['state']==='current');
    verifyHair('Duplicate dispatch is rejected',adminDenied(fn()=>$workflow->advance($pdo,$a,$order,'shipped','out_for_delivery',$dispatch['delivery_event_id'])));
    verifyHair('Stale delivery-event token cannot mark delivered',adminDenied(fn()=>$workflow->advance($pdo,$a,$order,'shipped','delivered',0))&&$status($order)==='shipped');
    $workflow->advance($pdo,$a,$order,'shipped','delivered',$dispatch['delivery_event_id']);

    verifyHair('Delivered cancellation rejected',adminDenied(fn()=>$workflow->advance($pdo,$a,$order,'delivered','cancelled'))&&$stock()===$n);
    foreach(['legacy','payment','missing','overflow','proof'] as $case){
        $order=$makeOrder();$priorStock=$stock();
        if($case==='legacy')$pdo->exec('UPDATE orders SET checkout_key=NULL WHERE id='.$order);
        if($case==='payment')$pdo->exec('UPDATE orders SET payment_method="manual" WHERE id='.$order);
        if($case==='missing')$pdo->exec('UPDATE order_items SET product_id=NULL WHERE order_id='.$order);
        if($case==='overflow')$pdo->exec('UPDATE products SET stock_qty=4294967295 WHERE id='.$product);
        if($case==='proof')$pdo->exec('DELETE FROM audit_logs WHERE action="order.create" AND target_type="order" AND target_id='.$order);
        $n=$stock();verifyHair('Unsafe cancellation fails closed: '.$case,adminDenied(fn()=>$workflow->advance($pdo,$a,$order,'pending','cancelled'))&&$stock()===$n&&$status($order)==='pending');
        if($case==='overflow')$pdo->exec('UPDATE products SET stock_qty='.$priorStock.' WHERE id='.$product);
    }
    $order=$makeOrder();$n=$stock();$stockAudits=(int)$pdo->query('SELECT COUNT(*) FROM audit_logs WHERE action="order.stock_restored"')->fetchColumn();
    $pdo->exec('CREATE TRIGGER admin_module_audit_fault BEFORE INSERT ON audit_logs FOR EACH ROW BEGIN IF NEW.action="order.cancelled" THEN SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT="Synthetic audit fault"; END IF; END');
    try{$failed=false;try{$workflow->advance($pdo,$a,$order,'pending','cancelled');}catch(Throwable){$failed=true;}
        verifyHair('Final audit failure rolls cancellation, stock, event and outbox back',$failed&&$stock()===$n&&$status($order)==='pending'&&(int)$pdo->query('SELECT COUNT(*) FROM order_status_events WHERE order_id='.$order)->fetchColumn()===1&&$pdo->query('SELECT status FROM order_email_outbox WHERE order_id='.$order)->fetchColumn()==='pending'&&(int)$pdo->query('SELECT COUNT(*) FROM audit_logs WHERE action="order.stock_restored"')->fetchColumn()===$stockAudits);
    }finally{$pdo->exec('DROP TRIGGER admin_module_audit_fault');}
    $race=adminRace([['cancel',$a,$order],['cancel',$b,$order]],$o);
    verifyHair('Concurrent cancellation restores once',count(array_filter($race,fn($r)=>$r==='applied'))===1&&$stock()===$n+2&&$status($order)==='cancelled');
    $order=$makeOrder();$n=$stock();
    $pdo->exec('UPDATE order_items SET quantity=1 WHERE order_id='.$order);
    $pdo->exec('INSERT INTO order_items(order_id,product_id,product_name_snapshot,quantity,unit_price_snapshot) SELECT order_id,product_id,product_name_snapshot,1,unit_price_snapshot FROM order_items WHERE order_id='.$order.' LIMIT 1');
    $workflow->advance($pdo,$a,$order,'pending','cancelled');
    verifyHair('Repeated product rows are grouped into one exact stock return',$stock()===$n+2);
    $order=$makeOrder();$n=$stock();$race=adminRace([['cancel',$a,$order],['restock',$manager,$product]],$o);
    verifyHair('Cancellation and catalog restock compose atomically',$race===['applied','applied']&&$stock()===$n+9);
    $order=$makeOrder();$n=$stock();$race=adminRace([['cancel',$a,$order],['advance',$b,$order]],$o);
    verifyHair('Cancellation and fulfillment race has one consistent outcome',count(array_filter($race,fn($r)=>$r==='applied'))===1&&in_array($status($order),['processing','cancelled'],true)&&$stock()===$n+($status($order)==='cancelled'?2:0));
    $prior=$users->findById($b);$pdo->prepare('UPDATE users SET status="inactive" WHERE id=?')->execute([$b]);$order=$makeOrder();$n=$stock();
    verifyHair('Inactive actor cannot cancel through service',adminDenied(fn()=>$workflow->advance($pdo,$b,$order,'pending','cancelled'))&&$stock()===$n);
    $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$prior['status'],$b]);
    $pdo->prepare('UPDATE users SET lock_requires_admin=1,locked_until=DATE_ADD(NOW(),INTERVAL 15 MINUTE) WHERE id=?')->execute([$b]);
    verifyHair('Locked actor cannot cancel through service',adminDenied(fn()=>$workflow->advance($pdo,$b,$order,'pending','cancelled'))&&$stock()===$n);
    $pdo->prepare('UPDATE users SET lock_requires_admin=0,locked_until=NULL WHERE id=?')->execute([$b]);
    verifyHair('Catalog actor cannot cancel through service',adminDenied(fn()=>$workflow->advance($pdo,$manager,$order,'pending','cancelled'))&&$stock()===$n);
    verifyHair('Security policy unchanged',$policyBefore===$pdo->query('SELECT * FROM security_settings ORDER BY setting_key')->fetchAll());
    // Remove only this invocation's synthetic product so fixed-catalog regressions remain isolated.
    $pdo->prepare('DELETE FROM cart_items WHERE product_id=?')->execute([$product]);
    $pdo->prepare('DELETE FROM products WHERE id=? AND sku=? AND name="Synthetic cancellation product"')->execute([$product,strtoupper($prefix)]);
    $result=['checks'=>$checks,'pass'=>count(array_filter($checks,fn($r)=>$r['status']==='PASS')),'fail'=>count(array_filter($checks,fn($r)=>$r['status']==='FAIL'))];
    if(isset($o['output']))file_put_contents($o['output'],json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    ob_end_flush();exit($result['fail']?1:0);
}catch(Throwable $e){
    if(isset($product,$prefix,$pdo)){
        $pdo->prepare('DELETE FROM cart_items WHERE product_id=?')->execute([$product]);
        $pdo->prepare('DELETE FROM products WHERE id=? AND sku=? AND name="Synthetic cancellation product"')->execute([$product,strtoupper($prefix)]);
    }
    fwrite(STDERR,'Admin tests stopped: '.get_class($e).' at line '.$e->getLine().'; private values suppressed.'.PHP_EOL);exit(1);
}
