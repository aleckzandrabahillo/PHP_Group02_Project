<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('AVELA_HAIR_TEST_LIBRARY', true);
require __DIR__ . '/hair_profiles.php';

function routineStateHash(PDO $pdo): string {
    $out=[];
    foreach (['routines','routine_items','routine_contexts','audit_logs'] as $table) {
        $rows=array_map(static fn($row)=>hash('sha256',json_encode($row)),$pdo->query('SELECT * FROM '.$table)->fetchAll());
        sort($rows);$out[$table]=$rows;
    }
    return hash('sha256',json_encode($out));
}
final class RoutineAuditFaultPDO extends PDO {
    public function prepare(string $query,array $options=[]):PDOStatement|false {
        if(str_starts_with($query,'INSERT INTO audit_logs'))throw new PDOException('Synthetic routine audit fault.');
        return parent::prepare($query,$options);
    }
}
try {
    $o=getopt('',['expect-database:','expect-port:','expect-datadir:','base-url:','session-directory:','scratch-directory:','output:','worker:']);
    $pdo=App\Core\Database::connection();
    $identity=$pdo->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch();
    $norm=static fn($p)=>strtolower(rtrim(str_replace('\\','/',$p),'/'));
    $base=$o['base-url']??'';$url=parse_url($base);
    $sessions=realpath($o['session-directory']??'');$scratch=realpath($o['scratch-directory']??'');
    if(!preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D',$identity['db'])||($o['expect-database']??'')!==$identity['db']
        ||(int)($o['expect-port']??0)!==(int)$identity['port']||in_array((int)$identity['port'],[3306,13307],true)
        ||$norm($o['expect-datadir']??'')!==$norm($identity['datadir'])||env('DB_HOST')!=='127.0.0.1'||env('APP_ENV')!=='development'
        ||!$sessions||!$scratch||str_starts_with($norm($sessions),$norm(dirname(__DIR__)).'/')
        ||str_starts_with($norm($scratch),$norm(dirname(__DIR__)).'/')||($url['host']??'')!=='127.0.0.1'
        ||($url['scheme']??'')!=='http'||in_array((int)($url['port']??80),[80,18080],true))throw new RuntimeException('Disposable-only guards.');
    ini_set('session.save_path',$sessions);
    if(isset($o['worker'])){
        $file=realpath($o['worker']);
        if(!$file||!str_starts_with($norm($file),$norm($scratch).'/'))throw new RuntimeException('Worker input guard.');
        $job=json_decode(file_get_contents($file),true,32,JSON_THROW_ON_ERROR);
        echo json_encode(['id'=>(new App\Services\RoutineBuilder())->save((int)$job['user'],$job['fingerprint'])],JSON_THROW_ON_ERROR);
        exit;
    }
    $checks=[];
    $users=new App\Models\User();$ids=[];
    foreach(['matching_a','matching_b','matching_concurrent'] as $name){
        $name.='_'.bin2hex(random_bytes(5));
        $id=$users->createCustomer(['username'=>$name,'email'=>$name.'@example.invalid','full_name'=>'Synthetic Matching Customer',
            'contact_no'=>'+639123456789','password_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);
        $users->activate($id);$ids[]=$id;
    }
    [$a,$b,$concurrent]=$ids;$client=hairClient($a);$other=hairClient($b);
    $profiles=new App\Models\HairProfile();$service=new App\Services\RecommendationService();$builder=new App\Services\RoutineBuilder();
    $input=['hair_texture'=>'wavy','scalp_type'=>'oily_scalp','hair_condition'=>'dry','concerns'=>['frizz','tangling'],'treatments'=>[],'concern_text'=>'Dry ends but oily roots.'];
    $demoBefore=$pdo->query('SELECT * FROM products ORDER BY id')->fetchAll();
    verifyHair('Routine migration repeat requires zero additions',(new App\Services\RoutineMigration())->run($pdo,false)['pending_additions']===0);
    foreach(['/recommendations','/routine'] as $path)verifyHair('Guest denied '.$path,hairRequest($path)['code']===302);
    verifyHair('Guest routine POST denied',hairRequest('/routine',null,[])['code']===302);
    foreach(['image_catalog_manager','image_admin'] as $name){
        $staff=hairClient((int)$users->findByIdentifier($name)['id']);
        verifyHair($name.' recommendations denied',hairRequest('/recommendations',$staff)['code']===403);
        verifyHair($name.' routine save denied',hairRequest('/routine',$staff,['_token'=>$staff['csrf']])['code']===403);
    }
    verifyHair('No-profile fallback is honest',str_contains(hairRequest('/recommendations',$client)['body'],'Start with your hair profile.'));
    $profiles->save($a,$input,[],App\Models\HairProfile::version(null));
    $preview=$service->forUser($a);$scores=array_column($preview['ranked'],'score','sku');
    verifyHair('Profile-first live recommendations exist',count($preview['ranked'])>0&&count($preview['routine']['items'])>0);
    verifyHair('Scalp-incompatible shampoo absent',!isset($scores['AVE-SMP-001'])&&isset($scores['AVE-SMP-002']));
    $matchesPage=hairRequest('/recommendations',$client)['body'];
    verifyHair('Rendered reasons and accurate matching limits',str_contains($matchesPage,'Why it matches')&&str_contains($matchesPage,'listed product attributes')&&str_contains($matchesPage,'not a guarantee of results')&&str_contains($matchesPage,'does not verify ingredients, treatment compatibility or effectiveness'));
    $before=routineStateHash($pdo);
    verifyHair('Invalid CSRF rejected without writes',hairRequest('/routine',$client,['_token'=>'bad','plan_fingerprint'=>$preview['fingerprint']])['code']===419&&routineStateHash($pdo)===$before);
    verifyHair('Array CSRF rejected safely',hairRequest('/routine',$client,['_token'=>['bad']])['code']===419);
    verifyHair('Invalid fingerprint rejected without writes',hairRequest('/routine',$client,['_token'=>$client['csrf'],'plan_fingerprint'=>['bad']])['code']===409&&routineStateHash($pdo)===$before);
    $p=(int)$pdo->query("SELECT id FROM products WHERE sku='AVE-CON-002'")->fetchColumn();
    $tag=(int)$pdo->query("SELECT id FROM tags WHERE tag_type='concern' AND code='dryness'")->fetchColumn();
    $pdo->prepare('INSERT INTO product_tags (product_id,tag_id) VALUES (?,?)')->execute([$p,$tag]);
    $added=$service->forUser($a);
    verifyHair('Unreviewed tag addition cannot increase score',array_column($added['ranked'],'score','sku')['AVE-CON-002']===$scores['AVE-CON-002']);
    $pdo->prepare('DELETE FROM product_tags WHERE product_id=? AND tag_id=?')->execute([$p,$tag]);
    $p=(int)$pdo->query("SELECT id FROM products WHERE sku='AVE-CON-001'")->fetchColumn();
    $frizz=(int)$pdo->query("SELECT id FROM tags WHERE tag_type='concern' AND code='frizz'")->fetchColumn();
    $pdo->prepare('DELETE FROM product_tags WHERE product_id=? AND tag_id=?')->execute([$p,$frizz]);
    verifyHair('Reviewed tag removal changes score by documented weight',array_column($service->forUser($a)['ranked'],'score','sku')['AVE-CON-001']===$scores['AVE-CON-001']-4);
    $pdo->prepare('INSERT INTO product_tags (product_id,tag_id) VALUES (?,?)')->execute([$p,$frizz]);
    $row=$pdo->query('SELECT * FROM products WHERE id='.$p)->fetch();
    $pdo->prepare('UPDATE products SET description=? WHERE id=?')->execute(['Magic moisture frizz cure with unsupported claims',$p]);
    verifyHair('Changed marketing copy cannot earn a match',!isset(array_column($service->forUser($a)['ranked'],'score','sku')['AVE-CON-001']));
    $pdo->prepare('UPDATE products SET description=? WHERE id=?')->execute([$row['description'],$p]);
    $preview=$service->forUser($a);$score=array_column($preview['ranked'],'score','sku')['AVE-CON-001'];
    $pdo->prepare('UPDATE products SET stock_qty=0 WHERE id=?')->execute([$p]);
    $out=$service->forUser($a);$outBySku=array_column($out['ranked'],null,'sku');
    verifyHair('Stock changes purchasing availability, not compatibility',$outBySku['AVE-CON-001']['score']===$score&&!$outBySku['AVE-CON-001']['available']);
    verifyHair('Unavailable product excluded from suggested routine',!in_array($p,array_column(array_column($out['routine']['items'],'product'),'id')));
    verifyHair('Changed stock invalidates old save form',hairRequest('/routine',$client,['_token'=>$client['csrf'],'plan_fingerprint'=>$preview['fingerprint']])['code']===409);
    $pdo->prepare('UPDATE products SET stock_qty=?,updated_at=? WHERE id=?')->execute([$row['stock_qty'],$row['updated_at'],$p]);
    $preview=$service->forUser($a);
    $post=['_token'=>$client['csrf'],'plan_fingerprint'=>$preview['fingerprint'],'user_id'=>$b,'hair_profile_id'=>999];
    verifyHair('Valid routine save redirects',hairRequest('/routine',$client,$post)['code']===303);
    $saved=(new App\Models\Routine())->stateForUser($a);$id=(int)$saved['routine']['id'];
    verifyHair('Routine items and explanations persisted',count($saved['items'])===count($preview['routine']['items'])&&!$saved['stale']&&count(array_filter($saved['items'],fn($i)=>!$i['explanation']))===0);
    verifyHair('Submitted owner IDs ignored',!$pdo->query('SELECT 1 FROM routines WHERE user_id='.$b)->fetchColumn());
    $before=routineStateHash($pdo);
    verifyHair('Repeated POST is idempotent',hairRequest('/routine',$client,$post)['code']===303&&routineStateHash($pdo)===$before);
    verifyHair('One routine audit',(int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='routine.create' AND actor_id=".$a)->fetchColumn()===1);
    verifyHair('Routine GET prevents private caching',(bool)array_filter(hairRequest('/routine',$client)['headers'],fn($h)=>str_starts_with(strtolower($h),'cache-control:')&&str_contains($h,'no-store')));
    verifyHair('Other customer cannot inspect saved routine',!str_contains(hairRequest('/routine?id='.$id.'&user_id='.$a,$other)['body'],'Repair Conditioner'));
    $product=(int)$saved['items'][0]['product_id'];
    $cartBefore=(new App\Models\Cart())->countForUser($a);
    verifyHair('Recommended product adds through existing cart route',hairRequest('/cart/add',$client,['_token'=>$client['csrf'],'product_id'=>$product,'quantity'=>1,'return_to'=>'/routine'])['code']===302&&(new App\Models\Cart())->countForUser($a)===$cartBefore+1);
    verifyHair('Checkout remains available after routine-to-cart journey',hairRequest('/checkout',$client)['code']===200);
    $restricted=false;try{$pdo->prepare('DELETE FROM products WHERE id=?')->execute([$product]);}catch(PDOException $e){$restricted=$e->getCode()==='23000';}
    verifyHair('Saved routine protects referenced products',$restricted);
    $profiles->save($b,$input,[],App\Models\HairProfile::version(null));$otherPreview=$service->forUser($b);$before=routineStateHash($pdo);
    $fault=new RoutineAuditFaultPDO('mysql:host=127.0.0.1;port='.env('DB_PORT').';dbname='.env('DB_DATABASE').';charset=utf8mb4',env('DB_USERNAME'),env('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $rollback=false;try{(new App\Services\RoutineBuilder($fault))->save($b,$otherPreview['fingerprint']);}catch(PDOException){$rollback=true;}
    verifyHair('Audit fault rolls back routine, items and context',$rollback&&routineStateHash($pdo)===$before);
    $pdo->prepare('UPDATE users SET status="inactive" WHERE id=?')->execute([$b]);
    $denied=false;try{$builder->save($b,$otherPreview['fingerprint']);}catch(InvalidArgumentException){$denied=true;}
    verifyHair('Current account eligibility enforced inside transaction',$denied&&routineStateHash($pdo)===$before);
    $pdo->prepare('UPDATE users SET status="active" WHERE id=?')->execute([$b]);
    $profiles->save($concurrent,$input,[],App\Models\HairProfile::version(null));$parallel=$service->forUser($concurrent);
    $job=$scratch.'/routine-'.bin2hex(random_bytes(6)).'.json';file_put_contents($job,json_encode(['user'=>$concurrent,'fingerprint'=>$parallel['fingerprint']],JSON_THROW_ON_ERROR));
    $command=[PHP_BINARY,__FILE__,'--expect-database='.$identity['db'],'--expect-port='.$identity['port'],'--expect-datadir='.$identity['datadir'],
        '--base-url='.$base,'--session-directory='.$sessions,'--scratch-directory='.$scratch,'--worker='.$job];
    $children=[];for($i=0;$i<2;$i++){$process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);fclose($pipes[0]);$children[]=[$process,$pipes];}
    $results=[];foreach($children as [$process,$pipes]){$output=stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);$results[]=$code===0?json_decode($output,true):null;}
    verifyHair('Concurrent first saves create exactly one routine',isset($results[0]['id'],$results[1]['id'])&&$results[0]['id']===$results[1]['id']&&(int)$pdo->query('SELECT COUNT(*) FROM routines WHERE user_id='.$concurrent)->fetchColumn()===1);
    $current=$profiles->forUser($a);$changed=array_replace($input,['hair_texture'=>'straight']);
    $profiles->save($a,$changed,[],App\Models\HairProfile::version($current));
    verifyHair('Profile change marks saved routine stale',(new App\Models\Routine())->stateForUser($a)['stale']);
    verifyHair('Stale routine displays warning',str_contains(hairRequest('/routine',$client)['body'],'changed since this routine was saved'));
    verifyHair('All twenty product fields preserved',$demoBefore===$pdo->query('SELECT * FROM products ORDER BY id')->fetchAll());
    foreach(['/shop','/product?id=1','/cart','/orders','/assessment'] as $path)verifyHair('Existing route regression '.$path,hairRequest($path,$client)['code']===200);
    $failed=count(array_filter($checks,fn($r)=>$r['status']==='FAIL'));
    if(isset($o['output']))file_put_contents($o['output'],json_encode($checks,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo (count($checks)-$failed).' PASS, '.$failed." FAIL\n";exit($failed?1:0);
}catch(Throwable $e){fwrite(STDERR,'TEST STOPPED: '.get_class($e).' at line '.$e->getLine()."; private diagnostics suppressed.\n");exit(1);}
