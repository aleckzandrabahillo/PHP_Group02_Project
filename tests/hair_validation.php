<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
require $root . '/app/Core/Env.php';
require $root . '/app/Support/helpers.php';
spl_autoload_register(static function ($class) use ($root) {
    if (str_starts_with($class, 'App\\')) require $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});
$count = 0;
function check(string $label, bool $ok): void {
    global $count;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $count++;
}
$valid = ['hair_texture' => 'wavy', 'scalp_type' => 'oily_scalp', 'hair_condition' => 'dry',
    'concerns' => ['frizz', 'tangling'], 'treatments' => ['colored'], 'concern_text' => 'Dry ends but oily roots.'];
$validator = App\Core\HairProfileValidator::class;
check('Valid combined profile', !$validator::validate($valid)['errors']);
foreach (['hair_texture','scalp_type','hair_condition'] as $field) {
    foreach (['', 'invented', [], str_repeat('a', 100)] as $bad) {
        check('Reject invalid ' . $field, isset($validator::validate(array_replace($valid, [$field => $bad]))['errors'][$field]));
    }
}
foreach (['concerns','treatments'] as $field) {
    foreach (['frizz', ['unknown'], [['nested']], array_fill(0, 20, 'frizz')] as $bad) {
        check('Reject malformed ' . $field, isset($validator::validate(array_replace($valid, [$field => $bad]))['errors'][$field]));
    }
}
foreach ([str_repeat('a',501), str_repeat('😀',501), "\0dry", "\xff", []] as $bad) {
    check('Reject text bounds or malformed input', isset($validator::validate(array_replace($valid, ['concern_text'=>$bad]))['errors']['concern_text']));
}
check('Unicode boundary accepted', !$validator::validate(array_replace($valid, ['concern_text'=>str_repeat('é',500)]))['errors']);
check('Optional empty text accepted', !$validator::validate(array_replace($valid, ['concern_text'=>'']))['errors']);
check('Markup retained for output encoding', $validator::validate(array_replace($valid,['concern_text'=>'<script>alert(1)</script>']))['values']['concern_text']==='<script>alert(1)</script>');
check('Markup encoded', e('<script>')==='&lt;script&gt;');
$tags=$validator::assessmentTags($valid);
check('Independent scalp and lengths', in_array(['type'=>'scalp','code'=>'oily_scalp'],$tags,true)&&in_array(['type'=>'concern','code'=>'dryness'],$tags,true));
$nlp=new App\Services\ConcernInterpreter();
check('Empty text needs no Python', $nlp->interpret('')['status']==='empty');
$result=$nlp->interpret($valid['concern_text']);
check('PHP invokes functional Python', in_array(['type'=>'scalp','code'=>'oily_scalp'],$result['tags'],true));
check('Negated concern absent', $nlp->interpret('Hindi naman dry yung hair ko.')['tags']===[]);
check('Unknown safely falls back', $nlp->interpret('I like music.')['status']==='unknown');
$old=getenv('NLP_PYTHON_BINARY');
putenv('NLP_PYTHON_BINARY=not-an-installed-python-executable');
$failed=false;try{$nlp->interpret('dry ends');}catch(Throwable){$failed=true;}
check('Unavailable Python fails safely', $failed);
putenv('NLP_PYTHON_BINARY='.($old===false?'':$old));
echo $count . " PASS, 0 FAIL\n";
