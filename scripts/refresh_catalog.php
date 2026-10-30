<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/import_demo_catalog.php';
try{
    $options=getopt('',['apply','actor:','expect-host:','expect-port:','expect-database:','expect-datadir:','expect-catalog-sha256:','expect-source-sha256:']);
    if($options===false)throw new DomainException('Invalid options.');
    $expected=[];foreach(['host','port','database','datadir','catalog_sha256','source_sha256']as $key)$expected[$key]=$options['expect-'.str_replace('_','-',$key)]??null;
    $result=(new App\Services\CatalogRefresh(App\Core\Database::connection(),dirname(__DIR__)))->run(isset($options['apply']),$options['actor']??null,$expected);
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(DomainException $e){fwrite(STDERR,'Catalog refresh stopped: '.$e->getMessage().PHP_EOL);exit(1);}
catch(Throwable){fwrite(STDERR,'Catalog refresh stopped; private database details suppressed. No partial transaction was committed.'.PHP_EOL);exit(1);}
