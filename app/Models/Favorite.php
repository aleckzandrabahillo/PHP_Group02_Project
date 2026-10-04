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

    /**
     * Toggle a customer's favorite and return true when the product is saved.
     */
    public function toggle(int $userId, int $productId): bool
    {
        return Database::transaction(function (PDO $pdo) use ($userId, $productId): bool {
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
                return false;
            }

            $insert = $pdo->prepare(
                'INSERT INTO favorites (user_id, product_id, created_at) VALUES (:user_id, :product_id, NOW())'
            );
            $insert->execute(['user_id' => $userId, 'product_id' => $productId]);
            return true;
        });
    }
}
