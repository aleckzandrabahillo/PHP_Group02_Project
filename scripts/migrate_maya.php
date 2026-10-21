<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);
require $root.'/app/Core/Env.php';App\Core\Env::load($root.'/.env');require $root.'/app/Support/helpers.php';
spl_autoload_register(static function(string $class)use($root):void{if(str_starts_with($class,'App\\')){$file=$root.'/app/'.str_replace('\\','/',substr($class,4)).'.php';if(is_file($file))require_once $file;}});
ini_set('display_errors','0');ini_set('log_errors','0');ini_set('zend.exception_ignore_args','1');

function mayaGuard(array $arguments,bool $worker=false): array
{
    $options=[];
    foreach($arguments as $arg){
        if(!preg_match('/^--(apply|expect-host|expect-port|expect-database|expect-server|expect-server-id|expect-datadir|limit)(?:=(.+))?$/D',$arg,$m)
            ||isset($options[$m[1]])||($m[1]==='apply'?isset($m[2]):!isset($m[2])))throw new DomainException('Supply exact target guards once. Dry-run is default.');
        $options[$m[1]]=$m[2]??true;
    }
    if(!$worker&&isset($options['limit']))throw new DomainException('Limit is a worker option.');
    if($worker&&isset($options['apply']))throw new DomainException('Worker does not perform migrations.');
    if(env('MAYA_ENVIRONMENT','sandbox')!=='sandbox')throw new DomainException('Sandbox environment required.');
    $pdo=App\Core\Database::connection();$id=$pdo->query('SELECT DATABASE() db,@@port port,@@hostname server,@@server_id sid,@@datadir dir')->fetch();
    $norm=static fn(string $s):string=>strtolower(rtrim(str_replace('\\','/',$s),'/'));
    if(($options['expect-host']??null)!==env('DB_HOST')||($options['expect-database']??null)!==$id['db']
        ||($options['expect-port']??null)!==(string)$id['port']||($options['expect-port']??null)!==(string)env('DB_PORT','3306')
        ||($options['expect-server']??null)!==$id['server']||($options['expect-server-id']??null)!==(string)$id['sid']
        ||!isset($options['expect-datadir'])||$norm($options['expect-datadir'])!==$norm($id['dir']))throw new DomainException('Database identity guards differ.');
    return [$pdo,$options];
}
if(defined('AVELA_MAYA_CLI_LIBRARY'))return;
try{
    [$pdo,$options]=mayaGuard(array_slice($argv,1));
    echo json_encode(['mode'=>isset($options['apply'])?'apply':'dry-run']+(new App\Services\MayaMigration())->run($pdo,isset($options['apply'])),JSON_THROW_ON_ERROR),PHP_EOL;
}catch(Throwable){fwrite(STDERR,'Maya migration stopped. Verify exact target guards and additive schema; private diagnostics suppressed.'.PHP_EOL);exit(1);}
