<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Middleware\AuthGuard;
use App\Services\AuthService;
use App\Services\RecaptchaService;

final class AuthController
{
    public function __construct(private readonly AuthService $auth, private readonly RecaptchaService $recaptcha) {}

    public function loginForm(): void
    {
        AuthGuard::guest();
        Session::forget('_auth_expired_notice');
        $siteKey = env('RECAPTCHA_SITE_KEY', '');


    View::render('auth/login', [
        'pageTitle' => 'Sign in',
        'recaptchaSiteKey' => $siteKey,
    ], 'auth');
    }

    public function login(): void
    {
        AuthGuard::guest();
        Csrf::enforce($_POST['_token'] ?? null);
        if (!\App\Services\RequestThrottle::allow('login', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 30, 60)) {
            http_response_code(429);
            header('Retry-After: 60');
            exit('Too many sign-in requests. Wait a minute before trying again.');
        }
        $errors = Validator::login($_POST);
       $recaptchaResponse = (string) ($_POST['g-recaptcha-response'] ?? '');

        if (!$this->recaptcha->verify($recaptchaResponse)) {
            $errors['captcha'] = 'Please complete the CAPTCHA verification.';
        }
        if ($errors) {
            Session::keepOld($_POST);
            Session::put('errors', $errors);
            Response::redirect('/login');
        }
        try {
            $result = $this->auth->attempt((string) $_POST['identifier'], (string) $_POST['password']);
        } catch (\App\Services\MailDeliveryException $e) {
            Session::flash('error', $e->getMessage());
            Response::redirect('/login');
        }
        if (!$result['ok']) {
            Session::keepOld($_POST);
            Session::put('errors', [
                'identifier' => '',
                'password' => '',
            ]);
            Session::flash('error', $result['message']);
            Response::redirect('/login');
        }
        if (in_array($result['next']??'', ['otp','activation_link'],true)) Response::redirect('/verify-otp');
        if (($result['next'] ?? '') === 'totp') Response::redirect('/verify-authenticator');
        Session::forget('activation_setup_offer');
        Response::redirect(AuthGuard::dashboardPath());
    }

    public function registerForm(): void
    {
        AuthGuard::guest();
        View::render('auth/register', ['pageTitle' => 'Create account', 'recaptchaSiteKey' => env('RECAPTCHA_SITE_KEY', ''),
            'passwordPolicy' => \App\Services\SecurityPolicy::values()], 'auth');
    }

    public function register(): void
    {
        AuthGuard::guest();
        Csrf::enforce($_POST['_token'] ?? null);
        $errors = Validator::registration($_POST);
        
        $recaptchaResponse = (string) ($_POST['g-recaptcha-response'] ?? '');
        if (!$this->recaptcha->verify($recaptchaResponse)) {
            $errors['captcha'] = 'Please complete the CAPTCHA verification.';
        }
        
        if ($errors) {
            Session::keepOld($_POST);
            Session::put('errors', $errors);
            Response::redirect('/register');
        }
        try {
            $result = $this->auth->register($_POST);
        } catch (\App\Services\MailDeliveryException $e) {
            Session::keepOld($_POST);
            Session::flash('error', $e->getMessage() . ' Your registration was not saved.');
            Response::redirect('/register');
        } catch (\PDOException) {
            Session::keepOld($_POST);
            Session::flash('error', 'Registration could not be completed. If you already registered, try signing in.');
            Response::redirect('/register');
        }
        if (!$result['ok']) {
            Session::keepOld($_POST);
            Session::put('errors', [$result['field'] => $result['message']]);
            Response::redirect('/register');
        }
        Session::flash('success', 'Account created. Enter the code sent to your email. We will then send your activation link.');
        Response::redirect('/verify-otp');
    }


    public function otpForm(): void
    {
        AuthGuard::guest();
        $flow = Session::get('pending_flow');
        if (!is_array($flow)) Response::redirect('/login');
        if (($flow['purpose'] ?? '') === 'login') {
            Session::forget('pending_flow');
            Session::flash('warning', 'Please sign in again to use the updated verification flow.');
            Response::redirect('/login');
        }
        $email = (string) ($flow['email'] ?? '');
        $masked = $this->maskEmail($email);
        if(($flow['purpose']??'')==='activation_link'){
            View::render('auth/activation-sent',['pageTitle'=>'Activate your account','maskedEmail'=>$masked,
                'expired'=>($flow['expires_at']??0)<time()],'auth');return;
        }
        View::render('auth/verify-otp', [
            'pageTitle' => $flow['purpose'] === 'activation' ? 'Verify your email' : 'Verify sign in',
            'maskedEmail' => $masked,
            'purpose' => $flow['purpose'],
        ], 'auth');
    }

    public function verifyOtp(): void
    {
        AuthGuard::guest();
        Csrf::enforce($_POST['_token'] ?? null);
        $code = preg_replace('/\D+/', '', (string) ($_POST['otp'] ?? ''));
        if (strlen($code) !== 6) {
            Session::put('errors', ['otp' => 'Enter the 6-digit code.']);
            Response::redirect('/verify-otp');
        }
        try{$result = $this->auth->verifyPendingOtp($code);}
        catch(\App\Services\MailDeliveryException $e){Session::put('errors',['otp'=>$e->getMessage().' Enter your code again to retry.']);Response::redirect('/verify-otp');}
        if (!$result['ok']) {
            Session::put('errors', ['otp' => $result['message']]);
            Response::redirect('/verify-otp');
        }
        if(($result['next']??'')==='activation_link'){
            Session::flash('success',$result['message']);Response::redirect('/verify-otp');
        }
        if (($result['next'] ?? '') === 'login') {
            Session::flash('success', $result['message']);
            Response::redirect('/login');
        }
        if (($result['next'] ?? '') === 'totp') Response::redirect('/verify-authenticator');
        Response::redirect(AuthGuard::dashboardPath());
    }

    public function resendOtp(): void
    {
        AuthGuard::guest();
        Csrf::enforce($_POST['_token'] ?? null);
        try {
            $result = $this->auth->resendPendingOtp();
        } catch (\App\Services\MailDeliveryException $e) {
            $result = ['ok'=>false, 'message'=>$e->getMessage()];
        }
        Session::flash($result['ok'] ? 'success' : 'warning', $result['message']);
        Response::redirect('/verify-otp');
    }

    public function logout(): void
    {
        Csrf::enforce($_POST['_token'] ?? null);
        $this->auth->logout();
        Session::flash('success', 'You have been signed out.');
        Response::redirect('/login');
    }

    private function maskEmail(string $email): string
    {
        if (!str_contains($email, '@')) return $email;
        [$name, $domain] = explode('@', $email, 2);
        $visible = substr($name, 0, min(2, strlen($name)));
        return $visible . str_repeat('•', max(2, strlen($name) - strlen($visible))) . '@' . $domain;
    }
}
