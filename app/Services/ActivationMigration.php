<?php

declare(strict_types=1);
namespace App\Services;

use DomainException;
use PDO;

final class ActivationMigration
{
    public const SQL = 'CREATE TABLE activation_tokens (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        token_digest CHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_activation_user (user_id),
        CONSTRAINT fk_activation_user FOREIGN KEY(user_id) REFERENCES users(id)
    ) ENGINE=InnoDB';

    public function run(PDO $pdo, bool $apply): array
    {
        $engine = $pdo->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="users"')->fetchColumn();
        if ($engine !== 'InnoDB') throw new DomainException('InnoDB users required.');
        $exists = $pdo->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="activation_tokens"')->fetchColumn();
        if ($exists !== false) {
            $columns = $pdo->query('SHOW COLUMNS FROM activation_tokens')->fetchAll(PDO::FETCH_UNIQUE);
            if ($exists !== 'InnoDB' || array_keys($columns) !== ['id','user_id','token_digest','expires_at','used_at','created_at']
                || !preg_match('/^bigint(?:\(20\))? unsigned$/D',$columns['id']['Type']) || $columns['id']['Key'] !== 'PRI' || $columns['id']['Extra'] !== 'auto_increment'
                || $columns['token_digest']['Type'] !== 'char(64)' || $columns['token_digest']['Key'] !== 'UNI'
                || $columns['token_digest']['Null'] !== 'NO'
                || !preg_match('/^bigint(?:\\(20\\))? unsigned$/D',$columns['user_id']['Type']) || $columns['user_id']['Null'] !== 'NO'
                || $columns['expires_at']['Type'] !== 'datetime' || $columns['expires_at']['Null'] !== 'NO'
                || $columns['used_at']['Type'] !== 'datetime' || $columns['used_at']['Null'] !== 'YES'
                || $columns['created_at']['Type'] !== 'datetime' || $columns['created_at']['Null'] !== 'NO') throw new DomainException('Unexpected activation schema; review before deployment.');
            $fk = $pdo->query('SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="activation_tokens" AND COLUMN_NAME="user_id" AND REFERENCED_TABLE_NAME="users" AND REFERENCED_COLUMN_NAME="id"')->fetchColumn();
            if ((int) $fk !== 1) throw new DomainException('Unexpected activation relationship.');
        } elseif ($apply) {
            // MySQL DDL implicitly commits; additive, not transactionally reversible.
            $pdo->exec(self::SQL);
        }
        return ['pending_additions' => $exists === false ? 1 : 0, 'applied' => $apply && $exists === false ? 1 : 0];
    }
}
