<?php

declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/scripts/assign_demo_images.php';
require_once dirname(__DIR__).'/vendor/autoload.php';
ob_start();ini_set('session.use_cookies','0');ini_set('session.cache_limiter','');
date_default_timezone_set((string)env('APP_TIMEZONE','Asia/Manila'));
$checks=[];
function verifyMfa(string $name,bool $ok):void{global $checks;$checks[]=['test'=>$name,'status'=>$ok?'PASS':'FAIL'];echo ($ok?'PASS: ':'FAIL: ').$name.PHP_EOL;}
function otpMfa(string $email):string{
    foreach(array_reverse(file(dirname(__DIR__).'/storage/logs/dev_mail.log')) as $line)if(str_contains($line,'TO: '.$email.' | PURPOSE: login')&&preg_match('/OTP: (\d{6})/',$line,$m))return $m[1];
    throw new RuntimeException('Synthetic OTP unavailable.');
}
function mfaSession():void{if(session_status()===PHP_SESSION_ACTIVE)session_write_close();session_name((string)env('SESSION_NAME'));session_id('');session_start();}
function mfaRequest(string $path,?array $client=null,?array $data=null):array{
    global $base;$head="Connection: close\r\n".($client?'Cookie: '.$client['cookie']."\r\n":'');
    $o=['method'=>$data===null?'GET':'POST','timeout'=>15,'ignore_errors'=>true,'follow_location'=>0,'header'=>$head];
    if($data!==null){$o['content']=http_build_query($data);$o['header'].="Content-Type: application/x-www-form-urlencoded\r\n";}
    $body=file_get_contents($base.$path,false,stream_context_create(['http'=>$o]));$h=$http_response_header??[];preg_match('/\s(\d{3})\s/',$h[0]??'',$m);$location='';$cookie='';$noStore=false;
    foreach($h as $line){if(str_starts_with(strtolower($line),'location:'))$location=trim(substr($line,9));if(str_starts_with(strtolower($line),'set-cookie:'))$cookie=explode(';',trim(substr($line,11)))[0];if(str_starts_with(strtolower($line),'cache-control:')&&str_contains($line,'no-store'))$noStore=true;}
    return ['code'=>(int)($m[1]??0),'body'=>(string)$body,'location'=>$location,'cookie'=>$cookie,'no_store'=>$noStore];
}
function mfaClient():array{return ['cookie'=>session_name().'='.session_id(),'csrf'=>App\Core\Csrf::token()];}
try {
    $o=getopt('',['expect-database:','expect-port:','expect-datadir:','base-url:','session-directory:','scratch-directory:','output:','worker:']);
    $pdo=App\Core\Database::connection();$t=$pdo->query('SELECT DATABASE() db,@@port port,@@datadir dir')->fetch();$norm=static fn($s)=>strtolower(rtrim(str_replace('\\','/',$s),'/'));
    $base=$o['base-url']??'';$url=parse_url($base);$scratch=realpath($o['scratch-directory']??'');$sessions=realpath($o['session-directory']??'');
    if(!preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D',$t['db'])||($o['expect-database']??'')!==$t['db']||(int)($o['expect-port']??0)!==(int)$t['port']||in_array((int)$t['port'],[3306,13307],true)
        ||$norm($o['expect-datadir']??'')!==$norm($t['dir'])||env('DB_HOST')!=='127.0.0.1'||env('APP_ENV')!=='development'||!$scratch||!$sessions
        ||str_starts_with($norm($scratch),$norm(dirname(__DIR__)).'/')||str_starts_with($norm($sessions),$norm(dirname(__DIR__)).'/')
        ||($url['host']??'')!=='127.0.0.1'||($url['scheme']??'')!=='http'||in_array((int)($url['port']??80),[80,18080],true))throw new RuntimeException('Disposable-only guard stopped.');
    ini_set('session.save_path',$sessions);$users=new App\Models\User();$logs=new App\Services\LogService();$otp=new App\Services\OtpService(new App\Services\MailService(),$logs);$mfa=new App\Services\MfaService($users,$otp,$logs);$auth=new App\Services\AuthService($users,$otp,$logs);$cipher=new App\Services\MfaCipher();
    if(isset($o['worker'])){$path=realpath($o['worker']);if(!$path||!str_starts_with($norm($path),$norm($scratch).'/'))throw new RuntimeException();$job=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);echo json_encode(['ok'=>$mfa->verifyLogin($job['flow'],$job['code'])['ok']]);exit;}
    $oldUsers=$pdo->query('SELECT * FROM users ORDER BY id')->fetchAll();
    $products=$pdo->query('SELECT * FROM products ORDER BY id')->fetchAll();$orders=$pdo->query('SELECT * FROM orders ORDER BY id')->fetchAll();
    $migration=new App\Services\MfaMigration();$dry=$migration->run($pdo,false);
    verifyMfa('MFA migration dry-run makes no changes',$dry['applied']===0);
    $migration->run($pdo,true);verifyMfa('MFA migration apply is repeat-safe',$migration->run($pdo,true)['applied']===0);
    $known=OTPHP\TOTP::createFromSecret('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ')->withDigits(8);
    verifyMfa('OTPHP matches the RFC 6238 public SHA-1 test vector',$known->at(59)==='94287082');
    $secret=OTPHP\TOTP::generate()->getSecret();$encrypted=$cipher->encrypt(123,$secret);
    verifyMfa('Authenticated secret encryption round trip',$cipher->decrypt(123,$encrypted)===$secret&&!str_contains($encrypted,$secret));
    verifyMfa('Encryption uses a fresh nonce',$cipher->encrypt(123,$secret)!==$encrypted);
    foreach([['user'=>124,'value'=>$encrypted],['user'=>123,'value'=>substr($encrypted,0,-4).'AAAA']] as $bad){$denied=false;try{$cipher->decrypt($bad['user'],$bad['value']);}catch(RuntimeException){$denied=true;}verifyMfa('Cipher rejects wrong owner or tampering',$denied);}
    $prefix='mfa_'.bin2hex(random_bytes(4));$accounts=[];
    foreach(['catalog_manager','admin','customer'] as $role){$a=['username'=>$prefix.'_'.$role,'email'=>$prefix.'_'.$role.'@example.invalid','password'=>'A1!'.bin2hex(random_bytes(18)),'role'=>$role];
        if($role==='customer'){$id=$users->createCustomer(['username'=>$a['username'],'email'=>$a['email'],'password_hash'=>password_hash($a['password'],PASSWORD_DEFAULT),'full_name'=>'Synthetic MFA Customer','contact_no'=>'+639170000000']);$users->activate($id);}
        else $id=$users->createStaff($role,$a['email'],$a['username'],password_hash($a['password'],PASSWORD_DEFAULT),'Synthetic MFA Staff');$accounts[$role]=$a+['id'=>$id];}
    // Exercise the Administrator role with its own verified enrollment.
    $admin=$accounts['admin'];mfaSession();$mailCount=(int)$pdo->query('SELECT COUNT(*) FROM otp_codes')->fetchColumn();
    $r=$auth->attempt($admin['email'],$admin['password']);
    verifyMfa('Administrator email and password allow initial setup',$r['next']==='dashboard'&&($_SESSION['auth_user']['role']??'')==='admin');
    verifyMfa('Administrator initial login does not issue OTP',(int)$pdo->query('SELECT COUNT(*) FROM otp_codes')->fetchColumn()===$mailCount);
    $mfa->beginEnrollment($admin['id'],$admin['password']);$adminSecret=$mfa->setupSecret($admin['id']);$adminTotp=OTPHP\TOTP::createFromSecret($adminSecret);
    $adminCodes=$mfa->confirmEnrollment($admin['id'],otpMfa($admin['email']),$adminTotp->now());$auth->logout();
    $mailCount=(int)$pdo->query('SELECT COUNT(*) FROM otp_codes')->fetchColumn();$r=$auth->attempt($admin['email'],$admin['password']);
    verifyMfa('Enrolled Administrator goes directly to authenticator',$r['next']==='totp'&&!isset($_SESSION['auth_user']));
    verifyMfa('Enrolled Administrator does not receive login email',(int)$pdo->query('SELECT COUNT(*) FROM otp_codes')->fetchColumn()===$mailCount);
    verifyMfa('Password finalizer cannot bypass Administrator authenticator',!$auth->finalizeLogin($users->findById($admin['id'])));
    verifyMfa('Administrator recovery proof completes its own challenge',$auth->verifyPendingAuthenticator($adminCodes[0])['ok']&&($_SESSION['auth_user']['role']??'')==='admin');
    $auth->logout();App\Core\Session::put('pending_flow',['user_id'=>$admin['id'],'purpose'=>'login','password_version'=>$users->findById($admin['id'])['password_version']??'']);
    verifyMfa('Legacy staff email challenge cannot bypass authenticator',!$auth->verifyPendingOtp('000000')['ok']&&!isset($_SESSION['auth_user']));
    verifyMfa('Legacy staff email challenge cannot resend',$auth->resendPendingOtp()['ok']===false);
    mfaSession();App\Core\Session::put('pending_flow',['user_id'=>$admin['id'],'purpose'=>'login']);$legacyClient=mfaClient();session_write_close();
    verifyMfa('Retired staff email sign-in page redirects to fresh login',mfaRequest('/verify-otp',$legacyClient)['location']==='/login');
    $a=$accounts['catalog_manager'];$id=$a['id'];mfaSession();$beforeOtp=(int)$pdo->query('SELECT COUNT(*) FROM otp_codes WHERE user_id='.$id)->fetchColumn();$r=$auth->attempt($a['username'],$a['password']);verifyMfa('Unenrolled staff can sign in before setup',$r['next']==='dashboard'&&isset($_SESSION['auth_user']));verifyMfa('Unenrolled staff login sends no email code',(int)$pdo->query('SELECT COUNT(*) FROM otp_codes WHERE user_id='.$id)->fetchColumn()===$beforeOtp);
    $olderState=$_SESSION;$originalId=session_id();session_write_close();session_id('');session_start();$_SESSION=$olderState;$olderClient=mfaClient();session_write_close();session_id($originalId);session_start();
    $denied=false;try{$mfa->beginEnrollment($id,'incorrect');}catch(DomainException){$denied=true;}verifyMfa('Enrollment requires current password',$denied&&$mfa->factor($id)===null);
    $pdo->prepare('UPDATE otp_codes SET created_at=DATE_SUB(NOW(),INTERVAL 61 SECOND) WHERE user_id=?')->execute([$id]);
    $flow=$mfa->beginEnrollment($id,$a['password']);$setup=$mfa->setupSecret($id);$totp=OTPHP\TOTP::createFromSecret($setup);
    verifyMfa('Pending setup secret is encrypted in session',!str_contains($flow['secret'],$setup)&&!isset($_SESSION['authenticator_secret']));
    $uri=$mfa->setupUri($id);$parts=parse_url($uri);parse_str($parts['query']??'',$parameters);
    verifyMfa('Provisioning URI belongs to current account and exact TOTP settings',$parts['scheme']==='otpauth'&&$parts['host']==='totp'&&$parameters['issuer']==='Avela'&&$parameters['secret']===$setup&&($parameters['digits']??'6')==='6'&&($parameters['period']??'30')==='30'&&str_contains(rawurldecode($parts['path']),$a['username']));
    $setupClient=mfaClient();session_write_close();$setupPage=mfaRequest('/account/mfa',$setupClient);session_id(substr($setupClient['cookie'],strpos($setupClient['cookie'],'=')+1));session_start();
    verifyMfa('QR enrollment page is private with local encoder and manual fallback',$setupPage['code']===200&&$setupPage['no_store']&&str_contains($setupPage['body'],'data-mfa-qr')&&str_contains($setupPage['body'],'/assets/js/qr-encoder.js')&&str_contains($setupPage['body'],'id="authenticator-setup-key"')&&str_contains($setupPage['body'],implode(' ',str_split($setup,4))));
    verifyMfa('Enrollment page retains both proofs and POST CSRF',str_contains($setupPage['body'],'name="email_code"')&&str_contains($setupPage['body'],'name="authenticator_code"')&&str_contains($setupPage['body'],'name="_token"')&&str_contains($setupPage['body'],'name="action" value="confirm"'));
    verifyMfa('Enrollment key keeps the full generated entropy',strlen($setup)>=32&&preg_match('/^[A-Z2-7]+$/D',$setup)===1);
    verifyMfa('Setup offers key-first actions, separate steps and countdown',str_contains($setupPage['body'],'data-mfa-copy')&&str_contains($setupPage['body'],'data-mfa-open-qr')&&str_contains($setupPage['body'],'data-mfa-step="2"')&&str_contains($setupPage['body'],'data-mfa-expiry'));
    session_write_close();
    $rejected=mfaRequest('/account/mfa',$setupClient,['_token'=>$setupClient['csrf'],'action'=>'confirm','email_code'=>'invalid','authenticator_code'=>$totp->now()]);
    $retryPage=mfaRequest('/account/mfa',$setupClient);
    session_id(substr($setupClient['cookie'],strpos($setupClient['cookie'],'=')+1));session_start();
    verifyMfa('Failed confirmation returns to step two without saving codes',$rejected['location']==='/account/mfa'&&str_contains($retryPage['body'],'data-current-step="2"')&&!isset(App\Core\Session::get('mfa_setup_flow')['email_code'])&&$mfa->factor($id)===null);
    $pending=App\Core\Session::get('mfa_setup_flow');App\Core\Session::put('mfa_setup_flow',[...$pending,'expires_at'=>time()-1]);
    verifyMfa('Expired setup has no provisioning URI',$mfa->setupUri($id)===null);
    App\Core\Session::put('mfa_setup_flow',$pending);
    verifyMfa('Other account cannot obtain provisioning URI',$mfa->setupUri($accounts['customer']['id'])===null);
    App\Core\Session::put('mfa_setup_flow',$pending);
    verifyMfa('Cooldown restart reuses the same pending setup',$mfa->beginEnrollment($id,$a['password'])['secret']===$flow['secret']);
    $wrong='not-a-code';$denied=false;try{$mfa->confirmEnrollment($id,otpMfa($a['email']),$wrong);}catch(DomainException){$denied=true;}verifyMfa('Wrong authenticator blocks enrollment without consuming email OTP',$denied&&$mfa->factor($id)===null);
    $denied=false;try{$mfa->confirmEnrollment($id,'invalid',$totp->now());}catch(DomainException){$denied=true;}verifyMfa('Wrong email code blocks enrollment',$denied&&$mfa->factor($id)===null);
    $pdo->exec("CREATE TRIGGER mfa_test_audit_failure BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic MFA audit fault'");
    $denied=false;try{$mfa->confirmEnrollment($id,otpMfa($a['email']),$totp->now());}catch(Throwable){$denied=true;}
    $pdo->exec('DROP TRIGGER mfa_test_audit_failure');
    verifyMfa('Enrollment rolls back factor and email consumption if audit fails',$denied&&$mfa->factor($id)===null&&$pdo->query('SELECT used_at FROM otp_codes WHERE user_id='.$id.' ORDER BY id DESC LIMIT 1')->fetchColumn()===null);
    $enrollmentCode=$totp->now();$codes=$mfa->confirmEnrollment($id,otpMfa($a['email']),$enrollmentCode);$factor=$mfa->factor($id);
    verifyMfa('Enrollment commits factor and eight recovery hashes',count($codes)===8&&count(json_decode($factor['recovery_hashes_json'],true))===8&&$cipher->decrypt($id,$factor['secret_encrypted'])===$setup);
    verifyMfa('Recovery codes are never stored in plaintext',!str_contains($factor['recovery_hashes_json'],str_replace('-','',$codes[0]))&&!str_contains($factor['recovery_hashes_json'],$codes[0]));
    verifyMfa('Successful enrollment resets failure counter',(int)$users->findById($id)['failed_attempts']===0);
    $client=mfaClient();session_write_close();$page=mfaRequest('/account/mfa/recovery',$client);verifyMfa('Recovery page is private and displayed once',$page['code']===200&&$page['no_store']&&str_contains($page['body'],$codes[0])&&!str_contains(mfaRequest('/account/mfa/recovery',$client)['body'],$codes[0]));
    verifyMfa('Enrolling authenticator invalidates older signed-in sessions',mfaRequest('/catalog',$olderClient)['location']==='/login');
    $challenge=$mfa->loginChallenge($id);$r=$mfa->verifyLogin($challenge,$enrollmentCode);verifyMfa('Enrollment code cannot be replayed for login',!$r['ok']);
    $r=$mfa->verifyLogin($challenge,$codes[0]);verifyMfa('Unused recovery code succeeds',$r['ok']);verifyMfa('Used recovery code is rejected',!$mfa->verifyLogin($challenge,$codes[0])['ok']);
    $pdo->prepare('UPDATE user_authenticators SET last_used_step=-1 WHERE user_id=?')->execute([$id]);
    $currentCode=$totp->now();verifyMfa('Current authenticator code succeeds',$mfa->verifyLogin($challenge,$currentCode)['ok']);verifyMfa('Authenticator code replay rejected',!$mfa->verifyLogin($challenge,$currentCode)['ok']);
    $expired=$challenge;$expired['expires_at']=time()-1;verifyMfa('Expired sign-in challenge rejected',!$mfa->verifyLogin($expired,$codes[1])['ok']);
    $users->resetFailures($id);$pdo->prepare('UPDATE user_authenticators SET last_used_step=-1 WHERE user_id=?')->execute([$id]);
    foreach(['totp'=>$totp->now(),'recovery'=>$codes[1]] as $kind=>$code){
        $job=$scratch.'/mfa-race-'.$kind.'.json';file_put_contents($job,json_encode(['flow'=>$mfa->loginChallenge($id),'code'=>$code],JSON_THROW_ON_ERROR));$workers=[];
        for($i=0;$i<2;$i++){$pipes=[];$h=proc_open([PHP_BINARY,__FILE__,...array_slice($argv,1),'--worker='.$job],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$h,$pipes];}
        $ok=[];foreach($workers as [$h,$pipes]){$out=stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($h)!==0)throw new RuntimeException();$ok[]=json_decode($out,true)['ok'];}
        sort($ok);verifyMfa('Concurrent '.$kind.' cannot be reused',$ok===[false,true]);$users->resetFailures($id);
    }
    $badFlow=$mfa->loginChallenge($id);$badFlow['version']=str_repeat('0',64);verifyMfa('Changed enrollment invalidates old challenge',!$mfa->verifyLogin($badFlow,$codes[2])['ok']);
    for($i=1;$i<=3;$i++)$mfa->verifyLogin($mfa->loginChallenge($id),'invalid');
    verifyMfa('Three incorrect authenticator attempts lock the account',App\Models\User::isLocked($users->findById($id))&&(int)$users->findById($id)['failed_attempts']===3);
    verifyMfa('Recovery does not bypass account lock',!$mfa->verifyLogin($challenge,$codes[2])['ok']);
    (new App\Services\SecurityManagement())->unlock($accounts['admin']['id'],$id,$users->findById($id)['locked_until']);
    verifyMfa('Admin unlock preserves authenticator factor',$mfa->factor($id)!==null&&$mfa->verifyLogin($mfa->loginChallenge($id),$codes[2])['ok']);
    mfaSession();$pdo->prepare('UPDATE otp_codes SET created_at=DATE_SUB(NOW(),INTERVAL 61 SECOND) WHERE user_id=?')->execute([$id]);$beforeOtp=(int)$pdo->query('SELECT COUNT(*) FROM otp_codes WHERE user_id='.$id)->fetchColumn();$r=$auth->attempt($a['username'],$a['password']);
    verifyMfa('Enrolled staff login requires authenticator without issuing email',(int)$pdo->query('SELECT COUNT(*) FROM otp_codes WHERE user_id='.$id)->fetchColumn()===$beforeOtp);
    verifyMfa('Password alone cannot finish enrolled login',$r['next']==='totp'&&!isset($_SESSION['auth_user'])&&isset($_SESSION['pending_totp']));
    $otpCount=(int)$pdo->query('SELECT COUNT(*) FROM otp_codes WHERE user_id='.$id)->fetchColumn();$restart=$auth->attempt($a['username'],$a['password']);
    verifyMfa('Restarting login during cooldown retains supported authenticator flow',$restart['next']==='totp'&&(int)$pdo->query('SELECT COUNT(*) FROM otp_codes WHERE user_id='.$id)->fetchColumn()===$otpCount&&!isset($_SESSION['auth_user']));
    $client=mfaClient();session_write_close();verifyMfa('Protected dashboard denied before authenticator',mfaRequest('/catalog',$client)['location']==='/login');
    verifyMfa('Authenticator form is private',mfaRequest('/verify-authenticator',$client)['code']===200&&mfaRequest('/verify-authenticator',$client)['no_store']);
    verifyMfa('Authenticator POST rejects invalid CSRF',mfaRequest('/verify-authenticator',$client,['_token'=>'bad','authenticator_code'=>$codes[3]])['code']===419);
    $r=mfaRequest('/verify-authenticator',$client,['_token'=>$client['csrf'],'authenticator_code'=>$codes[3]]);
    verifyMfa('Authenticator HTTP step completes login and rotates session',$r['location']==='/catalog'&&$r['cookie']!==''&&$r['cookie']!==$client['cookie']);$authenticated=['cookie'=>$r['cookie'],'csrf'=>''];
    verifyMfa('MFA login retains catalog permissions',mfaRequest('/catalog/products',$authenticated)['code']===200&&mfaRequest('/admin/security',$authenticated)['code']===403);
    $pdo->prepare('UPDATE users SET status="inactive" WHERE id=?')->execute([$id]);verifyMfa('Inactive account cannot complete authenticator step',!$mfa->verifyLogin($mfa->loginChallenge($id),$codes[4])['ok']);$pdo->prepare('UPDATE users SET status="active" WHERE id=?')->execute([$id]);
    mfaSession();$denied=false;try{$mfa->disable($id,'incorrect',$codes[4]);}catch(DomainException){$denied=true;}verifyMfa('Removing authenticator requires password and second factor',$denied&&$mfa->factor($id)!==null);
    $mfa->disable($id,$a['password'],$codes[4]);verifyMfa('Removing authenticator preserves email MFA',$mfa->factor($id)===null&&(int)$users->findById($id)['mfa_enabled']===1);
    verifyMfa('Removing authenticator invalidates older authenticated sessions',mfaRequest('/catalog',$authenticated)['location']==='/login');
    mfaSession();$r=$auth->attempt($a['username'],$a['password']);verifyMfa('Confirmed removal permits password sign-in before reenrollment',$r['next']==='dashboard');
    $_SESSION['pending_totp']=['synthetic'=>true];$_SESSION['mfa_setup_flow']=['synthetic'=>true];$_SESSION['mfa_recovery_codes']=['synthetic'];$auth->logout();verifyMfa('Logout clears every sensitive MFA flow',array_intersect_key($_SESSION,array_flip(['pending_totp','mfa_setup_flow','mfa_recovery_codes']))===[]);session_write_close();
    verifyMfa('Guest cannot start MFA setup',mfaRequest('/account/mfa',null,['action'=>'begin'])['location']==='/login');
    verifyMfa('Existing products and orders preserved',$products===$pdo->query('SELECT * FROM products ORDER BY id')->fetchAll()&&$orders===$pdo->query('SELECT * FROM orders ORDER BY id')->fetchAll());
    verifyMfa('All pre-existing accounts preserved',$oldUsers===$pdo->query('SELECT * FROM users WHERE id<'.min(array_column($accounts,'id')).' ORDER BY id')->fetchAll());
    file_put_contents($scratch.'/mfa-test-accounts-private.json',json_encode(['accounts'=>$accounts],JSON_THROW_ON_ERROR));
    $failed=count(array_filter($checks,static fn($r)=>$r['status']==='FAIL'));if(isset($o['output']))file_put_contents($o['output'],json_encode(['passed'=>count($checks)-$failed,'failed'=>$failed,'checks'=>$checks],JSON_PRETTY_PRINT));echo json_encode(['passed'=>count($checks)-$failed,'failed'=>$failed]),PHP_EOL;exit($failed?1:0);
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,'MFA TESTS STOPPED: '.get_class($e).' in '.basename($e->getFile()).' at line '.$e->getLine().'. Private details suppressed.'.PHP_EOL);exit(1);}
