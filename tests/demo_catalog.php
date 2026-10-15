<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/scripts/import_demo_catalog.php';

final class FailingDemoPDO extends PDO
{
    public int $productPrepares = 0;
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_starts_with($query, 'INSERT INTO products') && ++$this->productPrepares === 2) {
            throw new PDOException('Injected second-insert failure.');
        }
        return parent::prepare($query, $options);
    }
}

$results = [];
function check(string $name, bool $ok): void
{
    global $results;
    $results[] = ['test' => $name, 'status' => $ok ? 'PASS' : 'FAIL'];
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $name . PHP_EOL;
}
function fingerprint(PDO $pdo): array
{
    $out = [];
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/^[a-z_]+$/D', $table)) throw new RuntimeException();
        $out[$table] = hash('sha256', json_encode($pdo->query('SELECT * FROM ' . $table . ' ORDER BY 1,2')->fetchAll()));
    }
    return $out;
}
function launch(array $args): array
{
    $pipes = [];
    $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/scripts/import_demo_catalog.php', ...$args],
        [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException();
    fclose($pipes[0]);
    return [$process, $pipes];
}
function finish(array $job): array
{
    [$process, $pipes] = $job;
    $out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return ['code' => proc_close($process), 'data' => json_decode($out, true), 'error' => $error];
}
function cli(array $args = []): array { return finish(launch($args)); }

try {
    $options = getopt('', ['expect-database:','expect-port:','expect-datadir:','actor:','customer:','base-url:','output:']);
    $pdo = \App\Core\Database::connection();
    $target = $pdo->query('SELECT DATABASE() db,@@port port,@@datadir dir')->fetch();
    if (!preg_match('/^avela_demo_test_[A-Za-z0-9_]+$/D', (string) $target['db'])
        || ($options['expect-database'] ?? null) !== $target['db']
        || (int) ($options['expect-port'] ?? 0) !== (int) $target['port']
        || in_array((int) $target['port'], [3306,13307], true)
        || strcasecmp(rtrim(str_replace('\\','/', (string) ($options['expect-datadir'] ?? '')), '/'),
            rtrim(str_replace('\\','/', $target['dir']), '/')) !== 0
        || env('DB_HOST') !== '127.0.0.1' || env('APP_ENV') !== 'development') {
        throw new RuntimeException('Disposable target guard failed.');
    }
    $manifest = \App\Services\DemoCatalogImporter::readManifest(dirname(__DIR__) . '/database/demo_catalog.json');
    $importer = new \App\Services\DemoCatalogImporter($pdo);
    $confirmation = ['host' => '127.0.0.1', 'port' => (int) $target['port'], 'database' => $target['db']];
    $applyArgs = ['--apply','--actor=' . ($options['actor'] ?? ''),'--expect-host=127.0.0.1',
        '--expect-port=' . $target['port'],'--expect-database=' . $target['db']];
    $before = fingerprint($pdo);
    $seed = $pdo->query('SELECT * FROM products ORDER BY id')->fetchAll();
    $seedTags = $pdo->query('SELECT * FROM product_tags ORDER BY product_id,tag_id')->fetchAll();
    check('Verified disposable six-product baseline', count($seed) === 6);
    if (count($seed) !== 6) throw new RuntimeException('Fresh six-product baseline required.');
    $dry = cli();
    check('Default invocation is dry-run', $dry['code'] === 0 && ($dry['data']['mode'] ?? '') === 'dry-run');
    check('Dry-run plans fourteen additions and total twenty', ($dry['data']['planned_inserts'] ?? 0) === 14 && ($dry['data']['total_after'] ?? 0) === 20);
    check('Dry-run changes no database rows', fingerprint($pdo) === $before);
    check('Explicit dry-run works', cli(['--dry-run'])['code'] === 0);
    check('Help requires no writes', cli(['--help'])['code'] === 0);
    foreach ([
        'Missing write-target confirmation' => ['--apply','--actor=' . $options['actor']],
        'Missing actor' => array_values(array_filter($applyArgs, fn($x) => !str_starts_with($x, '--actor='))),
        'Wrong database confirmation' => array_map(fn($x) => str_starts_with($x,'--expect-database=') ? '--expect-database=wrong_database' : $x, $applyArgs),
        'Wrong port confirmation' => array_map(fn($x) => str_starts_with($x,'--expect-port=') ? '--expect-port=13307' : $x, $applyArgs),
        'Wrong host confirmation' => array_map(fn($x) => str_starts_with($x,'--expect-host=') ? '--expect-host=localhost' : $x, $applyArgs),
        'Customer actor denied' => array_map(fn($x) => str_starts_with($x,'--actor=') ? '--actor=' . $options['customer'] : $x, $applyArgs),
        'Unknown actor denied' => array_map(fn($x) => str_starts_with($x,'--actor=') ? '--actor=missing_demo_actor' : $x, $applyArgs),
        'Unknown flag denied' => ['--unexpected'],
        'Duplicate flag denied' => ['--dry-run','--dry-run'],
        'Conflicting mode flags denied' => ['--apply','--dry-run'],
        'Invalid port syntax denied' => ['--apply','--expect-port=not-a-port'],
    ] as $label => $args) {
        $r = cli($args);
        check($label, $r['code'] !== 0 && fingerprint($pdo) === $before);
    }
    $cases = [
        ['Empty name', 'name', '', false],
        ['Name at schema limit', 'name', str_repeat('N',160), true],
        ['Name exceeds schema limit', 'name', str_repeat('N',161), false],
        ['Array name', 'name', ['invalid'], false],
        ['SKU at schema limit', 'sku', 'AVE-' . str_repeat('A',46), true],
        ['SKU exceeds schema limit', 'sku', 'AVE-' . str_repeat('A',47), false],
        ['Zero price', 'price', '0.00', true],
        ['Maximum price', 'price', '99999999.99', true],
        ['Price overflow', 'price', '100000000.00', false],
        ['Excess price precision', 'price', '1.001', false],
        ['Numeric JSON price rejected', 'price', 12.34, false],
        ['Negative price', 'price', '-1.00', false],
        ['Zero stock', 'stock_qty', 0, true],
        ['Maximum stock', 'stock_qty', 4294967295, true],
        ['Stock overflow', 'stock_qty', 4294967296, false],
        ['Fractional stock', 'stock_qty', 1.5, false],
        ['Negative stock', 'stock_qty', -1, false],
        ['Description at byte limit', 'description', str_repeat('D',65535), true],
        ['Multibyte description at byte limit', 'description', str_repeat('é',32767) . 'a', true],
        ['Description exceeds byte limit', 'description', str_repeat('D',65536), false],
        ['Empty description', 'description', '', false],
        ['Invalid status', 'status', 'archived', false],
        ['Unknown routine step', 'routine_step', 'medical', false],
        ['Unknown category', 'category', 'Nonexistent category', false],
        ['Unknown tag', 'tags', ['concern:unknown'], false],
        ['Duplicate tag', 'tags', ['texture:straight','texture:straight'], false],
        ['Array instead of tag text', 'tags', [['texture:straight']], false],
        ['Nonexistent image path rejected', 'image_path', 'assets/images/not-installed.webp', false],
        ['Image filename traversal rejected', 'suggested_image', '../image.webp', false],
        ['Control character rejected', 'name', "Bad\x01name", false],
        ['Whitespace is not silently normalized', 'name', '  New name  ', false],
    ];
    foreach ($cases as [$label,$field,$value,$expected]) {
        $copy = $manifest; $copy['products'][6][$field] = $value;
        if ($field === 'sku') $copy['products'][6]['suggested_image'] = 'avela-' . strtolower($value) . '.webp';
        $ok = true;
        try { $importer->run($copy); } catch (DomainException $e) { $ok = false; }
        check($label, $ok === $expected && fingerprint($pdo) === $before);
    }
    foreach (['short','duplicate','wrong-kind','wrong-version','seed-conflict','seed-missing'] as $case) {
        $copy = $manifest;
        if ($case === 'short') array_pop($copy['products']);
        if ($case === 'duplicate') $copy['products'][7]['sku'] = $copy['products'][6]['sku'];
        if ($case === 'wrong-kind') $copy['products'][6]['kind'] = 'existing';
        if ($case === 'wrong-version') $copy['version'] = 2;
        if ($case === 'seed-conflict') $copy['products'][0]['price'] = '1.00';
        if ($case === 'seed-missing') { $copy['products'][0]['sku'] = 'AVE-SMP-999'; $copy['products'][0]['suggested_image'] = 'avela-ave-smp-999.webp'; }
        $rejected = false; try { $importer->run($copy, true, $options['actor'], $confirmation); } catch (DomainException $e) { $rejected = true; }
        check('Manifest safety: ' . $case, $rejected && fingerprint($pdo) === $before);
    }
    $tmp = tempnam(sys_get_temp_dir(), 'avela-demo-');
    try {
        file_put_contents($tmp, '{invalid JSON');
        check('Malformed JSON safely reported', cli(['--manifest=' . $tmp])['code'] !== 0 && fingerprint($pdo) === $before);
        file_put_contents($tmp, str_repeat('x', 2 * 1024 * 1024 + 1));
        check('Oversized manifest safely rejected', cli(['--manifest=' . $tmp])['code'] !== 0 && fingerprint($pdo) === $before);
    } finally { unlink($tmp); }
    $fault = new FailingDemoPDO('mysql:host=127.0.0.1;port=' . $target['port'] . ';dbname=' . $target['db'] . ';charset=utf8mb4',
        (string) env('DB_USERNAME'), (string) env('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $failed = false;
    try { (new \App\Services\DemoCatalogImporter($fault))->run($manifest, true, $options['actor'], $confirmation); }
    catch (PDOException $e) { $failed = true; }
    check('Simulated second-insert fault rolls back real product/tag/audit writes', $failed && $fault->productPrepares === 2 && !$fault->inTransaction() && fingerprint($pdo) === $before);
    $lock = 'avela.demo.' . substr(hash('sha256', $target['db']),0,40);
    $stmt = $pdo->prepare('SELECT IS_FREE_LOCK(:name)'); $stmt->execute(['name'=>$lock]);
    check('Failed import releases its advisory lock', (int) $stmt->fetchColumn() === 1);
    $jobs = [launch($applyArgs), launch($applyArgs)];
    $runs = [finish($jobs[0]), finish($jobs[1])];
    $inserted = array_map(fn($r) => $r['data']['inserted'] ?? -1, $runs); sort($inserted);
    check('Concurrent repeat-safe imports both succeed', $runs[0]['code'] === 0 && $runs[1]['code'] === 0);
    check('Exactly one concurrent import inserts fourteen', $inserted === [0,14]);
    $after = fingerprint($pdo);
    $again = cli($applyArgs);
    check('Sequential rerun inserts zero', $again['code'] === 0 && ($again['data']['inserted'] ?? -1) === 0);
    check('Sequential rerun leaves every table unchanged', fingerprint($pdo) === $after);
    check('Final product count is exactly twenty', (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() === 20);
    $ids = implode(',',array_map('intval',array_column($seed,'id')));
    check('All six original product rows remain byte-for-byte unchanged', $pdo->query('SELECT * FROM products WHERE id IN (' . $ids . ') ORDER BY id')->fetchAll() === $seed);
    check('All six original tag associations remain unchanged', $pdo->query('SELECT * FROM product_tags WHERE product_id IN (' . $ids . ') ORDER BY product_id,tag_id')->fetchAll() === $seedTags);
    foreach ($before as $table => $hash) if (!in_array($table,['products','product_tags','audit_logs'],true)) check('Preserved table: ' . $table, $after[$table] === $hash);
    check('Exactly fourteen product-create audits appended', (int)$pdo->query('SELECT COUNT(*) FROM audit_logs WHERE action="product.create"')->fetchColumn() === 14);
    check('All twenty image paths remain NULL', (int)$pdo->query('SELECT COUNT(*) FROM products WHERE image_path IS NULL')->fetchColumn() === 20);
    check('No duplicate SKUs', (int)$pdo->query('SELECT COUNT(DISTINCT sku) FROM products')->fetchColumn() === 20);
    foreach ($manifest['products'] as $item) {
        $s=$pdo->prepare('SELECT p.id,p.category_id,c.name category,p.price,p.stock_qty,p.status FROM products p JOIN categories c ON c.id=p.category_id WHERE p.sku=:sku');$s->execute(['sku'=>$item['sku']]);$row=$s->fetch();
        check('Persisted fields: ' . $item['sku'], $row && $row['category']===$item['category'] && $row['price']===$item['price'] && (int)$row['stock_qty']===$item['stock_qty'] && $row['status']==='active');
        $s=$pdo->prepare('SELECT CONCAT(t.tag_type,":",t.code) FROM product_tags pt JOIN tags t ON t.id=pt.tag_id WHERE pt.product_id=:id');$s->execute(['id'=>$row['id']]);$actual=$s->fetchAll(PDO::FETCH_COLUMN);$expected=$item['tags'];sort($actual);sort($expected);
        check('Persisted suitability tags: ' . $item['sku'], $actual===$expected);
    }
    $conflict=$manifest;$conflict['products'][6]['stock_qty']++;
    $stopped=false;try{$importer->run($conflict,true,$options['actor'],$confirmation);}catch(DomainException $e){$stopped=str_contains($e->getMessage(),'Conflicting SKU');}
    check('Existing imported SKU conflict is reported without overwriting', $stopped && fingerprint($pdo)===$after);
    $extra=$manifest;$extra['products'][6]['sku']='AVE-SMP-999';$extra['products'][6]['suggested_image']='avela-ave-smp-999.webp';
    $stopped=false;try{$importer->run($extra,true,$options['actor'],$confirmation);}catch(DomainException $e){$stopped=true;}
    check('Unexpected catalog products cause a safe stop', $stopped && fingerprint($pdo)===$after);
    $base=$options['base-url']??'';
    if(!preg_match('~^http://127\.0\.0\.1:[0-9]+$~D',$base))throw new RuntimeException('Disposable HTTP URL required.');
    $body=file_get_contents($base.'/shop');
    preg_match_all('/class="product-card-title">([^<]+)</',$body,$cards);
    check('Actual public catalog displays twenty products', count($cards[1])===20);
    foreach(array_slice($manifest['products'],6)as$item)check('Public catalog: '.$item['sku'],str_contains($body,htmlspecialchars($item['name'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')));
    check('Image fallback preserved',str_contains($body,'assets/images/placeholders/'));
    $search=file_get_contents($base.'/shop?q=Sectioning');check('Public search finds new accessory',str_contains($search,'Avela Sectioning Clips Set'));
    $filtered=file_get_contents($base.'/shop?category=1');preg_match_all('/class="product-card-title">([^<]+)</',$filtered,$cards);check('Existing category filter shows four shampoos',count($cards[1])===4);
    $s=$pdo->prepare('SELECT id FROM products WHERE sku=:sku');$s->execute(['sku'=>'AVE-SER-002']);$detail=file_get_contents($base.'/product?id='.$s->fetchColumn());
    check('New product detail displays persisted data',str_contains($detail,'Avela Daily Leave-In Milk')&&str_contains($detail,'329.00'));
    check('Public browsing changes no database rows',fingerprint($pdo)===$after);
} catch (Throwable $e) {
    check('Test harness completed without a blocked operation',false);
    fwrite(STDERR,"Test operation blocked; confidential details suppressed.\n");
}
if(isset($options['output']))file_put_contents($options['output'],json_encode($results,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
$failed=count(array_filter($results,fn($r)=>$r['status']!=='PASS'));
echo 'TOTAL: '.count($results).' checks; '.$failed.' FAIL'.PHP_EOL;
exit($failed===0?0:1);
