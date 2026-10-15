<?php

declare(strict_types=1);

namespace App\Services;

use DomainException;
use PDO;

final class SecurityMigration
{
    public function run(PDO $pdo, bool $apply): array
    {
        foreach (['users', 'security_settings', 'audit_logs'] as $table) {
            $stmt = $pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
            $stmt->execute([$table]);
            if ($stmt->fetchColumn() !== 'InnoDB') throw new DomainException('Existing security tables must use InnoDB.');
        }
        $stmt = $pdo->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="locked_accounts"');
        $engine = $stmt->fetchColumn();
        if ($engine !== false) {
            if ($engine !== 'InnoDB') throw new DomainException('Unexpected lock-history storage engine.');
            $this->validate($pdo);
            return ['pending_additions' => 0, 'applied' => 0, 'rows_updated' => 0];
        }
        if ($apply) {
            $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql');
            if (!preg_match('/CREATE TABLE IF NOT EXISTS locked_accounts \([\s\S]*?\) ENGINE=InnoDB;/', $schema, $match)) {
                throw new DomainException('The canonical lock-history definition is unavailable.');
            }
            // MySQL DDL commits implicitly. This creates only the new table; it never rewrites users or history.
            $pdo->exec($match[0]);
            $this->validate($pdo);
        }
        return ['pending_additions' => 1, 'applied' => $apply ? 1 : 0, 'rows_updated' => 0];
    }

    private function validate(PDO $pdo): void
    {
        $expected = [
            'id' => ['bigint', null, 'NO', true], 'user_id' => ['bigint', null, 'YES', true],
            'username_snapshot' => ['varchar', 40, 'NO', false], 'failed_attempts' => ['tinyint', null, 'NO', true],
            'locked_at' => ['datetime', null, 'NO', false], 'locked_until' => ['datetime', null, 'NO', false],
            'unlocked_at' => ['datetime', null, 'YES', false], 'unlocked_by' => ['bigint', null, 'YES', true],
        ];
        $rows = $pdo->query('SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, EXTRA
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="locked_accounts"')->fetchAll();
        if (count($rows) !== count($expected)) throw new DomainException('Unexpected lock-history columns.');
        foreach ($rows as $row) {
            $want = $expected[$row['COLUMN_NAME']] ?? null;
            if (!$want || $row['DATA_TYPE'] !== $want[0] || $row['IS_NULLABLE'] !== $want[2]
                || ($want[1] !== null && (int) $row['CHARACTER_MAXIMUM_LENGTH'] !== $want[1])
                || str_contains($row['COLUMN_TYPE'], 'unsigned') !== $want[3]
                || ($row['COLUMN_NAME'] === 'id' && !str_contains($row['EXTRA'], 'auto_increment'))) {
                throw new DomainException('An existing lock-history column differs from the reviewed definition.');
            }
        }
        $indexes = $pdo->query('SHOW INDEX FROM locked_accounts')->fetchAll();
        $byName = [];
        foreach ($indexes as $index) $byName[$index['Key_name']][] = $index['Column_name'];
        if (($byName['PRIMARY'] ?? []) !== ['id'] || ($byName['idx_locked_accounts_user_date'] ?? []) !== ['user_id', 'locked_at']) {
            throw new DomainException('Unexpected lock-history indexes.');
        }
        $foreignKeys = $pdo->query('SELECT k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE
            FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r
            ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME
            WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME="locked_accounts"')->fetchAll();
        if (count($foreignKeys) !== 2) throw new DomainException('Unexpected lock-history relationships.');
        foreach ($foreignKeys as $key) {
            if (!in_array($key['COLUMN_NAME'], ['user_id', 'unlocked_by'], true) || $key['REFERENCED_TABLE_NAME'] !== 'users'
                || $key['REFERENCED_COLUMN_NAME'] !== 'id' || $key['DELETE_RULE'] !== 'SET NULL') {
                throw new DomainException('Unexpected lock-history relationship.');
            }
        }
    }
}
