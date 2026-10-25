<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Sql;
use PDO;

final class Order
{
    /**
     * Allowed status moves: current status => statuses it may move to next.
     * Keep in sync with the orders.status ENUM in database/schema.sql.
     */
    public const FLOW = [
        'pending'    => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped'    => ['delivered'],
        'delivered'  => [],
        'cancelled'  => [],
    ];

    public function __construct(private readonly ?PDO $connection = null) {}

    /** @return string[] */
    public static function statuses(): array
    {
        return array_keys(self::FLOW);
    }

    /** @return string[] Statuses an order currently in $status may move to. */
    public static function nextStatuses(string $status): array
    {
        return self::FLOW[$status] ?? [];
    }

    /**
     * Paginated order list for the admin screen. $term matches order number, customer email or customer name.
     *
     * @return array{rows: array, total: int}
     */
    public function search(string $term, string $status, int $limit, int $offset): array
    {
        $pdo = $this->connection ?? Database::connection();
        $where = [];
        $params = [];

        $term = trim($term);
        if ($term !== '') {
            $like = Sql::contains($term);
            $clauses = ['u.email LIKE :s_email', 'COALESCE(cp.full_name, u.username) LIKE :s_name'];
            $params['s_email'] = $like;
            $params['s_name'] = $like;

            $number = ltrim($term, '#');
            if (ctype_digit($number) && strlen($number) <= 18) {   // "12" or "#12" also finds order 12
                $clauses[] = 'o.id = :s_id';
                $params['s_id'] = (int) $number;
            }
            $where[] = '(' . implode(' OR ', $clauses) . ')';
        }

        if (isset(self::FLOW[$status])) {
            $where[] = 'o.status = :status';
            $params['status'] = $status;
        }

        $from = 'FROM orders o
                 INNER JOIN users u ON u.id = o.user_id
                 LEFT JOIN customer_profiles cp ON cp.user_id = u.id'
              . ($where ? ' WHERE ' . implode(' AND ', $where) : '');

        $count = $pdo->prepare('SELECT COUNT(*) ' . $from);
        $count->execute($params);

        $rows = $pdo->prepare(
            'SELECT o.id, o.total, o.status, o.payment_method, o.created_at, u.email,
                    COALESCE(cp.full_name, u.username) AS customer '
            . $from . ' ORDER BY o.created_at DESC, o.id DESC' . Sql::limitOffset($limit, $offset)
        );
        $rows->execute($params);

        return ['rows' => $rows->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    /** One order with its line items (price/name snapshots) and customer details, for the admin screen. Null if it does not exist. */
    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $pdo = $this->connection ?? Database::connection();
        $stmt = $pdo->prepare(
            'SELECT o.*, u.email, COALESCE(cp.full_name, u.username) AS customer, cp.contact_no
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
     * Move an order to $to if FLOW allows it from its current status.
     *
     * The order row is locked (FOR UPDATE) and re-read inside the transaction, so a stale page
     * or two admins acting at once can never skip or repeat a step. $afterChange runs inside the
     * same transaction (the controller records the audit entry there), so the status change and
     * its audit entry are saved together or not at all.
     *
     * Stock is deliberately NOT touched: cancelling does not return stock here. Add that inside
     * this transaction if stock needs to be restored on cancel.
     *
     * @param null|callable(PDO, string, string): void $afterChange receives (pdo, from, to)
     * @return array{ok: bool, message: string, missing?: bool, from?: string}
     */
    public function transition(int $id, string $to, ?callable $afterChange = null): array
    {
        if (!isset(self::FLOW[$to])) {
            return ['ok' => false, 'message' => 'Unknown order status.'];
        }

        return Database::transaction(function (PDO $pdo) use ($id, $to, $afterChange): array {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();

            if (!$row) {
                return ['ok' => false, 'missing' => true, 'message' => 'Order not found.'];
            }
            $from = $row['status'];

            // An online order is only fulfilled once PayMongo has confirmed payment.
            if (
                ($row['payment_method'] ?? '') === 'paymongo'
                && ($row['payment_status'] ?? '') !== 'paid'
                && in_array($to, ['processing', 'shipped', 'delivered'], true)
            ) {
                return ['ok' => false, 'from' => $from, 'message' => "Order #{$id} is waiting for online payment and cannot move to {$to} until PayMongo confirms it."];
            }
            if (!in_array($to, self::FLOW[$from] ?? [], true)) {
                return ['ok' => false, 'from' => $from, 'message' => "Order #{$id} is {$from} and cannot be changed to {$to}."];
            }

            $pdo->prepare('UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :id')
                ->execute(['status' => $to, 'id' => $id]);

            if ($afterChange !== null) {
                $afterChange($pdo, $from, $to);
            }

            return ['ok' => true, 'from' => $from, 'message' => "Order #{$id} moved from {$from} to {$to}."];
        });
    }

    public function forCustomer(int $userId, int $page = 1): array
    {
        $pdo = $this->connection ?? Database::connection();
        $count = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id=?');
        $count->execute([$userId]);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / 10));
        $page = max(1, min($page, $pages));
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY id DESC LIMIT 10 OFFSET ' . (($page - 1) * 10));
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
