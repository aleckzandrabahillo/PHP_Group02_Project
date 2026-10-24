<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('AVELA_MAYA_CLI_LIBRARY',true);require __DIR__.'/migrate_maya.php';
try{
 [$pdo,$options]=mayaGuard(array_slice($argv,1));
 echo json_encode(['mode'=>isset($options['apply'])?'apply':'dry-run']+(new App\Services\AccountExperienceMigration())->run($pdo,isset($options['apply'])),JSON_THROW_ON_ERROR),PHP_EOL;
}catch(Throwable){fwrite(STDERR,'Account experience migration stopped. Verify exact database guards and additive schema. Private details suppressed.'.PHP_EOL);exit(1);}
