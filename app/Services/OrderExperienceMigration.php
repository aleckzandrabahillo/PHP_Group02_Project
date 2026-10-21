<?php
declare(strict_types=1);
namespace App\Services;
use DomainException;
use PDO;

final class OrderExperienceMigration
{
    public function run(PDO $pdo, bool $apply): array
    {
        if($pdo->inTransaction())throw new DomainException('Run additive DDL outside a business transaction.');
        $definitions=['order_status_events'=>['id','order_id','actor_id','from_status','to_status','created_at'],
            'order_email_outbox'=>['id','order_id','status','attempts','last_error','created_at','sent_at']];
        foreach(['orders','users','audit_logs'] as $table){$q=$pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);if($q->fetchColumn()!=='InnoDB')throw new DomainException('Existing transactional order tables are required.');}
        $pending=[];
        foreach($definitions as $table=>$columns){
            $q=$pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);$engine=$q->fetchColumn();
            if($engine===false){$pending[]=$table;continue;}
            $fields=$pdo->query('SHOW COLUMNS FROM '.$table)->fetchAll();
            if($engine!=='InnoDB'||array_column($fields,'Field')!==$columns)throw new DomainException('Unexpected order extension schema; no existing tables were changed.');
            foreach($fields as $field){
                $name=$field['Field'];$type=strtolower($field['Type']);$nullable=in_array($name,['actor_id','from_status','last_error','sent_at'],true);
                $valid=match($name){
                    'id','order_id','actor_id'=>str_starts_with($type,'bigint')&&str_contains($type,'unsigned'),
                    'attempts'=>str_starts_with($type,'tinyint')&&str_contains($type,'unsigned')&&(string)$field['Default']==='0',
                    'last_error'=>$type==='varchar(40)',
                    'created_at'=>str_starts_with($type,'datetime')&&str_starts_with(strtolower((string)$field['Default']),'current_timestamp'),
                    'sent_at'=>str_starts_with($type,'datetime'),
                    'status'=>$type==="enum('pending','sending','sent','failed')"&&trim((string)$field['Default'],"'")==='pending',
                    default=>$type==="enum('pending','processing','shipped','delivered','cancelled')",
                };
                if(!$valid||$field['Null']!==($nullable?'YES':'NO')||($name==='id'&&($field['Key']!=='PRI'||!str_contains($field['Extra'],'auto_increment'))))throw new DomainException('Unexpected order extension field.');
            }
            $ddl=$pdo->query('SHOW CREATE TABLE '.$table)->fetch(PDO::FETCH_NUM)[1];
            if(!str_contains($ddl,'FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE')
                ||($table==='order_status_events'&&!str_contains($ddl,'FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL'))
                ||($table==='order_email_outbox'&&(!str_contains($ddl,'UNIQUE KEY `uq_order_email_order` (`order_id`)')||!str_contains($ddl,"enum('pending','sending','sent','failed')"))))throw new DomainException('Unexpected order extension constraints.');
        }
        if($apply){
            $schema=file_get_contents(dirname(__DIR__,2).'/database/schema.sql');
            foreach($pending as $table){if(!preg_match('/CREATE TABLE IF NOT EXISTS '.preg_quote($table,'/').' \([\s\S]*?\) ENGINE=InnoDB;/',$schema,$m))throw new DomainException('Order extension definition unavailable.');$pdo->exec($m[0]);}
            $this->run($pdo,false);
        }
        return ['pending_additions'=>count($pending),'applied'=>$apply?count($pending):0,'rows_updated'=>0];
    }
}
