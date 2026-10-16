<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// One loopback-only SMTP peer for synthetic TLS tests. Never stores message bodies or authentication.
$config=json_decode((string)fgets(STDIN),true,8,JSON_THROW_ON_ERROR);
if(!in_array($config['mode']??'', ['tls','plain'],true))exit(1);
$context=stream_context_create(['ssl'=>['local_cert'=>$config['cert'],'local_pk'=>$config['key'],'verify_peer'=>false]]);
$server=stream_socket_server('tcp://127.0.0.1:0',$errno,$error,STREAM_SERVER_BIND|STREAM_SERVER_LISTEN,$context);
if(!$server)exit(1);
echo stream_socket_get_name($server,false)."\n";flush();
$client=@stream_socket_accept($server,15);
if(!$client)exit(1);
stream_set_timeout($client,5);
fwrite($client,"220 localhost synthetic SMTP\r\n");
$auth=0;$data=false;
while(($line=fgets($client,2048))!==false){
 $command=rtrim($line,"\r\n");
 if($data){if($command==='.'){fwrite($client,"250 accepted\r\n");$data=false;}continue;}
 if($auth){$auth--;fwrite($client,$auth?"334 UGFzc3dvcmQ6\r\n":"235 authenticated\r\n");continue;}
 if(str_starts_with($command,'EHLO')){fwrite($client,$config['mode']==='tls'?"250-localhost\r\n250-STARTTLS\r\n250 AUTH LOGIN\r\n":"250 localhost\r\n");}
 elseif($command==='STARTTLS'){
  if($config['mode']==='plain'){fwrite($client,"454 TLS required but unavailable\r\n");break;}
  fwrite($client,"220 begin TLS\r\n");
  if(!@stream_socket_enable_crypto($client,true,STREAM_CRYPTO_METHOD_TLS_SERVER))break;
 }
 elseif($command==='AUTH LOGIN'){$auth=2;fwrite($client,"334 VXNlcm5hbWU6\r\n");}
 elseif(str_starts_with($command,'MAIL FROM:')||str_starts_with($command,'RCPT TO:'))fwrite($client,"250 OK\r\n");
 elseif($command==='DATA'){$data=true;fwrite($client,"354 end with dot\r\n");}
 elseif($command==='QUIT'){fwrite($client,"221 bye\r\n");break;}
 else fwrite($client,"500 unsupported\r\n");
}
fclose($client);fclose($server);
