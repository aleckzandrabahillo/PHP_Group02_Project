<?php

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

final class Cart
{
    public function countForUser(int $userId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(SUM(ci.quantity), 0)
             FROM carts c
             LEFT JOIN cart_items ci ON ci.cart_id = c.id
             WHERE c.user_id = :user_id AND c.status = "active"'
        );
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function contentsForUser(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.id AS cart_id,
                    ci.product_id,
                    ci.quantity,
                    p.sku,
                    p.name,
                    p.description,
                    p.price,
                    p.stock_qty,
                    p.image_path,
                    p.routine_step,
                    p.status,
                    cat.name AS category_name,
                    cat.status AS category_status
             FROM carts c
             INNER JOIN cart_items ci ON ci.cart_id = c.id
             INNER JOIN products p ON p.id = ci.product_id
             INNER JOIN categories cat ON cat.id = p.category_id
             WHERE c.user_id = :user_id AND c.status = "active"
             ORDER BY ci.created_at ASC, ci.product_id ASC'
        );
        $stmt->execute(['user_id' => $userId]);
        $items = $stmt->fetchAll();

        $subtotal = 0.0;
        $itemCount = 0;
        $checkoutReady = !empty($items);

        foreach ($items as &$item) {
            $quantity = (int) $item['quantity'];
            $stock = (int) $item['stock_qty'];
            $lineTotal = round((float) $item['price'] * $quantity, 2);
            $canAdjust = (string) $item['status'] === 'active'
                && (string) $item['category_status'] === 'active'
                && $stock > 0;
            $available = $canAdjust && $quantity <= $stock;

            $item['line_total'] = $lineTotal;
            $item['can_adjust'] = $canAdjust;
            $item['is_available'] = $available;
            $subtotal += $lineTotal;
            $itemCount += $quantity;
            if (!$available) $checkoutReady = false;
        }
        unset($item);

        return [
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'item_count' => $itemCount,
            'checkout_ready' => $checkoutReady,
        ];
    }

    public function add(int $userId, int $productId, int $quantity): int
    {
        if ($quantity < 1 || $quantity > 99) {
            throw new InvalidArgumentException('Choose a quantity between 1 and 99.');
        }

        return Database::transaction(function (PDO $pdo) use ($userId, $productId, $quantity): int {
            $this->lockCustomer($pdo, $userId);
            $product = $this->lockPurchasableProduct($pdo, $productId);
            $stock = (int) $product['stock_qty'];
            if ($stock < 1) {
                throw new InvalidArgumentException('This product is currently out of stock.');
            }

            $cartId = $this->activeCartId($pdo, $userId, true);
            $itemStmt = $pdo->prepare(
                'SELECT quantity FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id FOR UPDATE'
            );
            $itemStmt->execute(['cart_id' => $cartId, 'product_id' => $productId]);
            $existing = $itemStmt->fetchColumn();
            $newQuantity = ($existing === false ? 0 : (int) $existing) + $quantity;

            if ($newQuantity > 99) throw new InvalidArgumentException('Choose a quantity between 1 and 99.');

            if ($newQuantity > $stock) {
                throw new InvalidArgumentException('Only ' . $stock . ' item' . ($stock === 1 ? '' : 's') . ' available in stock.');
            }

            if ($existing === false) {
                $stmt = $pdo->prepare(
                    'INSERT INTO cart_items (cart_id, product_id, quantity, created_at, updated_at)
                     VALUES (:cart_id, :product_id, :quantity, NOW(), NOW())'
                );
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE cart_items SET quantity = :quantity, updated_at = NOW()
                     WHERE cart_id = :cart_id AND product_id = :product_id'
                );
            }
            $stmt->execute(['cart_id' => $cartId, 'product_id' => $productId, 'quantity' => $newQuantity]);
            \App\Services\AuditService::record($pdo,$userId,'cart.item_added','product',$productId,['quantity_added'=>$quantity,'resulting_quantity'=>$newQuantity]);
            return $newQuantity;
        });
    }

    public function update(int $userId, int $productId, int $quantity): void
    {
        if ($quantity < 1 || $quantity > 99) {
            throw new InvalidArgumentException('Choose a quantity between 1 and 99.');
        }

        Database::transaction(function (PDO $pdo) use ($userId, $productId, $quantity): void {
            $this->lockCustomer($pdo, $userId);
            $product = $this->lockPurchasableProduct($pdo, $productId);
            $stock = (int) $product['stock_qty'];
            if ($quantity > $stock) {
                throw new InvalidArgumentException('Only ' . $stock . ' item' . ($stock === 1 ? '' : 's') . ' available in stock.');
            }

            $cartId = $this->activeCartId($pdo, $userId, false);
            if ($cartId === null) {
                throw new InvalidArgumentException('Your bag is empty.');
            }

            $stmt = $pdo->prepare(
                'UPDATE cart_items SET quantity = :quantity, updated_at = NOW()
                 WHERE cart_id = :cart_id AND product_id = :product_id'
            );
            $stmt->execute(['quantity' => $quantity, 'cart_id' => $cartId, 'product_id' => $productId]);
            if ($stmt->rowCount() < 1) {
                $exists = $pdo->prepare('SELECT 1 FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id');
                $exists->execute(['cart_id' => $cartId, 'product_id' => $productId]);
                if (!$exists->fetchColumn()) {
                    throw new InvalidArgumentException('That item is no longer in your bag.');
                }
            }
            \App\Services\AuditService::record($pdo,$userId,'cart.quantity_updated','product',$productId,['quantity'=>$quantity]);
        });
    }

    public function remove(int $userId, int $productId): void
    {
        Database::transaction(function (PDO $pdo) use ($userId, $productId): void {
            $this->lockCustomer($pdo, $userId);
            $cartId = $this->activeCartId($pdo, $userId, false);
            if ($cartId === null) return;

            $stmt = $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id');
            $stmt->execute(['cart_id' => $cartId, 'product_id' => $productId]);
            if($stmt->rowCount()>0)\App\Services\AuditService::record($pdo,$userId,'cart.item_removed','product',$productId);
        });
    }

    private function lockPurchasableProduct(PDO $pdo, int $productId): array
    {
        $link = $pdo->prepare('SELECT category_id FROM products WHERE id=?');
        $link->execute([$productId]);
        $categoryId = $link->fetchColumn();
        if ($categoryId === false) throw new InvalidArgumentException('That product is no longer available.');
        $category = $pdo->prepare('SELECT status FROM categories WHERE id=? FOR UPDATE');
        $category->execute([$categoryId]);
        if ($category->fetchColumn() !== 'active') throw new InvalidArgumentException('That product is no longer available.');
        $stmt = $pdo->prepare(
            'SELECT p.id, p.stock_qty
             FROM products p
             WHERE p.id = :id AND p.category_id = :category_id AND p.status = "active"
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['id' => $productId, 'category_id' => $categoryId]);
        $product = $stmt->fetch();
        if (!$product) {
            throw new InvalidArgumentException('That product is no longer available.');
        }
        return $product;
    }

    private function lockCustomer(PDO $pdo, int $userId): void
    {
        // Shared with checkout: one customer's bag mutations cannot cross conversion.
        $stmt = $pdo->prepare('SELECT role,status,locked_until,lock_requires_admin FROM users WHERE id=? FOR UPDATE');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!User::canAuthenticate($user ?: null) || $user['role'] !== 'customer') {
            throw new InvalidArgumentException('An active customer account is required.');
        }
    }

    private function activeCartId(PDO $pdo, int $userId, bool $create): ?int
    {
        $stmt = $pdo->prepare(
            'SELECT id FROM carts WHERE user_id = :user_id AND status = "active" ORDER BY id DESC LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['user_id' => $userId]);
        $cartId = $stmt->fetchColumn();
        if ($cartId !== false) return (int) $cartId;
        if (!$create) return null;

        $insert = $pdo->prepare(
            'INSERT INTO carts (user_id, status, created_at, updated_at) VALUES (:user_id, "active", NOW(), NOW())'
        );
        $insert->execute(['user_id' => $userId]);
        return (int) $pdo->lastInsertId();
    }
}
