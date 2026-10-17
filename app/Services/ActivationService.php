<?php

declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Models\User;
use PDO;

final class ActivationService
{
    public function __construct(private readonly OtpService $otp, private readonly MailService $mail, private readonly LogService $logs) {}

    /** Shares the account lock and resend limit with numeric activation. */
    public function issue(int $userId): array
    {
        $user = (new User())->findById($userId);
        if (!$user || $user['role'] !== 'customer' || $user['status'] !== 'pending' || User::isLocked($user)) throw new \DomainException('Activation is unavailable. Start sign-in again.');
        return $this->otp->issue($userId, $user['email'], 'activation', function (string $email, string $code) use ($userId): void {
            $pdo = Database::connection();
            $user = (new User())->findById($userId, true);
            if (!$user || $user['status'] !== 'pending' || User::isLocked($user)) throw new \DomainException('Activation is unavailable.');
            $token = bin2hex(random_bytes(32));
            $base = rtrim((string) env('APP_URL', ''), '/');
            $url = parse_url($base);
            if (!$url || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])
                || !isset($url['host']) || !in_array($url['scheme'] ?? '', ['https','http'], true)
                || (($url['scheme'] ?? '') !== 'https' && (env('APP_ENV') !== 'development' || !in_array($url['host'], ['localhost','127.0.0.1','[::1]'], true)))) {
                throw new MailDeliveryException('Configure the trusted HTTPS application URL before sending activation links.');
            }
            $pdo->prepare('UPDATE activation_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$userId]);
            $pdo->prepare('INSERT INTO activation_tokens(user_id,token_digest,expires_at) VALUES (?,?,?)')->execute([$userId,hash('sha256',$token),date('Y-m-d H:i:s',time()+1800)]);
            $this->mail->sendActivation($email, $base . '/activate#token=' . $token, $code);
        });
    }

    public function consume(#[\SensitiveParameter] mixed $token): bool
    {
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) return false;
        return Database::transaction(function (PDO $pdo) use ($token): bool {
            // Determine account, then lock account -> token, matching issuance/numeric activation.
            $q = $pdo->prepare('SELECT user_id FROM activation_tokens WHERE token_digest=?');
            $q->execute([hash('sha256',$token)]);
            $id = $q->fetchColumn();
            if (!$id) return false;
            $user = (new User())->findById((int) $id, true);
            $q = $pdo->prepare('SELECT id,expires_at,used_at FROM activation_tokens WHERE user_id=? AND token_digest=? FOR UPDATE');
            $q->execute([$id,hash('sha256',$token)]);
            $row = $q->fetch();
            if (!$user || $user['role'] !== 'customer' || $user['status'] !== 'pending' || User::isLocked($user)
                || !$row || $row['used_at'] !== null || strtotime($row['expires_at']) < time()) return false;
            (new User())->activate((int) $id);
            $pdo->prepare('UPDATE activation_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$id]);
            $pdo->prepare('UPDATE otp_codes SET used_at=NOW() WHERE user_id=? AND purpose="activation" AND used_at IS NULL')->execute([$id]);
            $pdo->prepare('INSERT INTO audit_logs(actor_id,action,target_type,target_id,details) VALUES (?,"account_activated","user",?,"Email verification completed")')->execute([$id,$id]);
            $this->logs->auth((int) $id, 'activation', 'success');
            return true;
        });
    }
}
