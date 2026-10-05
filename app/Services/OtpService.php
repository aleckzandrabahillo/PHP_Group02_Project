<?php

namespace App\Services;

use App\Core\Database;

final class OtpService
{
    public function __construct(private readonly MailService $mail, private readonly LogService $logs) {}

    public function issue(int $userId, string $email, string $purpose): void
    {
        if (!in_array($purpose, ['activation', 'login', 'profile_update'], true)) {
            throw new \InvalidArgumentException('Invalid OTP purpose.');
            }

        $pdo = Database::connection();
        $pdo->prepare('UPDATE otp_codes SET used_at = NOW() WHERE user_id = :user_id AND purpose = :purpose AND used_at IS NULL')
            ->execute(['user_id' => $userId, 'purpose' => $purpose]);

        $code = (string) random_int(100000, 999999);
        $hash = password_hash($code, PASSWORD_DEFAULT);
        $minutes = max(1, (int) env('OTP_EXPIRY_MINUTES', 5));
        $expires = date('Y-m-d H:i:s', time() + ($minutes * 60));

        $stmt = $pdo->prepare('INSERT INTO otp_codes (user_id, purpose, otp_hash, expires_at, attempts, created_at) VALUES (:user_id, :purpose, :otp_hash, :expires_at, 0, NOW())');
        $stmt->execute([
            'user_id' => $userId,
            'purpose' => $purpose,
            'otp_hash' => $hash,
            'expires_at' => $expires,
        ]);

        $this->mail->sendOtp($email, $code, $purpose);
        $this->logs->auth($userId, 'otp_sent', 'success', ['purpose' => $purpose]);
    }

    /**
     * @return array{ok:bool,reason:string,remaining:int}
     */
    public function verify(int $userId, string $purpose, string $code): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM otp_codes WHERE user_id = :user_id AND purpose = :purpose AND used_at IS NULL ORDER BY id DESC LIMIT 1');
        $stmt->execute(['user_id' => $userId, 'purpose' => $purpose]);
        $otp = $stmt->fetch();

        if (!$otp) {
            $this->logs->auth($userId, 'otp_verify', 'failure', ['purpose' => $purpose, 'reason' => 'missing']);
            return ['ok' => false, 'reason' => 'missing', 'remaining' => 0];
        }

        $maxAttempts = max(1, (int) env('OTP_MAX_ATTEMPTS', 5));
        $attempts = (int) $otp['attempts'];

        if (strtotime((string) $otp['expires_at']) < time()) {
            $this->logs->auth($userId, 'otp_verify', 'failure', ['purpose' => $purpose, 'reason' => 'expired']);
            return ['ok' => false, 'reason' => 'expired', 'remaining' => max(0, $maxAttempts - $attempts)];
        }

        if ($attempts >= $maxAttempts) {
            $this->logs->auth($userId, 'otp_verify', 'failure', ['purpose' => $purpose, 'reason' => 'attempt_limit']);
            return ['ok' => false, 'reason' => 'attempt_limit', 'remaining' => 0];
        }

        if (!password_verify(trim($code), (string) $otp['otp_hash'])) {
            $newAttempts = $attempts + 1;
            $pdo->prepare('UPDATE otp_codes SET attempts = :attempts WHERE id = :id')
                ->execute(['attempts' => $newAttempts, 'id' => $otp['id']]);

            $remaining = max(0, $maxAttempts - $newAttempts);
            $reason = $remaining === 0 ? 'attempt_limit' : 'invalid';
            $this->logs->auth($userId, 'otp_verify', 'failure', [
                'purpose' => $purpose,
                'reason' => $reason,
                'remaining' => $remaining,
            ]);

            return ['ok' => false, 'reason' => $reason, 'remaining' => $remaining];
        }

        $pdo->prepare('UPDATE otp_codes SET used_at = NOW() WHERE id = :id')->execute(['id' => $otp['id']]);
        $this->logs->auth($userId, 'otp_verify', 'success', ['purpose' => $purpose]);

        return ['ok' => true, 'reason' => 'verified', 'remaining' => max(0, $maxAttempts - $attempts)];
    }
}
