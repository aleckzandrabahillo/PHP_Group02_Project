<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('AVELA_HAIR_TEST_LIBRARY',true);require __DIR__.'/hair_profiles.php';require_once dirname(__DIR__).'/vendor/autoload.php';
date_default_timezone_set((string)env('APP_TIMEZONE','Asia/Manila'));
function passwordClient(int $id):array{
 session_name((string)env('SESSION_NAME'));session_id('');session_start();$user=(new App\Models\User())->findById($id);
 $_SESSION=['auth_user'=>['id'=>$id,'role'=>$user['role'],'username'=>$user['username'],'email'=>$user['email']],'last_activity'=>time(),'auth_password_version'=>$user['password_version'],'_csrf'=>bin2hex(random_bytes(32))];
 $version=App\Services\MfaService::sessionVersion($id);if($version!==null)$_SESSION['auth_mfa_version']=$version;
 $client=['cookie'=>session_name().'='.session_id(),'csrf'=>$_SESSION['_csrf']];session_write_close();return $client;
}
try{
 $options=getopt('',['expect-database:','expect-port:','expect-datadir:','base-url:','session-directory:','scratch-directory:','output:','worker:']);
 $pdo=App\Core\Database::connection();$target=$pdo->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch();$base=$options['base-url']??'';$url=parse_url($base);$sessions=realpath($options['session-directory']??'');$scratch=realpath($options['scratch-directory']??'');$norm=static fn($p)=>strtolower(rtrim(str_replace('\\','/',$p),'/'));
 if(!preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D',$target['db'])||($options['expect-database']??'')!==$target['db']||env('DB_HOST')!=='127.0.0.1'||(int)($options['expect-port']??0)!==(int)$target['port']||in_array((int)$target['port'],[3306,13307],true)||$norm($options['expect-datadir']??'')!==$norm($target['datadir'])||env('APP_ENV')!=='development'||env('MAIL_DRIVER')!=='log'||!$sessions||!$scratch||str_starts_with($norm($scratch),$norm(dirname(__DIR__)).'/')||($url['host']??'')!=='127.0.0.1'||in_array((int)($url['port']??80),[80,18080],true))throw new RuntimeException('Disposable guard');
 ini_set('session.save_path',$sessions);$users=new App\Models\User();$service=new App\Services\PasswordService();$checks=[];
 if(($options['worker']??'')==='change'){$input=json_decode((string)fgets(STDIN),true,8,JSON_THROW_ON_ERROR);try{$service->change($input['id'],$input['current'],$input['new']);echo 'changed';}catch(DomainException){echo 'denied';}exit;}
 $make=static function(string $role='customer')use($users):array{$name='password_'.bin2hex(random_bytes(5));$password='Ab1!'.bin2hex(random_bytes(18));$id=$role==='customer'?$users->createCustomer(['username'=>$name,'email'=>$name.'@example.invalid','full_name'=>'Synthetic password','contact_no'=>'+639123456789','password_hash'=>password_hash($password,PASSWORD_DEFAULT)]):$users->createStaff($role,$name.'@example.invalid',$name,password_hash($password,PASSWORD_DEFAULT),'Synthetic password');if($role==='customer')$users->activate($id);return ['id'=>$id,'password'=>$password,'name'=>$name,'email'=>$name.'@example.invalid'];};
 $admin=$make('admin');$policy=new App\Services\SecurityManagement();$defaults=['max_login_attempts'=>3,'lockout_minutes'=>15,'session_timeout_minutes'=>30,'password_min_length'=>12,'password_min_uppercase'=>1,'password_min_lowercase'=>1,'password_min_numbers'=>1,'password_min_special'=>1];$policy->savePolicy($admin['id'],$defaults);
 verifyHair('Password migration repeat adds nothing',(new App\Services\PasswordMigration())->run($pdo,false)['pending_additions']===0);
 verifyHair('Legacy unknown timestamp is never expired',!App\Services\PasswordService::expired(['password_changed_at'=>null]));
 $envFile=$scratch.'/password-expiry.env';file_put_contents($envFile,"PASSWORD_EXPIRY_DAYS=0\n");App\Core\Env::load($envFile);
 verifyHair('Existing explicit disabled setting is preserved',!App\Services\PasswordService::expired(['password_changed_at'=>'2000-01-01']));
file_put_contents($envFile,"PASSWORD_EXPIRY_DAYS=90\n");App\Core\Env::load($envFile);
 verifyHair('Approved-period support identifies expired password',App\Services\PasswordService::expired(['password_changed_at'=>date('Y-m-d H:i:s',time()-91*86400)]));
 verifyHair('90-day expiry includes the exact deadline',App\Services\PasswordService::expired(['password_changed_at'=>date('Y-m-d H:i:s',time()-90*86400)]));
 verifyHair('Password one minute before deadline remains eligible',!App\Services\PasswordService::expired(['password_changed_at'=>date('Y-m-d H:i:s',time()-90*86400+60)]));
 verifyHair('Recent password remains eligible',!App\Services\PasswordService::expired(['password_changed_at'=>date('Y-m-d H:i:s')]));
 verifyHair('Legacy account stays compatible before approved transition',!App\Services\PasswordService::expired(['password_changed_at'=>null]));
 $legacyGraceDeadline=time()+90*86400;
 file_put_contents($envFile,"PASSWORD_EXPIRY_DAYS=90\nPASSWORD_LEGACY_GRACE_END=".gmdate('Y-m-d\TH:i:sP',$legacyGraceDeadline)."\n");App\Core\Env::load($envFile);
 verifyHair('Legacy account receives prospective 90-day grace without rewriting age',!App\Services\PasswordService::expired(['password_changed_at'=>null])&&App\Services\PasswordService::expiresAt(['password_changed_at'=>null])===$legacyGraceDeadline);
 file_put_contents($envFile,"PASSWORD_LEGACY_GRACE_END=2000-01-01T00:00:00+00:00\n");App\Core\Env::load($envFile);
 verifyHair('Legacy account expires after explicit grace deadline',App\Services\PasswordService::expired(['password_changed_at'=>null]));
 verifyHair('Known current password ignores legacy grace date',!App\Services\PasswordService::expired(['password_changed_at'=>date('Y-m-d H:i:s')]));
 foreach(['PASSWORD_EXPIRY_DAYS=unexpected',"PASSWORD_EXPIRY_DAYS=90\nPASSWORD_LEGACY_GRACE_END=not-a-date"] as $bad){file_put_contents($envFile,$bad."\n");App\Core\Env::load($envFile);$denied=false;try{App\Services\PasswordService::expired(['password_changed_at'=>null]);}catch(RuntimeException){$denied=true;}verifyHair('Invalid enabled expiry configuration fails closed',$denied);}
 file_put_contents($envFile,"PASSWORD_EXPIRY_DAYS=90\nPASSWORD_LEGACY_GRACE_END=\n");App\Core\Env::load($envFile);
 file_put_contents($envFile,"PASSWORD_EXPIRY_DAYS=0\n");App\Core\Env::load($envFile);
 $a=$make();$b=$make();$qa=passwordClient($a['id']);$qb=passwordClient($b['id']);$new='Cd2!'.bin2hex(random_bytes(18));
 verifyHair('Guest change-password page protected',hairRequest('/account/password')['code']===302);
 verifyHair('Guest password mutation protected',hairRequest('/account/password',null,['password'=>$new])['code']===302);
 verifyHair('Customer password form available',hairRequest('/account/password',$qa)['code']===200);
 verifyHair('Staff password form available',hairRequest('/account/password',passwordClient($admin['id']))['code']===200);
 foreach([null,'bad',['bad']] as $csrf)verifyHair('Password mutation rejects missing/invalid/malformed CSRF',hairRequest('/account/password',$qa,['_token'=>$csrf,'current_password'=>$a['password'],'password'=>$new,'password_confirmation'=>$new])['code']===419);
 $bad=hairRequest('/account/password',$qa,['_token'=>$qa['csrf'],'current_password'=>['malformed'],'password'=>$new,'password_confirmation'=>$new]);verifyHair('Malformed password fields rejected safely',$bad['code']===302&&password_verify($a['password'],$users->findById($a['id'])['password_hash']));
 foreach(['short','abcdefghijklmnop','Ab1'.str_repeat(' ',15),'Ab1!'.str_repeat('x',70)] as $weak){$denied=false;try{$service->change($a['id'],$a['password'],$weak);}catch(DomainException){$denied=true;}verifyHair('Weak or oversized new password rejected',$denied);}
 $denied=false;try{$service->change($a['id'],$a['password'],$a['password']);}catch(DomainException){$denied=true;}verifyHair('Current password cannot be reused',$denied);
 $locked=$make();for($i=0;$i<3;$i++){try{$service->change($locked['id'],'wrong',$new);}catch(DomainException){}}
 verifyHair('Three wrong current-password proofs persist a lock',App\Models\User::isLocked($users->findById($locked['id'])));
 $before=$users->findById($a['id']);$pdo->exec("CREATE TRIGGER password_audit_fault BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic fault'");
 $denied=false;try{$service->change($a['id'],$a['password'],$new);}catch(Throwable){$denied=true;}$pdo->exec('DROP TRIGGER password_audit_fault');
 verifyHair('Audit failure rolls back hash/timestamp/version',$denied&&$users->findById($a['id'])===$before);
 $oldLogin=$users->findById($a['id']);$other=passwordClient($a['id']);$form=['_token'=>$qa['csrf'],'current_password'=>$a['password'],'password'=>$new,'password_confirmation'=>$new,'user_id'=>$b['id']];
 $response=hairRequest('/account/password',$qa,$form);$changed=$users->findById($a['id']);
 verifyHair('Actual HTTP password change succeeds without forced sign-out',$response['code']===302&&in_array('Location: /account/password',$response['headers'],true));
 verifyHair('Only authenticated owner changed, injected ID ignored',password_verify($new,$changed['password_hash'])&&password_verify($b['password'],$users->findById($b['id'])['password_hash']));
 verifyHair('New password stored only as a hash',$changed['password_hash']!==$new&&!password_verify($a['password'],$changed['password_hash']));
 verifyHair('Timestamp and credential version written',$changed['password_changed_at']!==null&&preg_match('/^[a-f0-9]{64}$/D',$changed['password_version'])===1);
 verifyHair('Other authenticated session revoked',hairRequest('/profile',$other)['code']===302&&in_array('Location: /login',hairRequest('/profile',$other)['headers'],true));
 $renewed=$qa;foreach($response['headers'] as $header)if(preg_match('/^Set-Cookie:\s*([^;]+)/i',$header,$cookie))$renewed['cookie']=$cookie[1];
 verifyHair('Renewed initiating session stays authenticated',hairRequest('/profile',$renewed)['code']===200);
 verifyHair('Original session ID cannot be reused',hairRequest('/profile',$qa)['code']===302);
 verifyHair('Old CSRF token is revoked on the retained session',hairRequest('/account/session/activity',$renewed,['_token'=>$qa['csrf']])['code']===419);
 $newPage=hairRequest('/account/password',$renewed);preg_match('/name="_token" value="([a-f0-9]+)"/',$newPage['body'],$freshToken);
 verifyHair('Retained session receives a fresh usable CSRF token',!empty($freshToken[1])&&$freshToken[1]!==$qa['csrf']&&hairRequest('/account/session/activity',$renewed,['_token'=>$freshToken[1]])['code']===200);
 $setCookies=array_values(array_filter($response['headers'],static fn($h)=>str_starts_with(strtolower($h),'set-cookie:')));
 verifyHair('Password change regenerates the initiating session ID',count($setCookies)>0&&!str_contains(implode('',$setCookies),$qa['cookie']));
 $logs=new App\Services\LogService();$otp=new App\Services\OtpService(new App\Services\MailService(),$logs);$auth=new App\Services\AuthService($users,$otp,$logs);
 session_name((string)env('SESSION_NAME'));session_id('');session_start();verifyHair('Stale password snapshot cannot finalize login',!$auth->finalizeLogin($oldLogin));
 verifyHair('Normal login with replacement password succeeds',$auth->attempt($a['name'],$new)['ok']);session_write_close();
 $staff=$make('catalog_manager');session_id('');session_start();$otp->issue($staff['id'],$staff['email'],'login');$oldFlow=['user_id'=>$staff['id'],'purpose'=>'login','email'=>$staff['email'],'password_version'=>$users->findById($staff['id'])['password_version']??''];$service->change($staff['id'],$staff['password'],$new);
 foreach(array_reverse(file(dirname(__DIR__).'/storage/logs/dev_mail.log')) as $line)if(str_contains($line,'TO: '.$staff['email'].' | PURPOSE: login')&&preg_match('/OTP: ([0-9]{6})/',$line,$m)){$oldCode=$m[1];break;}
 App\Core\Session::put('pending_flow',$oldFlow);verifyHair('Pending email proof cannot survive password change',!$auth->verifyPendingOtp($oldCode)['ok']);session_write_close();
 $factorUser=$make();session_id('');session_start();$mfa=new App\Services\MfaService($users,$otp,$logs);
 $mfa->beginEnrollment($factorUser['id'],$factorUser['password']);$secret=$mfa->setupSecret($factorUser['id']);
 foreach(array_reverse(file(dirname(__DIR__).'/storage/logs/dev_mail.log')) as $line)if(str_contains($line,'TO: '.$factorUser['email'].' | PURPOSE: login')&&preg_match('/OTP: ([0-9]{6})/',$line,$m)){$enrollCode=$m[1];break;}
 $totp=OTPHP\TOTP::create($secret);$mfa->confirmEnrollment($factorUser['id'],$enrollCode,$totp->now());
 $factorBefore=$mfa->factor($factorUser['id']);$challenge=$mfa->loginChallenge($factorUser['id']);$service->change($factorUser['id'],$factorUser['password'],$new);
 verifyHair('Password change preserves authenticator and recovery material',$mfa->factor($factorUser['id'])===$factorBefore);
 verifyHair('Pending authenticator proof cannot survive password change',!$mfa->verifyLogin($challenge,$totp->at(time()+30))['ok']);session_write_close();
 $pending=$make();session_id('');session_start();$mfa->beginEnrollment($pending['id'],$pending['password']);$service->change($pending['id'],$pending['password'],$new);verifyHair('Pending enrollment key revoked by password change',$mfa->setupSecret($pending['id'])===null);session_write_close();
 $race=$make();$workers=[];$workerArgs=[PHP_BINARY,__FILE__];foreach(['expect-database','expect-port','expect-datadir','base-url','session-directory','scratch-directory'] as $key)$workerArgs[]='--'.$key.'='.$options[$key];$workerArgs[]='--worker=change';
 foreach([1,2] as $n){$process=proc_open($workerArgs,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);fwrite($pipes[0],json_encode(['id'=>$race['id'],'current'=>$race['password'],'new'=>'Cd2!'.bin2hex(random_bytes(18))])."\n");fclose($pipes[0]);$workers[]=[$process,$pipes];}
 $success=0;$clean=true;foreach($workers as [$process,$pipes]){$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$clean=$clean&&proc_close($process)===0&&$error==='';$success+=(int)($output==='changed');}
 verifyHair('Concurrent current-password submissions change exactly once',$clean&&$success===1&&(int)$pdo->query('SELECT COUNT(*) FROM audit_logs WHERE action="password_changed" AND target_id='.$race['id'])->fetchColumn()===1);
 $privateEnv=dirname(__DIR__).'/.env';$savedEnv=file_get_contents($privateEnv);$fresh=passwordClient($a['id']);
 try{file_put_contents($privateEnv,$savedEnv."\nPASSWORD_EXPIRY_DAYS=90\n");$pdo->prepare('UPDATE users SET password_changed_at=DATE_SUB(NOW(),INTERVAL 91 DAY) WHERE id=?')->execute([$a['id']]);$expired=hairRequest('/profile',$fresh);verifyHair('Configured expired session redirected to password change',$expired['code']===302&&in_array('Location: /account/password',$expired['headers'],true));verifyHair('Expired session can access the protected change form',hairRequest('/account/password',$fresh)['code']===200);
 $ordersBefore=$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();$stockBefore=$pdo->query('SELECT SUM(stock_qty) FROM products')->fetchColumn();
 $blocked=hairRequest('/checkout',$fresh,['_token'=>$fresh['csrf'],'payment_method'=>'cod']);
 verifyHair('Expired session cannot place an order',$blocked['code']===302&&in_array('Location: /account/password',$blocked['headers'],true)&&$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn()===$ordersBefore&&$pdo->query('SELECT SUM(stock_qty) FROM products')->fetchColumn()===$stockBefore);}finally{file_put_contents($privateEnv,$savedEnv);$pdo->prepare('UPDATE users SET password_changed_at=NOW() WHERE id=?')->execute([$a['id']]);}
 $policy->savePolicy($admin['id'],array_replace($defaults,['password_min_uppercase'=>2,'password_min_lowercase'=>2,'password_min_numbers'=>2,'password_min_special'=>2]));
 verifyHair('Administrator stronger complexity enforced',App\Core\Validator::passwordError('Ab1!'.str_repeat('x',12))!==null&&App\Core\Validator::passwordError('ABab12!!'.str_repeat('x',12))===null);
 foreach(['password_min_uppercase','password_min_lowercase','password_min_numbers','password_min_special'] as $field){$denied=false;try{App\Services\SecurityPolicy::validate(array_replace($defaults,[$field=>0]));}catch(InvalidArgumentException){$denied=true;}verifyHair('Compulsory complexity cannot be disabled / '.$field,$denied);}
 $policy->savePolicy($admin['id'],$defaults);
 $register=hairRequest('/register')['body'];verifyHair('Registration checklist uses effective server policy',str_contains($register,'data-password-minimum="12"')&&str_contains($register,'data-password-uppercase="1"'));
 verifyHair('Enrollment key is read-only and grouped without shortening',str_contains(file_get_contents(dirname(__DIR__).'/app/Views/account/mfa.php'),'readonly autocomplete="off"')&&str_contains(file_get_contents(dirname(__DIR__).'/app/Views/account/mfa.php'),"str_split($"."setupSecret, 4)"));
 verifyHair('Password audit contains no credential material',(int)$pdo->query('SELECT COUNT(*) FROM audit_logs WHERE action="password_changed" AND target_id='.$a['id'].' AND details NOT LIKE "%hash%"')->fetchColumn()===1);
 $result=['checks'=>$checks];if(isset($options['output']))file_put_contents($options['output'],json_encode($result,JSON_PRETTY_PRINT));ob_end_flush();exit(count(array_filter($checks,static fn($c)=>$c['status']==='FAIL'))?1:0);
}catch(Throwable $e){fwrite(STDERR,'Password tests stopped: '.get_class($e).' / '.basename($e->getFile()).':'.$e->getLine()."; no proof values printed.\n");exit(1);}
