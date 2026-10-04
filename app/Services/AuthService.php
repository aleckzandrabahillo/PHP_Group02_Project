<?php

namespace App\Services;

use App\Core\Session;
use App\Core\Validator;
use App\Models\User;

final class AuthService
{
    public function __construct(private readonly User $users, private readonly OtpService $otp, private readonly LogService $logs) {}

    public function register(array $data): array
    {
        if ($this->users->emailExists($data['email'])) return ['ok' => false, 'field' => 'email', 'message' => 'That email is already registered.'];
        if ($this->users->usernameExists($data['username'])) return ['ok' => false, 'field' => 'username', 'message' => 'That username is already taken.'];

        $id = $this->users->createCustomer([
            'full_name' => trim($data['full_name']),
            'email' => trim($data['email']),
            'username' => trim($data['username']),
            'contact_no' => Validator::normalizePhilippineMobile((string) ($data['contact_no'] ?? '')) ?? '',
            'password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
        ]);
        $this->logs->auth($id, 'registration', 'success');
        $this->otp->issue($id, trim($data['email']), 'activation');
        Session::put('pending_flow', ['user_id' => $id, 'purpose' => 'activation', 'email' => trim($data['email']), 'sent_at' => time()]);
        return ['ok' => true];
    }

    public function attempt(string $identifier, string $password): array
    {
        $user = $this->users->findByIdentifier(trim($identifier));
        if (!$user) {
            $this->logs->auth(null, 'login', 'failure', ['reason' => 'invalid_credentials']);
            return ['ok' => false, 'message' => 'Invalid email/username or password.'];
        }

        if (!empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time()) {
            $this->logs->auth((int) $user['id'], 'login', 'failure', ['reason' => 'locked']);
            return ['ok' => false, 'message' => 'This account is temporarily locked. Try again later or contact an administrator.'];
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            $failure = $this->users->recordFailure((int) $user['id']);
            if (!empty($failure['locked_until'])) {
                $this->logs->auth((int) $user['id'], 'lockout', 'success');
                return ['ok' => false, 'message' => 'Too many failed sign-in attempts. This account is temporarily locked.'];
            }
            $this->logs->auth((int) $user['id'], 'login', 'failure', ['reason' => 'invalid_credentials']);
            return ['ok' => false, 'message' => 'Invalid email/username or password.'];
        }

        if ($user['status'] === 'inactive') {
            $this->logs->auth((int) $user['id'], 'login', 'failure', ['reason' => 'inactive']);
            return ['ok' => false, 'message' => 'This account is inactive. Contact an administrator if you need help.'];
        }

        if ($user['status'] === 'pending') {
            $this->otp->issue((int) $user['id'], (string) $user['email'], 'activation');
            Session::put('pending_flow', ['user_id' => (int) $user['id'], 'purpose' => 'activation', 'email' => $user['email'], 'sent_at' => time()]);
            return ['ok' => true, 'next' => 'otp'];
        }

        $requiresMfa = (bool) $user['mfa_enabled'] || in_array($user['role'], ['admin', 'catalog_manager'], true);
        if ($requiresMfa) {
            $this->otp->issue((int) $user['id'], (string) $user['email'], 'login');
            Session::put('pending_flow', ['user_id' => (int) $user['id'], 'purpose' => 'login', 'email' => $user['email'], 'sent_at' => time()]);
            return ['ok' => true, 'next' => 'otp'];
        }

        $this->finalizeLogin($user);
        return ['ok' => true, 'next' => 'dashboard'];
    }

    public function verifyPendingOtp(string $code): array
    {
        $flow = Session::get('pending_flow');
        if (!is_array($flow) || empty($flow['user_id']) || empty($flow['purpose'])) {
            return ['ok' => false, 'message' => 'Your verification session is no longer available. Start again.'];
        }
        $userId = (int) $flow['user_id'];
        $purpose = (string) $flow['purpose'];
        $verification = $this->otp->verify($userId, $purpose, $code);
        if (!$verification['ok']) {
            $message = match ($verification['reason']) {
                'expired' => 'This verification code has expired. Request a new code.',
                'attempt_limit' => 'Too many incorrect code attempts. Request a new code.',
                'missing' => 'No active verification code was found. Request a new code.',
                'invalid' => sprintf(
                    'The code you entered is incorrect. %d %s remaining.',
                    (int) $verification['remaining'],
                    (int) $verification['remaining'] === 1 ? 'attempt' : 'attempts'
                ),
                default => 'The verification code could not be confirmed. Request a new code.',
            };
            return ['ok' => false, 'field' => 'otp', 'message' => $message];
        }
        $user = $this->users->findById($userId);
        if (!$user) return ['ok' => false, 'message' => 'Account not found.'];

        if ($purpose === 'activation') {
            $this->users->activate($userId);
            $this->logs->auth($userId, 'activation', 'success');
            Session::forget('pending_flow');
            return ['ok' => true, 'next' => 'login', 'message' => 'Your account is active. You can sign in now.'];
        }

        $this->finalizeLogin($user);
        Session::forget('pending_flow');
        return ['ok' => true, 'next' => 'dashboard'];
    }

    public function resendPendingOtp(): array
    {
        $flow = Session::get('pending_flow');
        if (!is_array($flow) || empty($flow['user_id'])) return ['ok' => false, 'message' => 'Start the sign-in or activation flow again.'];
        $wait = max(1, (int) env('OTP_RESEND_SECONDS', 60));
        $elapsed = time() - (int) ($flow['sent_at'] ?? 0);
        if ($elapsed < $wait) {
            $remaining = max(1, $wait - $elapsed);
            return ['ok' => false, 'message' => "Please wait {$remaining} seconds before requesting another code."];
        }
        $user = $this->users->findById((int) $flow['user_id']);
        if (!$user) return ['ok' => false, 'message' => 'Account not found.'];
        $this->otp->issue((int) $user['id'], (string) $user['email'], (string) $flow['purpose']);
        $flow['sent_at'] = time();
        Session::put('pending_flow', $flow);
        return ['ok' => true, 'message' => 'A new code was sent.'];
    }

    public function finalizeLogin(array $user): void
    {
        session_regenerate_id(true);
        $this->users->resetFailures((int) $user['id']);
        Session::put('auth_user', [
            'id' => (int) $user['id'],
            'email' => $user['email'],
            'username' => $user['username'],
            'role' => $user['role'],
        ]);
        Session::put('last_activity', time());
        $this->logs->auth((int) $user['id'], 'login', 'success');
    }

    public function logout(): void
    {
        $user = Session::get('auth_user');
        if (is_array($user) && isset($user['id'])) $this->logs->auth((int) $user['id'], 'logout', 'success');
        Session::forgetAuth();
        Session::forget('pending_flow');
        session_regenerate_id(true);
    }
}
