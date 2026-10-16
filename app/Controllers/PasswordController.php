<?php

declare(strict_types=1);
namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Middleware\AuthGuard;
use App\Services\PasswordService;
use App\Services\SecurityPolicy;
use DomainException;

final class PasswordController
{
    public function page(): void
    {
        AuthGuard::auth();
        header('Cache-Control: no-store, private');
        View::render('account/password',['pageTitle'=>'Change password','passwordPolicy'=>SecurityPolicy::values()]);
    }

    public function change(): void
    {
        $user=AuthGuard::auth();
        Csrf::enforce($_POST['_token']??null);
        $values=[];
        foreach(['current_password','password','password_confirmation'] as $field){
            $raw=$_POST[$field]??null;
            if(!is_string($raw)||$raw===''||strlen($raw)>256){
                Session::flash('error','Complete all password fields using valid text.');
                Response::redirect('/account/password');
            }
            $values[$field]=$raw;
        }
        if(!hash_equals($values['password'],$values['password_confirmation'])){
            Session::flash('error','New passwords do not match.');
            Response::redirect('/account/password');
        }
        try {
            (new PasswordService())->change((int)$user['id'],$values['current_password'],$values['password']);
        } catch(DomainException $e) {
            Session::flash('error',$e->getMessage());
            Response::redirect('/account/password');
        } catch(\Throwable) {
            Session::flash('error','The password change could not be saved. No partial change was committed.');
            Response::redirect('/account/password');
        }
        Session::forgetAuth();
        session_regenerate_id(true);
        Session::flash('success','Password changed. Sign in again with your new password and your usual verification.');
        Response::redirect('/login');
    }
}
