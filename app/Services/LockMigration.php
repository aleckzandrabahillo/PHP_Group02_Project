<?php
declare(strict_types=1);
namespace App\Services;
use DomainException;
use PDO;

final class LockMigration
{
    public function run(PDO $pdo, bool $apply): array
    {
        foreach (['users','locked_accounts','audit_logs'] as $table) {
            $q=$pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);
            if($q->fetchColumn()!=='InnoDB')throw new DomainException('Run the reviewed security migration first; InnoDB is required.');
        }
        $column=$pdo->query('SELECT DATA_TYPE,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="users" AND COLUMN_NAME="lock_requires_admin"')->fetch();
        if($column && ($column['DATA_TYPE']!=='tinyint'||!str_contains($column['COLUMN_TYPE'],'unsigned')||$column['IS_NULLABLE']!=='NO'||(string)$column['COLUMN_DEFAULT']!=='0'))throw new DomainException('Unexpected persistent-lock definition.');
        $engine=$pdo->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="security_rate_limits"')->fetchColumn();
        if($engine!==false && $engine!=='InnoDB')throw new DomainException('Unexpected rate-limit engine.');
        if($engine!==false)$this->validateRateTable($pdo);
        if($column && (int)$pdo->query('SELECT COUNT(*) FROM users WHERE lock_requires_admin NOT IN (0,1) OR (lock_requires_admin=1 AND locked_until IS NULL)')->fetchColumn()!==0)throw new DomainException('Unexpected persistent-lock values.');
        $pending=(int)!$column+(int)($engine===false);
        $backfill=(int)$pdo->query($column?'SELECT COUNT(*) FROM users WHERE locked_until>NOW() AND lock_requires_admin=0':'SELECT COUNT(*) FROM users WHERE locked_until>NOW()')->fetchColumn();
        if($apply){
            // Additive DDL implicitly commits. Never drop/restore tables as rollback.
            if(!$column)$pdo->exec('ALTER TABLE users ADD COLUMN lock_requires_admin TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER locked_until');
            if($engine===false)$pdo->exec('CREATE TABLE security_rate_limits (bucket_hash CHAR(64) NOT NULL PRIMARY KEY, attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0, reset_at DATETIME NOT NULL, INDEX idx_security_rate_reset(reset_at)) ENGINE=InnoDB');
            $pdo->exec('UPDATE users SET lock_requires_admin=1 WHERE locked_until>NOW() AND lock_requires_admin=0');
        }
        if($engine!==false||$apply){
            $this->validateRateTable($pdo);
        }
        return ['pending_additions'=>$pending,'applied'=>$apply?$pending:0,'active_locks_to_preserve'=>$backfill,'rows_updated'=>$apply?$backfill:0];
    }

    private function validateRateTable(PDO $pdo): void
    {
        $columns=$pdo->query('SELECT COLUMN_NAME,DATA_TYPE,COLUMN_TYPE,IS_NULLABLE,CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="security_rate_limits" ORDER BY ORDINAL_POSITION')->fetchAll();
        if(array_column($columns,'COLUMN_NAME')!==['bucket_hash','attempts','reset_at']
            || $columns[0]['DATA_TYPE']!=='char' || (int)$columns[0]['CHARACTER_MAXIMUM_LENGTH']!==64
            || $columns[1]['DATA_TYPE']!=='smallint' || !str_contains($columns[1]['COLUMN_TYPE'],'unsigned')
            || $columns[2]['DATA_TYPE']!=='datetime' || array_unique(array_column($columns,'IS_NULLABLE'))!==['NO'])throw new DomainException('Unexpected rate-limit columns.');
        $indexes=[];foreach($pdo->query('SHOW INDEX FROM security_rate_limits')->fetchAll() as $index)$indexes[$index['Key_name']][]=$index['Column_name'];
        if(count($indexes)!==2||($indexes['PRIMARY']??[])!==['bucket_hash']||($indexes['idx_security_rate_reset']??[])!==['reset_at'])throw new DomainException('Unexpected rate-limit indexes.');
    }
}
