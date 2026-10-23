<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/Services/RoutineGuide.php';
use App\Services\RoutineGuide;
$checks=[];
function checkGuide(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'status'=>$ok?'PASS':'FAIL'];}
$profile=['hair_texture'=>'wavy','scalp_type'=>'oily_scalp','hair_condition'=>'dry','treatment_history'=>'colored',
 'tags'=>[['type'=>'concern','code'=>'dryness'],['type'=>'concern','code'=>'frizz'],['type'=>'concern','code'=>'tangling']],
 'concern_text'=>'PRIVATE DESCRIPTION MUST NOT APPEAR'];
$product=static fn($category,$sku)=>['product'=>['id'=>1,'sku'=>$sku,'category_name'=>$category,'price'=>'123.45','name'=>'Existing catalog product','available'=>true]];
$items=[$product('Conditioner','AVE-CON-001'),$product('Hair Tools','AVE-TOL-001')];$before=[$profile,$items];
$guide=RoutineGuide::forProfile($profile,$items);$steps=array_column($guide['wash'],null,'key');
checkGuide('Core sequence survives an incomplete catalog',array_column($guide['wash'],'key')===['cleanse','condition','detangle','dry']);
checkGuide('Missing cleansing product is not invented',$steps['cleanse']['product']===null);
checkGuide('Only the selected conditioner is attached',$steps['condition']['product']===$items[0]['product']);
checkGuide('Only the selected comb is attached',$steps['detangle']['product']===$items[1]['product']);
checkGuide('Oily roots guide wash timing rather than a rigid schedule',str_contains($guide['rhythm'],'Wash when they feel oily')&&!preg_match('/every day|\d times/',$guide['rhythm']));
checkGuide('Dry lengths receive distinct care',str_contains($steps['condition']['why'],'dryness')&&str_contains($steps['condition']['instruction'],'mid-lengths'));
checkGuide('Tangling changes the actual instructions',str_contains($steps['detangle']['instruction'],'ends')&&str_contains($steps['detangle']['why'],'Tangling'));
checkGuide('Frizz guidance concerns handling, not an invented cure',str_contains($steps['dry']['why'],'frizz')&&str_contains($steps['dry']['instruction'],'instead of rubbing'));
checkGuide('Between-wash care does not sell a second product set',count($guide['between'])===2&&count(array_filter($guide['between'],static fn($step)=>$step['product']!==null))===0);
checkGuide('Raw concern text never reaches guidance',!str_contains(json_encode($guide),$profile['concern_text']));
checkGuide('Guide does not mutate profile or selected products',$before===[$profile,$items]);
foreach(['curly','coily'] as $texture){$g=RoutineGuide::forProfile(array_replace($profile,['hair_texture'=>$texture]),$items);$s=array_column($g['wash'],null,'key');checkGuide($texture.' detangles before conditioner rinsing',str_contains($s['detangle']['when'],'conditioner')&&str_contains($s['detangle']['instruction'],'then rinse'));checkGuide($texture.' between-wash care avoids repeated dry brushing',str_contains($g['between'][1]['instruction'],'dry curls or coils'));}
$g=RoutineGuide::forProfile(array_replace($profile,['hair_texture'=>'straight']),$items);$keys=array_column($g['wash'],'key');
checkGuide('Straight hair drying precedes detangling',array_search('dry',$keys,true)<array_search('detangle',$keys,true));
checkGuide('Straight hair guidance waits before combing',str_contains(array_column($g['wash'],null,'key')['detangle']['instruction'],'dry a little'));
$g=RoutineGuide::forProfile(array_replace($profile,['scalp_type'=>'dry_scalp']),$items);
checkGuide('Dry-feeling scalp avoids hard scrubbing',str_contains($g['wash'][0]['instruction'],'gently')&&str_contains($g['wash'][0]['why'],'dry-feeling'));
$g=RoutineGuide::forProfile(array_replace($profile,['scalp_type'=>'unsure']),[]);
checkGuide('Unknown scalp gets no invented type or schedule',str_contains($g['wash'][0]['why'],'not confirmed'));
$g=RoutineGuide::forProfile(array_replace($profile,['scalp_type'=>'normal_scalp']),[]);
checkGuide('Balanced scalp stays distinct',str_contains($g['wash'][0]['why'],'balanced'));
$g=RoutineGuide::forProfile(array_replace($profile,['treatment_history'=>'heat_styled,colored']),[]);
checkGuide('Recorded heat styling adds a labelled precaution',count($g['between'])===3&&$g['between'][2]['key']==='heat');
checkGuide('Oil or serum is not represented as heat protection',str_contains($g['between'][2]['instruction'],'not automatically a heat protectant'));
$g=RoutineGuide::forProfile($profile,[$product('Treatment','AVE-TRT-001')]);$mask=array_column($g['wash'],null,'key')['treat'];
checkGuide('Known mask stays optional and label-led',$mask['optional']&&str_contains($mask['instruction'],'replaces your conditioner')&&str_contains($mask['when'],'Occasional'));
$g=RoutineGuide::forProfile($profile,[$product('Treatment','AVE-TRT-003')]);
checkGuide('Reviewed rinse-out cream gets correct directions',str_contains(array_column($g['wash'],null,'key')['treat']['instruction'],'then rinse'));
$g=RoutineGuide::forProfile($profile,[$product('Treatment','OTHER')]);
checkGuide('Unknown treatment never inherits a mask schedule',str_contains(array_column($g['wash'],null,'key')['treat']['instruction'],'not confirmed')&&!str_contains($g['rhythm'],'weekly'));
$g=RoutineGuide::forProfile($profile,[$product('Serum','AVE-SER-002')]);$keys=array_column($g['wash'],'key');
checkGuide('Reviewed damp leave-in goes before drying',array_search('finish',$keys,true)<array_search('dry',$keys,true));
$g=RoutineGuide::forProfile($profile,[$product('Serum','AVE-SER-003')]);$keys=array_column($g['wash'],'key');
checkGuide('Finishing serum goes after drying',array_search('finish',$keys,true)>array_search('dry',$keys,true));
$g=RoutineGuide::forProfile(['hair_texture'=>'unsure','scalp_type'=>'unsure','hair_condition'=>'unsure','tags'=>[]],[]);
checkGuide('Uncertain profile retains honest basic care',array_column($g['wash'],'key')===['cleanse','condition','dry']&&!array_filter($g['wash'],static fn($s)=>$s['product']));
$g=RoutineGuide::forProfile($profile,[$product('Hair Tools','AVE-TOL-003')]);
checkGuide('A wrap is not misrepresented as a detangling comb',array_column($g['wash'],null,'key')['detangle']['product']===null);
$failed=array_filter($checks,static fn($check)=>$check['status']==='FAIL');foreach($failed as $check)echo 'FAIL: '.$check['name'].PHP_EOL;
echo (count($checks)-count($failed)).' PASS, '.count($failed).' FAIL'.PHP_EOL;exit($failed?1:0);
