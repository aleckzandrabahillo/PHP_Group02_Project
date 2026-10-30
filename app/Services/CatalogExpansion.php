<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\User;
use DomainException;
use PDO;

/** Add the approved batch and missing photographs without replacing existing catalog work. */
final class CatalogExpansion
{
    public function __construct(private readonly PDO $pdo, private readonly string $root) {}

    public function run(bool $apply, ?string $actor, array $expected): array
    {
        if($this->pdo->inTransaction())throw new DomainException('Use an independent catalog transaction.');
        $source=file_get_contents($this->root.'/database/avela_catalog_expansion_20261031.json');
        $manifest=json_decode($source,true,32,JSON_THROW_ON_ERROR);$sourceHash=hash('sha256',$source);
        if(($manifest['catalog_id']??'')!=='avela-fictional-expansion-20261031'||($manifest['currency']??'')!=='PHP'
            ||count($manifest['products']??[])!==113||count($manifest['image_updates']??[])!==5)throw new DomainException('Unexpected expansion manifest.');
        $identity=$this->pdo->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch();
        $norm=static fn($v)=>strtolower(rtrim(str_replace('\\','/',(string)$v),'/'));
        if($apply&&(($expected['host']??'')!==env('DB_HOST')||($expected['database']??'')!==$identity['db']
            ||(int)($expected['port']??0)!==(int)$identity['port']||$norm($expected['datadir']??'')!==$norm($identity['datadir'])
            ||!hash_equals($sourceHash,$expected['source_sha256']??'')))throw new DomainException('Expansion source or database target changed.');
        $lock='avela.catalog.refresh.'.substr(hash('sha256',$identity['db']),0,30);$locked=false;
        try{
            if($apply){$q=$this->pdo->prepare('SELECT GET_LOCK(?,5)');$q->execute([$lock]);$locked=(int)$q->fetchColumn()===1;if(!$locked)throw new DomainException('Another catalog operation is running.');}
            else $this->pdo->exec('SET TRANSACTION READ ONLY');
            $this->pdo->beginTransaction();$suffix=$apply?' FOR UPDATE':'';
            $rows=$this->pdo->query('SELECT * FROM products ORDER BY id'.$suffix)->fetchAll();$fingerprint=CatalogRefresh::fingerprint($rows);
            if($apply&&!hash_equals($fingerprint,$expected['catalog_sha256']??''))throw new DomainException('Catalog changed since preview.');
            $current=array_column($rows,null,'sku');$categories=$this->pdo->query('SELECT * FROM categories ORDER BY id'.$suffix)->fetchAll();$categoryMap=[];
            foreach($categories as $category)if($category['status']==='active')$categoryMap[$category['name']]=$category['id'];
            $actorId=null;
            if($apply){$q=$this->pdo->prepare('SELECT * FROM users WHERE username=? FOR UPDATE');$q->execute([$actor]);$user=$q->fetch();
                if(!User::canAuthenticate($user)||!in_array($user['role'],['admin','catalog_manager'],true))throw new DomainException('An active unlocked staff actor is required.');$actorId=(int)$user['id'];}
            $inserts=[];$repairs=[];$seen=[];
            foreach($manifest['products'] as $index=>$item){
                if($item['sku']!==sprintf('AVE-26-%04d',$index+101)||isset($seen[$item['sku']])||!is_string($item['name'])||trim($item['name'])===''||mb_strlen($item['name'])>160
                    ||!preg_match('/^[0-9]{1,8}\.[0-9]{2}$/D',$item['price'])||(float)$item['price']<=0||$item['stock_qty']!==20||$item['status']!=='active'
                    ||!in_array($item['routine_step'],['cleanse','condition','treat','finish'],true)||$item['tags']!==[]||!isset($categoryMap[$item['category']]))throw new DomainException('Invalid approved product.');
                $seen[$item['sku']]=true;$this->image($item);
                if(isset($current[$item['sku']])){
                    foreach(['name','description','price','image_path','routine_step'] as $field)if((string)$current[$item['sku']][$field]!== (string)$item[$field])throw new DomainException('An existing expansion product was edited; preserve it and review.');
                    if((int)$current[$item['sku']]['category_id']!==(int)$categoryMap[$item['category']])throw new DomainException('Existing category changed.');
                }else $inserts[]=$item;
            }
            foreach($manifest['image_updates'] as $item){
                $this->image($item);$row=$current[$item['sku']]??null;
                if(!$row||$row['name']!==$item['name'])throw new DomainException('Photograph repair identity differs.');
                if($row['image_path']===$item['image_path'])continue;
                if($row['image_path']!==$item['from_image_path']||$item['from_image_path']!==null)throw new DomainException('An existing photograph was edited; no overwrite allowed.');
                $repairs[]=[$row,$item];
            }
            if($apply){
                foreach($inserts as $item){
                    $q=$this->pdo->prepare('INSERT INTO products(category_id,sku,name,description,price,stock_qty,image_path,status,routine_step) VALUES (?,?,?,?,?,?,?,?,?)');
                    $q->execute([$categoryMap[$item['category']],$item['sku'],$item['name'],$item['description'],$item['price'],20,$item['image_path'],'active',$item['routine_step']]);
                    AuditService::record($this->pdo,$actorId,'product.create','product',(int)$this->pdo->lastInsertId(),['source'=>$manifest['catalog_id'],'initial_stock'=>20]);
                }
                foreach($repairs as [$row,$item]){
                    $this->pdo->prepare('UPDATE products SET image_path=? WHERE id=? AND image_path IS NULL')->execute([$item['image_path'],$row['id']]);
                    AuditService::record($this->pdo,$actorId,'product.update','product',(int)$row['id'],['source'=>$manifest['catalog_id'],'fields'=>['image_path']]);
                }
                $this->pdo->commit();
            }else $this->pdo->rollBack();
            return ['mode'=>$apply?'apply':'dry-run','target'=>$identity,'source_sha256'=>$sourceHash,'catalog_sha256'=>$fingerprint,'inserts'=>count($inserts),'image_repairs'=>count($repairs),'existing_rows'=>count($rows),'existing_stock_or_price_updates'=>0,'archives'=>0];
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        finally{if($locked){$q=$this->pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}}
    }

    private function image(array $item): void
    {
        if(!preg_match('~^assets/images/products/catalog-20261031/[a-z0-9_-]+\.(webp|png|jpg)$~D',$item['image_path']??''))throw new DomainException('Invalid image path.');
        $path=$this->root.'/public/'.$item['image_path'];$info=@getimagesize($path);
        if(!$info||is_link($path)||filesize($path)>2621440||max($info[0],$info[1])>6000||min($info[0],$info[1])<1
            ||!in_array($info['mime'],['image/webp','image/png','image/jpeg'],true)||!hash_equals($item['image_sha256']??'',hash_file('sha256',$path)))throw new DomainException('Unverified image contents.');
    }
}
