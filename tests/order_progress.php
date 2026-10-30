<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/Services/OrderProgress.php';require dirname(__DIR__).'/app/Services/OrderReference.php';
$checks=[];$check=static function($test,$ok)use(&$checks){$checks[]=['test'=>$test,'status'=>$ok?'PASS':'FAIL'];};
$base=['id'=>16,'status'=>'pending','created_at'=>'2026-10-29 10:00:00','events'=>[]];
$j=App\Services\OrderProgress::journey($base);$check('Order creation confirms only the first delivery stage',$j['stages'][0]['state']==='current'&&$j['stages'][1]['state']==='upcoming');
$paid=$base;$paid['maya_payment']=['state'=>'paid'];$check('Payment does not invent shipment',App\Services\OrderProgress::journey($paid)['stages'][1]['state']==='upcoming');
$old=$base;$old['status']='delivered';$old['events']=[['to_status'=>'shipped','created_at'=>'2026-10-29 11:00:00']];$j=App\Services\OrderProgress::journey($old);$check('Legacy delivery does not invent an out-for-delivery scan',$j['stages'][2]['state']==='unrecorded'&&$j['stages'][3]['state']==='current');
foreach(['cancelled','failed'] as $state){$halt=$base;if($state==='cancelled')$halt['status']='cancelled';else $halt['maya_payment']=['state'=>'failed'];$j=App\Services\OrderProgress::journey($halt);$check($state.' never appears as a completed delivery journey',$j['halted']&&count(array_filter($j['stages'],fn($s)=>in_array($s['state'],['complete','current'])))===0);}
$refs=[];foreach([1,16,100000,999999,1000000,PHP_INT_MAX] as $id){$ref=App\Services\OrderReference::display(['id'=>$id]);$refs[]=$ref;$check('Display reference round-trips without truncation: '.$id,App\Services\OrderReference::displayId($ref)===$id);}$check('Display references remain unique',count($refs)===count(array_unique($refs)));
$check('Malformed display reference rejected',App\Services\OrderReference::displayId('AVE-1x00001')===null&&App\Services\OrderReference::displayId('AVE-000000')===null);
$bad=array_filter($checks,fn($c)=>$c['status']==='FAIL');echo json_encode(['checks'=>$checks,'pass'=>count($checks)-count($bad),'fail'=>count($bad)]);exit($bad?1:0);
