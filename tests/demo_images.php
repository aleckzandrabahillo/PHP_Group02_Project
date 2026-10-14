<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/scripts/assign_demo_images.php';

final class ImageAuditFaultStatement extends PDOStatement
{
    private int $calls=0;
    protected function __construct() {}
    public function execute(?array $params=null): bool
    {
        if(str_starts_with($this->queryString,'INSERT INTO audit_logs') && ++$this->calls===2)
            throw new PDOException('Synthetic second-audit failure.');
        return parent::execute($params);
    }
}
$results=[];
function imageCheck(string $test,bool $ok):void{
    global $results;$results[]=['test'=>$test,'status'=>$ok?'PASS':'FAIL'];echo ($ok?'PASS: ':'FAIL: ').$test.PHP_EOL;
}
function imageFingerprint(PDO $pdo):array{
    $out=[];foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table){
        if(!preg_match('/^[a-z_]+$/D',$table))throw new RuntimeException();
        $rows=[];foreach($pdo->query('SELECT * FROM '.$table)->fetchAll() as $r)$rows[]=hash('sha256',json_encode($r));
        sort($rows);$out[$table]=hash('sha256',implode('',$rows));
    }return $out;
}
function imageLaunch(array $args):array{
    $p=proc_open([PHP_BINARY,dirname(__DIR__).'/scripts/assign_demo_images.php',...$args],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($p))throw new RuntimeException();fclose($pipes[0]);return [$p,$pipes];
}
function imageFinish(array $job):array{
    [$p,$pipes]=$job;$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    return ['code'=>proc_close($p),'data'=>json_decode($out,true),'error'=>$err];
}
function imageCli(array $args):array{return imageFinish(imageLaunch($args));}
function imageReject(string $label,callable $fn,PDO $pdo):void{
    $before=imageFingerprint($pdo);$denied=false;
    try{$fn();}catch(DomainException){$denied=true;}
    imageCheck($label,$denied && imageFingerprint($pdo)===$before);
}
try{
    $o=getopt('',['expect-database:','expect-port:','expect-datadir:','base-url:','scratch-directory:','output:']);
    $pdo=App\Core\Database::connection();$s=$pdo->query('SELECT DATABASE() db,@@port port,@@hostname server,@@server_id server_id,@@datadir datadir')->fetch();
    $norm=static fn($v)=>strtolower(rtrim(str_replace('\\','/',$v),'/'));
    $scratch=realpath($o['scratch-directory']??'');$root=dirname(__DIR__);
    $base=$o['base-url']??'';$url=parse_url($base);
    if(!preg_match('/^avela_demo_images_test_[A-Za-z0-9_]+$/D',$s['db'])
        ||($o['expect-database']??null)!==$s['db']||(int)($o['expect-port']??0)!==(int)$s['port']
        ||in_array((int)$s['port'],[3306,13307],true)||$norm($o['expect-datadir']??'')!==$norm($s['datadir'])
        ||env('DB_HOST')!=='127.0.0.1'||env('APP_ENV')!=='development'||!$scratch
        ||str_starts_with($norm($scratch),$norm($root).'/')
        ||($url['host']??'')!=='127.0.0.1'||($url['scheme']??'')!=='http'||in_array((int)($url['port']??80),[80,18080],true))
        throw new RuntimeException('Disposable-only test guard stopped.');
    $map=App\Services\DemoImageAssigner::readManifest($root.'/database/demo_images.json');
    $service=new App\Services\DemoImageAssigner($pdo,$root.'/public');
    $actor='image_catalog_manager';
    $confirm=['host'=>'127.0.0.1','port'=>(int)$s['port'],'database'=>$s['db'],'server'=>$s['server'],'server_id'=>(int)$s['server_id'],'datadir'=>$s['datadir']];
    $original=$pdo->query('SELECT * FROM products ORDER BY id')->fetchAll();$originalById=array_column($original,null,'id');
    $ids=[];foreach($map['images'] as $img){$q=$pdo->prepare('SELECT id FROM products WHERE sku=?');$q->execute([$img['sku']]);$ids[$img['sku']]=(int)$q->fetchColumn();}
    imageCheck('Fresh disposable twenty-product NULL-image baseline',count($original)===20 && count(array_filter($original,fn($p)=>$p['image_path']!==null))===0);
    if(count($original)!==20||array_filter($original,fn($p)=>$p['image_path']!==null))throw new RuntimeException();
    $baseline=imageFingerprint($pdo);
    $guards=['--actor='.$actor,'--expect-host=127.0.0.1','--expect-port='.$s['port'],'--expect-database='.$s['db'],'--expect-server='.$s['server'],'--expect-server-id='.$s['server_id'],'--expect-datadir='.$s['datadir']];
    $apply=['--apply',...$guards];foreach($ids as $sku=>$id)$apply[]='--expect-product='.$sku.':'.$id;
    $dry=imageCli($guards);
    imageCheck('CLI defaults to guarded dry-run',$dry['code']===0&&($dry['data']['mode']??'')==='dry-run'&&($dry['data']['planned_assignments']??0)===2);
    imageCheck('Dry-run reports expected SKU and installation-specific IDs',array_column($dry['data']['products']??[],'id','sku')===$ids);
    imageCheck('Dry-run changes no rows',imageFingerprint($pdo)===$baseline);
    imageCheck('Explicit dry-run succeeds',imageCli(['--dry-run',...$guards])['code']===0);
    imageCheck('Help succeeds without writes',imageCli(['--help'])['code']===0);
    foreach(['host'=>'localhost','port'=>13307,'database'=>'wrong','server'=>'wrong','server_id'=>999999,'datadir'=>$s['datadir'].'wrong'] as $k=>$v){
        $bad=$confirm;$bad[$k]=$v;
        imageReject('Wrong target '.$k.' denied in dry-run',fn()=>$service->run($map,false,$actor,$bad),$pdo);
        imageReject('Wrong target '.$k.' denied in apply',fn()=>$service->run($map,true,$actor,$bad,$ids),$pdo);
    }
    imageReject('Customer actor denied',fn()=>$service->run($map,true,'image_customer',$confirm,$ids),$pdo);
    imageReject('Unknown actor denied',fn()=>$service->run($map,false,'missing_image_actor',$confirm),$pdo);
    imageCheck('Administrator actor accepted for dry-run',$service->run($map,false,'image_admin',$confirm)['planned_assignments']===2);
    $q=$pdo->prepare('SELECT status,locked_until,updated_at FROM users WHERE username=?');$q->execute([$actor]);$saved=$q->fetch();
    foreach(['inactive','locked'] as $kind){
        $q=$pdo->prepare('UPDATE users SET status=?,locked_until=? WHERE username=?');
        $q->execute([$kind==='inactive'?'inactive':'active',$kind==='locked'?date('Y-m-d H:i:s',time()+3600):null,$actor]);
        imageReject(ucfirst($kind).' actor denied',fn()=>$service->run($map,true,$actor,$confirm,$ids),$pdo);
        $q=$pdo->prepare('UPDATE users SET status=?,locked_until=?,updated_at=? WHERE username=?');
        $q->execute([$saved['status'],$saved['locked_until'],$saved['updated_at'],$actor]);
    }
    $badIds=$ids;$badIds[array_key_first($ids)]++;
    imageReject('Wrong product ID denied',fn()=>$service->run($map,true,$actor,$confirm,$badIds),$pdo);
    imageReject('Missing apply product IDs denied',fn()=>$service->run($map,true,$actor,$confirm),$pdo);
    $cases=[];
    $m=$map;$m['images'][0]['sku']='AVE-UNKNOWN-999';$m['images'][0]['path']='assets/images/products/demo/avela-ave-unknown-999.webp';$cases['Unknown SKU']=$m;
    $m=$map;$m['images'][0]['name']='Wrong Product';$cases['Wrong expected name']=$m;
    $m=$map;$m['images'][]=$m['images'][0];$cases['Duplicate SKU']=$m;
    $m=$map;$m['images'][0]['path']='../../.env';$cases['Path traversal']=$m;
    $m=$map;$m['images'][0]['path']=$m['images'][1]['path'];$cases['Wrong SKU filename']=$m;
    $m=$map;$m['images'][0]['sha256']=str_repeat('0',64);$cases['Wrong checksum']=$m;
    $m=$map;$m['images'][0]['width']=1201;$cases['Wrong dimensions']=$m;
    $m=$map;$m['images'][0]['width']='1200';$cases['Noninteger dimensions']=$m;
    $m=$map;$m['images'][0]['width']=5000;$cases['Unbounded dimensions']=$m;
    $m=$map;$m['images'][0]['extra']='unexpected';$cases['Unexpected field']=$m;
    $m=$map;$m['images']=[];$cases['Empty manifest']=$m;
    $m=$map;$m['version']=2;$cases['Unknown manifest version']=$m;
    foreach($cases as $label=>$m)imageReject($label.' rejected',fn()=>$service->run($m,false,$actor,$confirm),$pdo);
    $number=0;
    foreach(['missing','wrong-image','corrupt','truncated','wrong-mime','bad-riff','metadata','oversize'] as $kind){
        $dir=$scratch.'/case-'.(++$number).'/assets/images/products/demo';if(!mkdir($dir,0770,true))throw new RuntimeException();
        foreach($map['images'] as $img)copy($root.'/public/'.$img['path'],$dir.'/'.basename($img['path']));
        $file=$dir.'/'.basename($map['images'][0]['path']);$bytes=file_get_contents($file);$m=$map;
        if($kind==='missing')unlink($file);
        elseif($kind==='wrong-image')copy($root.'/public/'.$map['images'][1]['path'],$file);
        else{
            if($kind==='corrupt')$bytes[100]=chr(ord($bytes[100])^0x01);
            if($kind==='truncated')$bytes=substr($bytes,0,-7);
            if($kind==='wrong-mime')$bytes='not a WebP image';
            if($kind==='bad-riff')$bytes=substr_replace($bytes,pack('V',20),4,4);
            if($kind==='metadata'){$bytes.='EXIF'.pack('V',4).'test';$bytes=substr_replace($bytes,pack('V',strlen($bytes)-8),4,4);}
            if($kind==='oversize')$bytes=str_repeat('x',2*1024*1024+1);
            file_put_contents($file,$bytes);
            if(in_array($kind,['wrong-mime','bad-riff','metadata','oversize'],true))$m['images'][0]['sha256']=hash('sha256',$bytes);
        }
        $fixture=new App\Services\DemoImageAssigner($pdo,dirname($dir,4));
        imageReject('Private asset fixture '.$kind.' denied',fn()=>$fixture->run($m,false,$actor,$confirm),$pdo);
    }
    foreach([
        'Missing all guards'=>[],
        'Missing actor'=>array_values(array_filter($guards,fn($x)=>!str_starts_with($x,'--actor='))),
        'Apply missing IDs'=>['--apply',...$guards],
        'Unknown flag'=>['--wrong',...$guards],
        'Duplicate flag'=>['--dry-run','--dry-run',...$guards],
        'Conflicting modes'=>['--apply','--dry-run',...$guards],
        'Duplicate ID'=>[...$apply,'--expect-product=AVE-TOL-001:'.$ids['AVE-TOL-001']],
        'Zero ID'=>[...$guards,'--expect-product=AVE-TOL-001:0'],
        'Overflow ID'=>[...$guards,'--expect-product=AVE-TOL-001:4294967296'],
        'Unknown ID SKU'=>[...$guards,'--expect-product=AVE-UNKNOWN:1'],
        'Array-shaped flag'=>[...$guards,'--actor[]=image_admin']
    ] as $label=>$args){
        $before=imageFingerprint($pdo);$r=imageCli($args);
        imageCheck('CLI '.$label.' rejected',$r['code']!==0&&imageFingerprint($pdo)===$before&&!str_contains($r['error'],(string)env('DB_PASSWORD')));
    }
    $targetId=$ids['AVE-TOL-001'];$savedProduct=$originalById[$targetId];
    $q=$pdo->prepare('UPDATE products SET image_path=? WHERE id=?');$q->execute(['assets/images/products/demo/existing.webp',$targetId]);
    imageReject('Existing different image stops entire batch',fn()=>$service->run($map,true,$actor,$confirm,$ids),$pdo);
    $q=$pdo->prepare('UPDATE products SET image_path=NULL,updated_at=? WHERE id=?');$q->execute([$savedProduct['updated_at'],$targetId]);
    $fault=new PDO('mysql:host=127.0.0.1;port='.$s['port'].';dbname='.$s['db'].';charset=utf8mb4',(string)env('DB_USERNAME'),(string)env('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STATEMENT_CLASS=>[ImageAuditFaultStatement::class,[]]]);
    $before=imageFingerprint($pdo);$failed=false;
    try{(new App\Services\DemoImageAssigner($fault,$root.'/public'))->run($map,true,$actor,$confirm,$ids);}catch(PDOException){$failed=true;}
    imageCheck('Actual second-audit failure rolls back both product updates and first audit',$failed&&imageFingerprint($pdo)===$before);
    imageCheck('All preflight tests restore original baseline rows',imageFingerprint($pdo)===$baseline);
    $first=imageLaunch($apply);$second=imageLaunch($apply);$a=imageFinish($first);$b=imageFinish($second);
    $counts=[$a['data']['assigned']??-1,$b['data']['assigned']??-1];sort($counts);
    imageCheck('Concurrent real CLI applies serialize to two assignments total',$a['code']===0&&$b['code']===0&&$counts===[0,2]);
    $after=imageFingerprint($pdo);$changed=[];foreach($baseline as $table=>$hash)if($after[$table]!==$hash)$changed[]=$table;sort($changed);
    imageCheck('Only product rows and audit rows changed',$changed===['audit_logs','products']);
    $now=$pdo->query('SELECT * FROM products ORDER BY id')->fetchAll();
    foreach($now as $p){
        $originalRow=$originalById[$p['id']];
        $desired=null;foreach($map['images'] as $img)if($img['sku']===$p['sku'])$desired=$img['path'];
        imageCheck('Correct image mapping / placeholder: '.$p['sku'],$p['image_path']===$desired);
        unset($p['image_path'],$p['updated_at'],$originalRow['image_path'],$originalRow['updated_at']);
        imageCheck('All other product fields preserved: '.$p['sku'],$p===$originalRow);
    }
    $logs=$pdo->query("SELECT actor_id,target_id,details FROM audit_logs WHERE action='product.image.assign' ORDER BY target_id")->fetchAll();
    $actorId=$pdo->query("SELECT id FROM users WHERE username='image_catalog_manager'")->fetchColumn();
    $loggedIds=array_map('intval',array_column($logs,'target_id'));sort($loggedIds);$expected=array_values($ids);sort($expected);
    imageCheck('Exactly two appropriate assignment audits',count($logs)===2&&$loggedIds===$expected&&array_filter($logs,fn($l)=>(int)$l['actor_id']!==(int)$actorId)===[]);
    imageCheck('Audit details include approved mapping/checksum',array_filter($logs,fn($l)=>!str_contains($l['details'],'NULL -> assets/images/products/demo/')||!str_contains($l['details'],'SHA256 '))===[]);
    $again=imageCli($apply);imageCheck('Repeat apply skips both images and writes no rows',$again['code']===0&&$again['data']['assigned']===0&&$again['data']['unchanged']===2&&imageFingerprint($pdo)===$after);
    $again=imageCli($guards);imageCheck('Repeat dry-run plans zero changes',$again['code']===0&&$again['data']['planned_assignments']===0&&imageFingerprint($pdo)===$after);
    $http=static function(string $path)use($base):array{
        $headers=[];$ctx=stream_context_create(['http'=>['timeout'=>10,'ignore_errors'=>true]]);
        $body=file_get_contents($base.$path,false,$ctx);$headers=$http_response_header??[];
        return ['body'=>$body,'headers'=>$headers,'ok'=>isset($headers[0])&&str_contains($headers[0],' 200 ')];
    };
    $shop=$http('/shop');
    imageCheck('Real public catalog shows exactly two product images',$shop['ok']&&substr_count($shop['body'],'class="product-image"')===2);
    imageCheck('Other eighteen catalog products retain placeholders',substr_count($shop['body'],'class="product-placeholder ')===18);
    foreach($map['images'] as $img){
        imageCheck('Catalog correct image source: '.$img['sku'],str_contains($shop['body'],'src="/'.$img['path'].'"'));
        $detail=$http('/product?id='.$ids[$img['sku']]);
        imageCheck('Real product detail correct source: '.$img['sku'],$detail['ok']&&str_contains($detail['body'],'class="product-detail-image" src="/'.$img['path'].'"'));
        $asset=$http('/'.$img['path']);
        imageCheck('Asset HTTP 200 and approved bytes: '.$img['sku'],$asset['ok']&&hash('sha256',$asset['body'])===$img['sha256']&&str_contains(strtolower(implode("\n",$asset['headers'])),'content-type: image/webp'));
    }
    $other=array_values(array_diff(array_column($original,'id'),array_values($ids)))[0];$detail=$http('/product?id='.$other);
    imageCheck('Unassigned product detail retains placeholder',$detail['ok']&&str_contains($detail['body'],'product-placeholder--large')&&!str_contains($detail['body'],'class="product-detail-image"'));
    imageCheck('Public HTTP checks changed no database rows',imageFingerprint($pdo)===$after);
    $fail=count(array_filter($results,fn($r)=>$r['status']==='FAIL'));
    $report=['test_type'=>'Actual PDO/CLI/concurrent processes/HTTP on guarded synthetic disposable database; rollback test injects a second-audit failure','pass'=>count($results)-$fail,'fail'=>$fail,'results'=>$results];
    if(isset($o['output']))file_put_contents($o['output'],json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo json_encode(['pass'=>$report['pass'],'fail'=>$fail]),PHP_EOL;exit($fail?1:0);
}catch(Throwable $e){fwrite(STDERR,"Image test harness stopped; private diagnostics suppressed. Class ".get_class($e).' at line '.$e->getLine().PHP_EOL);exit(1);}
