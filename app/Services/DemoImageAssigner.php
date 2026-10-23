<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use DomainException;
use PDO;
use Throwable;

/** Trusted CLI operation for reviewed, checksum-bound demonstration assets. */
final class DemoImageAssigner
{
    public function __construct(private readonly PDO $pdo, private readonly string $publicRoot) {}

    public static function readManifest(string $file): array
    {
        $text = @file_get_contents($file);
        if ($text === false || strlen($text) > 131072) throw new DomainException('Image manifest is missing or too large.');
        try { $data = json_decode($text, true, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new DomainException('Image manifest must be valid JSON.'); }
        if (!is_array($data)) throw new DomainException('Image manifest must be an object.');
        return $data;
    }

    public function run(array $manifest, bool $apply, string $actor, array $confirm, array $expectedIds = []): array
    {
        $images = $this->validateManifest($manifest);
        if ($this->pdo->inTransaction()) throw new DomainException('Image assignment requires its own transaction.');
        $server = $this->pdo->query('SELECT DATABASE() db,@@port port,@@hostname server,@@server_id server_id,@@datadir datadir')->fetch();
        $target = ['host'=>(string)env('DB_HOST',''), 'port'=>(int)$server['port'], 'database'=>(string)$server['db'],
            'server'=>(string)$server['server'], 'server_id'=>(int)$server['server_id'], 'datadir'=>(string)$server['datadir']];
        foreach (['host','database','server'] as $key) {
            if (!is_string($confirm[$key] ?? null) || $confirm[$key] === '' || $confirm[$key] !== $target[$key])
                throw new DomainException('Database/server identity confirmation does not match.');
        }
        foreach (['port','server_id'] as $key) {
            if (!is_int($confirm[$key] ?? null) || $confirm[$key] !== $target[$key])
                throw new DomainException('Database/server identity confirmation does not match.');
        }
        if (!is_string($confirm['datadir'] ?? null) || $confirm['datadir'] === ''
            || self::directory($confirm['datadir']) !== self::directory($target['datadir'])
            || (int)env('DB_PORT',0) !== $target['port'] || env('DB_DATABASE','') !== $target['database']) {
            throw new DomainException('Database/data-directory confirmation does not match.');
        }
        if (!preg_match('/^[A-Za-z0-9._-]{4,40}$/D',$actor))
            throw new DomainException('An existing staff username is required.');
        $skus = array_column($images, 'sku');
        if (array_diff(array_keys($expectedIds), $skus) || ($apply && count($expectedIds) !== count($images)))
            throw new DomainException('Apply requires exactly one confirmed product ID for every manifest SKU.');
        foreach ($expectedIds as $id) if (!is_int($id) || $id < 1 || $id > 4294967295) throw new DomainException('Invalid expected product ID.');
        $lock = 'avela.images.'.substr(hash('sha256',$target['database']),0,40);
        $locked = false;
        try {
            if ($apply) {
                $q = $this->pdo->prepare('SELECT GET_LOCK(?,5)'); $q->execute([$lock]);
                $locked = (int)$q->fetchColumn() === 1;
                if (!$locked) throw new DomainException('Another image assignment is running. Retry later.');
            } else $this->pdo->exec('SET TRANSACTION READ ONLY');
            $this->pdo->beginTransaction();
            $q = $this->pdo->prepare('SELECT id,sku,name,image_path FROM products WHERE sku IN ('
                .implode(',',array_fill(0,count($skus),'?')).') ORDER BY id'.($apply?' FOR UPDATE':''));
            $q->execute($skus); $products = array_column($q->fetchAll(),null,'sku');
            $plan=[]; $report=[];
            foreach ($images as $image) {
                $sku=$image['sku']; $p=$products[$sku]??null;
                if (!$p || $p['sku'] !== $sku || $p['name'] !== $image['name'])
                    throw new DomainException('Expected product identity was not found for '.$sku.'.');
                if (isset($expectedIds[$sku]) && $expectedIds[$sku] !== (int)$p['id'])
                    throw new DomainException('Confirmed product ID does not match '.$sku.'.');
                if ($p['image_path'] !== null && $p['image_path'] !== $image['path'])
                    throw new DomainException('Existing image conflict for '.$sku.'; nothing will be overwritten.');
                $this->validateAsset($image);
                $pending=$p['image_path']===null;
                if ($pending) $plan[]=['id'=>(int)$p['id'],...$image];
                $report[]=['sku'=>$sku,'id'=>(int)$p['id'],'path'=>$image['path'],'state'=>$pending?'planned':'unchanged'];
            }
            // Match catalog write order: products first, then current actor eligibility.
            $q=$this->pdo->prepare('SELECT id,role,status,locked_until,lock_requires_admin FROM users WHERE username=?'.($apply?' FOR UPDATE':''));
            $q->execute([$actor]); $user=$q->fetch()?:null;
            if (!User::canAuthenticate($user) || !in_array($user['role'],['admin','catalog_manager'],true))
                throw new DomainException('Actor must be an active, unlocked Administrator or Catalog Manager.');
            if ($apply) {
                $update=$this->pdo->prepare('UPDATE products SET image_path=:path WHERE id=:id AND sku=:sku AND image_path IS NULL');
                $audit=$this->pdo->prepare('INSERT INTO audit_logs (actor_id,action,target_type,target_id,details) VALUES (:actor,"product.image.assign","product",:id,:details)');
                foreach ($plan as $image) {
                    // Recheck the exact deployment bytes immediately before using their path.
                    $this->validateAsset($image);
                    $update->execute(['path'=>$image['path'],'id'=>$image['id'],'sku'=>$image['sku']]);
                    if ($update->rowCount()!==1) throw new DomainException('Product changed during assignment; transaction rolled back.');
                    $audit->execute(['actor'=>(int)$user['id'],'id'=>$image['id'],
                        'details'=>'Catalog / Product Management; demo image '.$image['sku'].'; NULL -> '.$image['path'].'; SHA256 '.$image['sha256']]);
                }
                $this->pdo->commit();
            } else $this->pdo->rollBack();
            return ['mode'=>$apply?'apply':'dry-run','target'=>$target,'actor'=>$actor,'planned_assignments'=>count($plan),
                'assigned'=>$apply?count($plan):0,'unchanged'=>count($images)-count($plan),'products'=>$report];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        } finally {
            if ($locked) {
                try { $q=$this->pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]); } catch (Throwable) {}
            }
        }
    }

    private function validateManifest(array $manifest): array
    {
        if (array_diff(array_keys($manifest),['manifest_id','version','images'])
            || ($manifest['manifest_id']??null)!=='avela-demo-images-v1' || ($manifest['version']??null)!==1
            || !is_array($manifest['images']??null) || !array_is_list($manifest['images'])
            || count($manifest['images'])<1 || count($manifest['images'])>20) {
            throw new DomainException('Expected an image manifest with one to twenty reviewed assets.');
        }
        $seen=[]; $required=['sku','name','path','sha256','width','height'];
        foreach ($manifest['images'] as $image) {
            if (!is_array($image) || array_diff($required,array_keys($image)) || array_diff(array_keys($image),$required))
                throw new DomainException('Unexpected image manifest fields.');
            foreach (['sku','name','path','sha256'] as $key) if (!is_string($image[$key]))
                throw new DomainException('Image manifest text fields must be strings.');
            if (!preg_match('/^AVE-[A-Z0-9-]{1,46}$/D',$image['sku']) || strlen($image['sku'])>50 || isset($seen[$image['sku']]))
                throw new DomainException('Invalid or duplicate image SKU.');
            $seen[$image['sku']]=true;
            if (trim($image['name'])!==$image['name'] || $image['name']==='' || !mb_check_encoding($image['name'],'UTF-8') || mb_strlen($image['name'],'UTF-8')>160)
                throw new DomainException('Invalid expected product name.');
            if ($image['path']!=='assets/images/products/demo/avela-'.strtolower($image['sku']).'.webp'
                || !preg_match('/^[a-f0-9]{64}$/D',$image['sha256']))
                throw new DomainException('Image path or reviewed checksum is invalid.');
            foreach (['width','height'] as $key) if (!is_int($image[$key]) || $image[$key]<64 || $image[$key]>4096)
                throw new DomainException('Expected dimensions must be bounded integers.');
        }
        return $manifest['images'];
    }

    private function validateAsset(array $image): void
    {
        $root=realpath($this->publicRoot);
        if ($root===false) throw new DomainException('Public asset root is unavailable.');
        $directory=str_replace('\\','/',$root).'/assets/images/products/demo';
        $file=str_replace('\\','/',$root).'/'.$image['path'];
        clearstatcache(true,$file);
        if (!is_file($file) || is_link($file)
            || str_replace('\\','/',(string)realpath(dirname($file)))!==$directory
            || str_replace('\\','/',(string)realpath($file))!==$file)
            throw new DomainException('Missing or unsafe image asset for '.$image['sku'].'.');
        $size=filesize($file);
        if ($size===false || $size<20 || $size>5*1024*1024)
            throw new DomainException('Image asset size is invalid for '.$image['sku'].'.');
        $bytes=@file_get_contents($file);
        if ($bytes===false || strlen($bytes)!==$size || !hash_equals($image['sha256'],hash('sha256',$bytes)))
            throw new DomainException('Approved image checksum does not match '.$image['sku'].'.');
        $info=@getimagesizefromstring($bytes);
        if ((new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes)!=='image/webp'
            || ($info[2]??null)!==IMAGETYPE_WEBP || ($info[0]??null)!==$image['width'] || ($info[1]??null)!==$image['height']
            || substr($bytes,0,4)!=='RIFF' || substr($bytes,8,4)!=='WEBP'
            || unpack('V',substr($bytes,4,4))[1]+8!==$size)
            throw new DomainException('WebP type, dimensions or container is invalid for '.$image['sku'].'.');
        $offset=12; $payloads=0; $extended=false;
        while ($offset+8<=$size) {
            $kind=substr($bytes,$offset,4);$length=unpack('V',substr($bytes,$offset+4,4))[1];
            if (!in_array($kind,['VP8 ','VP8L','VP8X','ALPH','ICCP'],true) || $length<1 || $offset+8+$length>$size)
                throw new DomainException('Unsupported or damaged WebP chunks for '.$image['sku'].'.');
            if (in_array($kind,['VP8 ','VP8L'],true))$payloads++;
            if ($kind==='VP8X') {
                if ($extended || $length!==10 || (ord($bytes[$offset+8]) & 0x0E)!==0)
                    throw new DomainException('Animated or metadata-bearing WebP is not permitted.');
                $extended=true;
            }
            $offset+=8+$length+($length%2);
        }
        if ($offset!==$size || $payloads!==1) throw new DomainException('Incomplete WebP asset for '.$image['sku'].'.');
    }

    private static function directory(string $path): string
    {
        $path=rtrim(str_replace('\\','/',$path),'/');
        return PHP_OS_FAMILY==='Windows'?strtolower($path):$path;
    }
}
