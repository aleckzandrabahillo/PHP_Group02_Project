<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('AVELA_HAIR_TEST_LIBRARY',true);require __DIR__.'/hair_profiles.php';
try{
 $options=getopt('',['expect-database:','expect-port:','expect-datadir:','base-url:','session-directory:','scratch-directory:','output:']);
 $pdo=App\Core\Database::connection();$target=$pdo->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch();$base=$options['base-url']??'';$url=parse_url($base);$scratch=realpath($options['scratch-directory']??'');$norm=static fn($p)=>strtolower(rtrim(str_replace('\\','/',$p),'/'));
 if(!preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D',$target['db'])||($options['expect-database']??'')!==$target['db']||env('DB_HOST')!=='127.0.0.1'||(int)($options['expect-port']??0)!==(int)$target['port']||in_array((int)$target['port'],[3306,13307],true)||$norm($options['expect-datadir']??'')!==$norm($target['datadir'])||env('APP_ENV')!=='development'||!$scratch||str_starts_with($norm($scratch),$norm(dirname(__DIR__)).'/')||($url['host']??'')!=='127.0.0.1'||in_array((int)($url['port']??80),[80,18080],true))throw new RuntimeException('Disposable guard');
 $checks=[];$page=hairRequest('/privacy');verifyHair('Public privacy route available',$page['code']===200);
 foreach(['Policy for review','Hair assessment','Security records','Google reCAPTCHA','email provider','essential session cookie','no optional advertising or analytics','no fixed automatic deletion','monitored privacy inbox has not yet been configured'] as $phrase)verifyHair('Truthful policy includes / '.$phrase,str_contains($page['body'],$phrase));
 verifyHair('No invented contact address or fake consent controls',!str_contains($page['body'],'mailto:')&&!str_contains($page['body'],'>Accept All<')&&!str_contains($page['body'],'>Reject All<'));
 foreach(['/','/shop'] as $path)verifyHair('Privacy link reachable from '.$path,str_contains(hairRequest($path)['body'],'href="/help-faq#privacy-policy"'));
 foreach(['/login','/register'] as $path){$authPage=hairRequest($path)['body'];verifyHair('Focused auth page keeps home navigation and privacy inside the form column without a marketing footer: '.$path,str_contains($authPage,'aria-label="Avela home"')&&preg_match('~<main\b[^>]*class="auth-main auth-panel"[^>]*>.*?<nav class="auth-privacy"[^>]*>.*?href="/privacy".*?</nav>.*?</main>~s',$authPage)===1&&!str_contains($authPage,'<footer'));}
 $help=hairRequest('/help-faq');
 verifyHair('Help hub is public with one main heading',$help['code']===200&&preg_match_all('/<h1\b/',$help['body'])===1);
 foreach(['shopping','delivery','returns','hair-care','terms','privacy-policy']as $topic)verifyHair('Help section and jump link: '.$topic,str_contains($help['body'],'id="'.$topic.'"')&&str_contains($help['body'],'href="#'.$topic.'"'));
 verifyHair('Help reuses the authoritative privacy text and retains review status',str_contains($help['body'],'no fixed automatic deletion')&&str_contains($help['body'],'Policy for review')&&str_contains($help['body'],'Google reCAPTCHA'));
 verifyHair('Help has native keyboard-operable disclosures',substr_count($help['body'],'<details>')>=8&&substr_count($help['body'],'<summary>')>=8);
 verifyHair('Direct existing terms and refund routes stay available',hairRequest('/terms')['code']===200&&hairRequest('/refund-policy')['code']===200);
 $settings=$scratch.'/privacy-contact.env';
 foreach([['','true'],['privacy-fixture@example.invalid','false'],['bad<>email','true'],["test@example.invalid\r\nBcc:x@example.invalid",'true']] as [$email,$verified]){
  file_put_contents($settings,'PRIVACY_CONTACT_EMAIL='.json_encode($email)."\nPRIVACY_CONTACT_VERIFIED=".$verified."\n");App\Core\Env::load($settings);verifyHair('Unconfigured/unverified/malformed contact is not presented as monitored',App\Services\PrivacyContact::configured()===null);
 }
 file_put_contents($settings,"PRIVACY_CONTACT_EMAIL=privacy-fixture@example.invalid\nPRIVACY_CONTACT_VERIFIED=true\n");App\Core\Env::load($settings);
 verifyHair('Verified contact configuration resolves safely',App\Services\PrivacyContact::configured()==='privacy-fixture@example.invalid');
 $env=dirname(__DIR__).'/.env';$saved=file_get_contents($env);
 try{file_put_contents($env,$saved."\nPRIVACY_CONTACT_EMAIL=privacy-fixture@example.invalid\nPRIVACY_CONTACT_VERIFIED=true\n");$configured=hairRequest('/privacy');verifyHair('Configured mailto link is correctly encoded',str_contains($configured['body'],'href="mailto:privacy-fixture%40example.invalid?subject=Privacy%20inquiry%20about%20Avela"'));verifyHair('Configured policy still requires review',str_contains($configured['body'],'Policy for review'));}finally{file_put_contents($env,$saved);}
 $result=['checks'=>$checks];if(isset($options['output']))file_put_contents($options['output'],json_encode($result,JSON_PRETTY_PRINT));ob_end_flush();exit(count(array_filter($checks,static fn($c)=>$c['status']==='FAIL'))?1:0);
}catch(Throwable $e){fwrite(STDERR,'Privacy checks stopped: '.get_class($e).' / '.basename($e->getFile()).':'.$e->getLine()."; private values suppressed.\n");exit(1);}
