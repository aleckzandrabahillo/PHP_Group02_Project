<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';
try{
 $options=getopt('',['expect-database:','expect-port:','expect-datadir:','apply']);
 $pdo=App\Core\Database::connection();$target=$pdo->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch();
 $norm=static fn($p)=>strtolower(rtrim(str_replace('\\','/',$p),'/'));
 if(($options['expect-database']??'')!==$target['db']||(int)($options['expect-port']??0)!==(int)$target['port']||$norm($options['expect-datadir']??'')!==$norm($target['datadir'])||env('DB_HOST')!=='127.0.0.1')throw new RuntimeException('Explicit local database identity is required.');
 App\Services\PiiCipher::configured();
 echo json_encode(['target'=>$target,'migration'=>(new App\Services\AddressBookMigration())->run($pdo,array_key_exists('apply',$options))],JSON_PRETTY_PRINT).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'Address-book migration stopped: '.$e->getMessage().PHP_EOL);exit(1);}
