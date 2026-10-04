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
            $max = (int) env('MAX_LOGIN_ATTEMPTS', 5);
            $lockedUntil = null;
            if ($attempts >= $max) {
                $minutes = (int) env('LOCKOUT_MINUTES', 15);
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
        $stmt = $pdo->prepare('SELECT u.id, u.email, u.username, u.role, u.status, u.created_at, COALESCE(cp.full_name, sp.full_name, u.username) AS full_name, cp.contact_no, cp.delivery_address FROM users u LEFT JOIN customer_profiles cp ON cp.user_id = u.id LEFT JOIN staff_profiles sp ON sp.user_id = u.id WHERE u.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: [];
    }
}
