<?php

declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Core\Validator;
use App\Models\User;
use DomainException;
use PDO;

final class PasswordService
{
    /** Approved coursework period. Existing private deployment overrides remain authoritative. */
    public static function expired(array $user): bool
    {
        $deadline=self::expiresAt($user);
        return $deadline!==null && $deadline<=time();
    }

    /** Known password ages use 90 days; legacy ages get an explicit prospective grace deadline. */
    public static function expiresAt(array $user): ?int
    {
        $days=filter_var(env('PASSWORD_EXPIRY_DAYS',90),FILTER_VALIDATE_INT);
        if($days===0)return null;
        if($days===false||$days<30||$days>365)throw new \RuntimeException('Password security settings are unavailable.');
        if(!empty($user['password_changed_at'])){
            $changed=strtotime((string)$user['password_changed_at']);
            if($changed===false)throw new \RuntimeException('Password security information is unavailable.');
            return $changed+$days*86400;
        }
        $legacy=env('PASSWORD_LEGACY_GRACE_END','');
        if($legacy==='')return null; // Existing deployments retain their separately approved transition.
        if(!is_string($legacy)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/D',$legacy)
            ||($deadline=strtotime($legacy))===false||gmdate('Y-m-d\TH:i:sP',$deadline)!==$legacy)throw new \RuntimeException('Password security settings are unavailable.');
        return $deadline;
    }

    public function change(int $id, #[\SensitiveParameter] string $current, #[\SensitiveParameter] string $new): string
    {
        if ($error=Validator::passwordError($new)) throw new DomainException($error);
        $ok=Database::transaction(function(PDO $pdo)use($id,$current,$new):string|false{
            $user=(new User())->findById($id,true);
            if(!User::canAuthenticate($user))throw new DomainException('Account security is unavailable. Sign in again.');
            if(!password_verify($current,$user['password_hash'])){
                (new User())->recordFailure($id);
                (new LogService())->auth($id,'password_change','failure',['reason'=>'current_password']);
                return false;
            }
            if(password_verify($new,$user['password_hash']))throw new DomainException('Choose a password different from your current password.');
            $version=bin2hex(random_bytes(32));
            $pdo->prepare('UPDATE users SET password_hash=?,password_changed_at=NOW(),password_version=?,failed_attempts=0,updated_at=NOW() WHERE id=?')
                ->execute([password_hash($new,PASSWORD_DEFAULT),$version,$id]);
            $pdo->prepare('UPDATE otp_codes SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$id]);
            $pdo->prepare('UPDATE activation_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$id]);
            $pdo->prepare('INSERT INTO audit_logs(actor_id,action,target_type,target_id,details) VALUES (?,"password_changed","user",?,"Password replaced; other sessions and pending verification revoked")')->execute([$id,$id]);
            (new LogService())->auth($id,'password_change','success');
            return $version;
        });
        if(!$ok)throw new DomainException('Your current password could not be confirmed.');
        return $ok;
    }
}
