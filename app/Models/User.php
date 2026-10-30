<?php

namespace App\Models;

use App\Core\Database;
use PDO;

final class User
{
    public function findByIdentifier(string $identifier): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email OR username = :username LIMIT 1');
        $stmt->execute(['email' => strtolower($identifier), 'username' => $identifier]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findById(int $id, bool $forUpdate = false): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function isLocked(array $user): bool
    {
        return (int) ($user['lock_requires_admin'] ?? 0) === 1
            || (!empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time());
    }

    public static function canAuthenticate(?array $user): bool
    {
        return $user !== null
            && ($user['status'] ?? '') === 'active'
            && in_array($user['role'] ?? '', ['customer', 'catalog_manager', 'admin'], true)
            && !self::isLocked($user);
    }

    public function emailExists(string $email): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower($email)]);
        return (bool) $stmt->fetchColumn();
    }

    public function usernameExists(string $username): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        return (bool) $stmt->fetchColumn();
    }

    public function createCustomer(array $data): int
    {
        $create = function (PDO $pdo) use ($data): int {
            $stmt = $pdo->prepare('INSERT INTO users (email, username, password_hash, role, status, failed_attempts, mfa_enabled, created_at, updated_at, password_changed_at) VALUES (:email, :username, :password_hash, "customer", "pending", 0, :mfa_enabled, NOW(), NOW(), NOW())');
            $stmt->execute([
                'email' => strtolower($data['email']),
                'username' => $data['username'],
                'password_hash' => $data['password_hash'],
                // Customers use email activation. Staff accounts require MFA at login.
                'mfa_enabled' => 0,
            ]);
            $id = (int) $pdo->lastInsertId();
            $profile = $pdo->prepare('INSERT INTO customer_profiles (user_id, full_name, contact_no, delivery_address, created_at, updated_at) VALUES (:user_id, :full_name, :contact_no, NULL, NOW(), NOW())');
            $profile->execute([
                'user_id' => $id,
                'full_name' => $data['full_name'],
                'contact_no' => $data['contact_no'] ?: null,
            ]);
            \App\Services\PiiStorage::write($pdo,'customer_profiles',$id,['contact_no'=>$data['contact_no']?:null,'delivery_address'=>null]);
            \App\Services\AuditService::record($pdo,null,'account.registered','user',$id,['role'=>'customer','verification'=>'pending']);
            return $id;
        };
        $pdo = Database::connection();
        return $pdo->inTransaction() ? $create($pdo) : Database::transaction($create);
    }

    public function createStaff(string $role, string $email, string $username, string $passwordHash, string $fullName, ?int $actorId = null): int
    {
        if (!in_array($role, ['admin', 'catalog_manager'], true)) {
            throw new \InvalidArgumentException('Invalid staff role.');
        }
        $create = function (PDO $pdo) use ($role, $email, $username, $passwordHash, $fullName, $actorId): int {
            if($actorId!==null){
                $actor=$this->findById($actorId,true);
                if(!self::canAuthenticate($actor)||$actor['role']!=='admin')throw new \DomainException('An active Administrator is required.');
            }
            $stmt = $pdo->prepare('INSERT INTO users (email, username, password_hash, role, status, failed_attempts, mfa_enabled, activated_at, created_at, updated_at, password_changed_at) VALUES (:email, :username, :password_hash, :role, "active", 0, 1, NOW(), NOW(), NOW(), NOW())');
            $stmt->execute([
                'email' => strtolower($email), 'username' => $username, 'password_hash' => $passwordHash, 'role' => $role
            ]);
            $id = (int) $pdo->lastInsertId();
            $profile = $pdo->prepare('INSERT INTO staff_profiles (user_id, full_name, created_at, updated_at) VALUES (:user_id, :full_name, NOW(), NOW())');
            $profile->execute(['user_id' => $id, 'full_name' => $fullName]);
            \App\Services\AuditService::record($pdo,$actorId,'staff.created','user',$id,['role'=>$role,'channel'=>$actorId===null?'private_staff_setup':'admin_staff']);
            return $id;
        };
        $pdo=Database::connection();
        return $pdo->inTransaction() ? $create($pdo) : Database::transaction($create);
    }

    public function activate(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET status = "active", activated_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function resetFailures(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL, lock_requires_admin = 0, last_login_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function recordFailure(int $id): array
    {
        $record = function (PDO $pdo) use ($id): array {
            $user = $this->findById($id, true);
            if (!$user) throw new \DomainException('Account unavailable.');
            if (self::isLocked($user)) {
                return ['attempts' => (int) $user['failed_attempts'], 'locked_until' => $user['locked_until'], 'new_lock' => false];
            }
            $policy = \App\Services\SecurityPolicy::values();
            $attempts = ($user['locked_until'] === null ? (int) $user['failed_attempts'] : 0) + 1;
            $attempts = min(255, $attempts);
            $lockedUntil = $attempts >= $policy['max_login_attempts']
                ? date('Y-m-d H:i:s', time() + $policy['lockout_minutes'] * 60) : null;
            $pdo->prepare('UPDATE users SET failed_attempts=:attempts, locked_until=:until, lock_requires_admin=:persistent, updated_at=NOW() WHERE id=:id')
                ->execute(['attempts' => $attempts, 'until' => $lockedUntil, 'persistent' => $lockedUntil !== null ? 1 : 0, 'id' => $id]);
            if ($lockedUntil !== null) {
                $pdo->prepare('INSERT INTO locked_accounts (user_id, username_snapshot, failed_attempts, locked_at, locked_until)
                    VALUES (:user, :username, :attempts, NOW(), :until)')->execute([
                        'user' => $id, 'username' => $user['username'], 'attempts' => $attempts, 'until' => $lockedUntil,
                    ]);
                \App\Services\AuditService::record($pdo,null,'account.locked','user',$id,['failed_attempts'=>$attempts]);
            }
            return ['attempts' => $attempts, 'locked_until' => $lockedUntil, 'new_lock' => $lockedUntil !== null];
        };
        $pdo = Database::connection();
        return $pdo->inTransaction() ? $record($pdo) : Database::transaction($record);
    }

    public function profileFor(int $id): array
    {
        $pdo = Database::connection();
        $staff=\App\Services\StaffProfileMigration::installed($pdo);
        $protected=\App\Services\PiiMigration::installed($pdo,'customer_profiles')?', cp.contact_no_encrypted, cp.delivery_address_encrypted, cp.pii_encrypted':'';
        $contact=$staff?'COALESCE(cp.contact_no,sp.contact_no) AS contact_no':'cp.contact_no';
        $image=$staff?'COALESCE(cp.profile_image,sp.profile_image) AS profile_image':'cp.profile_image';
        $stmt = $pdo->prepare('SELECT u.id, u.email, u.username, u.role, u.status, u.created_at, COALESCE(cp.full_name, sp.full_name, u.username) AS full_name, '.$contact.', cp.delivery_address, '.$image.$protected.' FROM users u LEFT JOIN customer_profiles cp ON cp.user_id = u.id LEFT JOIN staff_profiles sp ON sp.user_id = u.id WHERE u.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $profile=$stmt->fetch() ?: [];
        return $profile&&$profile['role']==='customer'?\App\Services\PiiStorage::read('customer_profiles',$id,$profile):$profile;
    }

    public function emailExistsForOtherUser(string $email, int $userId): bool
{
    $stmt = Database::connection()->prepare(
        'SELECT 1
         FROM users
         WHERE email = :email
           AND id <> :user_id
         LIMIT 1'
    );

    $stmt->execute([
        'email' => strtolower(trim($email)),
        'user_id' => $userId,
    ]);

    return (bool) $stmt->fetchColumn();
}

public function updateCustomerProfile(
    int $userId,
    string $fullName,
    string $email,
    string $contactNo,
    ?string $deliveryAddress,
    ?string $username = null
): void {
    $update = function (PDO $pdo) use (
        $userId,
        $fullName,
        $email,
        $contactNo,
        $deliveryAddress,
        $username
    ): void {
        $user=$this->findById($userId,true);
        if(!self::canAuthenticate($user)||$user['role']!=='customer')throw new \DomainException('An active customer account is required.');
        if(trim($fullName)===''||mb_strlen($fullName)>120||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190
            ||trim($contactNo)===''||strlen($contactNo)>25||mb_strlen($deliveryAddress??'')>500)throw new \DomainException('Profile values exceed the supported limits.');
        $before=$this->profileFor($userId);$fields=[];
        $username=$username??$user['username'];
        if(!preg_match('/^[A-Za-z0-9._-]{4,40}$/D',$username))throw new \DomainException('Use 4–40 letters, numbers, dots, underscores, or hyphens.');
        if($username!==$user['username']){
            $duplicate=$pdo->prepare('SELECT id FROM users WHERE username=:username AND id<>:id LIMIT 1');
            $duplicate->execute(['username'=>$username,'id'=>$userId]);
            if($duplicate->fetchColumn())throw new \DomainException('That username is already in use.');
            $fields[]='username';
        }
        foreach(['full_name'=>$fullName,'email'=>strtolower(trim($email)),'contact_no'=>$contactNo,'delivery_address'=>$deliveryAddress] as $field=>$value)if((string)($before[$field]??'')!==(string)$value)$fields[]=$field;
        if(!$fields)return;
        $userStmt = $pdo->prepare(
            'UPDATE users
             SET email = :email, username = :username,
                 updated_at = NOW()
             WHERE id = :user_id
               AND role = "customer"'
        );

        $userStmt->execute([
            'email' => strtolower(trim($email)),
            'username' => $username,
            'user_id' => $userId,
        ]);

        $profileStmt = $pdo->prepare(
            'UPDATE customer_profiles
             SET full_name = :full_name,
                 contact_no = :contact_no,
                 delivery_address = :delivery_address,
                 updated_at = NOW()
             WHERE user_id = :user_id'
        );

        $profileStmt->execute([
            'full_name' => trim($fullName),
            'contact_no' => trim($contactNo) !== '' ? trim($contactNo) : null,
            'delivery_address' => trim($deliveryAddress ?? '') !== ''
                ? trim($deliveryAddress)
                : null,
            'user_id' => $userId,
        ]);
        \App\Services\PiiStorage::write($pdo,'customer_profiles',$userId,['contact_no'=>trim($contactNo)?:null,'delivery_address'=>trim($deliveryAddress??'')?:null]);
        \App\Services\AuditService::record($pdo,$userId,'profile.updated','user',$userId,['fields'=>$fields]);
    };
    $pdo=Database::connection();
    if($pdo->inTransaction())$update($pdo);else Database::transaction($update);
}

    public function updateProfileImage(int $userId, string $profileImage): ?string
{
    return Database::transaction(function(PDO $pdo)use($userId,$profileImage):?string{
        $user=$this->findById($userId,true);
        if(!self::canAuthenticate($user)||$user['role']!=='customer')throw new \DomainException('An active customer account is required.');
        if(!preg_match('~^uploads/profile/[a-f0-9]{32}\.(jpg|png|webp)$~D',$profileImage))throw new \InvalidArgumentException('Invalid profile image path.');
        $before=$this->profileFor($userId)['profile_image']??null;
        $stmt = $pdo->prepare(
        'UPDATE customer_profiles
         SET profile_image = :profile_image,
             updated_at = NOW()
         WHERE user_id = :user_id'
    );

    $stmt->execute([
        'profile_image' => $profileImage,
        'user_id' => $userId,
    ]);
        \App\Services\AuditService::record($pdo,$userId,'profile.image_updated','user',$userId);
        return $before;
    });
}
}
