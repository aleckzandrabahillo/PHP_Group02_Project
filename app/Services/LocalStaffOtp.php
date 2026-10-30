<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Models\User;

/** Explicit local test-account exception; never a customer SMTP fallback. */
final class LocalStaffOtp
{
    public static function allows(int $userId,string $email,string $purpose): bool
    {
        if(env('MAIL_LOCAL_STAFF_OTP',false)!==true)return false;
        $url=parse_url((string)env('APP_URL',''));
        $port=filter_var(env('DB_PORT'),FILTER_VALIDATE_INT);
        if(env('APP_ENV')!=='development'||env('DB_HOST')!=='127.0.0.1'||!$port||in_array($port,[3306,13307],true)
            ||!preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D',(string)env('DB_DATABASE',''))
            ||!$url||!in_array($url['scheme']??'',['http','https'],true)||!in_array($url['host']??'',['localhost','127.0.0.1','[::1]'],true)
            ||isset($url['user'])||isset($url['pass'])||isset($url['query'])||isset($url['fragment']))throw new MailDeliveryException('Local staff verification is unavailable in this environment.');
        $list=(string)env('MAIL_LOCAL_STAFF_USERNAMES','');
        if(strlen($list)>1024||!preg_match('/^[A-Za-z0-9_.-]+(?:,[A-Za-z0-9_.-]+)*$/D',$list))throw new MailDeliveryException('Local staff verification is not configured correctly.');
        $pdo=Database::connection();$identity=$pdo->query('SELECT DATABASE() db,@@port port')->fetch();
        if($identity['db']!==env('DB_DATABASE')||(int)$identity['port']!==$port)throw new MailDeliveryException('Local staff verification is unavailable.');
        $user=(new User())->findById($userId);
        return User::canAuthenticate($user)&&in_array($purpose,['login','profile_update'],true)
            &&in_array($user['role'],['admin','catalog_manager'],true)
            &&in_array($user['username'],explode(',',$list),true)
            &&hash_equals(strtolower($user['email']),strtolower($email));
    }
}
