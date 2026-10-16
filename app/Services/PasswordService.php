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
    /** Disabled pending approval. Legacy unknown timestamps are never silently backdated. */
    public static function expired(array $user): bool
    {
        $days=filter_var(env('PASSWORD_EXPIRY_DAYS',0),FILTER_VALIDATE_INT);
        return $days!==false && $days>=30 && $days<=365 && !empty($user['password_changed_at'])
            && strtotime($user['password_changed_at'])+$days*86400<=time();
    }

    public function change(int $id, #[\SensitiveParameter] string $current, #[\SensitiveParameter] string $new): void
    {
        if ($error=Validator::passwordError($new)) throw new DomainException($error);
        $ok=Database::transaction(function(PDO $pdo)use($id,$current,$new):bool{
            $user=(new User())->findById($id,true);
            if(!User::canAuthenticate($user))throw new DomainException('Account security is unavailable. Sign in again.');
            if(!password_verify($current,$user['password_hash'])){
                (new User())->recordFailure($id);
                (new LogService())->auth($id,'password_change','failure',['reason'=>'current_password']);
                return false;
            }
            if(password_verify($new,$user['password_hash']))throw new DomainException('Choose a password different from your current password.');
            $pdo->prepare('UPDATE users SET password_hash=?,password_changed_at=NOW(),password_version=?,failed_attempts=0,updated_at=NOW() WHERE id=?')
                ->execute([password_hash($new,PASSWORD_DEFAULT),bin2hex(random_bytes(32)),$id]);
            $pdo->prepare('UPDATE otp_codes SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$id]);
            $pdo->prepare('UPDATE activation_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$id]);
            $pdo->prepare('INSERT INTO audit_logs(actor_id,action,target_type,target_id,details) VALUES (?,"password_changed","user",?,"Password replaced; existing sessions and pending verification revoked")')->execute([$id,$id]);
            (new LogService())->auth($id,'password_change','success');
            return true;
        });
        if(!$ok)throw new DomainException('Your current password could not be confirmed.');
    }
}
