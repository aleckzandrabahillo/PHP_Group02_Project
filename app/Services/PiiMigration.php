<?php
declare(strict_types=1);
namespace App\Services;
use DomainException;
use PDO;

/** Additive columns only. DDL implicitly commits; backup and a separate rollout approval are mandatory. */
final class PiiMigration
{
    public function run(PDO $pdo,bool $apply=false): array
    {
        if ($pdo->inTransaction()) throw new DomainException('Run encryption DDL outside business transactions.');
        $pending=[];
        foreach(PiiCipher::FIELDS as $table=>$fields){
            $engine=$pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$engine->execute([$table]);
            if($engine->fetchColumn()!=='InnoDB')throw new DomainException('Transactional profile/order schema required.');
            $columns=$pdo->query('SHOW FULL COLUMNS FROM '.$table)->fetchAll(PDO::FETCH_UNIQUE);
            foreach(array_keys($fields) as $field){
                $name=$field.'_encrypted';
                if(isset($columns[$name])){
                    $c=$columns[$name];if($c['Type']!=='text'||$c['Null']!=='YES'||$c['Default']!==null||$c['Collation']!=='ascii_bin')throw new DomainException('Unexpected encryption column.');
                }else $pending[]='ALTER TABLE '.$table.' ADD '.$name.' TEXT CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL';
            }
            if(isset($columns['pii_encrypted'])){
                $c=$columns['pii_encrypted'];if($c['Type']!=='tinyint(1) unsigned'||$c['Null']!=='NO'||(string)$c['Default']!=='0')throw new DomainException('Unexpected encryption-state column.');
            }else $pending[]='ALTER TABLE '.$table.' ADD pii_encrypted TINYINT(1) UNSIGNED NOT NULL DEFAULT 0';
        }
        if($apply){foreach($pending as $sql)$pdo->exec($sql);$this->run($pdo,false);}
        return ['pending_additions'=>count($pending),'applied'=>$apply?count($pending):0,'rows_updated'=>0];
    }

    public static function installed(PDO $pdo,string $table): bool
    {
        if(!isset(PiiCipher::FIELDS[$table]))throw new DomainException('Unsupported encryption table.');
        $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME="pii_encrypted"');$q->execute([$table]);return (int)$q->fetchColumn()===1;
    }
}
