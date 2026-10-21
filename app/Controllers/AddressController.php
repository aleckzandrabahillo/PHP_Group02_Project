<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,Database,Response};
use App\Middleware\AuthGuard;
use App\Services\CustomerAddress;
final class AddressController
{
 public function save():void{
  $user=AuthGuard::roles(['customer']);Csrf::enforce(is_string($_POST['_token']??null)?$_POST['_token']:null);header('Cache-Control: no-store, private');
  $checked=CustomerAddress::validate($_POST);if($checked['errors'])Response::json(['ok'=>false,'errors'=>$checked['errors'],'message'=>reset($checked['errors'])],422);
  $version=$_POST['revision']??null;if(!is_string($version)||($version!==''&&!preg_match('/^[a-f0-9]{32}$/D',$version)))Response::json(['ok'=>false,'errors'=>['form'=>'Reload the address form.']],422);
  try{$saved=(new CustomerAddress())->save(Database::connection(),(int)$user['id'],$_POST,$version);Response::json(['ok'=>true,'message'=>'Delivery address saved.','address'=>$saved['delivery_address'],'revision'=>$saved['revision']]);}
  catch(\InvalidArgumentException $e){Response::json(['ok'=>false,'errors'=>['form'=>$e->getMessage()]],409);}
  catch(\Throwable){Response::json(['ok'=>false,'errors'=>['form'=>'Your address could not be saved. Please try again.']],503);}
 }
}
