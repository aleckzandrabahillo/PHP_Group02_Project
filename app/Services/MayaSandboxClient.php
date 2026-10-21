<?php
declare(strict_types=1);
namespace App\Services;

use Closure;
use InvalidArgumentException;
use RuntimeException;
use SensitiveParameter;

/** Sandbox transport only. No card data, production endpoint, logging or order mutations. */
final class MayaSandboxClient
{
    private const HOST='https://pg-sandbox.paymaya.com';

    public function __construct(
        #[SensitiveParameter] private readonly string $publicKey,
        #[SensitiveParameter] private readonly string $secretKey,
        private readonly string $merchantOrigin,
        private readonly ?Closure $transport=null,
        bool $allowLocalhostReturn=false,
    ) {
        $url=parse_url($merchantOrigin);
        $localReturn=$allowLocalhostReturn && MayaSandboxConfig::localHosted() && $merchantOrigin==='http://localhost:18080';
        if($allowLocalhostReturn&&!$localReturn)throw new InvalidArgumentException('Loopback returns require the guarded local Sandbox.');
        if(!$localReturn&&(($url['scheme']??'')!=='https'||empty($url['host'])||isset($url['user'])||isset($url['pass'])||isset($url['query'])||isset($url['fragment'])||($url['path']??'')!==''||isset($url['port'])&&$url['port']!==443))throw new InvalidArgumentException('A verified HTTPS merchant origin is required.');
        foreach([$publicKey,$secretKey] as $i=>$key)if(strlen($key)<16||strlen($key)>256||!str_starts_with($key,$i===0?'pk-':'sk-')||preg_match('/[\x00-\x20\x7f:]/',$key))throw new InvalidArgumentException('Configure private sandbox credentials.');
    }

    public function createCheckout(int $cents,string $reference,array $redirects): array
    {
        if($cents<1||$cents>9999999999||!preg_match('/^[A-Za-z0-9_-]{1,36}$/D',$reference))throw new InvalidArgumentException('Invalid server payment quote.');
        if(array_keys($redirects)!==['success','failure','cancel'])throw new InvalidArgumentException('Provide the three merchant result URLs.');
        foreach($redirects as $url){if(!is_string($url)||strlen($url)>2048||!str_starts_with($url,$this->merchantOrigin.'/')||preg_match('/[\x00-\x20\x7f\\\\]/',$url)||str_contains($url,'#'))throw new InvalidArgumentException('Payment returns must stay on the merchant origin.');}
        $result=$this->request('POST','/checkout/v1/checkouts',$this->publicKey,
            ['totalAmount'=>['value'=>$cents/100,'currency'=>'PHP'],'requestReferenceNumber'=>$reference,'redirectUrl'=>$redirects]);
        $id=$result['checkoutId']??null;$url=$result['redirectUrl']??null;
        if(!self::validId($id)||!is_string($url))throw new RuntimeException('Payment provider returned an invalid checkout.');
        $parts=parse_url($url);
        if(($parts['scheme']??'')!=='https'||!in_array($parts['host']??'', ['payments-web-sandbox.paymaya.com','payments-web-sandbox.maya.ph'],true)||isset($parts['user'])||isset($parts['pass'])||isset($parts['fragment'])||strlen($url)>2048||preg_match('/[\x00-\x20\x7f\\\\]/',$url)||(isset($parts['port'])&&$parts['port']!==443))throw new RuntimeException('Payment provider returned an unexpected destination.');
        return ['checkout_id'=>$id,'redirect_url'=>$url];
    }

    /** A browser success redirect or submitted webhook body is never sufficient payment evidence. */
    public function verifiedPayment(string $paymentId,string $reference,int $expectedCents): array
    {
        if(!self::validId($paymentId)||$expectedCents<1)throw new InvalidArgumentException('Invalid payment reference.');
        $payment=$this->request('GET','/payments/v1/payments/'.$paymentId,$this->secretKey);
        return $this->validatePayment($payment,$paymentId,$reference,$expectedCents);
    }

    public function paymentByReference(string $reference,int $expectedCents): ?array
    {
        if(!preg_match('/^[A-Za-z0-9_-]{1,36}$/D',$reference))throw new InvalidArgumentException('Invalid payment reference.');
        $rows=$this->request('GET','/payments/v1/payment-rrns/'.$reference,$this->secretKey,null,true);
        if(count($rows)===0)return null;
        if(count($rows)!==1||!is_array($rows[0])||!self::validId($rows[0]['id']??null))throw new RuntimeException('Payment reference requires review.');
        return $this->validatePayment($rows[0],$rows[0]['id'],$reference,$expectedCents);
    }

    public static function validId(mixed $id): bool
    {
        return is_string($id)&&preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/Di',$id)===1;
    }

    private function validatePayment(array $payment,string $paymentId,string $reference,int $expectedCents): array
    {
        $amount=$payment['totalAmount']??$payment['amount']??null;
        if(!is_array($amount))$amount=['value'=>$amount,'currency'=>$payment['currency']??null];
        if(!is_array($amount)||($amount['currency']??null)!=='PHP'||($payment['requestReferenceNumber']??null)!==$reference||($payment['id']??null)!==$paymentId)throw new RuntimeException('Payment does not match the order.');
        $value=$amount['value']??null;
        if((!is_string($value)&&!is_int($value)&&!is_float($value))||!preg_match('/^([0-9]{1,8})(?:\.([0-9]{1,2}))?$/D',(string)$value,$m))throw new RuntimeException('Invalid payment amount.');
        $cents=(int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
        if($cents!==$expectedCents)throw new RuntimeException('Payment amount differs from the order.');
        $status=$payment['paymentStatus']??$payment['status']??null;
        if(in_array($status,['PAYMENT_SUCCESS','CAPTURED'],true)&&isset($payment['isPaid'])&&$payment['isPaid']!==true)throw new RuntimeException('Payment confirmation differs.');
        if(!is_string($status)||!in_array($status,['PAYMENT_SUCCESS','PAYMENT_FAILED','PAYMENT_EXPIRED','PAYMENT_CANCELLED','PENDING_PAYMENT','PENDING_TOKEN','FOR_AUTHENTICATION','AUTHENTICATING','AUTH_SUCCESS','PAYMENT_PROCESSING','AUTHORIZED','CAPTURED','VOIDED','REFUNDED','AUTH_FAILED','CAPTURE_HOLD_EXPIRED'],true))throw new RuntimeException('Payment state requires review.');
        return ['payment_id'=>$paymentId,'status'=>$status,'paid'=>in_array($status,['PAYMENT_SUCCESS','CAPTURED'],true)];
    }

    private function request(string $method,string $path,#[SensitiveParameter] string $key,?array $body=null,bool $list=false): array
    {
        $encoded=$body===null?null:json_encode($body,JSON_THROW_ON_ERROR);
        $headers=['Accept: application/json','Content-Type: application/json','Authorization: Basic '.base64_encode($key.':')];
        if($this->transport){$response=($this->transport)($method,self::HOST.$path,$headers,$encoded);}
        else {
            if(!function_exists('curl_init'))throw new RuntimeException('Secure payment transport unavailable.');
            $curl=curl_init(self::HOST.$path);$bytes='';
            curl_setopt_array($curl,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk)use(&$bytes):int{if(strlen($bytes)+strlen($chunk)>65536)return 0;$bytes.=$chunk;return strlen($chunk);}]);
            if($encoded!==null)curl_setopt($curl,CURLOPT_POSTFIELDS,$encoded);
            $ok=curl_exec($curl);$code=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$type=curl_getinfo($curl,CURLINFO_CONTENT_TYPE);curl_close($curl);
            if($ok===false||!is_string($type)||!str_starts_with(strtolower($type),'application/json'))throw new RuntimeException('Payment service is unavailable.');
            $response=['status'=>$code,'body'=>$bytes];
        }
        if(!is_array($response)||!is_string($response['body']??null)||strlen($response['body'])>65536)throw new RuntimeException('Payment service rejected the request.');
        try{$result=json_decode($response['body'],true,32,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new RuntimeException('Payment service returned invalid data.');}
        // Only Maya's documented missing-payment code on an authenticated RRN lookup is absence.
        // Other 404s (including merchant configuration errors) remain verification failures.
        if($list&&$method==='GET'&&str_starts_with($path,'/payments/v1/payment-rrns/')&&($response['status']??0)===404&&is_array($result)&&($result['code']??null)==='PY0009')return [];
        if(!in_array($response['status']??0,[200,201],true))throw new MayaApiException((int)($response['status']??0),is_array($result)?($result['code']??null):null);
        if(!is_array($result)||($list?!array_is_list($result):array_is_list($result)))throw new RuntimeException('Payment service returned invalid data.');
        return $result;
    }
}
