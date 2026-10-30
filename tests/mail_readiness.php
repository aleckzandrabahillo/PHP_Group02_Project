<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('AVELA_HAIR_TEST_LIBRARY',true);require __DIR__.'/hair_profiles.php';require_once dirname(__DIR__).'/vendor/autoload.php';

function mailCheckWorker(array $args,string $input=''):array {
    $p=proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
    if(!is_resource($p))throw new RuntimeException('Synthetic mail worker unavailable');
    fwrite($pipes[0],$input);fclose($pipes[0]);return [$p,$pipes];
}
function mailCheckFinish(array $worker):array {
    [$p,$pipes]=$worker;$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    return ['exit'=>proc_close($p),'out'=>$out,'err'=>$err];
}
function mailCheckState(PDO $pdo):string {
    $state=[];
    foreach(['users','customer_profiles','staff_profiles','otp_codes','activation_tokens','user_authenticators','products','orders','order_items','carts','cart_items'] as $table){
        $hashes=array_map(static fn($r)=>hash('sha256',json_encode($r,JSON_THROW_ON_ERROR)),$pdo->query('SELECT * FROM '.$table)->fetchAll());
        sort($hashes);$state[$table]=$hashes;
    }
    return hash('sha256',json_encode($state,JSON_THROW_ON_ERROR));
}
try {
    $options=getopt('',['expect-database:','expect-port:','expect-datadir:','base-url:','session-directory:','scratch-directory:','output:']);
    $pdo=App\Core\Database::connection();$target=$pdo->query('SELECT DATABASE() db,@@port port,@@hostname server,@@server_id server_id,@@datadir datadir')->fetch();
    $base=$options['base-url']??'';$url=parse_url($base);$scratch=realpath($options['scratch-directory']??'');
    $norm=static fn($p)=>strtolower(rtrim(str_replace('\\','/',$p),'/'));
    if(!preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D',$target['db'])||($options['expect-database']??'')!==$target['db']||env('DB_HOST')!=='127.0.0.1'
        ||(int)($options['expect-port']??0)!==(int)$target['port']||in_array((int)$target['port'],[3306,13307],true)
        ||$norm($options['expect-datadir']??'')!==$norm($target['datadir'])||env('APP_ENV')!=='development'||!$scratch
        ||str_starts_with($norm($scratch),$norm(dirname(__DIR__)).'/')||($url['host']??'')!=='127.0.0.1'||in_array((int)($url['port']??80),[80,18080],true))throw new RuntimeException('Disposable guard');
    $checks=[];$users=new App\Models\User();$prefix='mailcheck_'.bin2hex(random_bytes(4));$password=password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT);
    $admin=$users->createStaff('admin',$prefix.'_admin@example.invalid',$prefix.'_admin',$password,'Synthetic mail operator');
    $manager=$users->createStaff('catalog_manager',$prefix.'_manager@example.invalid',$prefix.'_manager',$password,'Synthetic mail catalog manager');
    $customer=$users->createCustomer(['username'=>$prefix.'_customer','email'=>$prefix.'_customer@example.invalid','full_name'=>'Synthetic mail customer','contact_no'=>'+639123456789','password_hash'=>$password]);$users->activate($customer);unset($password);
    $before=mailCheckState($pdo);$auditBefore=(int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
    $guards=['--expect-host=127.0.0.1','--expect-port='.$target['port'],'--expect-database='.$target['db'],'--expect-server='.$target['server'],'--expect-server-id='.$target['server_id'],'--expect-datadir='.$target['datadir']];
    $command=[PHP_BINARY,dirname(__DIR__).'/scripts/check_mail.php','--actor='.$prefix.'_admin',...$guards];
    $local=mailCheckFinish(mailCheckWorker($command));
    verifyHair('Log mode is not misrepresented as real SMTP',$local['exit']===1&&str_contains($local['err'],'Local log delivery is not real email'));
    verifyHair('Operator script is inaccessible through HTTP',hairRequest('/scripts/check_mail.php')['code']===404);
    $envFile=dirname(__DIR__).'/.env';$original=file_get_contents($envFile);
    $set=static function(array $overrides=[])use($envFile,$original):void{
        $values=array_replace(['MAIL_DRIVER'=>'smtp','MAIL_HOST'=>'localhost','MAIL_PORT'=>'1','MAIL_ENCRYPTION'=>'tls',
            'MAIL_USERNAME'=>'synthetic@example.invalid','MAIL_PASSWORD'=>'SyntheticTransportOnly','MAIL_FROM_ADDRESS'=>'synthetic@example.invalid'],$overrides);
        $body=$original."\n";foreach($values as $k=>$v)$body.=$k.'='.json_encode($v)."\n";file_put_contents($envFile,$body);
    };
    try {
        $set();$dry=mailCheckFinish(mailCheckWorker($command));$data=json_decode($dry['out'],true);
        verifyHair('Default dry-run validates without contacting closed SMTP port',$dry['exit']===0&&$data['mode']==='dry-run'&&!$data['network_contacted']&&!$data['message_sent']);
        verifyHair('Dry-run does not create an audit or modify account/OTP/business rows',(int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn()===$auditBefore&&mailCheckState($pdo)===$before);
        foreach(['host'=>'different.invalid','port'=>'13307','database'=>'avela_phase1_20261009','server'=>'different','server-id'=>'999','datadir'=>'different'] as $key=>$bad){
            $args=array_map(static fn($arg)=>str_starts_with($arg,'--expect-'.$key.'=')?'--expect-'.$key.'='.$bad:$arg,$command);
            verifyHair('Wrong exact identity guard rejected / '.$key,mailCheckFinish(mailCheckWorker($args))['exit']===1);
        }
        foreach(['--send=false','--recipient=other@example.invalid','--password=do-not-accept','--actor=duplicate'] as $extra){
            verifyHair('Unexpected, credential, recipient or duplicate argument rejected',mailCheckFinish(mailCheckWorker([...$command,$extra]))['exit']===1);
        }
        foreach([$prefix.'_manager',$prefix.'_customer','unknown'] as $actor){
            $args=$command;$args[2]='--actor='.$actor;verifyHair('Unauthorized actor cannot run diagnostic',$args&&mailCheckFinish(mailCheckWorker($args))['exit']===1);
        }
        $pdo->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$admin]);
        verifyHair('Inactive Administrator rejected',mailCheckFinish(mailCheckWorker($command))['exit']===1);
        $pdo->prepare("UPDATE users SET status='active',lock_requires_admin=1,locked_until='2000-01-01' WHERE id=?")->execute([$admin]);
        verifyHair('Persistent lock rejected even after review time',mailCheckFinish(mailCheckWorker($command))['exit']===1);
        $pdo->prepare('UPDATE users SET lock_requires_admin=0,locked_until=NULL WHERE id=?')->execute([$admin]);
        foreach([['MAIL_ENCRYPTION'=>'none'],['MAIL_HOST'=>'tls://localhost;other.invalid'],['MAIL_PASSWORD'=>''],['MAIL_FROM_ADDRESS'=>"bad@example.invalid\r\nBcc:other@example.invalid"]] as $invalid){
            $set($invalid);verifyHair('Unsafe SMTP settings rejected without secret output',mailCheckFinish(mailCheckWorker($command))['exit']===1);
        }
        $set();$failed=mailCheckFinish(mailCheckWorker([...$command,'--send']));
        verifyHair('Connection failure is safe and creates no accepted-delivery audit',$failed['exit']===1&&!str_contains($failed['err'],'SyntheticTransportOnly')&&(int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn()===$auditBefore);
        $opensslConfig=$scratch.'/mail-readiness.cnf';file_put_contents($opensslConfig,"[req]\ndistinguished_name=dn\n[dn]\n[v3_req]\nsubjectAltName=DNS:localhost\nbasicConstraints=CA:TRUE\nkeyUsage=digitalSignature,keyEncipherment,keyCertSign\nextendedKeyUsage=serverAuth\n");
        $key=openssl_pkey_new(['config'=>$opensslConfig,'private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
        $csr=openssl_csr_new(['commonName'=>'localhost'],$key,['config'=>$opensslConfig,'digest_alg'=>'sha256']);
        $cert=openssl_csr_sign($csr,null,$key,1,['config'=>$opensslConfig,'x509_extensions'=>'v3_req','digest_alg'=>'sha256']);
        openssl_x509_export($cert,$certificate);openssl_pkey_export($key,$privateKey,null,['config'=>$opensslConfig]);
        $certPath=$scratch.'/mail-readiness-cert.pem';$keyPath=$scratch.'/mail-readiness-key.pem';file_put_contents($certPath,$certificate);file_put_contents($keyPath,$privateKey);@chmod($keyPath,0600);unset($privateKey,$key);
        foreach(['success','sender','untrusted','audit-fault'] as $case){
            $server=mailCheckWorker([PHP_BINARY,__DIR__.'/smtp_fixture.php'],json_encode(['mode'=>'tls','cert'=>$certPath,'key'=>$keyPath,'expected_recipient'=>$case==='sender'?'synthetic@example.invalid':$prefix.'_admin@example.invalid'])."\n");
            $address=trim((string)fgets($server[1][1]));if(!preg_match('/^127\.0\.0\.1:([0-9]+)$/D',$address,$match))throw new RuntimeException('Loopback SMTP unavailable');
            $set(['MAIL_PORT'=>$match[1]]);
            if($case==='audit-fault')$pdo->exec("CREATE TRIGGER mail_readiness_audit_fault BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic fault'");
            try{$send=mailCheckFinish(mailCheckWorker([PHP_BINARY,'-d','openssl.cafile='.($case==='untrusted'?$opensslConfig:$certPath),...array_slice($command,1),'--send',...($case==='sender'?['--to-sender']:[])]));}
            finally{if($case==='audit-fault')$pdo->exec('DROP TRIGGER mail_readiness_audit_fault');mailCheckFinish($server);}
            $data=json_decode($send['out'],true);
            if($case==='sender'){
                verifyHair('Explicit sender diagnostic is restricted to configured sender inbox',$send['exit']===0&&$data['message_sent']&&$data['network_contacted']);
            }elseif($case==='success'){
                verifyHair('Actual verified-TLS diagnostic sent only to existing Administrator address',$send['exit']===0&&$data['message_sent']&&$data['network_contacted']);
                verifyHair('Provider acceptance explicitly does not claim inbox receipt',str_contains($data['inbox_receipt'],'owner must verify'));
            }elseif($case==='untrusted')verifyHair('Real untrusted certificate rejected',$send['exit']===1);
            else verifyHair('Audit fault reports possible external acceptance without changing account/OTP rows',$send['exit']===1&&str_contains($send['err'],'may already have accepted')&&mailCheckState($pdo)===$before);
        }
        $q=$pdo->prepare('SELECT details FROM audit_logs WHERE actor_id=? AND action="mail.transport_test"');$q->execute([$admin]);$events=$q->fetchAll(PDO::FETCH_COLUMN);
        verifyHair('Exactly two successful transport audits without recipient or credential values',count($events)===2&&!str_contains($events[0],$prefix)&&!str_contains($events[0],'SyntheticTransportOnly'));
        verifyHair('Diagnostic leaves passwords, factors, activation, OTP, orders, inventory and carts untouched',mailCheckState($pdo)===$before);
    } finally { file_put_contents($envFile,$original); }
    $report=['checks'=>$checks];if(isset($options['output']))file_put_contents($options['output'],json_encode($report,JSON_PRETTY_PRINT));
    ob_end_flush();exit(count(array_filter($checks,static fn($c)=>$c['status']==='FAIL'))?1:0);
} catch(Throwable $e){fwrite(STDERR,'Mail-readiness checks stopped: '.get_class($e).' / '.basename($e->getFile()).':'.$e->getLine()."; private values suppressed.\n");exit(1);}
