<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\User;
use DomainException;
use PDO;

/** Explicit CLI catalog replacement: archive historical products, never delete their relations. */
final class CatalogRefresh
{
    public function __construct(private readonly PDO $pdo, private readonly string $root) {}

    public static function fingerprint(array $products): string
    {
        return hash('sha256',json_encode($products,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    }

    public function run(bool $apply, ?string $actor, array $expected): array
    {
        if($this->pdo->inTransaction())throw new DomainException('An independent transaction is required.');
        $source=file_get_contents($this->root.'/database/avela_catalog_20261030.json');
        $manifest=json_decode($source,true,32,JSON_THROW_ON_ERROR);
        $evidence=json_decode(file_get_contents($this->root.'/database/avela_suitability_20261030.json'),true,32,JSON_THROW_ON_ERROR);
        if(($manifest['catalog_id']??'')!=='avela-fictional-20261030'||($manifest['currency']??'')!=='PHP'||count($manifest['products']??[])!==100
            ||!hash_equals($evidence['catalog_sha256']??'',hash('sha256',$source)))throw new DomainException('Reviewed catalog identity differs.');
        $identity=$this->pdo->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch();
        $normalize=static fn($path)=>strtolower(rtrim(str_replace('\\','/',(string)$path),'/'));
        if($apply&&(($expected['host']??'')!==env('DB_HOST')||($expected['database']??'')!==$identity['db']
            ||(int)($expected['port']??0)!==(int)$identity['port']||$normalize($expected['datadir']??'')!==$normalize($identity['datadir'])
            ||($expected['source_sha256']??'')!==hash('sha256',$source)))throw new DomainException('Write target or source confirmation differs.');
        $lock='avela.catalog.refresh.'.substr(hash('sha256',$identity['db']),0,30);$locked=false;
        try{
            if($apply){$q=$this->pdo->prepare('SELECT GET_LOCK(?,5)');$q->execute([$lock]);$locked=(int)$q->fetchColumn()===1;if(!$locked)throw new DomainException('Another catalog refresh is running.');}
            else $this->pdo->exec('SET TRANSACTION READ ONLY');
            $this->pdo->beginTransaction();$suffix=$apply?' FOR UPDATE':'';
            $categories=$this->pdo->query('SELECT * FROM categories ORDER BY id'.$suffix)->fetchAll();
            $before=$this->pdo->query('SELECT * FROM products ORDER BY id'.$suffix)->fetchAll();
            $tags=$this->pdo->query('SELECT * FROM tags ORDER BY id'.$suffix)->fetchAll();
            $fingerprint=self::fingerprint($before);
            if($apply&&!hash_equals($expected['catalog_sha256']??'',$fingerprint))throw new DomainException('Catalog changed since preview; review again.');
            $old=json_decode(file_get_contents($this->root.'/database/demo_catalog.json'),true,32,JSON_THROW_ON_ERROR);
            $oldSkus=array_column($old['products'],'sku');$newSkus=array_column($manifest['products'],'sku');
            if(count(array_unique($newSkus))!==100)throw new DomainException('Duplicate import identity.');
            $archive=[];$existing=[];
            foreach($before as $row){
                if(in_array($row['sku'],$newSkus,true))$existing[$row['sku']]=$row;
                elseif(in_array($row['sku'],$oldSkus,true)){if($row['status']==='active')$archive[]=$row;}
                else throw new DomainException('An unrecognized product exists; preserve it and review the replacement.');
            }
            $categoryMap=[];foreach($categories as $category){if($category['status']==='active')$categoryMap[$category['name']]=$category['id'];}
            $tagMap=[];foreach($tags as $tag)$tagMap[$tag['tag_type'].':'.$tag['code']]=$tag['id'];
            $actorId=null;
            if($apply){
                $q=$this->pdo->prepare('SELECT * FROM users WHERE username=? FOR UPDATE');$q->execute([$actor]);$user=$q->fetch();
                if(!User::canAuthenticate($user)||!in_array($user['role'],['admin','catalog_manager'],true))throw new DomainException('An active unlocked staff actor is required.');
                $actorId=(int)$user['id'];
            }
            $insert=[];$needsCategory=[];
            foreach($manifest['products']as $item){
                if(!preg_match('/^AVE-26-[0-9]{4}$/D',$item['sku'])||mb_strlen($item['name'])>160||trim($item['name'])===''
                    ||!preg_match('/^[0-9]{1,8}\.[0-9]{2}$/D',$item['price'])||$item['stock_qty']!==20||$item['status']!=='active'
                    ||!in_array($item['routine_step'],['cleanse','condition','treat','finish'],true))throw new DomainException('Invalid reviewed product fields.');
                foreach($item['tags']as $tag)if(!isset($tagMap[$tag]))throw new DomainException('An imported tag is unsupported.');
                if($item['image_path']!==null){
                    if(!preg_match('~^assets/images/products/catalog-20261030/[a-z0-9_-]+\.(webp|png|jpg)$~D',$item['image_path']))throw new DomainException('Invalid image path.');
                    $path=$this->root.'/public/'.$item['image_path'];$image=@getimagesize($path);
                    if(!$image||!in_array($image['mime']??'',['image/webp','image/png','image/jpeg'],true)||is_link($path)||filesize($path)>2621440
                        ||!hash_equals($item['image_sha256']??'',hash_file('sha256',$path)))throw new DomainException('Approved product image is unavailable or changed.');
                }
                if(isset($existing[$item['sku']])){
                    // Never replenish an existing product or overwrite later staff edits on repeat runs.
                    foreach(['name','description','price','routine_step','image_path']as $field)if((string)$existing[$item['sku']][$field]!== (string)$item[$field])throw new DomainException('Existing imported metadata changed; no overwrite performed.');
                    continue;
                }
                if(!isset($categoryMap[$item['category']])){
                    if($item['category']!=='Styling')throw new DomainException('An expected category is unavailable.');
                    $needsCategory['Styling']=true;
                }
                $insert[]=$item;
            }
            if($apply){
                foreach(array_keys($needsCategory)as $name){
                    if(array_filter($categories,fn($c)=>$c['name']===$name))throw new DomainException('Requested category is inactive; review it first.');
                    $this->pdo->prepare('INSERT INTO categories(name,description,status) VALUES (?,?,"active")')->execute([$name,'Styling products for the fictional Avela demonstration.']);
                    $categoryMap[$name]=(int)$this->pdo->lastInsertId();AuditService::record($this->pdo,$actorId,'category.create','category',$categoryMap[$name],['source'=>'avela-fictional-20261030']);
                }
                foreach($insert as $item){
                    $q=$this->pdo->prepare('INSERT INTO products(category_id,sku,name,description,price,stock_qty,image_path,status,routine_step) VALUES (?,?,?,?,?,?,?,?,?)');
                    $q->execute([$categoryMap[$item['category']],$item['sku'],$item['name'],$item['description'],$item['price'],20,$item['image_path'],'active',$item['routine_step']]);$id=(int)$this->pdo->lastInsertId();
                    foreach($item['tags']as $tag)$this->pdo->prepare('INSERT INTO product_tags(product_id,tag_id) VALUES (?,?)')->execute([$id,$tagMap[$tag]]);
                    AuditService::record($this->pdo,$actorId,'product.create','product',$id,['source'=>'avela-fictional-20261030','initial_stock'=>20]);
                }
                foreach($archive as $item){
                    $this->pdo->prepare('UPDATE products SET status="inactive" WHERE id=?')->execute([$item['id']]);
                    AuditService::record($this->pdo,$actorId,'product.archive','product',(int)$item['id'],['reason'=>'Replaced by owner-approved fictional catalog; history and stock retained.']);
                }
                $this->pdo->commit();
            }else $this->pdo->rollBack();
            return ['mode'=>$apply?'apply':'dry-run','target'=>$identity,'catalog_sha256'=>$fingerprint,'source_sha256'=>hash('sha256',$source),'inserts'=>count($insert),'archives'=>count($archive),'unchanged_imported'=>count($existing),'missing_images'=>count(array_filter($manifest['products'],fn($p)=>$p['image_path']===null))];
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        finally{if($locked){try{$q=$this->pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}catch(\Throwable){}}}
    }
}
