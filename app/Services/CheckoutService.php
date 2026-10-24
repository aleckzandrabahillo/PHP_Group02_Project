<?php

namespace App\Services;

use App\Core\CheckoutValidator;
use App\Core\Database;
use App\Models\User;
use InvalidArgumentException;
use PDO;
use Throwable;

final class CheckoutService
{
    private const MAX_CENTS = 9999999999;

    public function __construct(private readonly ?PDO $connection = null) {}

    public function quote(int $userId): array
    {
        return $this->transaction(function (PDO $pdo) use ($userId): array {
            $this->lockCustomer($pdo, $userId);
            return $this->lockBag($pdo, $userId);
        });
    }

    public function place(int $userId, array $input, string $key, string $signature): int
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $key) || !preg_match('/^[a-f0-9]{64}$/D', $signature)) {
            throw new InvalidArgumentException('Refresh checkout before placing an order.');
        }
        return $this->transaction(function (PDO $pdo) use ($userId, $input, $key, $signature): int {
            $customer = $this->lockCustomer($pdo, $userId);
            // User-row locking serializes same-customer cart mutations and repeated submissions.
            // The customer lock handles replays; avoid absent-key gap locks before stock locking.
            $repeat = $pdo->prepare('SELECT id,user_id FROM orders WHERE checkout_key=?');
            $repeat->execute([$key]);
            if ($existing = $repeat->fetch()) {
                if ((int) $existing['user_id'] !== $userId) throw new InvalidArgumentException('Refresh checkout before placing an order.');
                return (int) $existing['id'];
            }
            $checked = CheckoutValidator::validate($input);
            if ($checked['errors']) throw new InvalidArgumentException('Check your contact and delivery information.');
            $values = $checked['values'];
            if(($input['location_version']??'')==='2'&&!DeliveryLocationMigration::installed($pdo))throw new InvalidArgumentException('Checkout is temporarily unavailable. Please try again later.');
            $bag = $this->lockBag($pdo, $userId);
            if (!$bag['items']) throw new InvalidArgumentException('Your bag is empty.');
            if (!$bag['checkout_ready']) throw new InvalidArgumentException('Review unavailable items or quantities in your bag.');
            if (!hash_equals($bag['signature'], $signature)) {
                throw new InvalidArgumentException('Your bag or product details changed. Review the updated summary before placing your order.');
            }
            if (!filter_var($customer['email'], FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Your account needs a valid email address before checkout.');
            $maya = $values['payment_method'] === 'maya_sandbox';
            $stmt = $pdo->prepare('INSERT INTO orders (user_id,delivery_address_snapshot,recipient_name_snapshot,contact_email_snapshot,contact_no_snapshot,payment_method,subtotal,total,status,checkout_key) VALUES (?,?,?,?,?,?,?,?,"pending",?)');
            $discounts=new DiscountService();$discount=null;
            if($values['discount_code']!==''){
                if(!preg_match('/^[a-f0-9]{32}$/D',$values['discount_revision']))throw new InvalidArgumentException('Apply the discount before placing your order.');
                $discount=$discounts->quote($pdo,$userId,$values['discount_code'],self::cents($bag['subtotal']),true,$values['discount_revision']);
            }
            $eligible=self::cents($bag['subtotal'])-($discount['amount_cents']??0);
            $shipping=ShippingService::quote($values['postal_code'],$values['shipping_method'],$values['city'],$values['region'],$eligible);
            $totalCents=$eligible+$shipping['cents'];
            if($totalCents > self::MAX_CENTS)throw new InvalidArgumentException('Your order exceeds the maximum total. Reduce quantities in your bag.');
            $total = self::decimal($totalCents);
            $stmt->execute([$userId, $values['delivery_address'], $values['full_name'], $customer['email'], $values['contact_no'], $maya ? 'manual' : 'cod', $bag['subtotal'], $total, $key]);
            $orderId = (int) $pdo->lastInsertId();
            PiiStorage::write($pdo,'orders',$orderId,['contact_no_snapshot'=>$values['contact_no'],'delivery_address_snapshot'=>$values['delivery_address']]);
            OrderReference::create($pdo,$orderId);
            if ($maya) MayaPaymentService::reserve($pdo, $orderId, $totalCents);
            if ($discount) $discounts->reserve($pdo,$orderId,$userId,$discount,$maya);
            $pdo->prepare('INSERT INTO order_delivery_details(order_id,country,first_name,last_name,address_line,barangay,postal_code,city,region,shipping_method,shipping_fee) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$orderId,$values['country'],$values['first_name'],$values['last_name'],$values['address_line'],$values['barangay'],$values['postal_code'],$values['city'],$values['region'],$shipping['method'],$shipping['fee']]);
            if(DeliveryLocationMigration::installed($pdo))$pdo->prepare('UPDATE order_delivery_details SET province=? WHERE order_id=?')->execute([$values['province']?:null,$orderId]);
            (new OrderWorkflow())->created($pdo, $orderId, $userId, !$maya);
            $insert = $pdo->prepare('INSERT INTO order_items (order_id,product_id,product_name_snapshot,quantity,unit_price_snapshot) VALUES (?,?,?,?,?)');
            $deduct = $pdo->prepare('UPDATE products SET stock_qty=stock_qty-?,updated_at=NOW() WHERE id=? AND stock_qty>=? AND status="active"');
            foreach ($bag['items'] as $item) {
                $deduct->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
                if ($deduct->rowCount() !== 1) throw new InvalidArgumentException('Stock changed. Review your bag and try again.');
                $insert->execute([$orderId, $item['product_id'], $item['name'], $item['quantity'], $item['price']]);
            }
            $convert = $pdo->prepare('UPDATE carts SET status="converted",updated_at=NOW() WHERE id=? AND user_id=? AND status="active"');
            foreach ($bag['cart_ids'] as $id) $convert->execute([$id, $userId]);
            $audit = $pdo->prepare('INSERT INTO audit_logs (actor_id,action,target_type,target_id,details) VALUES (?,"order.create","order",?,?)');
            $audit->execute([$userId, $orderId, 'Checkout / Order Processing; items ' . $bag['item_count'] . ($maya ? '; Maya Sandbox reserved; total PHP ' : '; COD; total PHP ') . $total]);
            return $orderId;
        });
    }

    private function lockCustomer(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare('SELECT id,email,role,status,locked_until,lock_requires_admin FROM users WHERE id=? FOR UPDATE');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!User::canAuthenticate($user ?: null) || $user['role'] !== 'customer') {
            throw new InvalidArgumentException('An active customer account is required.');
        }
        return $user;
    }

    private function lockBag(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare('SELECT id FROM carts WHERE user_id=? AND status="active" ORDER BY id FOR UPDATE');
        $stmt->execute([$userId]);
        $cartIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $rows = [];
        if ($cartIds) {
            $stmt = $pdo->prepare('SELECT cart_id,product_id,quantity FROM cart_items WHERE cart_id IN (' . implode(',', array_fill(0, count($cartIds), '?')) . ') ORDER BY product_id,cart_id FOR UPDATE');
            $stmt->execute($cartIds);
            $rows = $stmt->fetchAll();
        }
        $products = [];
        if ($rows) {
            $ids = array_values(array_unique(array_map('intval', array_column($rows, 'product_id'))));
            sort($ids);
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $find = $pdo->prepare('SELECT id,category_id FROM products WHERE id IN (' . $marks . ')');
            $find->execute($ids);
            $links = $find->fetchAll();
            $categories = array_values(array_unique(array_map('intval', array_column($links, 'category_id'))));
            sort($categories);
            $categoryRows = [];
            if ($categories) {
                $cats = $pdo->prepare('SELECT id,name,status FROM categories WHERE id IN (' . implode(',', array_fill(0, count($categories), '?')) . ') ORDER BY id FOR UPDATE');
                $cats->execute($categories);
                $categoryRows = array_column($cats->fetchAll(), null, 'id');
            }
            $locked = $pdo->prepare('SELECT * FROM products WHERE id IN (' . $marks . ') ORDER BY id FOR UPDATE');
            $locked->execute($ids);
            foreach ($locked->fetchAll() as $product) {
                $cat = $categoryRows[$product['category_id']] ?? null;
                // A concurrent category reassignment requires a fresh quote.
                if (!$cat) throw new InvalidArgumentException('Product details changed. Refresh checkout.');
                $product['category_name'] = $cat['name'];
                $product['category_status'] = $cat['status'];
                $products[$product['id']] = $product;
            }
        }
        $items = []; $fingerprint = []; $total = 0; $count = 0; $ready = !empty($rows);
        foreach ($rows as $row) {
            $id = (int) $row['product_id'];
            $p = $products[$id] ?? null;
            if (!$p) throw new InvalidArgumentException('A product is no longer available.');
            $quantity = (int) $row['quantity'];
            if ($quantity < 1 || $quantity > 99) throw new InvalidArgumentException('Choose bag quantities between 1 and 99 before checkout.');
            $price = self::cents((string) $p['price']);
            $fingerprint[] = [(int) $row['cart_id'], $id, $quantity, $p['price'], $p['name'], (int) $p['category_id'], $p['status'], $p['category_status']];
            if (!isset($items[$id])) $items[$id] = [...$p, 'product_id' => $id, 'quantity' => 0];
            $items[$id]['quantity'] += $quantity;
            $count += $quantity;
            $total += $price * $quantity;
            if ($total > self::MAX_CENTS) throw new InvalidArgumentException('Your bag exceeds the maximum order total. Reduce quantities before checkout.');
        }
        foreach ($items as &$item) {
            $item['is_available'] = $item['status'] === 'active' && $item['category_status'] === 'active'
                && $item['quantity'] >= 1 && $item['quantity'] <= 99 && $item['quantity'] <= (int) $item['stock_qty'];
            $item['line_total'] = self::decimal(self::cents((string) $item['price']) * $item['quantity']);
            if (!$item['is_available']) $ready = false;
        }
        unset($item);
        if ($total > self::MAX_CENTS) throw new InvalidArgumentException('Your bag exceeds the maximum order total. Reduce quantities before checkout.');
        return ['items' => array_values($items), 'cart_ids' => $cartIds, 'subtotal' => self::decimal($total),
            'item_count' => $count, 'checkout_ready' => $ready, 'signature' => hash('sha256', json_encode([$cartIds, $fingerprint], JSON_THROW_ON_ERROR))];
    }

    private static function cents(string $value): int
    {
        if (!preg_match('/^([0-9]{1,8})\.([0-9]{2})$/D', $value, $parts)) throw new InvalidArgumentException('A product price is invalid. Please contact the store.');
        return (int) $parts[1] * 100 + (int) $parts[2];
    }

    private static function decimal(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function transaction(callable $operation): mixed
    {
        $pdo = $this->connection ?? Database::connection();
        for ($attempt = 0; ; $attempt++) {
            try {
                $pdo->beginTransaction();
                $result = $operation($pdo);
                $pdo->commit();
                return $result;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                // A catalog deletion can choose the opposite lock order. Retry only rolled-back deadlocks.
                if ($attempt < 2 && $e instanceof \PDOException && (int) ($e->errorInfo[1] ?? 0) === 1213) continue;
                throw $e;
            }
        }
    }
}
