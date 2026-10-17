<?php

declare(strict_types=1);
namespace App\Services;

use DomainException;
use PDO;

final class PasswordMigration
{
    public function run(PDO $pdo, bool $apply): array
    {
        if ($pdo->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="users"')->fetchColumn() !== 'InnoDB') throw new DomainException('InnoDB users required.');
        $columns=$pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_UNIQUE);
        $definitions=['password_changed_at'=>'DATETIME NULL DEFAULT NULL','password_version'=>"CHAR(64) NOT NULL DEFAULT ''"];
        $pending=0;
        foreach($definitions as $name=>$definition){
            if(isset($columns[$name])){
                $column=$columns[$name];
                if($name==='password_changed_at' ? $column['Type']!=='datetime'||$column['Null']!=='YES'||$column['Default']!==null
                    : $column['Type']!=='char(64)'||$column['Null']!=='NO'||$column['Default']!=='') throw new DomainException('Unexpected password-security schema.');
            } else {
                $pending++;
                // Additive DDL implicitly commits; existing passwords/timestamps are not backfilled.
                if($apply)$pdo->exec('ALTER TABLE users ADD COLUMN '.$name.' '.$definition);
            }
        }
        return ['pending_additions'=>$pending,'applied'=>$apply?$pending:0,'existing_accounts_expired'=>0];
    }
}
