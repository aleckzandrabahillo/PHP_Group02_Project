<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/scripts/assign_demo_images.php';
$checks=[];$check=static function(string $name,bool $ok)use(&$checks):void{$checks[]=['test'=>$name,'status'=>$ok?'PASS':'FAIL'];};
try{
 $args=getopt('',['scratch-directory:','defender-binary:','output:']);$scratch=realpath($args['scratch-directory']??'');$root=strtolower(str_replace('\\','/',dirname(__DIR__)));
 if(!$scratch||str_starts_with(strtolower(str_replace('\\','/',$scratch)),$root.'/')||in_array($scratch,['/','C:\\'],true))throw new RuntimeException('Private scratch directory required');
 $process=new App\Services\UploadScanProcess();
 foreach([0,2,5] as $exit)$check('Controlled scanner exit '.$exit,$process->run([PHP_BINARY,'-r','exit('.$exit.');'],1)===$exit);
 $start=hrtime(true);$timed=false;try{$process->run([PHP_BINARY,'-r','sleep(10);'],.2);}catch(RuntimeException){$timed=true;}
 $elapsed=(hrtime(true)-$start)/1e9;$check('Timed-out scanner rejected within bounded cleanup',$timed&&$elapsed<.8);
 $imageFile=tempnam($scratch,'scan-');$settings=tempnam($scratch,'scan-config-');$destination=$scratch.'/scan-public-'.bin2hex(random_bytes(8)).'.png';
 try{
  $image=imagecreatetruecolor(8,8);imagepng($image,$imageFile);imagedestroy($image);
  file_put_contents($settings,"UPLOAD_SCANNER=unknown\n");App\Core\Env::load($settings);
  $blocked=false;try{App\Services\ImageUploadPolicy::store($imageFile,$destination,'image/png');}catch(RuntimeException){$blocked=true;}
  $check('Unavailable scanner blocks before public storage',$blocked&&!is_file($destination));
  file_put_contents($settings,"UPLOAD_SCANNER=defender\nUPLOAD_DEFENDER_BINARY=\"C:/Windows/System32/cmd.exe\"\n");App\Core\Env::load($settings);
  $blocked=false;try{App\Services\UploadScanner::assertClean($imageFile);}catch(RuntimeException){$blocked=true;}$check('Arbitrary executable rejected',$blocked);
  if(isset($args['defender-binary'])){
   $binary=$args['defender-binary'];if(str_contains($binary,"\n")||str_contains($binary,'"'))throw new RuntimeException('Invalid scanner path');
   file_put_contents($settings,'UPLOAD_SCANNER=defender'."\nUPLOAD_DEFENDER_BINARY=\"".$binary."\"\n");App\Core\Env::load($settings);
   $start=hrtime(true);App\Services\UploadScanner::assertClean($imageFile);$check('Real clean-file Defender scan passed',true);$scanSeconds=(hrtime(true)-$start)/1e9;
   $blocked=false;try{App\Services\UploadScanner::assertClean($scratch.'/nonexistent-image');}catch(RuntimeException){$blocked=true;}$check('Missing scan input rejected',$blocked);
  }
 }finally{foreach([$imageFile,$settings,$destination] as $file)if(is_file($file))unlink($file);}
 $result=['checks'=>$checks,'simulated_exit_results_only'=>true,'real_clean_scan_seconds'=>$scanSeconds??null,'timeout_cleanup_seconds'=>round($elapsed,3),'actual_malware_detection_tested'=>false];
 if(isset($args['output']))file_put_contents($args['output'],json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
 echo json_encode($result,JSON_THROW_ON_ERROR),PHP_EOL;exit(count(array_filter($checks,fn($c)=>$c['status']==='FAIL'))?1:0);
}catch(Throwable $e){fwrite(STDERR,'Upload scan tests stopped: '.get_class($e).' at line '.$e->getLine().'; private details suppressed.'.PHP_EOL);exit(1);}
