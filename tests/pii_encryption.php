<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/app/Services/PiiCipher.php';
use App\Services\PiiCipher;
$checks=[];
$check=static function(string $name,bool $ok)use(&$checks):void{$checks[]=['test'=>$name,'status'=>$ok?'PASS':'FAIL'];};
$denied=static function(callable $call):bool{try{$call();return false;}catch(RuntimeException){return true;}};
$old=random_bytes(32);$new=random_bytes(32);$cipher=new PiiCipher(['old'=>$old],'old');
foreach([null,'','Plain address','Café 🧴 — 日本語',str_repeat('漢',500)] as $value){
 $encoded=$cipher->encrypt('customer_profiles','delivery_address',4,$value);
 $check('UTF-8/null/empty/boundary round trip',$cipher->decrypt('customer_profiles','delivery_address',4,$encoded)===$value);
 $check('Random nonce for each encryption',$encoded!==$cipher->encrypt('customer_profiles','delivery_address',4,$value));
}
$sample=$cipher->encrypt('customer_profiles','contact_no',4,'+639123456789');
foreach([
 fn()=> $cipher->decrypt('customer_profiles','contact_no',5,$sample),
 fn()=> $cipher->decrypt('customer_profiles','delivery_address',4,$sample),
 fn()=> $cipher->decrypt('orders','contact_no_snapshot',4,$sample),
 fn()=> (new PiiCipher(['old'=>$new],'old'))->decrypt('customer_profiles','contact_no',4,$sample),
 fn()=> $cipher->decrypt('customer_profiles','contact_no',4,null),
 fn()=> $cipher->decrypt('customer_profiles','contact_no',4,'v2:old:AAAA'),
 fn()=> $cipher->decrypt('customer_profiles','contact_no',4,'v1:missing:AAAA'),
 fn()=> $cipher->decrypt('customer_profiles','contact_no',4,'v1:old:AAAA'),
 fn()=> $cipher->decrypt('customer_profiles','contact_no',4,str_repeat('x',2801)),
 fn()=> $cipher->encrypt('customer_profiles','delivery_address',4,str_repeat('x',501)),
 fn()=> $cipher->encrypt('customer_profiles','delivery_address',4,"bad\xff"),
 fn()=> $cipher->encrypt('users','email',4,'private'),
 fn()=> $cipher->encrypt('customer_profiles','contact_no',0,'private'),
 fn()=> new PiiCipher(['old'=>'short'],'old'),
] as $attack)$check('Reject wrong context/key/malformed or oversized input',$denied($attack));
$payload=base64_decode(explode(':',$sample,3)[2],true);
foreach([0,12,28] as $offset){$mutated=$payload;$mutated[$offset]=chr(ord($mutated[$offset])^1);$value='v1:old:'.base64_encode($mutated);$check('Nonce/tag/ciphertext tamper rejected',$denied(fn()=>$cipher->decrypt('customer_profiles','contact_no',4,$value)));}
$rotated=new PiiCipher(['old'=>$old,'new'=>$new],'new');
$check('Rotation reads retained old key',$rotated->decrypt('customer_profiles','contact_no',4,$sample)==='+639123456789');
$newSample=$rotated->encrypt('customer_profiles','contact_no',4,'+639123456789');
$check('Rotation writes active key',str_starts_with($newSample,'v1:new:')&&$rotated->decrypt('customer_profiles','contact_no',4,$newSample)==='+639123456789');
$check('Losing retired key fails closed',$denied(fn()=>(new PiiCipher(['new'=>$new],'new'))->decrypt('customer_profiles','contact_no',4,$sample)));
echo json_encode(['checks'=>$checks],JSON_THROW_ON_ERROR),PHP_EOL;
exit(count(array_filter($checks,fn($c)=>$c['status']==='FAIL'))?1:0);
