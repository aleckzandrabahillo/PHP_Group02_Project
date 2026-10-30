<?php

namespace App\Controllers;

use App\Core\View;
use App\Middleware\AuthGuard;
use App\Models\User;
use App\Services\DashboardService;

final class AdminController
{
    public function __construct(private readonly DashboardService $dashboard, private readonly User $users) {}

    public function dashboard(): void
    {
        $user = AuthGuard::roles(['admin']);
        header('Cache-Control: no-store, private');
        try{$days=\App\Services\DashboardAnalytics::days($_GET['days']??'30');}
        catch(\InvalidArgumentException){http_response_code(422);$days=30;\App\Core\Session::flash('warning','Choose a valid reporting period.');}
        $analytics=(new \App\Services\DashboardAnalytics(\App\Core\Database::connection()))->report($days,true);
        View::render('admin/dashboard', ['analytics'=>$analytics,'pageTitle'=>'Admin Dashboard', 'stats'=>$this->dashboard->admin(), 'profile'=>$this->users->profileFor((int)$user['id'])]);
    }

    public function page(string $view, string $title): void
    {
        $user = AuthGuard::roles(['admin']);
        View::render('admin/' . $view, ['pageTitle'=>$title, 'profile'=>$this->users->profileFor((int)$user['id'])]);
    }

    public function logs(string $kind): void
    {
        AuthGuard::roles(['admin']);
        header('Cache-Control: no-store, private');
        header('Referrer-Policy: same-origin');
        try {
            $data=(new \App\Services\AdminLogReader())->read($kind,$_GET);
        } catch(\InvalidArgumentException $e) {
            http_response_code(422);
            $data=['error'=>$e->getMessage()];
        } catch(\Throwable) {
            http_response_code(503);
            $data=['error'=>'Log records are temporarily unavailable. Please try again later.'];
        }
        $data+=['kind'=>$kind,'records'=>[],'events'=>[],'filters'=>array_fill_keys(['q','event','result','from','to','sort'],''),'total'=>0,'page'=>1,'pages'=>1];
        $data['pageTitle']=$kind==='auth'?'Authentication Logs':'Audit Logs';
        View::render('admin/'.($kind==='auth'?'auth_logs':'audit_logs'),$data);
    }


    public function customers(): void { $this->accountList(false); }
    public function staff(): void { $this->accountList(true); }

    private function accountList(bool $staff,array $extra=[]): void
    {
        AuthGuard::roles(['admin']);header('Cache-Control: no-store, private');
        try{$listing=(new \App\Services\AdminAccounts())->listing($staff,$_GET);$error=null;}
        catch(\InvalidArgumentException $e){http_response_code(422);$error=$e->getMessage();$listing=[];}
        catch(\Throwable){http_response_code(503);$error='Accounts are temporarily unavailable.';$listing=[];}
        $listing+=['items'=>[],'total'=>0,'page'=>1,'pages'=>1,'filters'=>['q'=>'','status'=>'','role'=>'']];
        View::render('admin/'.($staff?'staff':'users'),$extra+['pageTitle'=>$staff?'Staff & Roles':'Users','listing'=>$listing,'error'=>$error,'formValues'=>[],'formErrors'=>[],'passwordPolicy'=>\App\Services\SecurityPolicy::values()]);
    }

    public function staffCreate(): void
    {
        $actor=AuthGuard::roles(['admin']);header('Cache-Control: no-store, private');
        \App\Core\Csrf::enforce(is_string($_POST['_token']??null)?$_POST['_token']:null);
        $checked=\App\Core\StaffValidator::validate($_POST);$errors=$checked['errors'];
        if(!$errors){
            try{(new \App\Services\AdminAccounts())->create((int)$actor['id'],$_POST);}
            catch(\DomainException $e){$errors['account']=$e->getMessage();}
            catch(\Throwable){$errors['account']='The staff account was not created. Check availability and try again.';}
        }
        if($errors){http_response_code(422);$safe=$checked['values'];unset($safe['password']);$this->accountList(true,['formValues'=>$safe,'formErrors'=>$errors]);return;}
        \App\Core\Session::flash('success','Staff account created. Email verification and any enabled 2-step verification are required at sign-in.');
        \App\Core\Response::redirect('/admin/staff');
    }

    public function accountChange(string $kind): void
    {
        $actor=AuthGuard::roles(['admin']);header('Cache-Control: no-store, private');
        \App\Core\Csrf::enforce(is_string($_POST['_token']??null)?$_POST['_token']:null);
        try{
            $id=$this->postedId('id');
            $expected=$_POST['expected']??null;$next=$_POST[$kind]??null;
            if(!is_string($expected)||!is_string($next))throw new \DomainException('Choose a valid account update.');
            (new \App\Services\AdminAccounts())->change((int)$actor['id'],$id,$kind,$expected,$next);
            \App\Core\Session::flash('success','Account updated.');
        }catch(\DomainException $e){\App\Core\Session::flash('error',$e->getMessage());}
        catch(\Throwable){\App\Core\Session::flash('error','The account was not changed. Please try again.');}
        \App\Core\Response::redirect(($_POST['context']??null)==='staff'?'/admin/staff':'/admin/users');
    }

    public function accountUnlock(): void
    {
        $actor=AuthGuard::roles(['admin']);header('Cache-Control: no-store, private');
        \App\Core\Csrf::enforce(is_string($_POST['_token']??null)?$_POST['_token']:null);
        try{
            $id=$this->postedId('id');$expected=$_POST['expected_lock']??null;
            if(!is_string($expected)||!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$expected))throw new \DomainException('Refresh this account before unlocking it.');
            (new \App\Services\SecurityManagement())->unlock((int)$actor['id'],$id,$expected);
            \App\Core\Session::flash('success','Account unlocked.');
        }catch(\DomainException $e){\App\Core\Session::flash('error',$e->getMessage());}
        catch(\Throwable){\App\Core\Session::flash('error','The account was not unlocked. Please try again.');}
        \App\Core\Response::redirect(($_POST['context']??null)==='staff'?'/admin/staff':'/admin/users');
    }

    private function postedId(string $field): int
    {
        $raw=$_POST[$field]??null;$id=is_string($raw)?filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):false;
        if($id===false)throw new \DomainException('Choose a valid record.');
        return $id;
    }

    public function orderView(): void
    {
        AuthGuard::roles(['admin']);header('Cache-Control: no-store, private');
        $raw=$_GET['id']??null;$id=is_string($raw)?filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):false;
        $order=$id?(new \App\Models\Order())->forAdministrator($id):null;
        if(!$order){http_response_code(404);View::render('errors/404',['pageTitle'=>'Order not found']);return;}
        View::render('admin/order',['pageTitle'=>\App\Services\OrderReference::display($order),'order'=>$order]);
    }


    public function orders(): void
    {
        AuthGuard::roles(['admin']);header('Cache-Control: no-store, private');
        try{$history=(new \App\Models\Order())->forAdmin($_GET);$error=null;}
        catch(\InvalidArgumentException $e){http_response_code(422);$error=$e->getMessage();$history=['items'=>[],'total'=>0,'page'=>1,'pages'=>1,'status'=>'','q'=>''];}
        catch(\Throwable){http_response_code(503);$error='Orders are temporarily unavailable.';$history=['items'=>[],'total'=>0,'page'=>1,'pages'=>1,'status'=>'','q'=>''];}
        View::render('admin/orders',['pageTitle'=>'Orders','history'=>$history,'error'=>$error]);
    }

    public function orderStatus(): void
    {
        $user=AuthGuard::roles(['admin']);header('Cache-Control: no-store, private');
        \App\Core\Csrf::enforce(is_string($_POST['_token']??null)?$_POST['_token']:null);
        $id=is_string($_POST['order_id']??null)?filter_var($_POST['order_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):false;
        try{
            if(!$id||!is_string($_POST['expected_status']??null)||!is_string($_POST['status']??null))throw new \InvalidArgumentException('Choose an order and its next status.');
            $deliveryRevision=null;if(isset($_POST['expected_delivery_event'])){$deliveryRevision=is_string($_POST['expected_delivery_event'])?filter_var($_POST['expected_delivery_event'],FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]):false;if($deliveryRevision===false)throw new \InvalidArgumentException('Refresh the delivery update.');}
            (new \App\Services\OrderWorkflow())->advance(\App\Core\Database::connection(),(int)$user['id'],$id,$_POST['expected_status'],$_POST['status'],$deliveryRevision);
            \App\Core\Session::flash('success','Order status updated.');
        }catch(\InvalidArgumentException $e){\App\Core\Session::flash('error',$e->getMessage());}
        catch(\Throwable){\App\Core\Session::flash('error','The order was not changed. Please try again.');}
        \App\Core\Response::redirect('/admin/orders');
    }
}
