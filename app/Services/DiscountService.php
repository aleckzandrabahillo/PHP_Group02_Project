<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\User;
use InvalidArgumentException;
use PDO;
use Throwable;

/** One merchandise discount per order; fixed cents or percentage basis points. */
final class DiscountService
{
    public static function validate(array $input): array
    {
        $v=[];$errors=[];
        foreach(['code','description','kind','value','minimum','status','starts_at','expires_at','usage_limit','customer_limit'] as $field){
            $x=$input[$field]??'';
            if(!is_string($x)||!mb_check_encoding($x,'UTF-8')||preg_match('/[\x00-\x1f\x7f]/u',$x)){$errors[$field]='Enter a valid text value.';$x='';}
            $v[$field]=trim($x);
        }
        $v['code']=strtoupper($v['code']);
        if(!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,31}$/D',$v['code']))$errors['code']='Use 3–32 letters, numbers, hyphens or underscores.';
        if(mb_strlen($v['description'])>255)$errors['description']='Keep the description within 255 characters.';
        if(!in_array($v['kind'],['percentage','fixed'],true))$errors['kind']='Choose percentage or fixed amount.';
        if(!in_array($v['status'],['active','inactive'],true))$errors['status']='Choose active or inactive.';
        foreach(['value','minimum'] as $field){
            $x=$v[$field];if($field==='minimum'&&$x==='')$x='0';
            if(!preg_match('/^([0-9]{1,8})(?:\.([0-9]{1,2}))?$/D',$x,$m)){$errors[$field]='Enter an amount with up to two decimal places.';$units=0;}else{$units=(int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');}
            $v[$field==='value'?'value_units':'minimum_cents']=$units;
        }
        if($v['value_units']<1||($v['kind']==='percentage'&&$v['value_units']>10000))$errors['value']='Enter a positive discount; percentages cannot exceed 100%.';
        foreach(['starts_at','expires_at'] as $field){
            $x=$v[$field];if($x===''){$v[$field]=null;continue;}
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$x);
            if(!$date||$date->format('Y-m-d\TH:i')!==$x){$errors[$field]='Enter a valid date and time.';$v[$field]=null;}else{$v[$field]=$date->format('Y-m-d H:i:s');}
        }
        if($v['starts_at']!==null&&$v['expires_at']!==null&&$v['expires_at']<=$v['starts_at'])$errors['expires_at']='Expiry must be later than the start.';
        foreach(['usage_limit','customer_limit'] as $field){$x=$v[$field];if($x===''){$v[$field]=null;}elseif(!preg_match('/^[1-9][0-9]{0,6}$/D',$x)||((int)$x)>1000000){$errors[$field]='Use 1–1,000,000, or leave blank.';$v[$field]=null;}else{$v[$field]=(int)$x;}}
        if($v['usage_limit']!==null&&$v['customer_limit']!==null&&$v['customer_limit']>$v['usage_limit'])$errors['customer_limit']='The customer limit cannot exceed the total limit.';
        return ['values'=>$v,'errors'=>$errors];
    }

    public function quote(PDO $pdo,int $userId,mixed $code,int $subtotal,bool $lock=false,?string $revision=null): ?array
    {
        if($code==='')return null;
        if(!is_string($code)||!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,31}$/D',trim($code)))throw new InvalidArgumentException('Enter a valid discount code.');
        if(!DiscountMigration::installed($pdo))throw new InvalidArgumentException('Discounts are temporarily unavailable.');
        $q=$pdo->prepare('SELECT *, (starts_at IS NULL OR starts_at<=NOW()) started, (expires_at IS NULL OR expires_at>NOW()) unexpired FROM discount_codes WHERE code=?'.($lock?' FOR UPDATE':''));$q->execute([strtoupper(trim($code))]);$row=$q->fetch();
        if(!$row)throw new InvalidArgumentException("This discount code isn't valid.");
        if($row['status']!=='active')throw new InvalidArgumentException('This discount code is inactive.');
        if(!(int)$row['started'])throw new InvalidArgumentException('This discount is not available yet.');
        if(!(int)$row['unexpired'])throw new InvalidArgumentException('This discount code has expired.');
        if($revision!==null&&!hash_equals($row['revision'],$revision))throw new InvalidArgumentException('This discount changed. Apply it again to review your total.');
        if($subtotal<(int)$row['minimum_cents'])throw new InvalidArgumentException('A merchandise subtotal of ₱'.ShippingService::decimal((int)$row['minimum_cents']).' is required.');
        if($lock&&!$pdo->inTransaction())throw new \RuntimeException('A redemption quote requires an active transaction.');
        // A current locking read avoids an older REPEATABLE READ snapshot after waiting for the code lock.
        $q=$pdo->prepare('SELECT user_id FROM order_discounts WHERE discount_id=? AND state IN ("reserved","committed")'.($lock?' FOR UPDATE':''));$q->execute([$row['id']]);$rows=$q->fetchAll(PDO::FETCH_COLUMN);
        $usage=['uses_total'=>count($rows),'uses_customer'=>count(array_filter($rows,static fn($id)=>(int)$id===$userId))];
        if($row['usage_limit']!==null&&(int)$usage['uses_total']>=(int)$row['usage_limit'])throw new InvalidArgumentException('This discount has reached its usage limit.');
        if($row['customer_limit']!==null&&(int)$usage['uses_customer']>=(int)$row['customer_limit'])throw new InvalidArgumentException('You have already used this discount the permitted number of times.');
        $amount=min($subtotal,$row['kind']==='percentage'?intdiv($subtotal*(int)$row['value_units'],10000):(int)$row['value_units']);
        if($amount<1)throw new InvalidArgumentException('This discount does not apply to this bag.');
        return ['id'=>(int)$row['id'],'code'=>$row['code'],'revision'=>$row['revision'],'kind'=>$row['kind'],'value_units'=>(int)$row['value_units'],'amount_cents'=>$amount];
    }

    public function reserve(PDO $pdo,int $order,int $user,array $quote,bool $awaitingPayment): void
    {
        if(!$pdo->inTransaction())throw new \RuntimeException('Discount redemption requires the order transaction.');
        $pdo->prepare('INSERT INTO order_discounts(order_id,discount_id,user_id,code_snapshot,kind_snapshot,value_units_snapshot,amount_cents,state,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())')->execute([$order,$quote['id'],$user,$quote['code'],$quote['kind'],$quote['value_units'],$quote['amount_cents'],$awaitingPayment?'reserved':'committed']);
        AuditService::record($pdo,$user,'discount.applied','order',$order,['code'=>$quote['code'],'amount_cents'=>$quote['amount_cents']]);
    }

    public static function settle(PDO $pdo,int $order,bool $release): void
    {
        if(!DiscountMigration::installed($pdo))return;
        if(!$pdo->inTransaction())throw new \RuntimeException('Discount settlement requires the order transaction.');
        $q=$pdo->prepare('SELECT discount_id FROM order_discounts WHERE order_id=?');$q->execute([$order]);$id=$q->fetchColumn();if($id===false)return;
        $q=$pdo->prepare('SELECT id FROM discount_codes WHERE id=? FOR UPDATE');$q->execute([$id]);
        $q=$pdo->prepare('UPDATE order_discounts SET state=?,updated_at=NOW() WHERE order_id=? AND state IN ('.($release?'"reserved","committed"':'"reserved"').')');$q->execute([$release?'released':'committed',$order]);
        if($q->rowCount())AuditService::record($pdo,null,$release?'discount.released':'discount.committed','order',$order,[]);
    }

    public function save(PDO $pdo,int $actor,int $id,array $input,string $revision=''): int
    {
        $c=self::validate($input);if($c['errors'])throw new InvalidArgumentException('Check the discount fields.');$v=$c['values'];$pdo->beginTransaction();
        try{
            $this->actor($pdo,$actor);
            if($id){$row=$this->locked($pdo,$id);if($row['status']==='archived'||!hash_equals($row['revision'],$revision))throw new InvalidArgumentException('This discount changed. Refresh before editing.');}
            $q=$pdo->prepare('SELECT id FROM discount_codes WHERE code=? AND id<>?');$q->execute([$v['code'],$id]);if($q->fetchColumn()!==false)throw new InvalidArgumentException('That discount code already exists.');
            $p=[$v['code'],$v['description'],$v['kind'],$v['value_units'],$v['minimum_cents'],$v['status'],$v['starts_at'],$v['expires_at'],$v['usage_limit'],$v['customer_limit'],bin2hex(random_bytes(16))];
            if($id){$pdo->prepare('UPDATE discount_codes SET code=?,description=?,kind=?,value_units=?,minimum_cents=?,status=?,starts_at=?,expires_at=?,usage_limit=?,customer_limit=?,revision=?,updated_at=NOW() WHERE id=?')->execute([...$p,$id]);}
            else{$pdo->prepare('INSERT INTO discount_codes(code,description,kind,value_units,minimum_cents,status,starts_at,expires_at,usage_limit,customer_limit,revision,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')->execute($p);$id=(int)$pdo->lastInsertId();}
            AuditService::record($pdo,$actor,isset($row)?'discount.update':'discount.create','discount',$id,['code'=>$v['code'],'kind'=>$v['kind'],'status'=>$v['status']]);$pdo->commit();return $id;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof \PDOException&&($e->errorInfo[0]??'')==='23000')throw new InvalidArgumentException('That discount code already exists.');throw $e;}
    }

    public function action(PDO $pdo,int $actor,int $id,string $action,string $revision): void
    {
        if(!in_array($action,['activate','deactivate','remove'],true))throw new InvalidArgumentException('Choose an available action.');
        $pdo->beginTransaction();try{
            $this->actor($pdo,$actor);$row=$this->locked($pdo,$id);
            if(!hash_equals($row['revision'],$revision))throw new InvalidArgumentException('This discount changed. Refresh before continuing.');
            $q=$pdo->prepare('SELECT COUNT(*) FROM order_discounts WHERE discount_id=?');$q->execute([$id]);$used=(int)$q->fetchColumn();
            if($action==='remove'&&!$used){$pdo->prepare('DELETE FROM discount_codes WHERE id=?')->execute([$id]);$event='discount.delete';}
            else{if($row['status']==='archived')throw new InvalidArgumentException('This discount is archived.');$status=$action==='remove'?'archived':($action==='activate'?'active':'inactive');$pdo->prepare('UPDATE discount_codes SET status=?,revision=?,updated_at=NOW() WHERE id=?')->execute([$status,bin2hex(random_bytes(16)),$id]);$event='discount.'.$action;}
            AuditService::record($pdo,$actor,$event,'discount',$id,['code'=>$row['code']]);$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function find(PDO $pdo,int $id): ?array{$q=$pdo->prepare('SELECT * FROM discount_codes WHERE id=?');$q->execute([$id]);return $q->fetch()?:null;}

    public function listing(PDO $pdo,array $input): array
    {
        $q=$input['q']??'';$status=$input['status']??'';
        if(!is_string($q)||!mb_check_encoding($q,'UTF-8')||mb_strlen($q)>80||preg_match('/[\x00-\x1f\x7f]/u',$q)||!is_string($status)||!in_array($status,['','active','inactive','archived'],true))throw new InvalidArgumentException('Use valid discount filters.');
        $where=[];$params=[];if(trim($q)!==''){$where[]="d.code LIKE ? ESCAPE '!'";$params[]='%'.strtr(strtoupper(trim($q)),['!'=>'!!','%'=>'!%','_'=>'!_']).'%';}if($status!==''){$where[]='d.status=?';$params[]=$status;}
        $sql=' FROM discount_codes d'.($where?' WHERE '.implode(' AND ',$where):'');$stmt=$pdo->prepare('SELECT COUNT(*)'.$sql);$stmt->execute($params);$total=(int)$stmt->fetchColumn();$pages=max(1,(int)ceil($total/15));
        $raw=$input['page']??1;$page=(is_string($raw)||is_int($raw))?filter_var($raw,FILTER_VALIDATE_INT):false;$page=max(1,min($pages,$page===false?1:$page));
        $stmt=$pdo->prepare('SELECT d.*,(SELECT COUNT(*) FROM order_discounts od WHERE od.discount_id=d.id AND od.state IN ("reserved","committed")) uses_total'.$sql.' ORDER BY d.id DESC LIMIT 15 OFFSET '.(($page-1)*15));$stmt->execute($params);
        return ['items'=>$stmt->fetchAll(),'page'=>$page,'pages'=>$pages,'total'=>$total,'q'=>$q,'status'=>$status];
    }

    private function actor(PDO $pdo,int $id): void{$q=$pdo->prepare('SELECT id,role,status,locked_until,lock_requires_admin FROM users WHERE id=? FOR UPDATE');$q->execute([$id]);$u=$q->fetch();if(!User::canAuthenticate($u?:null)||!in_array($u['role'],['admin','catalog_manager'],true))throw new InvalidArgumentException('An active authorized staff account is required.');}
    private function locked(PDO $pdo,int $id): array{$q=$pdo->prepare('SELECT * FROM discount_codes WHERE id=? FOR UPDATE');$q->execute([$id]);return $q->fetch()?:throw new InvalidArgumentException('Discount not found.');}
}
