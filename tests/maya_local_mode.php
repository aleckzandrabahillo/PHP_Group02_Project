<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('AVELA_HAIR_TEST_LIBRARY',true);require __DIR__.'/hair_profiles.php';
try{
    $o=getopt('',['base-url:','output:','expect-host:','expect-port:','expect-database:','expect-server:','expect-server-id:','expect-datadir:','session-directory:','scratch-directory:']);$base=$o['base-url']??'';
    $url=parse_url($base);if(($url['host']??'')!=='127.0.0.1'||(int)($url['port']??0)!==18118||env('DB_PORT')!=='13321'||env('DB_DATABASE')!=='avela_demo_images_test_personalization_20261016')throw new RuntimeException('Disposable guards');
    $checks=[];$_ENV['MAYA_LOCAL_SIMULATION']='true';
    verifyHair('CLI cannot silently enable local simulation',!App\Services\MayaSandboxConfig::available());
    $blocked=false;try{App\Services\MayaSandboxConfig::client();}catch(RuntimeException){$blocked=true;}
    verifyHair('Simulation blocks default real-provider client',$blocked);
    unset($_ENV['MAYA_LOCAL_SIMULATION']);
    $response=hairRequest('/_test/local-mode');verifyHair('Guarded local launcher configuration endpoint responds',$response['code']===200);
    $results=json_decode($response['body'],true,32,JSON_THROW_ON_ERROR);
    foreach($results as $name=>$result)verifyHair('Local transport guard: '.$name,$result===true);
    $bad=count(array_filter($checks,fn($check)=>$check['status']!=='PASS'));file_put_contents($o['output'],json_encode($checks,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));ob_end_clean();echo json_encode(['pass'=>count($checks)-$bad,'fail'=>$bad,'network_requests'=>0]),PHP_EOL;exit($bad?1:0);
}catch(Throwable){ob_end_clean();fwrite(STDERR,'Local payment mode test stopped; private diagnostics suppressed.'.PHP_EOL);exit(1);}
