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
        $ok = $this->activation->consume($_POST['activation_token'] ?? null);
        if ($ok) {
            $flow = Session::get('pending_flow');
            // Activation never signs in or changes an existing authenticated session.
            if (is_array($flow) && ($flow['purpose'] ?? '') === 'activation'
                && ((new \App\Models\User())->findById((int) ($flow['user_id'] ?? 0))['status'] ?? '') === 'active') Session::forget('pending_flow');
        }
        Session::flash($ok ? 'success' : 'warning', $ok ? 'Your account is active. You can sign in now.' : 'This activation link is invalid, expired or already used. Sign in to request a new verification message.');
        Response::redirect('/login');
    }
}
