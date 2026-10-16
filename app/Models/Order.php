<?php

namespace App\Models;

use App\Core\Database;
use PDO;

final class Order
{
    public function __construct(private readonly ?PDO $connection = null) {}

    public function forCustomer(int $userId, int $page = 1): array
    {
        $pdo = $this->connection ?? Database::connection();
        $count = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id=?');
        $count->execute([$userId]);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / 10));
        $page = max(1, min($page, $pages));
        $stmt = $pdo->prepare('SELECT id,total,status,payment_method,created_at FROM orders WHERE user_id=? ORDER BY id DESC LIMIT 10 OFFSET ' . (($page - 1) * 10));
        $stmt->execute([$userId]);
        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public function owned(int $id, int $userId): ?array
    {
        $pdo = $this->connection ?? Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id=? AND user_id=?');
        $stmt->execute([$id, $userId]);
        $order = $stmt->fetch();
        if (!$order) return null;
        $items = $pdo->prepare('SELECT product_id,product_name_snapshot,quantity,unit_price_snapshot FROM order_items WHERE order_id=? ORDER BY id');
        $items->execute([$id]);
        $order['items'] = $items->fetchAll();
        return $order;
    }

    public function submitted(int $userId, string $key): ?int
    {
        $stmt = ($this->connection ?? Database::connection())->prepare('SELECT id FROM orders WHERE user_id=? AND checkout_key=?');
        $stmt->execute([$userId, $key]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }
}
