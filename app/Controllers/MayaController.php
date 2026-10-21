<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\AuthGuard;
use App\Models\Order;
use App\Services\MayaPaymentService;
use App\Services\MayaSandboxConfig;
use InvalidArgumentException;

final class MayaController
{
    public function __construct(private readonly ?MayaPaymentService $payments=null) {}

    /** A return URL or result parameter is never proof of payment. GET is read-only. */
    public function returned(): void
    {
        $user=AuthGuard::roles(['customer']);$id=$this->id($_GET['order']??null);
        if(!(new Order())->owned($id,(int)$user['id'])){http_response_code(404);return;}
        Response::redirect('/orders/view?id='.$id);
    }

    public function refresh(): void
    {
        $user=AuthGuard::roles(['customer']);Csrf::enforce(is_string($_POST['_token']??null)?$_POST['_token']:null);
        $id=$this->id($_POST['order_id']??null);
        try {
            $this->service()->refresh((int)$user['id'],$id);
            Session::flash('success','Payment status checked. Review the confirmed status below.');
        } catch(\Throwable){Session::flash('warning','Payment could not be confirmed yet. No successful payment was assumed. Please check again later.');}
        http_response_code(303);Response::redirect('/orders/view?id='.$id);
    }

    public function webhook(): void
    {
        header('Cache-Control: no-store');
        try {
            if(strtolower(trim(explode(';',(string)($_SERVER['CONTENT_TYPE']??''),2)[0]))!=='application/json')throw new InvalidArgumentException('Use JSON.',415);
            // Only socket peer and server-established TLS are trusted. Ignore forwarded headers.
            $peer=(string)($_SERVER['REMOTE_ADDR']??'');$tls=($_SERVER['HTTPS']??'')==='on';$port=(int)($_SERVER['SERVER_PORT']??0);
            if(!$tls||$port!==443||!in_array($peer,['13.229.160.234','3.1.199.75'],true))throw new InvalidArgumentException('Webhook source rejected.',403);
            $body=file_get_contents('php://input',false,null,0,16385);
            $this->service()->enqueue($body===false?'':$body,$peer,$tls,$port);
            Response::json(['received'=>true]);
        }catch(InvalidArgumentException $e){Response::json(['received'=>false],in_array($e->getCode(),[400,403,413,415],true)?$e->getCode():400);}
        catch(\Throwable){Response::json(['received'=>false],503);}
    }

    public function continuePayment(): void
    {
        $user=AuthGuard::roles(['customer']);Csrf::enforce(is_string($_POST['_token']??null)?$_POST['_token']:null);
        $id=$this->id($_POST['order_id']??null);
        try{$destination=$this->service()->start((int)$user['id'],$id);if($destination!==null){header('Location: '.$destination,true,303);exit;}}
        catch(\Throwable){}
        Session::flash('warning','Payment is awaiting confirmation or this checkout has expired. Check its status before starting again.');
        http_response_code(303);Response::redirect('/orders/view?id='.$id);
    }

    private function service(): MayaPaymentService
    {
        return $this->payments??new MayaPaymentService(Database::connection(),MayaSandboxConfig::client());
    }
    private function id(mixed $id): int
    {
        return is_string($id)&&preg_match('/^[1-9][0-9]{0,17}$/D',$id)?(int)$id:0;
    }
}
