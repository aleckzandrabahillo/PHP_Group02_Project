<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
spl_autoload_register(static function ($class) use ($root) {
    if (str_starts_with($class, 'App\\')) require $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});
$checks = 0;
function matchCheck(string $label, bool $ok): void {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
$catalog = json_decode(file_get_contents($root . '/database/demo_catalog.json'), true, 32, JSON_THROW_ON_ERROR);
$products = [];
foreach ($catalog['products'] as $index => $p) $products[] = [...$p, 'id' => $index + 1, 'category_name' => $p['category'],
    'category_status' => 'active', 'verified_tags' => $p['tags'], 'evidence' => ['status'=>'reviewed_demo'],
    'treatment_support' => [], 'accessory_purpose' => $p['sku']==='AVE-TOL-001'?'detangling':'other'];
$input = ['hair_texture'=>'curly','scalp_type'=>'oily_scalp','hair_condition'=>'dry','concerns'=>['frizz','tangling'],'treatments'=>[],'concern_text'=>''];
$profile = [...$input,'treatment_history'=>'','tags'=>App\Core\HairProfileValidator::assessmentTags($input)];
$matcher = new App\Services\ProductMatcher();
$ranked = $matcher->rank($profile, $products);
$skus = array_column($ranked,'sku');
matchCheck('Candidates exist', count($ranked)>0);
matchCheck('Scalp-incompatible shampoo excluded', !in_array('AVE-SMP-001',$skus,true));
matchCheck('Oily-scalp compatible shampoo retained', in_array('AVE-SMP-002',$skus,true));
matchCheck('Texture-incompatible shampoo excluded', !in_array('AVE-SMP-004',$skus,true));
matchCheck('Every candidate has reasons', count(array_filter($ranked,fn($p)=>!$p['reasons']))===0);
matchCheck('Scores equal reason points', count(array_filter($ranked,fn($p)=>$p['score']!==array_sum(array_column($p['reasons'],'points'))))===0);
matchCheck('Stable ordering', $ranked===$matcher->rank($profile,array_reverse($products)));
matchCheck('No marketing keyword matching', $matcher->rank($profile,[array_replace($products[0],['description'=>'frizz tangling moisture magic','verified_tags'=>[]])])===[]);
matchCheck('Pending evidence excluded', $matcher->rank($profile,[array_replace($products[1],['evidence'=>['status'=>'pending']])])===[]);
matchCheck('Inactive product excluded', $matcher->rank($profile,[array_replace($products[1],['status'=>'inactive'])])===[]);
matchCheck('Inactive category excluded', $matcher->rank($profile,[array_replace($products[1],['category_status'=>'inactive'])])===[]);
matchCheck('Category-step conflict excluded', $matcher->rank($profile,[array_replace($products[1],['routine_step'=>'cleanse'])])===[]);
$out = array_map(fn($p)=>array_replace($p,['stock_qty'=>0]),$products);
$zero = $matcher->rank($profile,$out);
matchCheck('Stock cannot change compatibility scores', array_column($zero,'score','sku')===array_column($ranked,'score','sku'));
matchCheck('Stock cannot change compatibility order', array_column($zero,'sku')===$skus);
matchCheck('Out of stock visible as unavailable', count(array_filter($zero,fn($p)=>$p['available']))===0);
matchCheck('Out of stock not in purchasable routine', $matcher->routine($profile,$zero)['items']===[]);
matchCheck('Missing products handled', $matcher->rank($profile,[])===[]);
$routine=$matcher->routine($profile,$ranked);
$roles=array_column(array_column($routine['items'],'product'),'role');
matchCheck('Routine has cleansing and conditioning', array_slice($roles,0,2)===['cleanse','condition']);
matchCheck('No redundant optional steps', count(array_intersect($roles,['treat','finish']))<=1);
matchCheck('Relevant detangling accessory', in_array('accessory',$roles,true));
matchCheck('At most four products', count($routine['items'])<=4);
$minimal=[...$profile,'tags'=>[['type'=>'texture','code'=>'curly'],['type'=>'scalp','code'=>'oily_scalp']]];
$minimalRoutine=$matcher->routine($minimal,$matcher->rank($minimal,$products));
matchCheck('No extras forced on minimal profile', count($minimalRoutine['items'])===2);
$unknown=[...$profile,'hair_texture'=>'unsure','scalp_type'=>'unsure','tags'=>[]];
matchCheck('Unknown profile gives honest empty result', $matcher->rank($unknown,$products)===[]);
$chem=[...$profile,'treatment_history'=>'colored'];
matchCheck('Unsupported chemical-treatment cleanser excluded', count(array_filter($matcher->rank($chem,$products),fn($p)=>$p['role']==='cleanse'))===0);
matchCheck('NLP treatment restrictions applied', count(array_filter($matcher->rank($profile,$products,['colored']),fn($p)=>$p['role']==='cleanse'))===0);
$changed=$products;
$changed[1]['verified_tags']=array_values(array_diff($changed[1]['verified_tags'],['concern:frizz']));
$changedRank=$matcher->rank($profile,$changed);
matchCheck('Reviewed tag removal predictably lowers score', array_column($changedRank,'score','sku')['AVE-CON-001']===array_column($ranked,'score','sku')['AVE-CON-001']-4);
matchCheck('Tag change can change first conditioner', array_values(array_filter($changedRank,fn($p)=>$p['role']==='condition'))[0]['sku']==='AVE-CON-003');
$damage=[...$profile,'tags'=>[...$profile['tags'],['type'=>'concern','code'=>'damage']]];
$damageRoles=array_column(array_column($matcher->routine($damage,$matcher->rank($damage,$products))['items'],'product'),'role');
matchCheck('Damage selects optional treatment instead of finish', in_array('treat',$damageRoles,true)&&!in_array('finish',$damageRoles,true));
$blocked=$products;
foreach($blocked as &$p)if($p['category_name']==='Shampoo')$p['stock_qty']=0;
unset($p);
matchCheck('Partial routine explains missing cleanser', count($matcher->routine($profile,$matcher->rank($profile,$blocked))['warnings'])===1);
echo $checks . " PASS, 0 FAIL\n";
