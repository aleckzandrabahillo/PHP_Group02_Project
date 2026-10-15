<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use App\Models\User;
use DomainException;
use OTPHP\TOTP;
use PDO;

final class MfaService
{
    public function __construct(private readonly User $users, private readonly OtpService $otp, private readonly LogService $logs) {}

    public function factor(int $userId, bool $lock = false): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM user_authenticators WHERE user_id=? AND enabled_at IS NOT NULL' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public static function sessionVersion(int $userId): ?string
    {
        $stmt = Database::connection()->prepare('SELECT credential_version FROM user_authenticators WHERE user_id=?');
        $stmt->execute([$userId]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : $value;
    }

    public function beginEnrollment(int $userId, #[\SensitiveParameter] string $password): array
    {
        $user = $this->users->findById($userId);
        if (!User::canAuthenticate($user) || $this->factor($userId)) throw new DomainException('An active account without an authenticator is required.');
        $cipher = new MfaCipher();
        if (!$cipher->configured()) throw new DomainException('Authenticator enrollment is unavailable until the private encryption key is configured.');
        if (!password_verify($password, $user['password_hash'])) {
            $this->users->recordFailure($userId);
            $this->logs->auth($userId, 'mfa_enrollment', 'failure', ['reason'=>'password']);
            throw new DomainException('The password could not be confirmed.');
        }
        $issued = $this->otp->issue($userId, $user['email'], 'login');
        if (!$issued['issued'] && !$issued['reusable']) throw new DomainException('Please wait for the email-code cooldown before starting enrollment.');
        // Keep an existing pending secret when cooldown reuses its email challenge.
        $pending = Session::get('mfa_setup_flow');
        if (!$issued['issued'] && is_array($pending) && ($pending['user_id'] ?? 0) === $userId && ($pending['expires_at'] ?? 0) >= time()) return $pending;
        $secret = TOTP::generate()->getSecret();
        $pending = ['user_id'=>$userId, 'secret'=>$cipher->encrypt($userId,$secret), 'expires_at'=>time()+300];
        Session::put('mfa_setup_flow',$pending);
        return $pending;
    }

    public function setupSecret(int $userId): ?string
    {
        $flow=Session::get('mfa_setup_flow');
        if (!is_array($flow) || ($flow['user_id']??0)!==$userId || ($flow['expires_at']??0)<time()) {
            Session::forget('mfa_setup_flow'); return null;
        }
        return (new MfaCipher())->decrypt($userId,$flow['secret']);
    }

    public function confirmEnrollment(int $userId, #[\SensitiveParameter] string $emailCode, #[\SensitiveParameter] string $code): array
    {
        $secret=$this->setupSecret($userId);
        if ($secret===null) throw new DomainException('Enrollment expired. Start again.');
        $codes=Database::transaction(function(PDO $pdo) use($userId,$secret,$emailCode,$code):array {
            $user=$this->users->findById($userId,true);
            if (!User::canAuthenticate($user) || $this->factor($userId,true)) throw new DomainException('Account enrollment is unavailable.');
            $step=$this->matchingStep($secret,$code,-1);
            if ($step===null) {
                $this->users->recordFailure($userId);
                return ['ok'=>false,'message'=>'The authenticator code could not be confirmed.'];
            }
            if (!$this->otp->verify($userId,'login',$emailCode)['ok']) return ['ok'=>false,'message'=>'The email code could not be confirmed. Request a new enrollment if it expired.'];
            $cipher=new MfaCipher();$plain=[];$hashes=[];
            for($i=0;$i<8;$i++) {
                $raw=strtoupper(bin2hex(random_bytes(16)));$plain[]=implode('-',str_split($raw,4));
                $hashes[]=$cipher->recoveryHash($userId,$raw);
            }
            $version=bin2hex(random_bytes(32));
            $pdo->prepare('INSERT INTO user_authenticators (user_id,secret_encrypted,recovery_hashes_json,last_used_step,enabled_at,credential_version)
                VALUES (:user,:secret,:hashes,:step,NOW(),:version) ON DUPLICATE KEY UPDATE secret_encrypted=VALUES(secret_encrypted),
                recovery_hashes_json=VALUES(recovery_hashes_json),last_used_step=VALUES(last_used_step),enabled_at=VALUES(enabled_at),credential_version=VALUES(credential_version)')->execute([
                    'user'=>$userId,'secret'=>$cipher->encrypt($userId,$secret),'hashes'=>json_encode($hashes,JSON_THROW_ON_ERROR),'step'=>$step,'version'=>$version,
                ]);
            $pdo->prepare('UPDATE users SET mfa_enabled=1,failed_attempts=0,locked_until=NULL,updated_at=NOW() WHERE id=?')->execute([$userId]);
            $this->audit($pdo,$userId,'authenticator_enrolled');
            return ['ok'=>true,'codes'=>$plain,'version'=>$version];
        });
        if (!$codes['ok']) throw new DomainException($codes['message']);
        Session::forget('mfa_setup_flow');
        Session::put('mfa_recovery_codes',$codes['codes']);
        Session::put('auth_mfa_version',$codes['version']);
        session_regenerate_id(true);
        return $codes['codes'];
    }

    public function loginChallenge(int $userId): array
    {
        $factor=$this->factor($userId);
        if (!$factor) throw new DomainException('Authenticator unavailable.');
        return ['user_id'=>$userId,'expires_at'=>time()+300,'version'=>$factor['credential_version']];
    }

    public function verifyLogin(array $flow, #[\SensitiveParameter] string $code): array
    {
        if (($flow['expires_at']??0)<time() || !isset($flow['user_id'],$flow['version'])) return ['ok'=>false,'message'=>'The sign-in challenge expired. Sign in again.','expired'=>true];
        return Database::transaction(function(PDO $pdo) use($flow,$code):array {
            $id=(int)$flow['user_id'];$user=$this->users->findById($id,true);$factor=$this->factor($id,true);
            if (!User::canAuthenticate($user) || !$factor || !hash_equals($factor['credential_version'],$flow['version'])) {
                return ['ok'=>false,'message'=>'Sign-in is no longer available. Start again.','expired'=>true];
            }
            if (!$this->consume($pdo,$factor,$code)) {
                $this->users->recordFailure($id);
                $this->logs->auth($id,'authenticator_verify','failure');
                return ['ok'=>false,'message'=>'The code is invalid or was already used.'];
            }
            $this->users->resetFailures($id);
            $this->logs->auth($id,'authenticator_verify','success');
            $this->logs->auth($id,'login','success');
            return ['ok'=>true,'user'=>$user,'factor_version'=>$factor['credential_version']];
        });
    }

    public function disable(int $userId, #[\SensitiveParameter] string $password, #[\SensitiveParameter] string $code): void
    {
        $result=Database::transaction(function(PDO $pdo) use($userId,$password,$code):bool {
            $user=$this->users->findById($userId,true);$factor=$this->factor($userId,true);
            if (!User::canAuthenticate($user) || !$factor) throw new DomainException('Authenticator unavailable.');
            if (!password_verify($password,$user['password_hash']) || !$this->consume($pdo,$factor,$code)) {
                $this->users->recordFailure($userId);return false;
            }
            $pdo->prepare('UPDATE user_authenticators SET secret_encrypted=NULL,recovery_hashes_json="[]",enabled_at=NULL,last_used_step=-1,credential_version=? WHERE user_id=?')
                ->execute([bin2hex(random_bytes(32)),$userId]);
            $pdo->prepare('UPDATE users SET failed_attempts=0,locked_until=NULL,updated_at=NOW() WHERE id=?')->execute([$userId]);
            // Keep email MFA enabled, including for customers who opted into MFA.
            $this->audit($pdo,$userId,'authenticator_removed');return true;
        });
        if (!$result) throw new DomainException('Password and current authenticator/recovery code could not be confirmed.');
        Session::forget('mfa_recovery_codes');Session::forget('mfa_setup_flow');session_regenerate_id(true);
    }

    private function consume(PDO $pdo, array $factor, #[\SensitiveParameter] string $code): bool
    {
        $id = (int) $factor['user_id'];
        $cipher = new MfaCipher();
        $step = $this->matchingStep($cipher->decrypt($id, $factor['secret_encrypted']), $code, (int) $factor['last_used_step']);
        if ($step !== null) {
            $pdo->prepare('UPDATE user_authenticators SET last_used_step=? WHERE user_id=?')->execute([$step, $id]);
            return true;
        }
        $raw = strtoupper(str_replace('-', '', trim($code)));
        if (!preg_match('/^[A-F0-9]{32}$/D', $raw)) return false;
        $hash = $cipher->recoveryHash($id, $raw);
        $hashes = json_decode($factor['recovery_hashes_json'], true, 512, JSON_THROW_ON_ERROR);
        $remaining = [];
        $found = false;
        foreach ($hashes as $stored) {
            if (hash_equals($stored, $hash)) $found = true;
            else $remaining[] = $stored;
        }
        if (!$found) return false;
        $pdo->prepare('UPDATE user_authenticators SET recovery_hashes_json=? WHERE user_id=?')
            ->execute([json_encode($remaining, JSON_THROW_ON_ERROR), $id]);
        $this->audit($pdo, $id, 'authenticator_recovery_used');
        return true;
    }

    private function matchingStep(#[\SensitiveParameter] string $secret, #[\SensitiveParameter] string $code, int $lastStep): ?int
    {
        if (!preg_match('/^[0-9]{6}$/D', $code)) return null;
        $now = intdiv(time(), 30);
        $totp = TOTP::createFromSecret($secret);
        foreach ([$now, $now - 1, $now + 1] as $step) {
            if ($step > $lastStep && $totp->verify($code, $step * 30, 0)) return $step;
        }
        return null;
    }

    private function audit(PDO $pdo, int $userId, string $action): void
    {
        $pdo->prepare('INSERT INTO audit_logs (actor_id,action,target_type,target_id,details) VALUES (?, ?, "user", ?, "Authenticator account security")')
            ->execute([$userId,$action,$userId]);
    }
}
