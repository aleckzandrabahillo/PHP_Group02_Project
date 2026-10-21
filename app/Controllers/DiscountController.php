<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,Database,Response,Session,View};
use App\Middleware\AuthGuard;
use App\Services\{DiscountMigration,DiscountService};
use InvalidArgumentException;

final class DiscountController
{
    public function page(array $values=[],array $errors=[],int $editId=0): void
    {
        AuthGuard::roles(['admin','catalog_manager']);header('Cache-Control: no-store, private');$pdo=Database::connection();$service=new DiscountService();
        if(!DiscountMigration::installed($pdo)){http_response_code(503);View::render('errors/500',['pageTitle'=>'Discounts unavailable','message'=>'Discount management is temporarily unavailable.'],'plain');return;}
        try{$list=$service->listing($pdo,$_GET);}catch(InvalidArgumentException $e){$errors['filters']=$e->getMessage();$list=$service->listing($pdo,[]);}
        if(!$editId)$editId=$this->id($_GET['edit']??'0');$record=$editId?$service->find($pdo,$editId):null;
        if($editId&&!$record){http_response_code(404);View::render('errors/404',['pageTitle'=>'Discount not found'],'plain');return;}
        if(!$values&&$record)$values=[...$record,'value'=>\App\Services\ShippingService::decimal((int)$record['value_units']),'minimum'=>\App\Services\ShippingService::decimal((int)$record['minimum_cents'])];
        View::render('catalog/discounts',['pageTitle'=>'Discounts','list'=>$list,'values'=>$values,'errors'=>$errors,'editing'=>$record,'editId'=>$editId]);
    }

    public function manage(): void
    {
        $actor=AuthGuard::roles(['admin','catalog_manager']);Csrf::enforce(is_string($_POST['_token']??null)?$_POST['_token']:null);
        $service=new DiscountService();$action=$_POST['action']??null;$id=$this->id($_POST['id']??'0');$revision=$_POST['revision']??'';
        try{
            if(!is_string($action)||!is_string($_POST['id']??null)||!preg_match('/^[0-9]{1,17}$/D',$_POST['id'])||!is_string($revision)||($id>0&&!preg_match('/^[a-f0-9]{32}$/D',$revision)))throw new InvalidArgumentException('Refresh the discount form before continuing.');
            if($action==='save'){
                $checked=DiscountService::validate($_POST);if($checked['errors']){http_response_code(422);$this->page($checked['values'],$checked['errors'],$id);return;}
                $service->save(Database::connection(),(int)$actor['id'],$id,$_POST,$revision);Session::flash('success',$id?'Discount updated.':'Discount created.');
            }else{
                if(!$id)throw new InvalidArgumentException('Choose a valid discount.');$service->action(Database::connection(),(int)$actor['id'],$id,$action,$revision);Session::flash('success',$action==='remove'?'Unused discount removed, or used discount archived.':'Discount status updated.');
            }
            http_response_code(303);Response::redirect('/catalog/discounts');
        }catch(InvalidArgumentException $e){http_response_code(422);$this->page(is_array($_POST)?$_POST:[],['form'=>$e->getMessage()],$id);}
        catch(\Throwable){http_response_code(503);$this->page([],['form'=>'The discount could not be saved. Please try again.'],$id);}
    }
    private function id(mixed $value): int{return is_string($value)&&preg_match('/^[0-9]{1,17}$/D',$value)?(int)$value:0;}
}
