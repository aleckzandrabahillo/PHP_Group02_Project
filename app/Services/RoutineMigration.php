<?php
declare(strict_types=1);

namespace App\Services;

use DomainException;
use PDO;

final class RoutineMigration
{
    public function run(PDO $pdo, bool $apply): array
    {
        foreach (['users', 'hair_profiles', 'routines', 'routine_items', 'audit_logs'] as $table) {
            $stmt = $pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
            $stmt->execute([$table]);
            if ($stmt->fetchColumn() !== 'InnoDB') throw new DomainException('Existing profile/routine tables must use InnoDB.');
        }
        $engine = $pdo->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="routine_contexts"')->fetchColumn();
        if ($engine !== false) {
            if ($engine !== 'InnoDB') throw new DomainException('Unexpected routine context engine.');
            $this->validate($pdo);
            return ['pending_additions' => 0, 'applied' => 0, 'rows_updated' => 0];
        }
        if ($apply) {
            $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql');
            if (!preg_match('/CREATE TABLE IF NOT EXISTS routine_contexts \([\s\S]*?\) ENGINE=InnoDB;/', $schema, $match)) throw new DomainException('Routine context definition unavailable.');
            // DDL implicitly commits: one additive table only; never rewrite existing profiles or routines.
            $pdo->exec($match[0]);
            $this->validate($pdo);
        }
        return ['pending_additions' => 1, 'applied' => $apply ? 1 : 0, 'rows_updated' => 0];
    }

    private function validate(PDO $pdo): void
    {
        $expected = ['routine_id' => ['bigint', null], 'profile_version' => ['char',64], 'plan_fingerprint' => ['char',64],
            'rules_version' => ['varchar',20], 'explanation_json' => ['longtext',null], 'created_at' => ['datetime',null]];
        $rows = $pdo->query('SELECT COLUMN_NAME,DATA_TYPE,COLUMN_TYPE,CHARACTER_MAXIMUM_LENGTH,IS_NULLABLE,COLUMN_DEFAULT
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="routine_contexts"')->fetchAll();
        if (count($rows) !== count($expected)) throw new DomainException('Unexpected routine context columns.');
        foreach ($rows as $row) {
            $want = $expected[$row['COLUMN_NAME']] ?? null;
            if (!$want || ($row['DATA_TYPE'] !== $want[0] && !($row['COLUMN_NAME'] === 'explanation_json' && $row['DATA_TYPE'] === 'json'))
                || $row['IS_NULLABLE'] !== 'NO' || ($want[1] !== null && (int) $row['CHARACTER_MAXIMUM_LENGTH'] !== $want[1])
                || ($row['COLUMN_NAME'] === 'routine_id' && !str_contains($row['COLUMN_TYPE'],'unsigned'))) throw new DomainException('Unexpected routine context column.');
            if ($row['COLUMN_NAME'] === 'explanation_json' && $row['DATA_TYPE'] !== 'json') {
                $definition = $pdo->query('SHOW CREATE TABLE routine_contexts')->fetch(PDO::FETCH_NUM)[1];
                if (!preg_match('/json_valid\s*\(\s*\x60?explanation_json\x60?\s*\)/i', $definition)) throw new DomainException('Routine explanations need JSON validation.');
            }
            if ($row['COLUMN_NAME'] === 'created_at' && !str_contains(strtolower((string) $row['COLUMN_DEFAULT']), 'current_timestamp')) {
                throw new DomainException('Unexpected routine context timestamp default.');
            }
        }
        $indexes = $pdo->query('SHOW INDEX FROM routine_contexts')->fetchAll();
        if (count($indexes) !== 1 || $indexes[0]['Key_name'] !== 'PRIMARY' || $indexes[0]['Column_name'] !== 'routine_id') throw new DomainException('Unexpected routine context index.');
        $keys = $pdo->query('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE
            FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r
            ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME
            WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME="routine_contexts"')->fetchAll();
        if (count($keys) !== 1 || $keys[0]['COLUMN_NAME'] !== 'routine_id' || $keys[0]['REFERENCED_TABLE_NAME'] !== 'routines'
            || $keys[0]['REFERENCED_COLUMN_NAME'] !== 'id' || $keys[0]['DELETE_RULE'] !== 'CASCADE') throw new DomainException('Unexpected routine context relationship.');
    }
}
