<?php

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

final class Favorite
{
    public function idsForUser(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT product_id FROM favorites WHERE user_id = :user_id ORDER BY created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'product_id'));
    }

    public function countForUser(int $userId): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function productsForUser(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.id, p.sku, p.name, p.description, p.price, p.stock_qty, p.image_path, p.routine_step,
                    c.name AS category_name, f.created_at AS favorited_at
             FROM favorites f
             INNER JOIN products p ON p.id = f.product_id
             INNER JOIN categories c ON c.id = p.category_id
             WHERE f.user_id = :user_id
               AND p.status = "active"
               AND c.status = "active"
             ORDER BY f.created_at DESC, p.name ASC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function pageForUser(int $userId,array $input=[]):array
    {
        $pdo=Database::connection();$q=is_string($input['q']??'')?mb_substr(trim($input['q']??''),0,100):'';
        $sort=is_string($input['sort']??'')?($input['sort']??''):'';$orders=['saved'=>'f.created_at DESC,p.id DESC','name'=>'p.name ASC,p.id ASC','price_asc'=>'p.price ASC,p.id ASC','price_desc'=>'p.price DESC,p.id ASC'];if(!isset($orders[$sort]))$sort='saved';
        $where=' FROM favorites f JOIN products p ON p.id=f.product_id JOIN categories c ON c.id=p.category_id WHERE f.user_id=:user AND p.status="active" AND c.status="active"';$params=['user'=>$userId];
        if($q!==''){$where.=' AND (p.name LIKE :name OR p.sku LIKE :sku)';$literal='%'.strtr($q,['='=>'==','%'=>'=%','_'=>'=_']).'%';$where=str_replace('LIKE :name','LIKE :name ESCAPE \'=\'',$where);$where=str_replace('LIKE :sku','LIKE :sku ESCAPE \'=\'',$where);$params['name']=$literal;$params['sku']=$literal;}
        $count=$pdo->prepare('SELECT COUNT(*)'.$where);$count->execute($params);$total=(int)$count->fetchColumn();$pages=max(1,(int)ceil($total/12));$raw=$input['page']??1;$page=(is_string($raw)||is_int($raw))?filter_var($raw,FILTER_VALIDATE_INT):false;$page=max(1,min($pages,$page===false?1:$page));
        $stmt=$pdo->prepare('SELECT p.id,p.sku,p.name,p.description,p.price,p.stock_qty,p.image_path,p.routine_step,c.name AS category_name,f.created_at AS favorited_at'.$where.' ORDER BY '.$orders[$sort].' LIMIT 12 OFFSET '.(($page-1)*12));$stmt->execute($params);
        return ['items'=>$stmt->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages,'q'=>$q,'sort'=>$sort];
    }
    /**
     * Toggle a customer's favorite and return true when the product is saved.
     */
    public function toggle(int $userId, int $productId): bool
    {
        return Database::transaction(function (PDO $pdo) use ($userId, $productId): bool {
            $user=(new User())->findById($userId,true);
            if(!User::canAuthenticate($user)||$user['role']!=='customer')throw new InvalidArgumentException('An active customer account is required.');
            $product = $pdo->prepare(
                'SELECT p.id
                 FROM products p
                 INNER JOIN categories c ON c.id = p.category_id
                 WHERE p.id = :product_id AND p.status = "active" AND c.status = "active"
                 LIMIT 1'
            );
            $product->execute(['product_id' => $productId]);
            if (!$product->fetchColumn()) {
                throw new InvalidArgumentException('Product is not available.');
            }

            $existing = $pdo->prepare(
                'SELECT 1 FROM favorites WHERE user_id = :user_id AND product_id = :product_id LIMIT 1 FOR UPDATE'
            );
            $existing->execute(['user_id' => $userId, 'product_id' => $productId]);

            if ($existing->fetchColumn()) {
                $delete = $pdo->prepare('DELETE FROM favorites WHERE user_id = :user_id AND product_id = :product_id');
                $delete->execute(['user_id' => $userId, 'product_id' => $productId]);
                \App\Services\AuditService::record($pdo,$userId,'favorite.removed','product',$productId);
                return false;
            }

            $insert = $pdo->prepare(
                'INSERT INTO favorites (user_id, product_id, created_at) VALUES (:user_id, :product_id, NOW())'
            );
            $insert->execute(['user_id' => $userId, 'product_id' => $productId]);
            \App\Services\AuditService::record($pdo,$userId,'favorite.added','product',$productId);
            return true;
        });
    }
}
