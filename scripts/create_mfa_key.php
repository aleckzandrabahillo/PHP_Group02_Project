<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors','0');
try {
    if (count($argv)!==2 || !str_starts_with($argv[1],'--output=')) throw new DomainException('Use --output=PRIVATE_FILE outside the checkout.');
    $path=substr($argv[1],9);$parent=realpath(dirname($path));$root=realpath(dirname(__DIR__));
    $norm=static fn($s)=>strtolower(rtrim(str_replace('\\','/',$s),'/'));
    if(!$parent||$norm($parent)===$norm($root)||str_starts_with($norm($parent),$norm($root).'/'))throw new DomainException('Choose an existing private folder outside the checkout.');
    $file=@fopen($parent.DIRECTORY_SEPARATOR.basename($path),'x');
    if($file===false)throw new DomainException('The private output must not already exist.');
    $value='MFA_ENCRYPTION_KEY='.base64_encode(random_bytes(32)).PHP_EOL;
    if(fwrite($file,$value)!==strlen($value)){fclose($file);throw new RuntimeException();}
    fclose($file);@chmod($parent.DIRECTORY_SEPARATOR.basename($path),0600);
    echo 'Private MFA key file created. Keep it confidential and backed up; no key was printed.',PHP_EOL;
} catch(DomainException $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
catch(Throwable){fwrite(STDERR,"Private key creation failed. No key was printed.\n");exit(1);}
