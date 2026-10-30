<?php

declare(strict_types=1);
namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\ActivationService;
use App\Services\RequestThrottle;

final class ActivationController
{
    public function __construct(private readonly ActivationService $activation) {}

    public function page(): void
    {
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        View::render('auth/activate', ['pageTitle'=>'Activate account'], 'auth');
    }

    public function activate(): void
    {
        Csrf::enforce($_POST['_token'] ?? null);
        if (!RequestThrottle::allow('activation', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 20, 60)) {
            http_response_code(429);
            header('Retry-After: 60');
            exit('Too many activation requests. Wait a minute.');
        }
        $flow = Session::get('pending_flow');
        $account = $this->activation->consumeAccount($_POST['activation_token'] ?? null);
        if ($account) {
            // The registration session already proved both password and email OTP.
            // A link opened elsewhere cannot silently replace an existing identity or bypass MFA.
            $verified = is_array($flow) && ($flow['purpose'] ?? '') === 'activation_link'
                && (int)($flow['user_id'] ?? 0) === (int)$account['id']
                && ($flow['email'] ?? '') === $account['email']
                && ($flow['verified_at'] ?? 0) <= time() && ($flow['verified_at'] ?? 0) >= time()-1800
                && ($flow['expires_at'] ?? 0) >= time()
                && hash_equals((string)$account['password_version'], (string)($flow['password_version'] ?? ''));
            $current = Session::get('auth_user');
            if (!$current && $verified) {
                $logs = new \App\Services\LogService();
                $auth = new \App\Services\AuthService(new \App\Models\User(), new \App\Services\OtpService(new \App\Services\MailService(), $logs), $logs);
                if ($auth->finalizeLogin($account)) {
                    Session::forget('pending_flow');
                    Session::flash('success', 'Your account is active. Welcome to Avela.');
                    Response::redirect('/');
                }
            }
            if ($verified) Session::forget('pending_flow');
            Session::flash('success', $current ? 'The account is active. Your current sign-in is unchanged.' : 'Your account is active. Sign in on this browser to continue securely.');
            Response::redirect($current ? '/' : '/login');
        }
        Session::flash('warning', 'This activation link is invalid, expired or already used. Sign in to request a new verification message.');
        Response::redirect('/login');
    }
}
