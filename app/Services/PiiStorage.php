<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use RuntimeException;

final class PiiStorage
{
    public static function enabled(): bool {return env('PII_ENCRYPTION_ENABLED',false)===true;}

    public static function read(string $table,int $id, #[\SensitiveParameter] array $row): array
    {
        if(!isset(PiiCipher::FIELDS[$table]))throw new RuntimeException('Unsupported protected record.');
        $state=(int)($row['pii_encrypted']??0);
        if(!in_array($state,[0,1],true))throw new RuntimeException('Invalid protected record state.');
        if($state===1){
            // Never silently display empty/plaintext fields if encryption configuration is missing.
            $cipher=PiiCipher::configured();
            foreach(PiiCipher::FIELDS[$table] as $field=>$limit){
                if(!array_key_exists($field.'_encrypted',$row))throw new RuntimeException('Protected record is incomplete.');
                $row[$field]=$cipher->decrypt($table,$field,$id,$row[$field.'_encrypted']);
            }
        }else{
            foreach(PiiCipher::FIELDS[$table] as $field=>$limit)if(($row[$field.'_encrypted']??null)!==null)throw new RuntimeException('Inconsistent protected record state.');
        }
        foreach(PiiCipher::FIELDS[$table] as $field=>$limit)unset($row[$field.'_encrypted']);
        unset($row['pii_encrypted']);return $row;
    }

    /** Caller owns the business transaction and account/stock locks; ciphertext and audits commit together. */
    public static function write(PDO $pdo,string $table,int $id, #[\SensitiveParameter] array $values,bool $preserveTimestamp=false): void
    {
        if(!self::enabled()){
            if(PiiMigration::installed($pdo,$table)){
                $key=$table==='orders'?'id':'user_id';$q=$pdo->prepare('SELECT pii_encrypted FROM '.$table.' WHERE '.$key.'=?');$q->execute([$id]);
                if((int)$q->fetchColumn()!==0)throw new RuntimeException('Encrypted records cannot be saved with protection disabled.');
            }
            return;
        }
        if(!$pdo->inTransaction()||!PiiMigration::installed($pdo,$table))throw new RuntimeException('Personal-data protection needs its reviewed schema.');
        if(array_diff(array_keys($values),array_keys(PiiCipher::FIELDS[$table])))throw new RuntimeException('Unsupported protected fields.');
        $key=$table==='orders'?'id':'user_id';$q=$pdo->prepare('SELECT * FROM '.$table.' WHERE '.$key.'=? FOR UPDATE');$q->execute([$id]);$raw=$q->fetch();
        if(!$raw)throw new RuntimeException('Protected record unavailable.');
        $current=self::read($table,$id,$raw);$cipher=PiiCipher::configured();$set=[];$params=[];
        foreach(PiiCipher::FIELDS[$table] as $field=>$limit){
            $text=array_key_exists($field,$values)?$values[$field]:$current[$field];
            $encrypted=$cipher->encrypt($table,$field,$id,$text);
            if($cipher->decrypt($table,$field,$id,$encrypted)!==$text)throw new RuntimeException('Personal-data protection verification failed.');
            $set[]=$field.'_encrypted=?';$params[]=$encrypted;
            $set[]=$field.'=?';$params[]=(($table==='orders'&&$field==='delivery_address_snapshot')||($table==='customer_profiles'&&$field==='contact_no'))?'':null;
        }
        $params[]=$id;$pdo->prepare('UPDATE '.$table.' SET '.implode(',',$set).',pii_encrypted=1'.($preserveTimestamp?',updated_at=updated_at':'').' WHERE '.$key.'=?')->execute($params);
    }

    /** Explicit, bounded backfill, not automatic on GET. No field values enter evidence or audits. */
    public static function backfill(PDO $pdo,int $actor,string $table,int $limit=100): int
    {
        if(!self::enabled()||!isset(PiiCipher::FIELDS[$table])||$limit<1||$limit>100||$pdo->inTransaction())throw new RuntimeException('Invalid encryption batch.');
        $pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT id,role,status,locked_until,lock_requires_admin FROM users WHERE id=? FOR UPDATE');$q->execute([$actor]);$user=$q->fetch();
            if(!\App\Models\User::canAuthenticate($user?:null)||$user['role']!=='admin')throw new RuntimeException('An active Administrator is required.');
            $key=$table==='orders'?'id':'user_id';
            $rows=$pdo->query('SELECT '.$key.' FROM '.$table.' WHERE pii_encrypted=0 ORDER BY '.$key.' LIMIT '.$limit.' FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN);
            foreach($rows as $id)self::write($pdo,$table,(int)$id,[],true);
            if($rows)AuditService::record($pdo,$actor,'pii.backfilled',$table,null,['records'=>count($rows),'fields'=>array_keys(PiiCipher::FIELDS[$table])]);
            $pdo->commit();return count($rows);
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
