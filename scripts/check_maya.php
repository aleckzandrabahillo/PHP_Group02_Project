<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('AVELA_MAYA_CLI_LIBRARY',true);require __DIR__.'/migrate_maya.php';
$configured=false;$schema=false;$guarded=false;
$local=env('MAYA_LOCAL_HOSTED_SANDBOX',false)!==false;
try{
    if($local){
        [$pdo]=mayaGuard(array_slice($argv,1),true);
        define('AVELA_LOCAL_HOSTED_SANDBOX_ACTIVE',true);$guarded=true;
    }else{$pdo=App\Core\Database::connection();}
    $schema=(new App\Services\MayaMigration())->run($pdo,false)['pending_additions']===0;
    App\Services\MayaSandboxConfig::client();$configured=true;
}catch(Throwable){}
$report=['mode'=>'read-only','sandbox_configuration_valid'=>$configured,'additive_schema_ready'=>$schema,
    'webhook_readiness_declared'=>env('MAYA_WEBHOOK_READY',false)===true,
    'worker_readiness_declared'=>env('MAYA_RECONCILIATION_READY',false)===true,
    'curl_available'=>extension_loaded('curl'),'openssl_available'=>extension_loaded('openssl'),
    'live_network_requests'=>0,'checkout_enabled'=>App\Services\MayaSandboxConfig::available()&&$schema,
    'externally_verified'=>false,'local_hosted_selected'=>$local,
    'identity_guards_required'=>$local&&!$guarded,
    'local_hosted_mode'=>App\Services\MayaSandboxConfig::localHosted(),
    'local_simulation_selected'=>env('MAYA_LOCAL_SIMULATION',false)===true,
    'remaining'=>['Public webhook testing needs separately approved HTTPS source-restricted ingress',
        'No scheduled reconciliation worker is declared ready','Real Sandbox card outcome acceptance is separate from this offline check']];
echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;
exit($configured&&$schema&&$report['curl_available']&&$report['openssl_available']?0:2);
