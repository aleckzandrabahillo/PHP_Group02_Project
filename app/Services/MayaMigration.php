<?php
declare(strict_types=1);
namespace App\Services;

use DomainException;
use PDO;

final class MayaMigration
{
    public static function installed(PDO $pdo): bool
    {
        return (int)$pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ("maya_payments","maya_webhook_inbox") AND ENGINE="InnoDB"')->fetchColumn()===2;
    }

    public function run(PDO $pdo,bool $apply): array
    {
        if($pdo->inTransaction())throw new DomainException('Run additive migration outside business transactions.');
        foreach(['orders','order_items','products','audit_logs','order_status_events','order_email_outbox','order_delivery_details'] as $table){
            $q=$pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);
            if($q->fetchColumn()!=='InnoDB')throw new DomainException('Existing transactional checkout schema is required.');
        }
        $sql=file_get_contents(dirname(__DIR__,2).'/database/maya_sandbox.sql');$pending=[];
        foreach(['maya_payments','maya_webhook_inbox'] as $table){
            if(!preg_match('/CREATE TABLE IF NOT EXISTS '.$table.' \([\s\S]*?\) ENGINE=InnoDB;/',$sql,$m))throw new DomainException('Migration definition missing.');
            $definition=$m[0];
            $q=$pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);$engine=$q->fetchColumn();
            if($engine===false){$pending[$table]=$definition;continue;}
            // Compare canonical structural DDL, including column types, keys and FK deletion policies.
            $actual=$pdo->query('SHOW CREATE TABLE '.$table)->fetch(PDO::FETCH_NUM)[1];
            $normalize=static fn(string $ddl):string=>preg_replace('/\s*([(),])\s*/','$1',trim(preg_replace('/\s+/',' ',strtolower(preg_replace('/`|\bON DELETE RESTRICT\b/i','',rtrim(trim($ddl),','))))));
            $actualLines=array_map($normalize,explode("\n",$actual));
            // Verify each required field and constraint without modifying an existing table.
            preg_match_all('/^  ([a-z_]+) (.+),?$/m',$definition,$fields,PREG_SET_ORDER);
            $columns=$pdo->query('SHOW COLUMNS FROM '.$table)->fetchAll(PDO::FETCH_ASSOC);
            $expected=[];foreach($fields as $field)if(!in_array(strtolower($field[1]),['unique','index','constraint'],true))$expected[]=$field[1];
            if($engine!=='InnoDB'||array_column($columns,'Field')!==$expected)throw new DomainException('Unexpected Maya schema; no existing table changed.');
            foreach($columns as $column){
                preg_match('/^  '.preg_quote($column['Field'],'/').' ([^\n]+)/m',$definition,$field);
                $type=strtolower(strtok($field[1],' '));
                $actualType=strtolower(preg_replace('/\b(bigint|tinyint)\(\d+\)/i','$1',$column['Type']));
                if(!str_starts_with($actualType,$type)||str_contains($field[1],'UNSIGNED')!==str_contains($actualType,'unsigned')
                    ||$column['Null']!==(str_contains($field[1],'NOT NULL')||str_contains($field[1],'PRIMARY KEY')?'NO':'YES'))throw new DomainException('Unexpected Maya column definition.');
                if(preg_match('/DEFAULT ([^ ,]+)/',$field[1],$default)){
                    $clean=static fn($v)=>strtolower(trim(str_replace('()','',(string)$v),"'"));
                    if($clean($column['Default'])!==$clean($default[1]))throw new DomainException('Unexpected Maya default.');
                }
                if(str_contains($field[1],'PRIMARY KEY')&&$column['Key']!=='PRI')throw new DomainException('Unexpected Maya primary key.');
                if(str_contains($field[1],'AUTO_INCREMENT')&&!str_contains($column['Extra'],'auto_increment'))throw new DomainException('Unexpected Maya identity column.');
            }
            foreach(explode("\n",$definition) as $line){
                if(preg_match('/^  (UNIQUE KEY|INDEX|CONSTRAINT) /',$line)){
                    $fragment=rtrim(trim($line),',');$fragment=str_replace('INDEX ','KEY ',$fragment);
                    if(!in_array($normalize($fragment),$actualLines,true))throw new DomainException('Unexpected Maya constraints.');
                }
            }
        }
        if($apply){foreach($pending as $ddl)$pdo->exec($ddl);$this->run($pdo,false);}
        return ['pending_additions'=>count($pending),'applied'=>$apply?count($pending):0,'rows_updated'=>0];
    }
}
