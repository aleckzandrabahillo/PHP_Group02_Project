<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,Database,Response};
use App\Middleware\AuthGuard;
use App\Services\{CustomerAddress,AddressBook};
final class AddressController
{
 public function save():void
 {
  $user=AuthGuard::roles(['customer']);Csrf::enforce(is_string($_POST['_token']??null)?$_POST['_token']:null);header('Cache-Control: no-store, private');
  $action=$_POST['action']??'save';$id=filter_var($_POST['address_id']??-1,FILTER_VALIDATE_INT);$version=$_POST['revision']??null;
  if(!is_string($action)||$id===false||$id< -1||!is_string($version)||($version!==''&&!preg_match('/^[a-f0-9]{32}$/D',$version)))Response::json(['ok'=>false,'errors'=>['form'=>'Reload the address form.']],422);
  if($action==='save'){$checked=CustomerAddress::validate($_POST);if($checked['errors'])Response::json(['ok'=>false,'errors'=>$checked['errors'],'message'=>reset($checked['errors'])],422);}
  try {
   $pdo=Database::connection();$addresses=(new AddressBook())->mutate($pdo,(int)$user['id'],$action,$id,$version,$_POST);
   ob_start();require dirname(__DIR__).'/Views/partials/address_cards.php';$html=ob_get_clean();
   Response::json(['ok'=>true,'message'=>'Your delivery addresses are updated.','html'=>$html]);
  }catch(\InvalidArgumentException $e){Response::json(['ok'=>false,'errors'=>['form'=>$e->getMessage()]],409);}
  catch(\Throwable){Response::json(['ok'=>false,'errors'=>['form'=>'Your addresses could not be updated. Please try again.']],503);}
 }
}
