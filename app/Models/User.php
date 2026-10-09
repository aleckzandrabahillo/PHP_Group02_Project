<?php

namespace App\Models;

use App\Core\Database;
use App\Services\SecuritySettings;
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

    public function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
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
        return Database::transaction(function (PDO $pdo) use ($data): int {
            $stmt = $pdo->prepare('INSERT INTO users (email, username, password_hash, role, status, failed_attempts, mfa_enabled, created_at, updated_at) VALUES (:email, :username, :password_hash, "customer", "pending", 0, :mfa_enabled, NOW(), NOW())');
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
            return $id;
        });
    }

    public function createStaff(string $role, string $email, string $username, string $passwordHash, string $fullName): int
    {
        if (!in_array($role, ['admin', 'catalog_manager'], true)) {
            throw new \InvalidArgumentException('Invalid staff role.');
        }
        return Database::transaction(function (PDO $pdo) use ($role, $email, $username, $passwordHash, $fullName): int {
            $stmt = $pdo->prepare('INSERT INTO users (email, username, password_hash, role, status, failed_attempts, mfa_enabled, activated_at, created_at, updated_at) VALUES (:email, :username, :password_hash, :role, "active", 0, 1, NOW(), NOW(), NOW())');
            $stmt->execute([
                'email' => strtolower($email), 'username' => $username, 'password_hash' => $passwordHash, 'role' => $role
            ]);
            $id = (int) $pdo->lastInsertId();
            $profile = $pdo->prepare('INSERT INTO staff_profiles (user_id, full_name, created_at, updated_at) VALUES (:user_id, :full_name, NOW(), NOW())');
            $profile->execute(['user_id' => $id, 'full_name' => $fullName]);
            return $id;
        });
    }

    public function activate(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET status = "active", activated_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function resetFailures(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function recordFailure(int $id): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT failed_attempts FROM users WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $attempts = (int) $stmt->fetchColumn() + 1;
            $max = SecuritySettings::get('max_login_attempts');
            $lockedUntil = null;
            if ($attempts >= $max) {
                $minutes = SecuritySettings::get('lockout_minutes');
                $lockedUntil = date('Y-m-d H:i:s', time() + ($minutes * 60));
            }
            $update = $pdo->prepare('UPDATE users SET failed_attempts = :attempts, locked_until = :locked_until, updated_at = NOW() WHERE id = :id');
            $update->execute(['attempts' => $attempts, 'locked_until' => $lockedUntil, 'id' => $id]);
            $pdo->commit();
            return ['attempts' => $attempts, 'locked_until' => $lockedUntil];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function profileFor(int $id): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT u.id, u.email, u.username, u.role, u.status, u.created_at, COALESCE(cp.full_name, sp.full_name, u.username) AS full_name, cp.contact_no, cp.delivery_address, cp.profile_image FROM users u LEFT JOIN customer_profiles cp ON cp.user_id = u.id LEFT JOIN staff_profiles sp ON sp.user_id = u.id WHERE u.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: [];
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
    ?string $deliveryAddress
): void {
    Database::transaction(function (PDO $pdo) use (
        $userId,
        $fullName,
        $email,
        $contactNo,
        $deliveryAddress
    ): void {
        $userStmt = $pdo->prepare(
            'UPDATE users
             SET email = :email,
                 updated_at = NOW()
             WHERE id = :user_id
               AND role = "customer"'
        );

        $userStmt->execute([
            'email' => strtolower(trim($email)),
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
    });
}

public function updateProfileImage(int $userId, string $profileImage): void
{
    $pdo = Database::connection();

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
}

public function listAccounts(array $roles, string $q, string $status, int $limit, int $offset): array
{
    $p = []; $in = [];
    foreach (array_values($roles) as $i => $r) { $in[] = ":r$i"; $p["r$i"] = $r; }
    $from = 'FROM users u LEFT JOIN customer_profiles cp ON cp.user_id=u.id LEFT JOIN staff_profiles sp ON sp.user_id=u.id
             WHERE u.role IN (' . implode(',', $in) . ')';
    if ($q !== '') {
        $from .= ' AND (u.email LIKE :q1 OR u.username LIKE :q2 OR COALESCE(cp.full_name, sp.full_name) LIKE :q3)';
        $p += ['q1'=>"%$q%", 'q2'=>"%$q%", 'q3'=>"%$q%"];
    }
    if (in_array($status, ['pending','active','inactive'], true)) { $from .= ' AND u.status = :st'; $p['st'] = $status; }

    $pdo = Database::connection();
    $c = $pdo->prepare("SELECT COUNT(*) $from"); $c->execute($p);
    $s = $pdo->prepare("SELECT u.id,u.email,u.username,u.role,u.status,u.failed_attempts,u.locked_until,u.last_login_at,u.created_at,
                        COALESCE(cp.full_name, sp.full_name, u.username) AS full_name $from
                        ORDER BY u.created_at DESC LIMIT " . (int)$limit . ' OFFSET ' . (int)$offset);
    $s->execute($p);
    return ['rows'=>$s->fetchAll(), 'total'=>(int)$c->fetchColumn()];
}

public function setStatus(int $id, string $status): void
{
    Database::connection()->prepare('UPDATE users SET status=:s, updated_at=NOW() WHERE id=:id')
        ->execute(['s'=>$status, 'id'=>$id]);
}

public function unlock(int $id): bool
{
    $st = Database::connection()->prepare(
        'UPDATE users SET failed_attempts=0, locked_until=NULL, updated_at=NOW()
         WHERE id=:id AND (locked_until IS NOT NULL OR failed_attempts>0)');
    $st->execute(['id'=>$id]);
    return $st->rowCount() > 0;
}

public function changeStaffRole(int $id, string $role): void   // admin <-> catalog_manager only
{
    Database::connection()->prepare(
        'UPDATE users SET role=:r, updated_at=NOW() WHERE id=:id AND role IN ("admin","catalog_manager")'
    )->execute(['r'=>$role, 'id'=>$id]);
}

public function activeAdminCount(): int
{
    return (int) Database::connection()->query('SELECT COUNT(*) FROM users WHERE role="admin" AND status="active"')->fetchColumn();
}

public function updateStaff(int $actorId, int $targetId, ?string $newRole, ?string $newStatus): array
{
    $staffRoles = ['admin', 'catalog_manager'];
    if ($newRole !== null && !in_array($newRole, $staffRoles, true)) return ['ok'=>false, 'message'=>'Invalid role.'];
    if ($newStatus !== null && !in_array($newStatus, ['active', 'inactive'], true)) return ['ok'=>false, 'message'=>'Invalid status.'];

    return Database::transaction(function (PDO $pdo) use ($actorId, $targetId, $newRole, $newStatus, $staffRoles): array {
        // Lock the active-admin set FIRST, in a fixed order, so concurrent requests serialize instead of deadlocking.
        $admins = $pdo->query('SELECT id FROM users WHERE role = "admin" AND status = "active" ORDER BY id FOR UPDATE')
                      ->fetchAll(PDO::FETCH_COLUMN);

        $s = $pdo->prepare('SELECT id, role, status FROM users WHERE id = :id FOR UPDATE');
        $s->execute(['id' => $targetId]);
        $t = $s->fetch();
        if (!$t) return ['ok'=>false, 'message'=>'Account not found.'];

        // Rule 1: customers are never changed here
        if (!in_array($t['role'], $staffRoles, true)) {
            return ['ok'=>false, 'message'=>'Customer accounts cannot be changed from the staff page.'];
        }

        $role   = $newRole   ?? $t['role'];
        $status = $newStatus ?? $t['status'];
        if ($role === $t['role'] && $status === $t['status']) return ['ok'=>false, 'message'=>'No changes to apply.'];

        // Rule 2: no self-demotion / self-deactivation
        if ($targetId === $actorId && ($role !== $t['role'] || $status !== 'active')) {
            return ['ok'=>false, 'message'=>'You cannot change your own role or deactivate your own account.'];
        }

        // Rule 3: never remove the last active admin
        $wasActiveAdmin   = $t['role'] === 'admin' && $t['status'] === 'active';
        $staysActiveAdmin = $role === 'admin' && $status === 'active';
        if ($wasActiveAdmin && !$staysActiveAdmin && count($admins) <= 1) {
            return ['ok'=>false, 'message'=>'At least one active administrator is required.'];
        }

        $pdo->prepare('UPDATE users SET role = :r, status = :s, updated_at = NOW() WHERE id = :id')
            ->execute(['r'=>$role, 's'=>$status, 'id'=>$targetId]);

        $changes = [];
        if ($role !== $t['role'])     $changes[] = "role: {$t['role']} → $role";
        if ($status !== $t['status']) $changes[] = "status: {$t['status']} → $status";
        return ['ok'=>true, 'message'=>'Staff account updated.', 'changes'=>$changes];
    });
}

// Customers get their own, role-locked method (replaces the earlier generic setStatus)
public function setCustomerStatus(int $id, string $status): bool
{
    if (!in_array($status, ['active', 'inactive'], true)) return false;
    $st = Database::connection()->prepare('UPDATE users SET status = :s, updated_at = NOW() WHERE id = :id AND role = "customer"');
    $st->execute(['s'=>$status, 'id'=>$id]);
    return $st->rowCount() > 0;
}
}