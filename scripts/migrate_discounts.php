<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('AVELA_MAYA_CLI_LIBRARY',true);require __DIR__.'/migrate_maya.php';
try{
    [$pdo,$options]=mayaGuard(array_slice($argv,1));
    echo json_encode(['mode'=>isset($options['apply'])?'apply':'dry-run']+(new App\Services\DiscountMigration())->run($pdo,isset($options['apply'])),JSON_THROW_ON_ERROR),PHP_EOL;
}catch(Throwable){fwrite(STDERR,'Discount migration stopped. Check the exact target guards and additive schema; private details suppressed.'.PHP_EOL);exit(1);}
