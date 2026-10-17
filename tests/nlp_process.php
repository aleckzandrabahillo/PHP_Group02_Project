<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/Core/Env.php';
require dirname(__DIR__).'/app/Support/helpers.php';
ini_set('display_errors','0');ini_set('log_errors','0');
$options=getopt('', ['output:','worker:']);
$root=dirname(__DIR__);
$python=(string)env('NLP_PYTHON_BINARY','python');
function resultFile(string $path, array $data):void { file_put_contents($path,json_encode($data,JSON_THROW_ON_ERROR)); }
function invokeNlp(string $text):array {
    $start=hrtime(true);$result=null;$error=null;
    try{$result=(new App\Services\ConcernInterpreter())->interpret($text);}catch(Throwable $e){$error=get_class($e);}
    return ['seconds'=>round((hrtime(true)-$start)/1e9,4),'result'=>$result,'error'=>$error];
}
if(isset($options['worker'])){
    $job=json_decode(file_get_contents($options['worker']),true,32,JSON_THROW_ON_ERROR);
    require $job['fixture'].'/app/Services/ConcernInterpreter.php';
    resultFile($job['output'],invokeNlp($job['text']));exit;
}
$tempRoot=realpath(sys_get_temp_dir());
if($tempRoot===false)throw new RuntimeException('Temporary directory unavailable.');
$fixture=$tempRoot.'/avela-nlp-test-'.bin2hex(random_bytes(8)).' with spaces';
foreach(['/app/Services','/nlp'] as $dir)if(!mkdir($fixture.$dir,0700,true))throw new RuntimeException('Fixture directory unavailable.');
foreach(['app/Services/ConcernInterpreter.php','nlp/runner.py','nlp/concerns.json'] as $file)copy($root.'/'.$file,$fixture.'/'.$file);
require $fixture.'/app/Services/ConcernInterpreter.php';
$checks=[];$measurements=[];$workers=[];
function verifyProcess(string $name,bool $pass):void { global $checks;$checks[]=['test'=>$name,'status'=>$pass?'PASS':'FAIL']; }
function writeFixture(string $source):void { global $fixture;file_put_contents($fixture.'/nlp/interpret.py',$source); }
function assertStopped(int $pid):bool {
    if(PHP_OS_FAMILY!=='Windows')return !function_exists('posix_kill')||!@posix_kill($pid,0);
    // Query one recorded fixture PID; never terminate or enumerate processes by executable name.
    $p=proc_open(['C:/Windows/System32/tasklist.exe','/FI','PID eq '.$pid,'/FO','CSV','/NH'],
        [0=>['file','NUL','r'],1=>['pipe','w'],2=>['file','NUL','w']],$pipes,null,null,['bypass_shell'=>true]);
    $text=stream_get_contents($pipes[1]);fclose($pipes[1]);proc_close($p);
    return !preg_match('/","'.preg_quote((string)$pid,'/').'","/',$text);
}
try{
    copy($root.'/nlp/interpret.py',$fixture.'/nlp/interpret.py');
    $normal=invokeNlp('Dry ends but oily roots.');$measurements['normal']=$normal['seconds'];
    verifyProcess('Normal fast Python result',($normal['result']['status']??'')==='recognized'&&$normal['seconds']<3);
    verifyProcess('Fixed script path containing spaces works',$normal['error']===null);
    verifyProcess('Unicode input survives JSON round trip',invokeNlp('Tuyô at buhaghag ang hair ko.')['error']===null);
    $injection=invokeNlp('dry ends; $(echo unexpected) & | < > " café 😀');
    verifyProcess('Shell metacharacters remain bounded JSON data',$injection['error']===null);
    $empty=invokeNlp('');verifyProcess('Empty text does not require a process',($empty['result']['status']??'')==='empty');
    verifyProcess('Too-long input is rejected',invokeNlp(str_repeat('a',501))['error']===InvalidArgumentException::class);
    verifyProcess('Invalid UTF-8 is rejected',invokeNlp("\xff")['error']===InvalidArgumentException::class);
    writeFixture("import json, sys\npayload=json.loads(sys.stdin.buffer.read().decode('utf-8'))\nprint(json.dumps({'status':'unknown','tags':[],'matches':[payload['text']],'warnings':[]}, ensure_ascii=False))\n");
    $unicode='Café 😀 buhaghag';$roundTrip=invokeNlp($unicode);
    verifyProcess('Unicode output preserves exact input bytes',($roundTrip['result']['matches'][0]??'')===$unicode);
    foreach(['sleep'=>"import time\ntime.sleep(10)\n",'hung'=>"while True:\n    pass\n"] as $label=>$code){
        $pidFile=$fixture.'/'.$label.'.pid';
        writeFixture("import os\nfrom pathlib import Path\nPath(".json_encode($pidFile).").write_text(str(os.getpid()))\n".$code);
        $r=invokeNlp('Synthetic dry ends.');$measurements[$label]=$r['seconds'];
        verifyProcess($label.' deadline and <=0.5 second cleanup allowance',$r['error']===RuntimeException::class&&$r['seconds']>=3&&$r['seconds']<3.5);
        $pid=(int)file_get_contents($pidFile);verifyProcess($label.' interpreter is no longer running',assertStopped($pid));
    }
    $pids=$fixture.'/tree.json';
    writeFixture("import os, subprocess, sys, time\nfrom pathlib import Path\nchild = subprocess.Popen([sys.executable, '-I', '-B', '-c', 'import time; time.sleep(30)'])\nPath(".json_encode($pids).").write_text(str(os.getpid()) + ',' + str(child.pid))\ntime.sleep(30)\n");
    $r=invokeNlp('Synthetic dry ends.');$measurements['child_timeout']=$r['seconds'];
    verifyProcess('Child-producing invocation is bounded',$r['error']===RuntimeException::class&&$r['seconds']<3.5);
    [$parent,$child]=array_map('intval',explode(',',file_get_contents($pids)));
    verifyProcess('Only the invocation parent and child were cleaned up',assertStopped($parent)&&assertStopped($child));
    $sentinel=proc_open([$python,'-I','-B','-c','import time; time.sleep(20)'],[0=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','r'],1=>['file',$fixture.'/sentinel.log','w'],2=>['file',$fixture.'/sentinel-error.log','w']],$pipes,null,null,['bypass_shell'=>true]);
    try{
        writeFixture("import time\ntime.sleep(20)\n");invokeNlp('Synthetic timeout.');
        verifyProcess('Timeout leaves an unrelated Python invocation running',proc_get_status($sentinel)['running']);
    }finally{proc_terminate($sentinel,9);proc_close($sentinel);}
    writeFixture("import os, subprocess, sys\nfrom pathlib import Path\nchild=subprocess.Popen([sys.executable,'-I','-B','-c','import time; time.sleep(20)'])\nPath(".json_encode($fixture.'/success-child.pid').").write_text(str(child.pid))\nprint('{\"status\":\"unknown\",\"tags\":[],\"matches\":[],\"warnings\":[]}')\n");
    $r=invokeNlp('Synthetic normal exit.');
    verifyProcess('Normal parent exit also cleans up descendants',$r['error']===null&&assertStopped((int)file_get_contents($fixture.'/success-child.pid')));
    foreach(['invalid_json'=>"print('invalid json')",'wrong_schema'=>"print('{}')",'oversized'=>"print('x' * 40000)",
        'empty_output'=>'pass','nonzero'=>"import sys\nsys.exit(7)",
        'exception'=>"raise RuntimeError('Synthetic private concern must not be logged')"] as $label=>$code){
        writeFixture($code."\n");$r=invokeNlp('Synthetic dry ends.');$measurements[$label]=$r['seconds'];
        verifyProcess($label.' rejected promptly',$r['error']!==null&&$r['seconds']<1);
    }
    writeFixture("import time\ntime.sleep(1)\nprint('{\"status\":\"unknown\",\"tags\":[],\"matches\":[],\"warnings\":[]}')\n");
    for($i=0;$i<2;$i++){
        $job=$fixture.'/worker-'.$i.'.json';$out=$fixture.'/worker-'.$i.'-result.json';
        resultFile($job,['fixture'=>$fixture,'output'=>$out,'text'=>'Synthetic request '.$i]);
        $process=proc_open([PHP_BINARY,__FILE__,'--worker='.$job],[0=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','r'],1=>['file',$fixture.'/worker-'.$i.'.log','w'],2=>['file',$fixture.'/worker-'.$i.'-error.log','w']],$pipes,null,null,['bypass_shell'=>true]);
        $workers[]=[$process,$out];
    }
    $ok=true;foreach($workers as [$p,$out]){$exit=proc_close($p);$r=json_decode(file_get_contents($out),true);$ok=$ok&&$exit===0&&$r['error']===null&&$r['seconds']<3;}
    $workers=[];verifyProcess('Concurrent invocations keep independent output and cleanup',$ok);
    $fail=count(array_filter($checks,static fn($r)=>$r['status']==='FAIL'));
    $report=['platform'=>PHP_OS_FAMILY,'pass'=>count($checks)-$fail,'fail'=>$fail,'seconds'=>$measurements,'checks'=>$checks];
    if(isset($options['output']))resultFile($options['output'],$report);
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;
}finally{
    foreach($workers as [$p])if(is_resource($p)){proc_terminate($p,9);proc_close($p);}
    // Only this randomly created fixture tree is removed; no project or existing data is touched.
    $resolved=realpath($fixture);
    if($resolved===false||dirname($resolved)!==$tempRoot||!str_starts_with(basename($resolved),'avela-nlp-test-'))throw new RuntimeException('Fixture cleanup guard.');
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $file){if($file->isDir())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($fixture);
}
exit($fail?1:0);
