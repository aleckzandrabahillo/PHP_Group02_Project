<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\User;
use DomainException;
use PDO;

final class SecurityManagement
{
    public function savePolicy(int $actorId, array $input): void
    {
        $values = SecurityPolicy::validate($input);
        Database::transaction(function (PDO $pdo) use ($actorId, $values): void {
            $this->administrator($actorId);
            $before = $pdo->query('SELECT setting_key, setting_value FROM security_settings FOR UPDATE')
                ->fetchAll(PDO::FETCH_KEY_PAIR);
            $stmt = $pdo->prepare('INSERT INTO security_settings (setting_key, setting_value, updated_by)
                VALUES (:key, :value, :actor) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_by=VALUES(updated_by)');
            foreach ($values as $key => $value) {
                $stmt->execute(['key' => $key, 'value' => (string) $value, 'actor' => $actorId]);
            }
            $this->audit($pdo, $actorId, 'security_policy_updated', 'security_settings', null, [
                'before' => array_intersect_key($before, $values), 'after' => $values,
            ]);
        });
    }

    public function unlock(int $actorId, int $userId, string $expectedLock): void
    {
        Database::transaction(function (PDO $pdo) use ($actorId, $userId, $expectedLock): void {
            $this->administrator($actorId);
            $user = (new User())->findById($userId, true);
            if (!$user || !User::isLocked($user) || $user['locked_until'] !== $expectedLock) {
                throw new DomainException('The account lock changed or was released. Refresh the page before trying again.');
            }
            $pdo->prepare('UPDATE users SET failed_attempts=0, locked_until=NULL, lock_requires_admin=0, updated_at=NOW() WHERE id=?')
                ->execute([$userId]);
            $pdo->prepare('UPDATE locked_accounts SET unlocked_at=NOW(), unlocked_by=:actor
                WHERE user_id=:user AND locked_until=:until AND unlocked_at IS NULL')
                ->execute(['actor' => $actorId, 'user' => $userId, 'until' => $expectedLock]);
            $this->audit($pdo, $actorId, 'account_unlocked', 'user', $userId, ['failed_attempts' => (int) $user['failed_attempts']]);
        });
    }

    public function lockedAccounts(): array
    {
        return Database::connection()->query('SELECT id, username, role, status, failed_attempts, locked_until
            FROM users WHERE locked_until IS NOT NULL AND (lock_requires_admin=1 OR locked_until>NOW()) ORDER BY locked_until DESC, id DESC LIMIT 100')->fetchAll();
    }

    public function history(int $beforeId = 0): array
    {
        $stmt = Database::connection()->prepare('SELECT l.*, u.username AS unlocked_by_name FROM locked_accounts l
            LEFT JOIN users u ON u.id=l.unlocked_by WHERE (:all_rows=1 OR l.id < :before_id) ORDER BY l.id DESC LIMIT 21');
        $stmt->execute(['all_rows' => $beforeId === 0 ? 1 : 0, 'before_id' => $beforeId]);
        return $stmt->fetchAll();
    }

    private function administrator(int $id): void
    {
        $actor = (new User())->findById($id, true);
        if (!User::canAuthenticate($actor) || $actor['role'] !== 'admin') {
            throw new DomainException('An active Administrator account is required.');
        }
    }

    private function audit(PDO $pdo, int $actorId, string $action, string $type, ?int $id, array $details): void
    {
        // Audit and security changes commit together; an audit failure rolls everything back.
        $pdo->prepare('INSERT INTO audit_logs (actor_id, action, target_type, target_id, details)
            VALUES (:actor, :action, :type, :target, :details)')->execute([
                'actor' => $actorId, 'action' => $action, 'type' => $type, 'target' => $id,
                'details' => json_encode($details, JSON_THROW_ON_ERROR),
            ]);
    }
}
