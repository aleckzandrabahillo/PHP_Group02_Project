<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('AVELA_HAIR_TEST_LIBRARY',true);require __DIR__.'/hair_profiles.php';require_once dirname(__DIR__).'/vendor/autoload.php';
date_default_timezone_set((string)env('APP_TIMEZONE','Asia/Manila'));

function authFlowCode(string $email,string $purpose='activation'):string{
 foreach(array_reverse(file(dirname(__DIR__).'/storage/logs/dev_mail.log')) as $line)if(str_contains($line,'TO: '.$email.' | PURPOSE: '.$purpose.' |')&&preg_match('/OTP: ([0-9]{6})/',$line,$m))return $m[1];
 throw new RuntimeException('Synthetic code unavailable.');
}
function authFlowLink(int $id,string $email):string{
 $q=App\Core\Database::connection()->prepare('SELECT token_digest FROM activation_tokens WHERE user_id=? ORDER BY id DESC LIMIT 1');$q->execute([$id]);$digest=$q->fetchColumn();
 foreach(glob((string)env('MAIL_DEV_OUTBOX_DIRECTORY').'/*.eml') as $file){$text=file_get_contents($file);if(str_contains($text,'To: '.$email."\n")&&preg_match('/#token=([a-f0-9]{64})/',$text,$m)&&hash_equals((string)$digest,hash('sha256','avela:activation:otp-verified:v2:'.$m[1])))return $m[1];}
 throw new RuntimeException('Synthetic activation link unavailable.');
}
function authFlowRequest(string $path,array &$client,?array $data=null):array{
 $r=hairRequest($path,$client,$data);
 foreach($r['headers'] as $line)if(preg_match('/^Set-Cookie:\s*'.preg_quote((string)env('SESSION_NAME'),'/').'=([^;]+)/i',$line,$m))$client['cookie']=env('SESSION_NAME').'='.$m[1];
 return $r;
}
function authFlowSession(?array $client=null):void{
 if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
 session_name((string)env('SESSION_NAME'));session_id($client?explode('=',$client['cookie'],2)[1]:'');session_start();
 if(!$client)$_SESSION=[];
}
function authFlowClient():array{return ['cookie'=>session_name().'='.session_id(),'csrf'=>App\Core\Csrf::token()];}

try{
 $o=getopt('',['expect-database:','expect-port:','expect-datadir:','base-url:','session-directory:','scratch-directory:','output:']);
 $pdo=App\Core\Database::connection();$target=$pdo->query('SELECT DATABASE() db,@@port port,@@datadir dir')->fetch();$base=$o['base-url']??'';$url=parse_url($base);$sessions=realpath($o['session-directory']??'');$scratch=realpath($o['scratch-directory']??'');
 $norm=static fn(string $v):string=>strtolower(rtrim(str_replace('\\','/',$v),'/'));
 if(!preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D',$target['db'])||($o['expect-database']??'')!==$target['db']||(int)($o['expect-port']??0)!==(int)$target['port']||in_array((int)$target['port'],[3306,13307],true)||$norm($o['expect-datadir']??'')!==$norm($target['dir'])||env('DB_HOST')!=='127.0.0.1'||env('APP_ENV')!=='development'||env('MAIL_DRIVER')!=='log'||!$sessions||!$scratch||str_starts_with($norm($sessions),$norm(dirname(__DIR__)).'/')||str_starts_with($norm($scratch),$norm(dirname(__DIR__)).'/')||($url['host']??'')!=='127.0.0.1'||in_array((int)($url['port']??80),[80,18080],true))throw new RuntimeException('Disposable-only guards.');
 ini_set('session.save_path',$sessions);$checks=[];$users=new App\Models\User();$logs=new App\Services\LogService();$mail=new App\Services\MailService();$otp=new App\Services\OtpService($mail,$logs);$auth=new App\Services\AuthService($users,$otp,$logs);$activation=new App\Services\ActivationService($otp,$mail,$logs);$cipher=new App\Services\MfaCipher();
 $signInPage=hairRequest('/login');
 verifyHair('Sign-in has no optional enrollment suggestion',$signInPage['code']===200&&!str_contains($signInPage['body'],'name="setup_authenticator"')&&!str_contains($signInPage['body'],'authenticator-offer'));
 verifyHair('Sign-in retains password, CAPTCHA and CSRF controls',str_contains($signInPage['body'],'name="password"')&&str_contains($signInPage['body'],'class="g-recaptcha"')&&str_contains($signInPage['body'],'name="_token"'));
 $make=static function(string $role='customer')use($users):array{
  $name='flow_'.bin2hex(random_bytes(5));$pass='Ab1!'.bin2hex(random_bytes(18));$email=$name.'@example.invalid';
  $id=$role==='customer'?$users->createCustomer(['username'=>$name,'email'=>$email,'password_hash'=>password_hash($pass,PASSWORD_DEFAULT),'full_name'=>'Synthetic auth flow','contact_no'=>'+639123456789']):$users->createStaff($role,$email,$name,password_hash($pass,PASSWORD_DEFAULT),'Synthetic staff flow');
  if($role==='customer')$users->activate($id);return compact('id','name','pass','email');
 };
 $customer=$make();authFlowSession();$count=(int)$pdo->query('SELECT COUNT(*) FROM otp_codes')->fetchColumn();$r=$auth->attempt($customer['name'],$customer['pass']);
 verifyHair('Unenrolled Customer signs in without email OTP',$r['next']==='dashboard'&&isset($_SESSION['auth_user'])&&(int)$pdo->query('SELECT COUNT(*) FROM otp_codes')->fetchColumn()===$count);
 verifyHair('First successful unenrolled Customer login schedules own optional suggestion',App\Core\Session::get('first_login_mfa_offer')===$customer['id']);
 $firstClient=authFlowClient();session_write_close();$firstHome=authFlowRequest('/',$firstClient);$secondHome=authFlowRequest('/',$firstClient);
 verifyHair('Enrollment suggestion appears once on the landing page only',str_contains($firstHome['body'],'id="first-login-offer"')&&!str_contains($secondHome['body'],'id="first-login-offer"'));
 authFlowSession($firstClient);$auth->logout();
 verifyHair('Logout clears optional enrollment suggestion',App\Core\Session::get('first_login_mfa_offer')===null);
 $pdo->prepare('UPDATE users SET mfa_enabled=1 WHERE id=?')->execute([$customer['id']]);$r=$auth->attempt($customer['name'],$customer['pass']);verifyHair('Legacy email-only flag does not force Customer email OTP',$r['next']==='dashboard');verifyHair('Subsequent successful login does not repeat suggestion',App\Core\Session::get('first_login_mfa_offer')===null);$auth->logout();
 $secret=OTPHP\TOTP::generate()->getSecret();$totp=OTPHP\TOTP::createFromSecret($secret);$recovery=strtoupper(bin2hex(random_bytes(16)));$version=bin2hex(random_bytes(32));
 $pdo->prepare('INSERT INTO user_authenticators(user_id,secret_encrypted,recovery_hashes_json,last_used_step,enabled_at,credential_version) VALUES(?,?,?,-1,NOW(),?)')->execute([$customer['id'],$cipher->encrypt($customer['id'],$secret),json_encode([$cipher->recoveryHash($customer['id'],$recovery)]),$version]);
 $r=$auth->attempt($customer['name'],$customer['pass']);verifyHair('Enrolled Customer goes directly to Authenticator',$r['next']==='totp'&&!isset($_SESSION['auth_user'])&&!isset($_SESSION['pending_flow'])&&(int)$pdo->query('SELECT COUNT(*) FROM otp_codes')->fetchColumn()===$count);
 $challenge=$_SESSION['pending_totp'];$r=$auth->attempt($customer['name'],$customer['pass']);verifyHair('Restart preserves unexpired Customer Authenticator challenge',$r['next']==='totp'&&$_SESSION['pending_totp']===$challenge);
 $_SESSION['pending_totp']['expires_at']=time()-1;$r=$auth->verifyPendingAuthenticator('123456');verifyHair('Expired Customer challenge cannot sign in',!$r['ok']&&!isset($_SESSION['auth_user']));
 $auth->attempt($customer['name'],$customer['pass']);$r=$auth->verifyPendingAuthenticator('invalid');verifyHair('Wrong Customer Authenticator code cannot sign in',!$r['ok']&&!isset($_SESSION['auth_user']));
 $code=$totp->now();$r=$auth->verifyPendingAuthenticator($code);verifyHair('Correct Customer Authenticator completes login',$r['ok']&&($_SESSION['auth_user']['id']??0)===$customer['id']);$auth->logout();
 $auth->attempt($customer['name'],$customer['pass']);verifyHair('Customer Authenticator replay is rejected',!$auth->verifyPendingAuthenticator($code)['ok']&&!isset($_SESSION['auth_user']));
 verifyHair('Customer can use an existing one-use recovery code',$auth->verifyPendingAuthenticator($recovery)['ok']);$auth->logout();$auth->attempt($customer['name'],$customer['pass']);verifyHair('Recovery code cannot be reused',!$auth->verifyPendingAuthenticator($recovery)['ok']);
 $pdo->prepare('UPDATE users SET failed_attempts=0,locked_until=NULL,lock_requires_admin=0,status="inactive" WHERE id=?')->execute([$customer['id']]);verifyHair('Inactive Customer cannot finish Authenticator',!$auth->verifyPendingAuthenticator($totp->now())['ok']);
 $pdo->prepare('UPDATE users SET status="active" WHERE id=?')->execute([$customer['id']]);authFlowSession();$auth->attempt($customer['name'],$customer['pass']);$pdo->prepare('UPDATE users SET password_version=? WHERE id=?')->execute([bin2hex(random_bytes(32)),$customer['id']]);verifyHair('Password change invalidates Customer challenge',!$auth->verifyPendingAuthenticator($totp->now())['ok']);
 $disableCode=strtoupper(bin2hex(random_bytes(16)));$pdo->prepare('UPDATE user_authenticators SET recovery_hashes_json=? WHERE user_id=?')->execute([json_encode([$cipher->recoveryHash($customer['id'],$disableCode)]),$customer['id']]);
 $mfa=new App\Services\MfaService($users,$otp,$logs);$mfa->disable($customer['id'],$customer['pass'],$disableCode);verifyHair('Turning off optional Customer Authenticator disables MFA',(int)$users->findById($customer['id'])['mfa_enabled']===0&&$mfa->factor($customer['id'])===null);
 authFlowSession();$r=$auth->attempt($customer['name'],$customer['pass']);$disabledClient=authFlowClient();verifyHair('Formerly enrolled Customer can sign in without email OTP',$r['next']==='dashboard'&&($_SESSION['auth_mfa_version']??null)===App\Services\MfaService::sessionVersion($customer['id']));session_write_close();verifyHair('Formerly enrolled Customer session can open protected profile',hairRequest('/profile',$disabledClient)['code']===200);
 foreach(['admin','catalog_manager'] as $role){$staff=$make($role);authFlowSession();$r=$auth->attempt($staff['name'],$staff['pass']);verifyHair($role.' can sign in before authenticator enrollment',$r['next']==='dashboard'&&($_SESSION['auth_user']['role']??'')===$role);verifyHair($role.' initial login sends no email code',(int)$pdo->query('SELECT COUNT(*) FROM otp_codes WHERE user_id='.$staff['id'])->fetchColumn()===0);$auth->logout();}
 // Previously issued Customer email-login challenges must not remain a second login path.
 $legacy=$make();$pdo->prepare('UPDATE users SET mfa_enabled=1 WHERE id=?')->execute([$legacy['id']]);$otp->issue($legacy['id'],$legacy['email'],'login');authFlowSession();App\Core\Session::put('pending_flow',['user_id'=>$legacy['id'],'email'=>$legacy['email'],'purpose'=>'login','password_version'=>$users->findById($legacy['id'])['password_version']??'']);verifyHair('Old Customer email-login proof is rejected',!$auth->verifyPendingOtp(authFlowCode($legacy['email'],'login'))['ok']&&!isset($_SESSION['auth_user']));
 // Registration must send OTP first, then a separate activation link, then offer optional setup.
 authFlowSession();$name='registration_'.bin2hex(random_bytes(5));$pass='Ab1!'.bin2hex(random_bytes(18));$email=$name.'@example.invalid';$auth->register(['username'=>$name,'email'=>$email,'password'=>$pass,'full_name'=>'Synthetic registration','contact_no'=>'09123456789']);$user=$users->findByIdentifier($name);$id=(int)$user['id'];$client=authFlowClient();session_write_close();
 verifyHair('Registration stays pending and has no activation link before OTP',$user['status']==='pending'&&(int)$pdo->query('SELECT COUNT(*) FROM activation_tokens WHERE user_id='.$id)->fetchColumn()===0);
 $first=authFlowRequest('/verify-otp',$client);verifyHair('First screen asks for email code only',str_contains($first['body'],'Verify your email')&&str_contains($first['body'],'name="otp"')&&!str_contains($first['body'],'Either method'));
 $bad=authFlowRequest('/verify-otp',$client,['_token'=>['bad'],'otp'=>'123456']);verifyHair('Registration OTP POST requires valid CSRF',$bad['code']===419);
 $registrationCode=authFlowCode($email);$verified=authFlowRequest('/verify-otp',$client,['_token'=>$client['csrf'],'otp'=>$registrationCode]);verifyHair('Correct registration code advances without activating',$verified['code']===302&&$users->findById($id)['status']==='pending');
 $waiting=authFlowRequest('/verify-otp',$client);verifyHair('Second screen shows activation link instructions without OTP input',str_contains($waiting['body'],'separate activation link')&&!str_contains($waiting['body'],'name="otp"')&&str_contains($waiting['body'],'Send a new activation link'));
 $oldLink=authFlowLink($id,$email);$before=(int)$pdo->query('SELECT COUNT(*) FROM activation_tokens WHERE user_id='.$id)->fetchColumn();authFlowRequest('/otp/resend',$client,['_token'=>$client['csrf']]);verifyHair('Activation link resend cooldown prevents duplicate send',(int)$pdo->query('SELECT COUNT(*) FROM activation_tokens WHERE user_id='.$id)->fetchColumn()===$before);
 $pdo->prepare('UPDATE activation_tokens SET created_at=DATE_SUB(NOW(),INTERVAL 61 SECOND) WHERE user_id=?')->execute([$id]);authFlowRequest('/otp/resend',$client,['_token'=>$client['csrf']]);$link=authFlowLink($id,$email);verifyHair('Resend replaces old link without activating',$link!==$oldLink&&!$activation->consume($oldLink)&&$users->findById($id)['status']==='pending');
 authFlowSession($client);$r=$auth->attempt($name,$pass);verifyHair('Pending login resumes verified activation stage',$r['next']==='activation_link'&&!isset($_SESSION['auth_user']));session_write_close();
 $done=authFlowRequest('/activate',$client,['_token'=>$client['csrf'],'activation_token'=>$link]);verifyHair('Separate verified activation link signs in and redirects home',$done['code']===302&&in_array('Location: /',$done['headers'],true)&&$users->findById($id)['status']==='active');
 $home=authFlowRequest('/',$client);verifyHair('Activation preserves a usable customer session with optional enrollment on home only',$home['code']===200&&str_contains($home['body'],'id="first-login-offer"')&&authFlowRequest('/profile',$client)['code']===200);
 verifyHair('Activation link cannot be reused',!$activation->consume($link));
 $oldToken=bin2hex(random_bytes(32));$pending=$users->createCustomer(['username'=>'old_'.bin2hex(random_bytes(5)),'email'=>'old_'.bin2hex(random_bytes(5)).'@example.invalid','password_hash'=>password_hash($pass,PASSWORD_DEFAULT),'full_name'=>'Synthetic legacy registration','contact_no'=>'09123456789']);$pdo->prepare('INSERT INTO activation_tokens(user_id,token_digest,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))')->execute([$pending,hash('sha256',$oldToken)]);verifyHair('Legacy combined-message link cannot bypass the new OTP-first flow',!$activation->consume($oldToken)&&$users->findById($pending)['status']==='pending');
 // A failed second email must preserve the OTP for a safe retry and keep the account pending.
 $failure=$make();$pdo->prepare('UPDATE users SET status="pending" WHERE id=?')->execute([$failure['id']]);$activation->issue($failure['id']);$failureCode=authFlowCode($failure['email']);
 $settings=$scratch.'/authflow-mail-disabled.env';file_put_contents($settings,"MAIL_DRIVER=unsupported\n");App\Core\Env::load($settings);$denied=false;try{$activation->verifyAndSendLink($failure['id'],$failureCode);}catch(App\Services\MailDeliveryException){$denied=true;}file_put_contents($settings,"MAIL_DRIVER=log\n");App\Core\Env::load($settings);
 verifyHair('Activation-mail failure rolls back link and code consumption',$denied&&$users->findById($failure['id'])['status']==='pending'&&(int)$pdo->query('SELECT COUNT(*) FROM activation_tokens WHERE user_id='.$failure['id'])->fetchColumn()===0&&$activation->verifyAndSendLink($failure['id'],$failureCode)['ok']);
 $result=['checks'=>$checks,'external_services_contacted'=>false];if(isset($o['output']))file_put_contents($o['output'],json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));ob_end_flush();exit(count(array_filter($checks,fn($c)=>$c['status']==='FAIL'))?1:0);
}catch(Throwable $e){fwrite(STDERR,'Customer auth tests stopped: '.get_class($e).' at line '.$e->getLine().'; private details suppressed.'.PHP_EOL);exit(1);}
