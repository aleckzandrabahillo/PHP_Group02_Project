<?php

namespace App\Models;

use App\Core\Database;
use PDO;

final class Order
{
    /**
     * Allowed status transitions: current status => statuses it may move to.
     * Must match the orders.status ENUM in database/schema.sql.
     */
    public const FLOW = [
        'pending'    => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped'    => ['delivered'],
        'delivered'  => [],
        'cancelled'  => [],
    ];

    /**
     * Set to true ONLY once checkout deducts products.stock_qty when an order is placed.
     * Today no code inserts orders or deducts stock, so restoring on cancel would inflate inventory.
     */
    private const RESTORE_STOCK_ON_CANCEL = false;

    /**
     * Paginated order list with optional search (order #, customer email or name) and status filter.
     *
     * @return array{rows: array, total: int}
     */
    public function search(string $q, string $status, int $limit, int $offset): array
    {
        $where = [];
        $params = [];

        $q = trim($q);
        if ($q !== '') {
            $where[] = '(o.id = :qid OR u.email LIKE :q_email OR COALESCE(cp.full_name, "") LIKE :q_name)';
            $params['qid'] = (int) ltrim($q, '#');
            $params['q_email'] = '%' . $q . '%';
            $params['q_name'] = '%' . $q . '%';
        }

        if (isset(self::FLOW[$status])) {
            $where[] = 'o.status = :status';
            $params['status'] = $status;
        }

        $clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $from = 'FROM orders o
                 INNER JOIN users u ON u.id = o.user_id
                 LEFT JOIN customer_profiles cp ON cp.user_id = u.id ' . $clause;

        $pdo = Database::connection();

        $count = $pdo->prepare('SELECT COUNT(*) ' . $from);
        $count->execute($params);

        $rows = $pdo->prepare(
            'SELECT o.id, o.total, o.status, o.payment_method, o.created_at, u.email,
                    COALESCE(cp.full_name, u.username) AS customer
             ' . $from . '
             ORDER BY o.created_at DESC, o.id DESC
             LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
        $rows->execute($params);

        return ['rows' => $rows->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    /** One order with its line items (name/price snapshots), or null if not found. */
    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT o.*, u.email, u.username, COALESCE(cp.full_name, u.username) AS customer, cp.contact_no
             FROM orders o
             INNER JOIN users u ON u.id = o.user_id
             LEFT JOIN customer_profiles cp ON cp.user_id = u.id
             WHERE o.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $order = $stmt->fetch();
        if (!$order) {
            return null;
        }

        $items = $pdo->prepare(
            'SELECT id, product_id, product_name_snapshot, quantity, unit_price_snapshot,
                    (quantity * unit_price_snapshot) AS line_total
             FROM order_items
             WHERE order_id = :id
             ORDER BY id ASC'
        );
        $items->execute(['id' => $id]);
        $order['items'] = $items->fetchAll();

        return $order;
    }

    /**
     * Move an order to a new status if the transition is allowed.
     * The row is locked so two admins cannot change the same order at once.
     *
     * @return array{ok: bool, message: string, from?: string}
     */
    public function transition(int $id, string $to): array
    {
        if (!isset(self::FLOW[$to])) {
            return ['ok' => false, 'message' => 'Unknown order status.'];
        }

        return Database::transaction(function (PDO $pdo) use ($id, $to): array {
            $stmt = $pdo->prepare('SELECT status FROM orders WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $from = $stmt->fetchColumn();

            if ($from === false) {
                return ['ok' => false, 'message' => 'Order not found.'];
            }
            if (!in_array($to, self::FLOW[$from] ?? [], true)) {
                return ['ok' => false, 'message' => "An order that is {$from} cannot be changed to {$to}."];
            }

            $pdo->prepare('UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :id')
                ->execute(['status' => $to, 'id' => $id]);

            if ($to === 'cancelled' && self::RESTORE_STOCK_ON_CANCEL) {
                $pdo->prepare(
                    'UPDATE products p
                     INNER JOIN order_items oi ON oi.product_id = p.id
                     SET p.stock_qty = p.stock_qty + oi.quantity
                     WHERE oi.order_id = :id'
                )->execute(['id' => $id]);
            }

            return ['ok' => true, 'message' => "Order #{$id} is now {$to}.", 'from' => $from];
        });
    }
}