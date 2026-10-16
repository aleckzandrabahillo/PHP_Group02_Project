<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Middleware\AuthGuard;
use App\Services\AuthService;
use App\Services\MfaCipher;
use App\Services\MfaService;
use DomainException;
use Throwable;

final class MfaController
{
    public function __construct(private readonly MfaService $mfa, private readonly AuthService $auth) {}

    private function privatePage(): void
    {
        header('Cache-Control: no-store, private');
        header('Referrer-Policy: no-referrer');
    }

    public function settings(): void
    {
        $user=AuthGuard::auth();$this->privatePage();
        $factor=$this->mfa->factor((int)$user['id']);
        View::render('account/mfa',[
            'pageTitle'=>'Authenticator','enabledAt'=>$factor['enabled_at']??null,
            'remaining'=>$factor?count(json_decode($factor['recovery_hashes_json'],true)):0,
            'configured'=>(new MfaCipher())->configured(),'setupSecret'=>$this->mfa->setupSecret((int)$user['id']),
        ]);
    }

    public function manage(): void
    {
        $user=AuthGuard::auth();
        $this->privatePage();
        Csrf::enforce(is_string($_POST['_token']??null)?$_POST['_token']:null);
        $id=(int)$user['id'];
        try {
            switch($_POST['action']??'') {
                case 'begin':
                    $this->mfa->beginEnrollment($id,$this->input('password',256));
                    Session::flash('success','Enter the email verification code and a code from your authenticator to finish setup.');break;
                case 'confirm':
                    $this->mfa->confirmEnrollment($id,$this->input('email_code',6),$this->input('authenticator_code',6));
                    Response::redirect('/account/mfa/recovery');
                case 'cancel':
                    Session::forget('mfa_setup_flow');Session::flash('success','Authenticator setup canceled.');break;
                case 'disable':
                    $this->mfa->disable($id,$this->input('password',256),$this->input('authenticator_code',64));
                    $this->auth->logout();Session::flash('success','Authenticator removed. Email MFA remains enabled. Please sign in again.');
                    Response::redirect('/login');
                default: throw new DomainException('Choose a valid authenticator action.');
            }
        } catch(DomainException $e) {
            Session::flash('error',$e->getMessage());
        } catch(Throwable) {
            Session::flash('error','Authenticator setup could not be completed. Contact an administrator if the problem continues.');
        }
        Response::redirect('/account/mfa');
    }

    public function recovery(): void
    {
        AuthGuard::auth();$this->privatePage();
        $codes=Session::get('mfa_recovery_codes');Session::forget('mfa_recovery_codes');
        View::render('account/mfa_recovery',['pageTitle'=>'Recovery codes','codes'=>is_array($codes)?$codes:[]]);
    }

    public function verifyForm(): void
    {
        AuthGuard::guest();$this->privatePage();$flow=Session::get('pending_totp');
        if(!is_array($flow)||($flow['expires_at']??0)<time()) {
            Session::forget('pending_totp');Session::flash('warning','Your sign-in challenge expired. Sign in again.');Response::redirect('/login');
        }
        View::render('auth/verify-authenticator',['pageTitle'=>'Verify authenticator'],'auth');
    }

    public function verify(): void
    {
        $this->privatePage();
        AuthGuard::guest();Csrf::enforce(is_string($_POST['_token']??null)?$_POST['_token']:null);
        try {
            $result=$this->auth->verifyPendingAuthenticator($this->input('authenticator_code',64));
            if($result['ok'])Response::redirect(AuthGuard::dashboardPath());
            Session::flash('error',$result['message']);
            if(!empty($result['expired']))Response::redirect('/login');
        } catch(Throwable) {
            Session::flash('error','Authenticator verification is unavailable. Contact an administrator if this continues.');
        }
        Response::redirect('/verify-authenticator');
    }

    private function input(string $key, int $max): string
    {
        $value=$_POST[$key]??'';
        return is_string($value)&&strlen($value)<=$max?$value:'';
    }
}
