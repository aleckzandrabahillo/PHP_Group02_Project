<?php

namespace App\Models;

use App\Core\Database;
use PDOException;

final class Review
{
    public function forProduct(int $productId, int $limit = 20): array
    {
        $limit = max(1, min($limit, 50));
        try {
            $stmt = Database::connection()->prepare(
                'SELECT pr.id, pr.rating, pr.review_text, pr.created_at,
                        COALESCE(cp.full_name, u.username) AS reviewer_name
                 FROM product_reviews pr
                 INNER JOIN users u ON u.id = pr.user_id
                 LEFT JOIN customer_profiles cp ON cp.user_id = u.id
                 WHERE pr.product_id = :product_id AND pr.status = "published"
                 ORDER BY pr.created_at DESC, pr.id DESC
                 LIMIT ' . $limit
            );
            $stmt->execute(['product_id' => $productId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02') return [];
            throw $e;
        }
    }

    public function summaryForProduct(int $productId): array
    {
        try {
            $stmt = Database::connection()->prepare(
                'SELECT COUNT(*) AS review_count, COALESCE(AVG(rating), 0) AS average_rating
                 FROM product_reviews
                 WHERE product_id = :product_id AND status = "published"'
            );
            $stmt->execute(['product_id' => $productId]);
            $row = $stmt->fetch() ?: ['review_count' => 0, 'average_rating' => 0];
            return [
                'count' => (int) $row['review_count'],
                'average' => round((float) $row['average_rating'], 1),
            ];
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02') return ['count' => 0, 'average' => 0.0];
            throw $e;
        }
    }
}
