<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/scripts/assign_demo_images.php';
$_SERVER['SCRIPT_NAME']='/index.php'; $_SERVER['REQUEST_URI']='/recommendations';
$_SESSION=['auth_user'=>['id'=>1,'role'=>'customer','username'=>'synthetic'],'_csrf'=>str_repeat('a',64)];
$checks=[];
function checkPresentation(string $name,bool $ok):void { global $checks; $checks[]=['test'=>$name,'status'=>$ok?'PASS':'FAIL']; }
function renderRecommendations(array $result):string { $error=null; ob_start(); require dirname(__DIR__).'/app/Views/customer/recommendations.php'; return ob_get_clean(); }
$profile=['hair_texture'=>'curly','scalp_type'=>'dry_scalp','hair_condition'=>'dry','treatment_history'=>'heat_styled','concern_text'=>'PRIVATE TEXT NEVER ECHOED',
    'tags'=>[['type'=>'concern','source'=>'assessment','code'=>'tangling','display_name'=>'Tangling']]];
$product=['id'=>1,'sku'=>'AVE-SMP-001','name'=>'A long synthetic product name <script>unsafe</script>','price'=>123.45,'category_name'=>'Shampoo','stock_qty'=>2,'available'=>true,'image_path'=>'assets/images/products/catalog-20261030/does-not-exist.webp'];
$reason=['code'=>'scalp.dry_scalp'];$match=$product+['role'=>'cleanse','reasons'=>[$reason]];
$result=['profile'=>$profile,'analysis'=>['tags'=>[],'warnings'=>[]],'ranked'=>[$match], 'routine'=>['items'=>[['product'=>$product]],'warnings'=>[]],'fingerprint'=>str_repeat('f',64)];
$html=renderRecommendations($result);
checkPresentation('Editorial introduction has one page heading',substr_count($html,'<h1>')===1&&str_contains($html,'A little clarity.'));
checkPresentation('All three reading sections and old direct links remain visible',str_contains($html,'id="assessment-insights"')&&str_contains($html,'id="product-matches-title"')&&str_contains($html,'id="routine-preview-title"')&&!str_contains($html,'data-result-panel'));
checkPresentation('Current routine needs no extra save and shortcuts use shared reading navigation',!str_contains($html,'plan_fingerprint')&&str_contains($html,'help-navigation recommendation-shortcuts')&&str_contains($html,'data-reading-page'));
checkPresentation('Profile facts appear once',substr_count($html,'Natural texture</small>')===1&&substr_count($html,'<dt>Scalp</dt>')===1&&!str_contains($html,'match-profile-glance'));
checkPresentation('Inferred text and user-controlled names stay private or escaped',!str_contains($html,$profile['concern_text'])&&!str_contains($html,'<script>unsafe</script>')&&str_contains($html,'&lt;script&gt;unsafe&lt;/script&gt;'));
checkPresentation('Full evidence remains available in disclosures',str_contains($html,'Why this matches')&&str_contains($html,'Listed for a dry-feeling scalp.')&&str_contains($html,'not independently verified'));
checkPresentation('No large About this guidance block',!str_contains($html,'About this guidance'));
foreach(['curly','straight','unsure'] as $texture){
    $case=$result; $case['profile']['hair_texture']=$texture;
    if($texture==='unsure'){$case['profile']['tags']=[];$case['profile']['treatment_history']='';$case['ranked']=[];$case['routine']['items']=[];}
    $output=renderRecommendations($case);$guide=App\Services\RoutineGuide::forProfile($case['profile'],$case['routine']['items']);
    preg_match_all('/data-care-step="([^"]+)"/',$output,$seen);
    checkPresentation($texture.' preserves every ordered step in both modes',$seen[1]===array_merge(array_column($guide['wash'],'key'),array_column($guide['between'],'key')));
    foreach(array_merge($guide['wash'],$guide['between']) as $step)checkPresentation($texture.' retains full instruction: '.$step['key'],str_contains($output,e($step['instruction'])));
    checkPresentation($texture.' has both timing views',str_contains($output,'data-mode-panel="wash"')&&str_contains($output,'data-mode-panel="between"'));
    if($texture==='unsure')checkPresentation('No match state retains care without inventing a product or save action',str_contains($output,'No supported matches yet.')&&str_contains($output,'No supported product match for this step.')&&!str_contains($output,'class="recommendation-actions"'));
}
$unavailable=$result;$unavailable['ranked'][0]['available']=false;$unavailable['ranked'][0]['stock_qty']=0;
$unavailable['routine']['items'][0]['product']['available']=false;
$html=renderRecommendations($unavailable);
checkPresentation('Unavailable state is explicit and has no purchase form',str_contains($html,'Currently unavailable')&&!str_contains($html,'action="/cart/add"'));
$missing=$result;$missing['ranked'][0]['image_path']=null;$missing['routine']['items'][0]['product']['image_path']=null;
$html=renderRecommendations($missing);
checkPresentation('Missing photographs use honest existing placeholders',!str_contains($html,'does-not-exist.webp')&&str_contains($html,'product-placeholder'));
$many=$result;$second=$match;$second['id']=2;$second['role']='condition';$many['ranked'][]=$second;
$html=renderRecommendations($many);$dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
checkPresentation('Curated products stay on this page; alternatives go to editable Shop filters',$xpath->query('//article[@data-stream-key="product-1"]')->length===1&&$xpath->query('//article[@data-stream-key="product-2"]')->length===0&&str_contains($html,'/shop?concern%5B0%5D=tangling'));
checkPresentation('Every purchase and favorite keeps its own CSRF token',$xpath->query('//form[@action="/cart/add"]/input[@name="_token"]')->length===1&&$xpath->query('//form[@action="/favorites/toggle"]/input[@name="_token"]')->length===1);
$conflict=$result;$conflict['analysis']=['tags'=>[['type'=>'scalp','code'=>'oily_scalp']],'warnings'=>['Analysis temporarily limited <unsafe>']];$html=renderRecommendations($conflict);
checkPresentation('Conflicting wording preserves selected-answer warning and escaped service warnings',str_contains($html,'We kept your selected answer')&&str_contains($html,'Analysis temporarily limited &lt;unsafe&gt;'));
$before=$result;renderRecommendations($result);checkPresentation('Rendering never mutates its input or fingerprint',$before===$result);
$empty=['profile'=>null,'ranked'=>[],'routine'=>['items'=>[],'warnings'=>[]],'fingerprint'=>''];
checkPresentation('No profile offers assessment without fabricated results',str_contains(renderRecommendations($empty),'Create hair profile')&&!str_contains(renderRecommendations($empty),'data-result-panel'));
$privacyEmail=null;ob_start();require dirname(__DIR__).'/app/Views/public/help.php';$help=ob_get_clean();
checkPresentation('Help contains full terms and refunds inline',str_contains($help,'Your account')&&str_contains($help,'Payment and fulfillment')&&str_contains($help,'Before returning an item')&&str_contains($help,'Refunds and cancellations')&&!str_contains($help,'Read the terms of service'));
checkPresentation('Help has one page heading and unique IDs',substr_count($help,'<h1>')===1&&preg_match_all('/\bid="([^"]+)"/',$help,$ids)===count(array_unique($ids[1])));
checkPresentation('Help exposes reading sections and active-topic navigation hook',str_contains($help,'data-reading-page')&&substr_count($help,'class="help-section"')===6);
$failed=array_filter($checks,static fn($c)=>$c['status']==='FAIL');foreach($failed as $failure)echo 'FAIL: '.$failure['test'].PHP_EOL;
echo json_encode(['pass'=>count($checks)-count($failed),'fail'=>count($failed),'checks'=>$checks],JSON_PRETTY_PRINT).PHP_EOL;exit($failed?1:0);
