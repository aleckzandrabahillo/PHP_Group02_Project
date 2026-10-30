<?php
declare(strict_types=1);
namespace App\Services;

use RuntimeException;

/** Selected PII only. Separate versioned keyring; never reuse the authenticator key. */
final class PiiCipher
{
    public const FIELDS = [
        'customer_profiles'=>['contact_no'=>25,'delivery_address'=>500],
        'orders'=>['contact_no_snapshot'=>25,'delivery_address_snapshot'=>500],
    ];

    private const EXTRA_FIELDS = ['customer_address_book'=>['payload'=>2400]];

    public function __construct(#[\SensitiveParameter] private readonly array $keys, private readonly string $active)
    {
        if (!extension_loaded('openssl') || !$keys || count($keys)>8 || !isset($keys[$active])) self::fail();
        foreach ($keys as $id=>$key) if (!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,24}$/D',$id) || !is_string($key) || strlen($key)!==32) self::fail();
    }

    public static function configured(): self
    {
        $raw=(string)env('PII_KEYRING_FILE','');
        if($raw===''||strlen($raw)>2048||preg_match('/[\x00-\x1f]/',$raw))self::fail();
        $file=realpath($raw);$root=realpath(dirname(__DIR__,2));
        $norm=static fn(string $v): string=>strtolower(str_replace('\\','/',$v));
        if (!$file || is_link($raw) || !$root || str_starts_with($norm($file),$norm($root).'/') || !is_file($file) || filesize($file)>4096) self::fail();
        // Deployment must additionally verify the service/owner-only ACL on this external file and parent.
        if (PHP_OS_FAMILY!=='Windows' && ((fileperms($file)&0077)!==0)) self::fail();
        try {$data=json_decode((string)file_get_contents($file),true,8,JSON_THROW_ON_ERROR);}catch(\Throwable){self::fail();}
        if (!is_array($data) || array_keys($data)!==['active','keys'] || !is_string($data['active']) || !is_array($data['keys'])) self::fail();
        $keys=[];foreach($data['keys'] as $id=>$encoded){$key=is_string($encoded)?base64_decode($encoded,true):false;if($key===false || base64_encode($key)!==$encoded)self::fail();$keys[$id]=$key;}
        return new self($keys,$data['active']);
    }

    public function encrypt(string $table,string $field,int $id, #[\SensitiveParameter] ?string $text): string
    {
        $context=$this->context($table,$field,$id,$this->active);
        if ($text!==null) $this->validateText($table,$field,$text);
        $payload=$text===null?'N':'S'.$text;$nonce=random_bytes(12);$tag='';
        $cipher=openssl_encrypt($payload,'aes-256-gcm',$this->keys[$this->active],OPENSSL_RAW_DATA,$nonce,$tag,$context,16);
        if ($cipher===false || strlen($tag)!==16) self::fail();
        return 'v1:'.$this->active.':'.base64_encode($nonce.$tag.$cipher);
    }

    public function decrypt(string $table,string $field,int $id, #[\SensitiveParameter] ?string $envelope): ?string
    {
        $this->context($table,$field,$id,$this->active);
        if ($envelope===null || strlen($envelope)>($table==='customer_address_book'?16000:2800) || !preg_match('/^v1:([a-zA-Z0-9_-]{1,24}):([A-Za-z0-9+\/=]+)$/D',$envelope,$parts) || !isset($this->keys[$parts[1]])) self::fail();
        $bytes=base64_decode($parts[2],true);
        if ($bytes===false || base64_encode($bytes)!==$parts[2] || strlen($bytes)<28) self::fail();
        $text=openssl_decrypt(substr($bytes,28),'aes-256-gcm',$this->keys[$parts[1]],OPENSSL_RAW_DATA,substr($bytes,0,12),substr($bytes,12,16),$this->context($table,$field,$id,$parts[1]));
        if ($text==='N') return null;
        if ($text===false || !str_starts_with($text,'S')) self::fail();
        $text=substr($text,1);$this->validateText($table,$field,$text);return $text;
    }

    private function context(string $table,string $field,int $id,string $key): string
    {
        if (!isset((self::FIELDS + self::EXTRA_FIELDS)[$table][$field]) || $id<1) self::fail();
        return 'avela:pii:v1:'.$key.':'.$table.':'.$field.':'.$id;
    }
    private function validateText(string $table,string $field, #[\SensitiveParameter] string $text): void
    {
        if (!mb_check_encoding($text,'UTF-8') || mb_strlen($text,'UTF-8')>(self::FIELDS + self::EXTRA_FIELDS)[$table][$field] || str_contains($text,"\0")) self::fail();
    }
    private static function fail(): never {throw new RuntimeException('Protected personal information is unavailable. Contact the account administrator.');}
}
