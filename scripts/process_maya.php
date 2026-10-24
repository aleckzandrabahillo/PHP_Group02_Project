<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('AVELA_MAYA_CLI_LIBRARY',true);require __DIR__.'/migrate_maya.php';
try{
    [$pdo,$options]=mayaGuard(array_slice($argv,1),true);
    // An explicitly invoked CLI worker establishes local mode only after exact server guards.
    if(env('MAYA_LOCAL_HOSTED_SANDBOX',false)===true)define('AVELA_LOCAL_HOSTED_SANDBOX_ACTIVE',true);
    $limit=filter_var($options['limit']??10,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>50]]);
    if($limit===false)throw new DomainException('Limit must be 1–50.');
    (new App\Services\MayaMigration())->run($pdo,false);
    if(!App\Services\MayaMigration::installed($pdo))throw new DomainException('Apply reviewed migration first.');
    $service=new App\Services\MayaPaymentService($pdo,App\Services\MayaSandboxConfig::client());$done=0;$retry=0;
    for($i=0;$i<$limit;$i++){$result=$service->processNext();if($result===null)break;$result?$done++:$retry++;}
    $orders=$pdo->query('SELECT order_id FROM maya_payments WHERE state IN ("initiating","pending") ORDER BY updated_at,order_id LIMIT '.(int)$limit)->fetchAll(PDO::FETCH_COLUMN);
    foreach($orders as $id){try{
        if(App\Services\MayaSandboxConfig::localHosted()){
            $q=$pdo->prepare('SELECT checkout_id,merchant_reference FROM maya_payments WHERE order_id=?');$q->execute([$id]);
            if(App\Services\MayaPaymentService::simulatedReference($q->fetch()?:[]))continue;
        }
        $service->reconcile((int)$id);\App\Services\OrderNotification::deliver($pdo,(int)$id);$done++;
    }catch(Throwable){$retry++;}}
    echo json_encode(['processed'=>$done,'verification_pending'=>$retry,'sandbox_only'=>true,'raw_payloads_logged'=>false],JSON_THROW_ON_ERROR),PHP_EOL;
    exit($retry?2:0);
}catch(Throwable){fwrite(STDERR,'Maya worker stopped. Verify sandbox configuration, migration and exact database guards.'.PHP_EOL);exit(1);}
