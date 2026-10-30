<?php

declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('AVELA_HAIR_TEST_LIBRARY',true);require __DIR__.'/hair_profiles.php';require_once dirname(__DIR__).'/vendor/autoload.php';
date_default_timezone_set((string)env('APP_TIMEZONE','Asia/Manila'));

function activationMessage(string $email): array {
 foreach(array_reverse(glob((string)env('MAIL_DEV_OUTBOX_DIRECTORY').'/*.eml')) as $file){
  $text=file_get_contents($file);
  if(str_contains($text,'To: '.$email."\n")&&preg_match('/#token=([a-f0-9]{64})/',$text,$m))return ['token'=>$m[1],'file'=>$file,'body'=>$text];
 }
 throw new RuntimeException('Synthetic delivery missing.');
}
function activationCode(string $email): string {
 foreach(array_reverse(file(dirname(__DIR__).'/storage/logs/dev_mail.log')) as $line){
  if(str_contains($line,'TO: '.$email.' | PURPOSE: activation |')&&preg_match('/OTP: ([0-9]{6})/',$line,$m))return $m[1];
 }
 throw new RuntimeException('Synthetic registration code missing.');
}
function verifiedActivation(array $account): array {
 global $service;
 $service->issue($account['id']);
 $result=$service->verifyAndSendLink($account['id'],activationCode($account['email']));
 if(!$result['ok'])throw new RuntimeException('Synthetic verification failed.');
 return activationMessage($account['email']);
}
function emailSettings(array $values): void {
 global $scratch;
 $defaults=['APP_ENV'=>'development','MAIL_DRIVER'=>'log','APP_URL'=>$GLOBALS['base'],'MAIL_DEV_OUTBOX_DIRECTORY'=>$GLOBALS['outbox'],'MAIL_HOST'=>'localhost','MAIL_PORT'=>'587','MAIL_USERNAME'=>'synthetic@example.invalid','MAIL_PASSWORD'=>'SyntheticTransportOnly','MAIL_FROM_ADDRESS'=>'synthetic@example.invalid','MAIL_ENCRYPTION'=>'tls'];
 $raw='';foreach(array_replace($defaults,$values) as $k=>$v)$raw.=$k.'='.json_encode((string)$v)."\n";
 file_put_contents($scratch.'/mail-settings.env',$raw);
 App\Core\Env::load($scratch.'/mail-settings.env');
}
function emailWorker(array $args, string $input=''): array {
 $p=proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
 if(!is_resource($p))throw new RuntimeException('Synthetic worker unavailable.');
 fwrite($pipes[0],$input);fclose($pipes[0]);
 return [$p,$pipes];
}
function emailFinish(array $worker): array {
 [$p,$pipes]=$worker;$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return [proc_close($p),$out,$err];
}
try {
 $options=getopt('',['expect-database:','expect-port:','expect-datadir:','base-url:','session-directory:','scratch-directory:','output:','worker:','settings:']);
 $pdo=App\Core\Database::connection();$target=$pdo->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch();$norm=static fn($p)=>strtolower(rtrim(str_replace('\\','/',$p),'/'));
 $base=$options['base-url']??'';$url=parse_url($base);$sessions=realpath($options['session-directory']??'');$scratch=realpath($options['scratch-directory']??'');$outbox=realpath((string)env('MAIL_DEV_OUTBOX_DIRECTORY',''));
 if(!preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D',$target['db'])||($options['expect-database']??'')!==$target['db']||env('DB_HOST')!=='127.0.0.1'||(int)($options['expect-port']??0)!==(int)$target['port']||in_array((int)$target['port'],[3306,13307],true)||$norm($options['expect-datadir']??'')!==$norm($target['datadir'])||env('APP_ENV')!=='development'||!$sessions||!$scratch||!$outbox||str_starts_with($norm($scratch),$norm(dirname(__DIR__)).'/')||($url['host']??'')!=='127.0.0.1'||in_array((int)($url['port']??80),[80,18080],true))throw new RuntimeException('Disposable-only guard.');
 ini_set('session.save_path',$sessions);$users=new App\Models\User();$logs=new App\Services\LogService();$mail=new App\Services\MailService();$otp=new App\Services\OtpService($mail,$logs);$service=new App\Services\ActivationService($otp,$mail,$logs);$checks=[];
 if(($options['worker']??'')==='consume'){echo $service->consume(trim((string)fgets(STDIN)))?'1':'0';exit;}
 if(($options['worker']??'')==='mail'){App\Core\Env::load((string)$options['settings']);try{$mail->sendOtp('recipient@example.invalid','123456','login');echo 'sent';exit;}catch(App\Services\MailDeliveryException){echo 'rejected';exit(2);}}
 $migration=new App\Services\ActivationMigration();verifyHair('Guarded migration repeats with zero additions',$migration->run($pdo,false)['pending_additions']===0);
 emailSettings([]);
 $make=static function()use($users):array{$name='activation_'.bin2hex(random_bytes(5));$id=$users->createCustomer(['email'=>$name.'@example.invalid','username'=>$name,'password_hash'=>password_hash('A9!'.bin2hex(random_bytes(16)),PASSWORD_DEFAULT),'full_name'=>'Synthetic activation','contact_no'=>'09123456789']);return ['id'=>$id,'email'=>$name.'@example.invalid'];};
 $a=$make();$issued=$service->issue($a['id']);$code=activationCode($a['email']);
 verifyHair('Registration first sends only OTP and creates no activation token',$issued['issued']&&strlen($code)===6&&(int)$pdo->query('SELECT COUNT(*) FROM activation_tokens WHERE user_id='.$a['id'])->fetchColumn()===0);
 verifyHair('Incorrect OTP cannot issue a link',!$service->verifyAndSendLink($a['id'],'000000')['ok']&&(int)$pdo->query('SELECT COUNT(*) FROM activation_tokens WHERE user_id='.$a['id'])->fetchColumn()===0);
 verifyHair('Correct OTP sends separate link while account stays pending',$service->verifyAndSendLink($a['id'],$code)['ok']&&$users->findById($a['id'])['status']==='pending');
 $proof=activationMessage($a['email']);
 verifyHair('Activation email has no numeric fallback',!str_contains($proof['body'],'six-digit code')&&!str_contains($proof['body'],$code));
 $q=$pdo->prepare('SELECT token_digest FROM activation_tokens WHERE user_id=?');$q->execute([$a['id']]);verifyHair('Only versioned token digest stored',hash_equals(hash('sha256','avela:activation:otp-verified:v2:'.$proof['token']),$q->fetchColumn()));
 verifyHair('Consumed registration OTP cannot issue another link',!$service->verifyAndSendLink($a['id'],$code)['ok']);
 foreach([null,[],str_repeat('x',64),str_repeat('a',65),'\"><script>'] as $bad)verifyHair('Malformed or unknown activation is rejected',!$service->consume($bad));
 verifyHair('Valid bound activation succeeds',$service->consume($proof['token'])&&$users->findById($a['id'])['status']==='active');
 verifyHair('Reused link rejected',!$service->consume($proof['token']));
 $b=$make();$expired=verifiedActivation($b);$pdo->prepare('UPDATE activation_tokens SET expires_at="2000-01-01" WHERE user_id=?')->execute([$b['id']]);verifyHair('Expired link rejected',!$service->consume($expired['token'])&&$users->findById($b['id'])['status']==='pending');
 $c=$make();$service->issue($c['id']);$numeric=activationCode($c['email']);session_name((string)env('SESSION_NAME'));session_start();App\Core\Session::put('pending_flow',['user_id'=>$c['id'],'email'=>$c['email'],'purpose'=>'activation']);$auth=new App\Services\AuthService($users,$otp,$logs);$verified=$auth->verifyPendingOtp($numeric);verifyHair('OTP advances to link stage without activating',$verified['next']==='activation_link'&&$users->findById($c['id'])['status']==='pending'&&!isset($_SESSION['auth_user']));session_write_close();$numericProof=activationMessage($c['email']);verifyHair('Separate link remains required',$service->consume($numericProof['token']));
 $d=$make();$atomic=verifiedActivation($d);
 $pdo->exec("CREATE TRIGGER activation_audit_fault BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic fault'");
 $blocked=false;try{$service->consume($atomic['token']);}catch(Throwable){$blocked=true;}$pdo->exec('DROP TRIGGER activation_audit_fault');
 verifyHair('Audit failure rolls back activation and token',$blocked&&$users->findById($d['id'])['status']==='pending'&&$service->consume($atomic['token']));
 $e=$make();$parallel=verifiedActivation($e);$workerArgs=[PHP_BINARY,__FILE__];
 foreach(['expect-database','expect-port','expect-datadir','base-url','session-directory','scratch-directory'] as $key)$workerArgs[]='--'.$key.'='.$options[$key];$workerArgs[]='--worker=consume';
 $w1=emailWorker($workerArgs,$parallel['token']."\n");$w2=emailWorker($workerArgs,$parallel['token']."\n");$r1=emailFinish($w1);$r2=emailFinish($w2);
 verifyHair('Concurrent activation exactly once',$r1[0]===0&&$r2[0]===0&&((int)$r1[1]+(int)$r2[1])===1);
 verifyHair('Concurrent activation has one audit',(int)$pdo->query('SELECT COUNT(*) FROM audit_logs WHERE action="account_activated" AND target_id='.$e['id'])->fetchColumn()===1);
 $locked=$make();$lockProof=verifiedActivation($locked);for($i=0;$i<3;$i++)$users->recordFailure($locked['id']);verifyHair('Locked account cannot activate',!$service->consume($lockProof['token']));
 $client=hairClient($a['id']);verifyHair('Activation page never exposes stored proof',!str_contains(hairRequest('/activate',$client)['body'],$proof['token']));
 verifyHair('Activation mutation requires CSRF',hairRequest('/activate',$client,['activation_token'=>$expired['token'],'_token'=>['bad']])['code']===419);
 $valid=$make();$http=verifiedActivation($valid);$response=hairRequest('/activate',$client,['_token'=>$client['csrf'],'activation_token'=>$http['token']]);verifyHair('Activation preserves the existing sign-in and redirects home',$response['code']===302&&in_array('Location: /',$response['headers'],true)&&$users->findById($valid['id'])['status']==='active');
 verifyHair('Activation does not change authenticated identity',str_contains(hairRequest('/profile',$client)['body'],'Profile'));
 $log=file_get_contents(dirname(__DIR__).'/storage/logs/dev_mail.log');verifyHair('Activation tokens excluded from shared development mail log',!str_contains($log,$proof['token'])&&!str_contains($log,$http['token']));
 $transport=$mail->smtpMailer();verifyHair('SMTP requires verified TLS without debugging',$transport->SMTPSecure==='tls'&&$transport->SMTPOptions['ssl']['verify_peer']&&$transport->SMTPOptions['ssl']['verify_peer_name']&&!$transport->SMTPOptions['ssl']['allow_self_signed']&&$transport->SMTPDebug===0&&$transport->Timeout===10);
 foreach([['MAIL_HOST'=>'tls://smtp.example.invalid;localhost'],['MAIL_ENCRYPTION'=>'none'],['MAIL_PORT'=>0],['MAIL_FROM_ADDRESS'=>"bad@example.invalid\r\nBcc:other@example.invalid"]] as $bad){emailSettings($bad);$denied=false;try{$mail->smtpMailer();}catch(App\Services\MailDeliveryException){$denied=true;}verifyHair('Unsafe SMTP configuration rejected',$denied);}
 emailSettings(['APP_ENV'=>'production','MAIL_DRIVER'=>'log']);$denied=false;try{$mail->sendOtp('test@example.invalid','123456','login');}catch(App\Services\MailDeliveryException){$denied=true;}verifyHair('Production rejects local OTP logging',$denied);
 $opensslConfig=$scratch.'/smtp-fixture.cnf';
 file_put_contents($opensslConfig,"[req]\ndistinguished_name=dn\n[dn]\n[v3_req]\nsubjectAltName=DNS:localhost\nbasicConstraints=CA:TRUE\nkeyUsage=digitalSignature,keyEncipherment,keyCertSign\nextendedKeyUsage=serverAuth\n");
 $key=openssl_pkey_new(['config'=>$opensslConfig,'private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
 $csr=openssl_csr_new(['commonName'=>'localhost'],$key,['config'=>$opensslConfig,'digest_alg'=>'sha256']);
 $certificate=openssl_csr_sign($csr,null,$key,1,['config'=>$opensslConfig,'x509_extensions'=>'v3_req','digest_alg'=>'sha256']);
 openssl_x509_export($certificate,$certText);openssl_pkey_export($key,$keyText,null,['config'=>$opensslConfig]);
 $certFile=$scratch.'/smtp-fixture-cert.pem';$keyFile=$scratch.'/smtp-fixture-key.pem';file_put_contents($certFile,$certText);file_put_contents($keyFile,$keyText);@chmod($keyFile,0600);unset($keyText);
 foreach([['mode'=>'tls','trusted'=>true,'result'=>'sent'],['mode'=>'tls','trusted'=>false,'result'=>'rejected'],['mode'=>'plain','trusted'=>true,'result'=>'rejected']] as $case){
  $server=emailWorker([PHP_BINARY,__DIR__.'/smtp_fixture.php'],json_encode(['mode'=>$case['mode'],'cert'=>$certFile,'key'=>$keyFile])."\n");
  $address=trim((string)fgets($server[1][1]));if(!preg_match('/^127\.0\.0\.1:([0-9]+)$/D',$address,$port))throw new RuntimeException('Synthetic SMTP listener unavailable.');
  emailSettings(['MAIL_DRIVER'=>'smtp','MAIL_HOST'=>'localhost','MAIL_PORT'=>$port[1]]);
  $smtpArgs=[PHP_BINARY,'-d','openssl.cafile='.($case['trusted']?$certFile:$opensslConfig),__FILE__];
  foreach(['expect-database','expect-port','expect-datadir','base-url','session-directory','scratch-directory'] as $option)$smtpArgs[]='--'.$option.'='.$options[$option];
  $smtpArgs[]='--worker=mail';$smtpArgs[]='--settings='.$scratch.'/mail-settings.env';
  $delivery=emailFinish(emailWorker($smtpArgs));emailFinish($server);
  verifyHair('Actual loopback SMTP '.$case['mode'].' / trusted certificate '.($case['trusted']?'yes':'no'),trim($delivery[1])===$case['result']&&$delivery[0]===($case['result']==='sent'?0:2));
 }
 emailSettings(['MAIL_DRIVER'=>'smtp','MAIL_HOST'=>'localhost','MAIL_PORT'=>1]);
 $name='failed_mail_'.bin2hex(random_bytes(4));$auth=new App\Services\AuthService($users,$otp,$logs);$denied=false;
 try{$auth->register(['email'=>$name.'@example.invalid','username'=>$name,'full_name'=>'Synthetic failure','contact_no'=>'09123456789','password'=>'A9!'.bin2hex(random_bytes(16))]);}catch(App\Services\MailDeliveryException){$denied=true;}
 verifyHair('Delivery failure rolls back registration/profile/token/OTP',$denied&&!$users->findByIdentifier($name));
 emailSettings([]);
 $result=['checks'=>$checks];if(isset($options['output']))file_put_contents($options['output'],json_encode($result,JSON_PRETTY_PRINT));ob_end_flush();exit(count(array_filter($checks,static fn($c)=>$c['status']==='FAIL'))?1:0);
}catch(Throwable $e){fwrite(STDERR,'Email tests stopped: '.get_class($e).' / '.basename($e->getFile()).':'.$e->getLine()."; no proof values printed.\n");exit(1);}
