<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
ini_set('display_errors','0');ini_set('log_errors','0');
$root=dirname(__DIR__);
require_once $root.'/app/Core/Env.php';
\App\Core\Env::load($root.'/.env');
require_once $root.'/app/Support/helpers.php';
spl_autoload_register(static function(string $class) use($root):void{
    if(str_starts_with($class,'App\\')){
        $file=$root.'/app/'.str_replace('\\','/',substr($class,4)).'.php';
        if(is_file($file))require_once $file;
    }
});

function avela_assign_images_main(array $arguments): int
{
    try {
        $options=[];$ids=[];
        foreach(array_slice($arguments,1) as $arg){
            if(!preg_match('/^--(apply|dry-run|help|manifest|actor|expect-host|expect-port|expect-database|expect-server|expect-server-id|expect-datadir|expect-product)(?:=(.*))?$/D',$arg,$m))
                throw new DomainException('Unknown image assignment option. Use --help.');
            $key=$m[1];$value=$m[2]??null;
            if($key==='expect-product'){
                if(!is_string($value)||!preg_match('/^(AVE-[A-Z0-9-]{1,46}):([1-9][0-9]{0,9})$/D',$value,$p)||isset($ids[$p[1]])||(int)$p[2]>4294967295)
                    throw new DomainException('Use each --expect-product=SKU:ID once with a valid product ID.');
                $ids[$p[1]]=(int)$p[2];continue;
            }
            if(array_key_exists($key,$options))throw new DomainException('Duplicate option: --'.$key);
            if(in_array($key,['apply','dry-run','help'],true)){
                if($value!==null)throw new DomainException('Flags do not accept values.');
                $options[$key]=true;
            }else{
                if(!is_string($value)||$value==='')throw new DomainException('Option requires a value: --'.$key);
                $options[$key]=$value;
            }
        }
        if(isset($options['help'])){
            echo "Avela approved demo image assignment (CLI only, dry-run by default).\n";
            echo "php scripts/assign_demo_images.php [--dry-run|--apply] --actor=USERNAME --expect-host=HOST --expect-port=PORT --expect-database=DB --expect-server=HOSTNAME --expect-server-id=ID --expect-datadir=PATH [--manifest=PATH]\n";
            echo "Apply also requires one --expect-product=SKU:ID for every image in the reviewed manifest.\n";
            echo "Run dry-run first; review IDs and target. Never overwrite an existing image. Matching assignments are skipped.\n";
            return 0;
        }
        if(isset($options['apply'],$options['dry-run']))throw new DomainException('Choose one mode.');
        $confirm=[];
        foreach(['host','database','server','datadir'] as $key)$confirm[$key]=$options['expect-'.$key]??null;
        foreach(['port'=>65535,'server-id'=>4294967295] as $key=>$max){
            $v=$options['expect-'.$key]??null;
            if(!is_string($v)||!ctype_digit($v)||strlen($v)>10||(int)$v<($key==='port'?1:0)||(int)$v>$max)
                throw new DomainException('Exact numeric server/port confirmation is required.');
            $confirm[str_replace('-','_',$key)]=(int)$v;
        }
        $manifest=\App\Services\DemoImageAssigner::readManifest($options['manifest']??dirname(__DIR__).'/database/demo_images.json');
        $result=(new \App\Services\DemoImageAssigner(\App\Core\Database::connection(),dirname(__DIR__).'/public'))
            ->run($manifest,isset($options['apply']),$options['actor']??'',$confirm,$ids);
        echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
        return 0;
    }catch(DomainException $e){fwrite(STDERR,'ASSIGNMENT STOPPED: '.$e->getMessage().PHP_EOL);}
    catch(Throwable){fwrite(STDERR,"ASSIGNMENT FAILED: Check database access, reviewed assets and constraints; private diagnostics are suppressed.\n");}
    return 1;
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(avela_assign_images_main($argv));
