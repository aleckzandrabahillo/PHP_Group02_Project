<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;
use App\Models\User;
use DomainException;
use PDO;

final class AdministratorRecovery
{
    public function __construct(private readonly MfaService $mfa,private readonly OtpService $otp,private readonly LogService $logs) {}

    public function sendCode(int $actor): void
    {
        $user=(new User())->findById($actor);
        if(!$user||$user['role']!=='admin'||$user['status']!=='active')throw new DomainException('Recovery unavailable.');
        if($this->mfa->factor($actor)!==null)throw new DomainException('Use your authenticator or an unused recovery code.');
        $issued=$this->otp->issue($actor,$user['email'],'login');
        if(!$issued['issued']&&!$issued['reusable'])throw new DomainException('Wait before requesting another recovery verification code.');
    }

    public function unlock(int $actor,int $target,#[\SensitiveParameter] string $password,#[\SensitiveParameter] string $code): bool
    {
        if(!RequestThrottle::allow('administrator_recovery',(string)$actor,3,900))throw new DomainException('Recovery is temporarily rate limited. Wait fifteen minutes.');
        return Database::transaction(function(PDO $pdo)use($actor,$target,$password,$code):bool{
            $q=$pdo->prepare('SELECT id FROM users WHERE id IN (?,?) ORDER BY id FOR UPDATE');$q->execute([$actor,$target]);$q->fetchAll();
            $user=(new User())->findById($target,true);
            if(!$user||!User::isLocked($user))throw new DomainException('Choose a currently locked account.');
            if(!$this->mfa->administratorRecovery($pdo,$actor,$password,$code)){
                $this->logs->auth($actor,'administrator_recovery','failure');return false;
            }
            $pdo->prepare('UPDATE users SET failed_attempts=0,locked_until=NULL,lock_requires_admin=0,updated_at=NOW() WHERE id=?')->execute([$target]);
            $pdo->prepare('UPDATE locked_accounts SET unlocked_at=NOW(),unlocked_by=? WHERE user_id=? AND locked_until=? AND unlocked_at IS NULL')->execute([$actor,$target,$user['locked_until']]);
            $pdo->prepare('INSERT INTO audit_logs(actor_id,action,target_type,target_id,details) VALUES (?,"account_unlocked","user",?,"Security / verified private-console Administrator recovery")')->execute([$actor,$target]);
            $this->logs->auth($actor,'administrator_recovery','success');
            return true;
        });
    }
}
