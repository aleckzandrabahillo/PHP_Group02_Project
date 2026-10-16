<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('AVELA_HAIR_TEST_LIBRARY',true);require __DIR__.'/hair_profiles.php';
try{
 $options=getopt('',['expect-database:','expect-port:','expect-datadir:','base-url:','session-directory:','scratch-directory:','output:']);
 $pdo=App\Core\Database::connection();$id=$pdo->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch();$base=$options['base-url']??'';$url=parse_url($base);$sessions=realpath($options['session-directory']??'');
 $norm=static fn($p)=>strtolower(rtrim(str_replace('\\','/',$p),'/'));
 if(!preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D',$id['db'])||($options['expect-database']??'')!==$id['db']||env('DB_HOST')!=='127.0.0.1'||(int)($options['expect-port']??0)!==(int)$id['port']||in_array((int)$id['port'],[3306,13307],true)||$norm($options['expect-datadir']??'')!==$norm($id['datadir'])||env('APP_ENV')!=='development'||env('MAIL_DRIVER')!=='log'||!$sessions||str_starts_with($norm($sessions),$norm(dirname(__DIR__)).'/')||($url['host']??'')!=='127.0.0.1'||in_array((int)($url['port']??80),[80,18080],true))throw new RuntimeException('Disposable-only guard');
 ini_set('session.save_path',$sessions);$checks=[];$users=new App\Models\User();$logs=new App\Services\LogService();$otp=new App\Services\OtpService(new App\Services\MailService(),$logs);$mfa=new App\Services\MfaService($users,$otp,$logs);$auth=new App\Services\AuthService($users,$otp,$logs);$security=new App\Services\SecurityManagement();$recovery=new App\Services\AdministratorRecovery($mfa,$otp,$logs);
 $prefix='persist_'.bin2hex(random_bytes(4));$accounts=[];
 foreach(['admin','admin2','catalog_manager'] as $role){$password='A9!'.bin2hex(random_bytes(18));$name=$prefix.'_'.$role;$userId=$users->createStaff($role==='admin2'?'admin':$role,$name.'@example.invalid',$name,password_hash($password,PASSWORD_DEFAULT),'Synthetic recovery staff');$accounts[$role]=['id'=>$userId,'name'=>$name,'password'=>$password];}
 $a=$accounts['admin'];$b=$accounts['admin2'];$c=$accounts['catalog_manager'];
 $security->savePolicy($a['id'],['max_login_attempts'=>3,'lockout_minutes'=>15,'session_timeout_minutes'=>30,'password_min_length'=>12]);
 verifyHair('Persistent flag fails closed even with an inconsistent review timestamp',App\Models\User::isLocked(['lock_requires_admin'=>1,'locked_until'=>null]));
 $migration=new App\Services\LockMigration();verifyHair('Repeated additive migration has zero additions',$migration->run($pdo,false)['pending_additions']===0);
 $pdo->prepare('UPDATE users SET failed_attempts=3,locked_until=DATE_ADD(NOW(),INTERVAL 15 MINUTE),lock_requires_admin=0 WHERE id=?')->execute([$c['id']]);
 verifyHair('Dry-run identifies active legacy lock without changing it',$migration->run($pdo,false)['active_locks_to_preserve']===1&&(int)$users->findById($c['id'])['lock_requires_admin']===0);
 $migration->run($pdo,true);verifyHair('Migration preserves active lock for manual release',(int)$users->findById($c['id'])['lock_requires_admin']===1);
 $security->unlock($a['id'],$c['id'],$users->findById($c['id'])['locked_until']);
 foreach([$a,$b] as $account){for($n=1;$n<=3;$n++){$auth->attempt($account['name'],'wrong');verifyHair('Credential failure '.$n.' / '.$account['name'],(int)$users->findById($account['id'])['failed_attempts']===$n&&App\Models\User::isLocked($users->findById($account['id']))===($n===3));}}
 verifyHair('All synthetic administrators locked, with tested console recovery available',App\Models\User::isLocked($users->findById($a['id']))&&App\Models\User::isLocked($users->findById($b['id'])));
 $pdo->prepare('UPDATE users SET locked_until="2000-01-01 00:00:00" WHERE id IN (?,?)')->execute([$a['id'],$b['id']]);
 $pdo->prepare('UPDATE locked_accounts SET locked_until="2000-01-01 00:00:00" WHERE user_id IN (?,?) AND unlocked_at IS NULL')->execute([$a['id'],$b['id']]);
 foreach([$a,$b] as $account){verifyHair('Elapsed review interval cannot restore password access',!$auth->attempt($account['name'],$account['password'])['ok']);}
 $before=(int)$pdo->query('SELECT COUNT(*) FROM locked_accounts')->fetchColumn();$users->recordFailure($a['id']);verifyHair('Repeated locked failure preserves one incident',(int)$pdo->query('SELECT COUNT(*) FROM locked_accounts')->fetchColumn()===$before);
 $denied=false;try{$security->unlock($c['id'],$a['id'],$users->findById($a['id'])['locked_until']);}catch(DomainException){$denied=true;}verifyHair('Catalog Manager cannot unlock',$denied);
 $denied=false;try{$security->unlock($b['id'],$a['id'],$users->findById($a['id'])['locked_until']);}catch(DomainException){$denied=true;}verifyHair('Locked Administrator cannot use normal unlock',$denied);
 $recovery->sendCode($a['id']);$code=null;
 foreach(file(dirname(__DIR__).'/storage/logs/dev_mail.log',FILE_IGNORE_NEW_LINES) as $line)if(str_contains($line,'TO: '.$a['name'].'@example.invalid | PURPOSE: login |')&&preg_match('/OTP: (\d{6}) /',$line,$match))$code=$match[1];
 if(!$code)throw new RuntimeException('Synthetic proof missing');
 session_name((string)env('SESSION_NAME'));session_start();App\Core\Session::put('pending_flow',['user_id'=>$a['id'],'purpose'=>'login','email'=>$a['name'].'@example.invalid']);
 verifyHair('Email OTP cannot bypass persisted lock',!$auth->verifyPendingOtp($code)['ok']);
 verifyHair('Wrong console password cannot unlock',!$recovery->unlock($a['id'],$a['id'],'wrong',$code));
 $pdo->exec("CREATE TRIGGER persistent_audit_fault BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic fault'");
 $denied=false;try{$recovery->unlock($a['id'],$a['id'],$a['password'],$code);}catch(Throwable){$denied=true;}$pdo->exec('DROP TRIGGER persistent_audit_fault');
 verifyHair('Recovery audit fault rolls back lock release and proof consumption',$denied&&App\Models\User::isLocked($users->findById($a['id'])));
 verifyHair('Verified Administrator can recover after every admin is locked',$recovery->unlock($a['id'],$a['id'],$a['password'],$code));
 verifyHair('Console recovery preserves and marks incident history',(int)$pdo->query('SELECT COUNT(*) FROM locked_accounts WHERE user_id='.$a['id'].' AND unlocked_by='.$a['id'].' AND unlocked_at IS NOT NULL')->fetchColumn()===1);
 verifyHair('Recovery does not create a signed-in session',App\Core\Session::get('auth_user')===null);
 verifyHair('Recovered Administrator role/status/factors preserved',$users->findById($a['id'])['role']==='admin'&&$users->findById($a['id'])['status']==='active'&&(int)$users->findById($a['id'])['mfa_enabled']===1);
 $security->unlock($a['id'],$b['id'],$users->findById($b['id'])['locked_until']);verifyHair('Normal authorized unlock clears persisted flag',(int)$users->findById($b['id'])['lock_requires_admin']===0&&!App\Models\User::isLocked($users->findById($b['id'])));
 $bucket=bin2hex(random_bytes(12));verifyHair('Request throttle accepts allowed attempts',App\Services\RequestThrottle::allow('login',$bucket,2,60)&&App\Services\RequestThrottle::allow('login',$bucket,2,60));verifyHair('Request throttle rejects excess attempts',!App\Services\RequestThrottle::allow('login',$bucket,2,60));
 $client=hairClient($a['id']);verifyHair('Administrator UI explains persisted release',str_contains(hairRequest('/admin/security',$client)['body'],'review interval never unlocks'));
 verifyHair('Legacy malformed CSRF yields 419',hairRequest('/logout',$client,['_token'=>['malformed']])['code']===419);
 for($i=0;$i<3;$i++)$users->recordFailure($b['id']);$recovery->sendCode($b['id']);$proof=null;
 foreach(file(dirname(__DIR__).'/storage/logs/dev_mail.log',FILE_IGNORE_NEW_LINES) as $line)if(str_contains($line,'TO: '.$b['name'].'@example.invalid | PURPOSE: login |')&&preg_match('/OTP: (\d{6}) /',$line,$match))$proof=$match[1];
 $server=$pdo->query('SELECT @@hostname server,@@server_id server_id')->fetch();
 $args=[PHP_BINARY,dirname(__DIR__).'/scripts/recover_admin.php','--actor='.$b['name'],'--target='.$b['name'],'--expect-host=127.0.0.1','--expect-port='.$id['port'],'--expect-database='.$id['db'],'--expect-server='.$server['server'],'--expect-server-id='.$server['server_id'],'--expect-datadir='.$id['datadir'],'--apply','--proof-stdin'];
 $process=proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);fwrite($pipes[0],$b['password']."\n".$proof."\n");fclose($pipes[0]);$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
 verifyHair('Actual guarded CLI accepts private stdin proof',$exit===0&&!App\Models\User::isLocked($users->findById($b['id'])));
 verifyHair('CLI does not print password or verification proof',!str_contains($output.$error,$b['password'])&&!str_contains($output.$error,(string)$proof));
 $result=['checks'=>$checks,'pass'=>count(array_filter($checks,static fn($x)=>$x['status']==='PASS')),'fail'=>count(array_filter($checks,static fn($x)=>$x['status']==='FAIL'))];if(isset($options['output']))file_put_contents($options['output'],json_encode($result,JSON_PRETTY_PRINT));ob_end_flush();exit($result['fail']?1:0);
}catch(Throwable){fwrite(STDERR,'Persistent lock tests stopped; inspect disposable identity/private diagnostics, no proof values printed.'.PHP_EOL);exit(1);}
