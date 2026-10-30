<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('AVELA_HAIR_TEST_LIBRARY',true);require __DIR__.'/hair_profiles.php';
try{
    $options=getopt('',['expect-database:','expect-port:','expect-datadir:','base-url:','session-directory:','scratch-directory:','output:']);
    $pdo=App\Core\Database::connection();$target=$pdo->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch();
    $base=$options['base-url']??'';$url=parse_url($base);$sessions=realpath($options['session-directory']??'');
    $norm=static fn($p)=>strtolower(rtrim(str_replace('\\','/',$p),'/'));
    if(!preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D',$target['db'])||($options['expect-database']??'')!==$target['db']
        ||env('DB_HOST')!=='127.0.0.1'||(int)($options['expect-port']??0)!==(int)$target['port']||in_array((int)$target['port'],[3306,13307,13321],true)
        ||$norm($options['expect-datadir']??'')!==$norm($target['datadir'])||env('APP_ENV')!=='development'||env('MAIL_DRIVER')!=='log'
        ||!$sessions||str_starts_with($norm($sessions),$norm(dirname(__DIR__)).'/')||($url['host']??'')!=='127.0.0.1'||in_array((int)($url['port']??80),[80,18080],true))throw new RuntimeException('Disposable-only target guard');
    ini_set('session.save_path',$sessions);$checks=[];$users=new App\Models\User();$prefix='identity_'.bin2hex(random_bytes(5));
    $make=static function($suffix)use($users,$prefix){$id=$users->createCustomer(['username'=>$prefix.$suffix,'email'=>$prefix.$suffix.'@example.invalid','full_name'=>'Synthetic Identity','contact_no'=>'+639123456789','password_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);$users->activate($id);return $id;};
    $id=$make('_one');$other=$make('_two');$client=hairClient($id);$before=$users->profileFor($id);$otherBefore=$users->profileFor($other);
    $values=['_token'=>$client['csrf'],'full_name'=>$before['full_name'],'username'=>$before['username'],'expected_username'=>$before['username'],'email'=>$before['email'],'contact_no'=>$before['contact_no']];
    verifyHair('Editable username is present with a stale-edit token',str_contains(hairRequest('/profile',$client)['body'],'name="username"')&&str_contains(hairRequest('/profile',$client)['body'],'name="expected_username"'));
    verifyHair('Guest cannot edit identity',hairRequest('/profile/update',null,$values)['code']===302);
    verifyHair('Invalid CSRF cannot edit identity',hairRequest('/profile/update',$client,[...$values,'_token'=>'invalid','username'=>$prefix.'_new'])['code']===419&&$users->profileFor($id)===$before);
    foreach(['bad name','abc',str_repeat('x',41),['bad']]as $bad){hairRequest('/profile/update',$client,[...$values,'username'=>$bad]);verifyHair('Invalid username leaves identity unchanged',$users->profileFor($id)['username']===$before['username']);}
    foreach([$otherBefore['username'],strtoupper($otherBefore['username'])]as $duplicate){hairRequest('/profile/update',$client,[...$values,'username'=>$duplicate]);verifyHair('Duplicate username cannot claim another identity',$users->profileFor($id)['username']===$before['username']);}
    $security=$pdo->query('SELECT password_hash,password_version,role,status FROM users WHERE id='.$id)->fetch();
    $new=$prefix.'_new';$response=hairRequest('/profile/update',$client,[...$values,'username'=>$new,'user_id'=>$other,'role'=>'admin']);
    verifyHair('Own username changes through protected profile form',$response['code']===302&&$users->profileFor($id)['username']===$new);
    verifyHair('Old login identifier is released and new identifier identifies same account',$users->findByIdentifier($before['username'])===null&&(int)$users->findByIdentifier($new)['id']===$id);
    verifyHair('Username edit preserves role and password security',$security===$pdo->query('SELECT password_hash,password_version,role,status FROM users WHERE id='.$id)->fetch());
    verifyHair('Submitted foreign ID is ignored',$users->profileFor($other)===$otherBefore);
    hairRequest('/profile/update',$client,[...$values,'username'=>$prefix.'_stale']);
    verifyHair('Stale form cannot overwrite a later username',$users->profileFor($id)['username']===$new);
    $values=['_token'=>$client['csrf'],'full_name'=>$before['full_name'],'username'=>$prefix.'_verified','expected_username'=>$new,'email'=>$prefix.'_updated@example.invalid','contact_no'=>$before['contact_no']];
    $response=hairRequest('/profile/update',$client,$values);
    verifyHair('Combined email and username edit waits for OTP',$response['code']===302&&in_array('Location: /profile/verify-otp',$response['headers'],true)&&$users->profileFor($id)['email']===$before['email']&&$users->profileFor($id)['username']===$new);
    $code=null;foreach(array_reverse(file(dirname(__DIR__).'/storage/logs/dev_mail.log'))as $line){if(str_contains($line,'TO: '.$before['email'].' | PURPOSE: profile_update')&&preg_match('/OTP: ([0-9]{6})/',$line,$m)){$code=$m[1];break;}}
    if(!$code)throw new RuntimeException('Synthetic log-mail OTP unavailable');
    $flow=['user_id'=>$id,'current_email'=>$before['email'],'before_username'=>$new,'username'=>$values['username'],'email'=>$values['email'],'before_full_name'=>$before['full_name'],'full_name'=>$before['full_name'],'before_contact_no'=>$before['contact_no'],'contact_no'=>$before['contact_no']];
    $service=new App\Services\CustomerProfileService(new App\Services\OtpService(new App\Services\MailService(),new App\Services\LogService()));
    $bad=$service->confirm($id,$flow,$code==='000000'?'111111':'000000');
    verifyHair('Incorrect OTP saves neither email nor username',!$bad['ok']&&$users->profileFor($id)['email']===$before['email']&&$users->profileFor($id)['username']===$new);
    $bad=$service->confirm($id,[...$flow,'before_username'=>'stale'],$code);
    verifyHair('Stale pending identity is rejected before verification',!$bad['ok']&&$bad['reason']==='account_unavailable');
    $rejected=false;try{$service->confirm($id,[...$flow,'username'=>$otherBefore['username']],$code);}catch(DomainException){$rejected=true;}
    verifyHair('Duplicate username rolls back a valid OTP and every profile field',$rejected&&$users->profileFor($id)['email']===$before['email']&&$users->profileFor($id)['username']===$new);
    $ok=$service->confirm($id,$flow,$code);
    verifyHair('Valid OTP saves the intended email and username atomically',$ok['ok']&&$users->profileFor($id)['email']===$values['email']&&$users->profileFor($id)['username']===$values['username']);
    verifyHair('Consumed OTP cannot replay the update',!$service->confirm($id,$flow,$code)['ok']);unset($code,$m);
    $audit=$pdo->query('SELECT details FROM audit_logs WHERE target_type="user" AND target_id='.$id.' AND action="profile.updated" ORDER BY id DESC LIMIT 1')->fetchColumn();
    verifyHair('Identity audit records changed field names without personal values',str_contains($audit,'username')&&!str_contains($audit,$values['username'])&&!str_contains($audit,$values['email']));
    $result=['checks'=>$checks,'pass'=>count(array_filter($checks,fn($c)=>$c['status']==='PASS')),'fail'=>count(array_filter($checks,fn($c)=>$c['status']==='FAIL'))];
    if(isset($options['output']))file_put_contents($options['output'],json_encode($result,JSON_PRETTY_PRINT));ob_end_flush();exit($result['fail']?1:0);
}catch(Throwable $e){fwrite(STDERR,'Account identity checks stopped: '.get_class($e).' / '.basename($e->getFile()).':'.$e->getLine().'; private values suppressed.'.PHP_EOL);exit(1);}
