<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';
try{
    $options=getopt('',['apply','actor:','expect-host:','expect-port:','expect-database:','expect-datadir:','expect-catalog-sha256:','expect-source-sha256:']);$expected=[];
    foreach(['host','port','database','datadir','catalog_sha256','source_sha256'] as $key)$expected[$key]=$options['expect-'.str_replace('_','-',$key)]??null;
    $result=(new App\Services\CatalogExpansion(App\Core\Database::connection(),dirname(__DIR__)))->run(isset($options['apply']),$options['actor']??null,$expected);
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(DomainException $e){fwrite(STDERR,'Catalog expansion stopped: '.$e->getMessage().PHP_EOL);exit(1);}
catch(Throwable){fwrite(STDERR,'Catalog expansion stopped; no partial transaction committed. Private details suppressed.'.PHP_EOL);exit(1);}
