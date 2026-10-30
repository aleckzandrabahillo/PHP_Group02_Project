<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\User;
use PDO;
use InvalidArgumentException;

/** Account-owned addresses. Payloads are authenticated encryption, bound to their row. */
final class AddressBook
{
    public const FIELDS = ['country','first_name','last_name','address_line','barangay','postal_code','city','region','province','contact_no','delivery_address'];

    public static function installed(PDO $pdo): bool
    {
        return (int)$pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="customer_address_book" AND ENGINE="InnoDB"')->fetchColumn() === 1;
    }

    public static function initialized(PDO $pdo,int $user): bool
    {
        if(!self::installed($pdo))return false;
        $q=$pdo->prepare('SELECT id FROM customer_address_book WHERE user_id=? LIMIT 1');$q->execute([$user]);return $q->fetchColumn()!==false;
    }

    public function all(PDO $pdo, int $user): array
    {
        if (self::initialized($pdo,$user)) {
            $q=$pdo->prepare('SELECT * FROM customer_address_book WHERE user_id=? AND deleted_at IS NULL ORDER BY is_default DESC,id');$q->execute([$user]);
            return array_map(fn($row)=>$this->decode($row),$q->fetchAll());
        }
        $legacy=(new CustomerAddress())->findLegacy($pdo,$user);
        return $legacy ? [[...$legacy,'delivery_address'=>CustomerAddress::validate($legacy)['values']['delivery_address'],'id'=>0,'is_default'=>true]] : [];
    }

    public function default(PDO $pdo,int $user): ?array
    {
        foreach ($this->all($pdo,$user) as $address) if ($address['is_default']) return $address;
        return null;
    }

    public function mutate(PDO $pdo,int $user,string $action,int $id,string $expected,array $input=[]): array
    {
        if (!self::installed($pdo)) throw new \RuntimeException('Address management requires the address-book migration.');
        if (!in_array($action,['save','delete','default'],true)) throw new InvalidArgumentException('Choose a valid address action.');
        if (($id<0 && ($action!=='save'||$expected!=='')) || $id< -1) throw new InvalidArgumentException('Choose an existing address or add a new one.');
        $values=[];
        if ($action==='save') {
            if (($input['location_version']??'')==='2' && !DeliveryLocationMigration::installed($pdo)) throw new \RuntimeException('Address locations are unavailable.');
            $checked=CustomerAddress::validate($input);
            if ($checked['errors']) throw new InvalidArgumentException('Check your address fields.');
            $values=array_intersect_key($checked['values'],array_flip(self::FIELDS));
        }
        $cipher=PiiCipher::configured(); // Fail closed before opening a transaction.
        $pdo->beginTransaction();
        try {
            $q=$pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');$q->execute([$user]);$actor=$q->fetch();
            if (!User::canAuthenticate($actor?:null)||$actor['role']!=='customer') throw new InvalidArgumentException('An active customer account is required.');
            $q=$pdo->prepare('SELECT user_id FROM customer_profiles WHERE user_id=?');$q->execute([$user]);
            if (!$q->fetchColumn()) throw new InvalidArgumentException('Your profile is unavailable.');
            $q=$pdo->prepare('SELECT COUNT(*) FROM customer_address_book WHERE user_id=?');$q->execute([$user]);
            if ((int)$q->fetchColumn()===0 && ($legacy=(new CustomerAddress())->findLegacy($pdo,$user))) {
                // Copy on the first explicit account mutation, never on reads or migration.
                if ($id===0 && $expected!==($legacy['revision']??'')) throw new InvalidArgumentException('Your address changed. Reload your profile.');
                $legacyId=$this->insert($pdo,$cipher,$user,array_intersect_key([...$legacy,'delivery_address'=>CustomerAddress::validate($legacy)['values']['delivery_address']],array_flip(self::FIELDS)),true);
                if ($id===0) { $id=$legacyId; $q=$pdo->prepare('SELECT revision FROM customer_address_book WHERE id=?');$q->execute([$id]);$expected=$q->fetchColumn(); }
            }
            $q=$pdo->prepare('SELECT * FROM customer_address_book WHERE id=? AND user_id=? AND deleted_at IS NULL FOR UPDATE');$q->execute([$id,$user]);$row=$q->fetch();
            if ($id>=0 && (!$row || !hash_equals($row['revision'],$expected))) throw new InvalidArgumentException('Your address changed or is unavailable. Reload your profile.');
            if ($action==='save') {
                $q=$pdo->prepare('SELECT COUNT(*) FROM customer_address_book WHERE user_id=? AND deleted_at IS NULL');$q->execute([$user]);$count=(int)$q->fetchColumn();
                if ($id<0 && $count>=10) throw new InvalidArgumentException('You can keep up to 10 delivery addresses.');
                $makeDefault=$count===0||($input['make_default']??'')==='1'||($row && (bool)$row['is_default']);
                if ($id<0) $id=$this->insert($pdo,$cipher,$user,$values,false);
                else {
                    $pdo->prepare('UPDATE customer_address_book SET payload_ciphertext=?,revision=?,updated_at=NOW() WHERE id=? AND user_id=?')->execute([$cipher->encrypt('customer_address_book','payload',$id,json_encode($values,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)),bin2hex(random_bytes(16)),$id,$user]);
                }
                if ($makeDefault) $this->setDefault($pdo,$user,$id);
            } elseif ($action==='default') $this->setDefault($pdo,$user,$id);
            else {
                $pdo->prepare('UPDATE customer_address_book SET deleted_at=NOW(),is_default=NULL,revision=?,updated_at=NOW() WHERE id=? AND user_id=?')->execute([bin2hex(random_bytes(16)),$id,$user]);
                if ($row['is_default']) {
                    $q=$pdo->prepare('SELECT id FROM customer_address_book WHERE user_id=? AND deleted_at IS NULL ORDER BY id LIMIT 1');$q->execute([$user]);
                    if ($next=$q->fetchColumn()) $this->setDefault($pdo,$user,(int)$next);
                }
            }
            AuditService::record($pdo,$user,'address.'.$action,'user',$user,['address_id'=>$id]);
            $result=$this->all($pdo,$user);$pdo->commit();return $result;
        } catch (\Throwable $e) { if($pdo->inTransaction())$pdo->rollBack();throw $e; }
    }

    private function insert(PDO $pdo,PiiCipher $cipher,int $user,array $values,bool $default): int
    {
        $revision=bin2hex(random_bytes(16));
        $pdo->prepare('INSERT INTO customer_address_book(user_id,payload_ciphertext,revision,is_default,created_at,updated_at) VALUES (?,\'\',?,?,NOW(),NOW())')->execute([$user,$revision,$default?1:null]);
        $id=(int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE customer_address_book SET payload_ciphertext=? WHERE id=? AND user_id=?')->execute([$cipher->encrypt('customer_address_book','payload',$id,json_encode($values,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)),$id,$user]);
        return $id;
    }
    private function setDefault(PDO $pdo,int $user,int $id): void
    {
        $pdo->prepare('UPDATE customer_address_book SET is_default=NULL,revision=?,updated_at=NOW() WHERE user_id=? AND is_default=1 AND id<>?')->execute([bin2hex(random_bytes(16)),$user,$id]);
        $pdo->prepare('UPDATE customer_address_book SET is_default=1,revision=?,updated_at=NOW() WHERE id=? AND user_id=? AND deleted_at IS NULL')->execute([bin2hex(random_bytes(16)),$id,$user]);
    }
    private function decode(array $row): array
    {
        $data=json_decode(PiiCipher::configured()->decrypt('customer_address_book','payload',(int)$row['id'],$row['payload_ciphertext']),true,16,JSON_THROW_ON_ERROR);
        return [...array_intersect_key($data,array_flip(self::FIELDS)),'id'=>(int)$row['id'],'revision'=>$row['revision'],'is_default'=>(bool)$row['is_default']];
    }
}
