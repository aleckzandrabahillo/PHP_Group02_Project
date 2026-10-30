<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Models\User;
use PDO;

final class ActivationService
{
    public function __construct(private readonly OtpService $otp, private readonly MailService $mail, private readonly LogService $logs) {}

    /** Registration step one sends a code only. It never creates an activation link. */
    public function issue(int $userId): array
    {
        $issue=function(PDO $pdo)use($userId):array{
            $user=$this->pending($userId);
            $result=$this->otp->issue($userId,$user['email'],'activation');
            if($result['issued'])$pdo->prepare('UPDATE activation_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$userId]);
            return $result;
        };
        $pdo=Database::connection();
        return $pdo->inTransaction()?$issue($pdo):Database::transaction($issue);
    }

    /** The account stays pending: only a correct, unused email code can issue step two. */
    public function verifyAndSendLink(int $userId, #[\SensitiveParameter] string $code): array
    {
        $verify=function(PDO $pdo)use($userId,$code):array{
            $user=$this->pending($userId);
            $result=$this->otp->verify($userId,'activation',$code);
            if(!$result['ok'])return $result;
            $this->sendLink($pdo,$user);
            AuditService::record($pdo,$userId,'registration.email_verified','user',$userId);
            return $result+['activation_link'=>true];
        };
        $pdo=Database::connection();
        return $pdo->inTransaction()?$verify($pdo):Database::transaction($verify);
    }

    /** Resend requires this session's short-lived, already verified registration flow. */
    public function resendLink(array $flow): array
    {
        return Database::transaction(function(PDO $pdo)use($flow):array{
            $user=$this->pending((int)($flow['user_id']??0));
            if(($flow['purpose']??'')!=='activation_link'||($flow['verified_at']??0)>time()
                ||($flow['verified_at']??0)<time()-1800||($flow['expires_at']??0)<time()
                ||($flow['email']??'')!==$user['email']
                ||!hash_equals((string)($user['password_version']??''),(string)($flow['password_version']??''))) {
                throw new \DomainException('Please sign in and verify your email again.');
            }
            $q=$pdo->prepare('SELECT TIMESTAMPDIFF(SECOND,created_at,NOW()) FROM activation_tokens WHERE user_id=? ORDER BY id DESC LIMIT 1');
            $q->execute([$user['id']]);$elapsed=$q->fetchColumn();$wait=max(1,(int)env('OTP_RESEND_SECONDS',60));
            if($elapsed!==false&&(int)$elapsed<$wait)return ['issued'=>false,'reusable'=>true,'retry_after'=>$wait-(int)$elapsed];
            $this->sendLink($pdo,$user);
            return ['issued'=>true,'reusable'=>true,'retry_after'=>0];
        });
    }

    public function consume(#[\SensitiveParameter] mixed $token): bool
    {
        return $this->consumeAccount($token) !== null;
    }

    public function consumeAccount(#[\SensitiveParameter] mixed $token): ?array
    {
        if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token))return null;
        return Database::transaction(function(PDO $pdo)use($token):?array{
            // Domain separation rejects old combined code/link messages, without changing rows.
            $digest=self::digest($token);
            $q=$pdo->prepare('SELECT user_id FROM activation_tokens WHERE token_digest=?');$q->execute([$digest]);$id=$q->fetchColumn();
            if(!$id)return null;
            $user=(new User())->findById((int)$id,true);
            $q=$pdo->prepare('SELECT id,expires_at,used_at FROM activation_tokens WHERE user_id=? AND token_digest=? FOR UPDATE');$q->execute([$id,$digest]);$row=$q->fetch();
            if(!$user||$user['role']!=='customer'||$user['status']!=='pending'||User::isLocked($user)
                ||!$row||$row['used_at']!==null||strtotime($row['expires_at'])<=time())return null;
            (new User())->activate((int)$id);
            $pdo->prepare('UPDATE activation_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$id]);
            $pdo->prepare('UPDATE otp_codes SET used_at=NOW() WHERE user_id=? AND purpose="activation" AND used_at IS NULL')->execute([$id]);
            AuditService::record($pdo,(int)$id,'account_activated','user',(int)$id);
            $this->logs->auth((int)$id,'activation','success');
            return (new User())->findById((int)$id);
        });
    }

    private function pending(int $id): array
    {
        $user=(new User())->findById($id,true);
        if(!$user||$user['role']!=='customer'||$user['status']!=='pending'||User::isLocked($user))throw new \DomainException('Account verification is unavailable. Please sign in again.');
        return $user;
    }

    private function sendLink(PDO $pdo,array $user): void
    {
        $base=rtrim((string)env('APP_URL',''),'/');$url=parse_url($base);
        if(!$url||isset($url['user'])||isset($url['pass'])||isset($url['query'])||isset($url['fragment'])
            ||!isset($url['host'])||!in_array($url['scheme']??'',['https','http'],true)
            ||(($url['scheme']??'')!=='https'&&(env('APP_ENV')!=='development'||!in_array($url['host'],['localhost','127.0.0.1','[::1]'],true))))throw new MailDeliveryException('Email delivery is temporarily unavailable. Please try again later.');
        $token=bin2hex(random_bytes(32));$id=(int)$user['id'];
        $pdo->prepare('UPDATE activation_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$id]);
        $pdo->prepare('INSERT INTO activation_tokens(user_id,token_digest,expires_at) VALUES (?,?,?)')->execute([$id,self::digest($token),date('Y-m-d H:i:s',time()+1800)]);
        $this->mail->sendActivation($user['email'],$base.'/activate#token='.$token);
        $this->logs->auth($id,'activation_link_sent','success');
    }

    private static function digest(#[\SensitiveParameter] string $token): string
    {
        return hash('sha256','avela:activation:otp-verified:v2:'.$token);
    }
}
