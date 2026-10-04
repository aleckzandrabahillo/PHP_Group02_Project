<?php

namespace App\Services;

use App\Core\Database;

final class DashboardService
{
    private function scalar(string $sql, array $params = []): int
    {
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function customer(int $userId): array
    {
        return [
            'orders' => $this->scalar('SELECT COUNT(*) FROM orders WHERE user_id = :id', ['id' => $userId]),
            'cart_items' => $this->scalar('SELECT COALESCE(SUM(ci.quantity),0) FROM carts c JOIN cart_items ci ON ci.cart_id = c.id WHERE c.user_id = :id AND c.status = "active"', ['id' => $userId]),
            'hair_profiles' => $this->scalar('SELECT COUNT(*) FROM hair_profiles WHERE user_id = :id', ['id' => $userId]),
        ];
    }

    public function catalog(): array
    {
        return [
            'products' => $this->scalar('SELECT COUNT(*) FROM products'),
            'active_products' => $this->scalar('SELECT COUNT(*) FROM products WHERE status = "active"'),
            'low_stock' => $this->scalar('SELECT COUNT(*) FROM products WHERE stock_qty BETWEEN 1 AND 5'),
            'out_of_stock' => $this->scalar('SELECT COUNT(*) FROM products WHERE stock_qty = 0'),
            'categories' => $this->scalar('SELECT COUNT(*) FROM categories'),
        ];
    }

    public function admin(): array
    {
        return [
            'customers' => $this->scalar('SELECT COUNT(*) FROM users WHERE role = "customer"'),
            'staff' => $this->scalar('SELECT COUNT(*) FROM users WHERE role IN ("admin","catalog_manager")'),
            'orders' => $this->scalar('SELECT COUNT(*) FROM orders'),
            'locked' => $this->scalar('SELECT COUNT(*) FROM users WHERE locked_until IS NOT NULL AND locked_until > NOW()'),
        ];
    }
}
