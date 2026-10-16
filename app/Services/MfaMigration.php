<?php

declare(strict_types=1);

namespace App\Services;

use DomainException;
use PDO;

final class MfaMigration
{
    public function run(PDO $pdo, bool $apply): array
    {
        if ((new SecurityMigration())->run($pdo, false)['pending_additions'] !== 0) {
            throw new DomainException('Apply the account-security migration before authenticator enrollment.');
        }
        $engine=$pdo->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="users"')->fetchColumn();
        if($engine!=='InnoDB')throw new DomainException('The existing users table must use InnoDB.');
        $engine=$pdo->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="user_authenticators"')->fetchColumn();
        if($engine===false && $apply) {
            $schema=file_get_contents(dirname(__DIR__,2).'/database/schema.sql');
            if(!preg_match('/CREATE TABLE IF NOT EXISTS user_authenticators \([\s\S]*?\) ENGINE=InnoDB;/',$schema,$m))throw new DomainException('Canonical authenticator definition unavailable.');
            $pdo->exec($m[0]);
        }
        if($engine!==false || $apply)$this->validate($pdo);
        return ['pending_additions'=>$engine===false?1:0,'applied'=>$engine===false&&$apply?1:0,'rows_updated'=>0];
    }

    private function validate(PDO $pdo): void
    {
        $engine=$pdo->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="user_authenticators"')->fetchColumn();
        $rows=$pdo->query('SELECT COLUMN_NAME,DATA_TYPE,COLUMN_TYPE,IS_NULLABLE,CHARACTER_MAXIMUM_LENGTH,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="user_authenticators"')->fetchAll();
        $expected=['user_id'=>'bigint','secret_encrypted'=>'varchar','recovery_hashes_json'=>'text','last_used_step'=>'bigint','enabled_at'=>'datetime','credential_version'=>'char'];
        if($engine!=='InnoDB'||count($rows)!==count($expected))throw new DomainException('Unexpected authenticator table definition.');
        foreach($rows as $row) {
            $name=$row['COLUMN_NAME'];
            if(($expected[$name]??null)!==$row['DATA_TYPE']||$row['IS_NULLABLE']!==(in_array($name,['secret_encrypted','enabled_at'],true)?'YES':'NO')
                ||($name==='user_id'&&!str_contains($row['COLUMN_TYPE'],'unsigned'))
                ||($name==='secret_encrypted'&&(int)$row['CHARACTER_MAXIMUM_LENGTH']!==512)
                ||($name==='credential_version'&&(int)$row['CHARACTER_MAXIMUM_LENGTH']!==64)
                ||($name==='last_used_step'&&(string)$row['COLUMN_DEFAULT']!=='-1'))throw new DomainException('Unexpected authenticator column.');
        }
        $index=$pdo->query('SHOW INDEX FROM user_authenticators WHERE Key_name="PRIMARY"')->fetchAll();
        if(count($index)!==1||$index[0]['Column_name']!=='user_id')throw new DomainException('Unexpected authenticator primary key.');
        $fk=$pdo->query('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k
            JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME
            WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME="user_authenticators"')->fetchAll();
        if(count($fk)!==1||$fk[0]['COLUMN_NAME']!=='user_id'||$fk[0]['REFERENCED_TABLE_NAME']!=='users'||$fk[0]['REFERENCED_COLUMN_NAME']!=='id'||$fk[0]['DELETE_RULE']!=='CASCADE')throw new DomainException('Unexpected authenticator relationship.');
    }
}
