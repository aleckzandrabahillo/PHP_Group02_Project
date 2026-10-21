<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Core\StaffValidator;
use App\Models\User;
use DomainException;
use InvalidArgumentException;
use PDO;

final class AdminAccounts
{
    public function listing(bool $staff, array $input): array
    {
        $filters=[];
        foreach(['q','status','role'] as $key) {
            $v=$input[$key]??'';
            if(!is_string($v)||!mb_check_encoding($v,'UTF-8')||mb_strlen($v)>100||preg_match('/[\x00-\x1f\x7f]/',$v))throw new InvalidArgumentException('Use valid text within 100 characters for filters.');
            $filters[$key]=trim($v);
        }
        if(!in_array($filters['status'],['','active','inactive','pending','locked'],true)
            ||!in_array($filters['role'],$staff?['','admin','catalog_manager']:[''],true))throw new InvalidArgumentException('Choose a valid account filter.');
        $where=[$staff?'u.role IN ("admin","catalog_manager")':'u.role="customer"'];$params=[];
        $locked='(u.lock_requires_admin=1 OR u.locked_until>NOW())';
        if($filters['status']==='locked')$where[]=$locked;
        elseif($filters['status']!==''){$where[]='u.status=?';$params[]=$filters['status'];}
        if($filters['role']!==''){$where[]='u.role=?';$params[]=$filters['role'];}
        if($filters['q']!=='') {
            $q='%'.strtr($filters['q'],['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
            $where[]="(u.username LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!' OR COALESCE(p.full_name,'') LIKE ? ESCAPE '!')";
            array_push($params,$q,$q,$q);
        }
        $from=' FROM users u LEFT JOIN '.($staff?'staff_profiles':'customer_profiles').' p ON p.user_id=u.id WHERE '.implode(' AND ',$where);
        $pdo=Database::connection();$count=$pdo->prepare('SELECT COUNT(*)'.$from);$count->execute($params);$total=(int)$count->fetchColumn();
        $pages=max(1,(int)ceil($total/20));$raw=$input['page']??1;
        $page=(is_string($raw)||is_int($raw))?filter_var($raw,FILTER_VALIDATE_INT):false;
        $page=max(1,min($pages,1000000,$page===false?1:$page));
        $query=$pdo->prepare('SELECT u.id,u.username,u.email,u.role,u.status,u.locked_until,u.lock_requires_admin,u.created_at,COALESCE(p.full_name,u.username) full_name'.$from.' ORDER BY u.id DESC LIMIT 20 OFFSET '.(($page-1)*20));
        $query->execute($params);
        return ['items'=>$query->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages,'filters'=>$filters];
    }

    public function create(int $actorId, #[\SensitiveParameter] array $input): int
    {
        $checked=StaffValidator::validate($input);
        if($checked['errors'])throw new DomainException('Review the staff account details.');
        $v=$checked['values'];
        // Hash before taking account locks; plaintext never enters audit/old-input storage.
        $hash=password_hash($v['password'],PASSWORD_DEFAULT);
        return Database::transaction(function(PDO $pdo)use($actorId,$v,$hash):int{
            $this->lockAccounts($pdo,$actorId);
            if($error=\App\Core\Validator::passwordError($v['password']))throw new DomainException($error);
            $users=new User();
            if($users->emailExists($v['email'])||$users->usernameExists($v['username']))throw new DomainException('That email or username is already registered.');
            return $users->createStaff($v['role'],$v['email'],$v['username'],$hash,$v['full_name'],$actorId);
        });
    }

    public function change(int $actorId,int $targetId,string $kind,string $expected,string $next): void
    {
        if($targetId<1||!in_array($kind,['status','role'],true))throw new DomainException('Choose an account action.');
        if(($kind==='status'&&(!in_array($expected,['active','inactive'],true)||!in_array($next,['active','inactive'],true)))
            ||($kind==='role'&&(!in_array($expected,['admin','catalog_manager'],true)||!in_array($next,['admin','catalog_manager'],true))))
            throw new DomainException('Choose an available account update.');
        Database::transaction(function(PDO $pdo)use($actorId,$targetId,$kind,$expected,$next):void{
            $admins=$this->lockAccounts($pdo,$actorId,$targetId);
            $target=(new User())->findById($targetId,true);
            if(!$target)throw new DomainException('Account not found.');
            if($actorId===$targetId)throw new DomainException('Use another Administrator to change your own access.');
            if($kind==='role'&&!in_array($target['role'],['admin','catalog_manager'],true))throw new DomainException('Customer roles cannot be changed here.');
            if($target[$kind]!==$expected)throw new DomainException('This account changed. Refresh before updating it.');
            if($next===$expected)return;
            $eligible=array_filter($admins,static fn($u)=>User::canAuthenticate($u));
            if($target['role']==='admin'&&User::canAuthenticate($target)&&count($eligible)<=1
                &&(($kind==='status'&&$next!=='active')||($kind==='role'&&$next!=='admin')))
                throw new DomainException('Keep at least one active, unlocked Administrator.');
            // Revoke established sessions and outstanding proofs even if access is later restored.
            $pdo->prepare('UPDATE users SET '.$kind.'=?,password_version=?,updated_at=NOW() WHERE id=?')->execute([$next,bin2hex(random_bytes(32)),$targetId]);
            $pdo->prepare('UPDATE otp_codes SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$targetId]);
            $pdo->prepare('UPDATE activation_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$targetId]);
            AuditService::record($pdo,$actorId,'account.'.$kind.'_changed','user',$targetId,['before'=>$expected,'after'=>$next,'role'=>$target['role']]);
        });
    }

    private function lockAccounts(PDO $pdo,int $actorId,?int $targetId=null): array
    {
        // Every access mutation takes the Administrator set first, serializing competing demotions.
        $admins=$pdo->query('SELECT id,role,status,locked_until,lock_requires_admin FROM users WHERE role="admin" ORDER BY id FOR UPDATE')->fetchAll();
        $actor=(new User())->findById($actorId,true);
        if(!User::canAuthenticate($actor)||$actor['role']!=='admin')throw new DomainException('An active Administrator is required.');
        if($targetId!==null&&(new User())->findById($targetId,true)===null)throw new DomainException('Account not found.');
        return $admins;
    }
}
