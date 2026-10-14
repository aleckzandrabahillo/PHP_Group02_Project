<?php

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

final class Product
{
    public const MAX_STOCK = 4294967295;
    private const DEFAULT_RECOMMENDATION_LIMIT = 5;

    private const RECOMMENDATION_WEIGHTS = [
        'concern' => 4,
        'category' => 3,
        'texture' => 2,
        'scalp' => 2,
        'routine_step' => 1,
        'in_stock' => 1,
        'other_tag' => 1,
    ];
    public function featured(int $limit = 4): array
    {
        $limit = max(1, min($limit, 8));
        $sql = 'SELECT p.id, p.sku, p.name, p.description, p.price, p.stock_qty, p.image_path, p.routine_step,
                       c.name AS category_name
                FROM products p
                INNER JOIN categories c ON c.id = p.category_id
                WHERE p.status = "active" AND c.status = "active"
                ORDER BY p.id ASC
                LIMIT ' . $limit;

        return Database::connection()->query($sql)->fetchAll();
    }

    public function categories(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, name, description FROM categories WHERE status = "active" ORDER BY name ASC'
        );
        return $stmt->fetchAll();
    }

    public function filterOptions(): array
    {
        $options = [
            'categories' => $this->categories(),
            'concern' => [],
            'texture' => [],
            'scalp' => [],
            'routine_step' => [
                ['code' => 'cleanse', 'display_name' => 'Cleanse'],
                ['code' => 'condition', 'display_name' => 'Condition'],
                ['code' => 'treat', 'display_name' => 'Treat'],
                ['code' => 'finish', 'display_name' => 'Finish'],
            ],
        ];

        $stmt = Database::connection()->query(
            'SELECT tag_type, code, display_name
             FROM tags
             WHERE tag_type IN ("concern", "texture", "scalp")
             ORDER BY tag_type ASC, display_name ASC'
        );

        foreach ($stmt->fetchAll() as $row) {
            $type = (string) $row['tag_type'];
            if (isset($options[$type])) {
                $options[$type][] = [
                    'code' => (string) $row['code'],
                    'display_name' => (string) $row['display_name'],
                ];
            }
        }

        return $options;
    }

    /**
     * Public catalog query. Filter groups are ANDed together; values inside one group are ORed.
     */
    public function catalog(string $search = '', array $filters = [], string $sort = 'newest'): array
    {
        $sql = 'SELECT p.id, p.sku, p.name, p.description, p.price, p.stock_qty, p.image_path, p.routine_step,
                       c.name AS category_name
                FROM products p
                INNER JOIN categories c ON c.id = p.category_id
                WHERE p.status = "active" AND c.status = "active"';
        $params = [];

        $search = trim($search);
        if ($search !== '') {
            $sql .= ' AND (p.name LIKE :search_name OR p.description LIKE :search_description OR c.name LIKE :search_category OR p.sku LIKE :search_sku)';
            $needle = '%' . $search . '%';
            $params['search_name'] = $needle;
            $params['search_description'] = $needle;
            $params['search_category'] = $needle;
            $params['search_sku'] = $needle;
        }

        $categories = $this->positiveInts($filters['categories'] ?? []);
        if ($categories !== []) {
            $placeholders = $this->placeholders('category', $categories, $params);
            $sql .= ' AND p.category_id IN (' . implode(',', $placeholders) . ')';
        }

        $tagGroups = [
            'concerns' => 'concern',
            'textures' => 'texture',
            'scalps' => 'scalp',
        ];

        foreach ($tagGroups as $filterKey => $tagType) {
            $values = $this->cleanCodes($filters[$filterKey] ?? []);
            if ($values === []) continue;

            $prefix = 'tag_' . $tagType;
            $placeholders = $this->placeholders($prefix, $values, $params);
            $alias = preg_replace('/[^a-z_]/', '', $tagType) ?: 'tag';
            $sql .= ' AND EXISTS (
                        SELECT 1
                        FROM product_tags pt_' . $alias . '
                        INNER JOIN tags t_' . $alias . ' ON t_' . $alias . '.id = pt_' . $alias . '.tag_id
                        WHERE pt_' . $alias . '.product_id = p.id
                          AND t_' . $alias . '.tag_type = "' . $tagType . '"
                          AND t_' . $alias . '.code IN (' . implode(',', $placeholders) . ')
                      )';
        }

        $routineSteps = array_values(array_intersect(
            $this->cleanCodes($filters['routine_steps'] ?? []),
            ['cleanse', 'condition', 'treat', 'finish']
        ));
        if ($routineSteps !== []) {
            $placeholders = $this->placeholders('routine', $routineSteps, $params);
            $sql .= ' AND p.routine_step IN (' . implode(',', $placeholders) . ')';
        }

        $availability = (string) ($filters['availability'] ?? '');
        if ($availability === 'in_stock') {
            $sql .= ' AND p.stock_qty > 0';
        } elseif ($availability === 'out_of_stock') {
            $sql .= ' AND p.stock_qty = 0';
        }

        $minPrice = $this->priceOrNull($filters['min_price'] ?? null);
        $maxPrice = $this->priceOrNull($filters['max_price'] ?? null);
        if ($minPrice !== null) {
            $sql .= ' AND p.price >= :min_price';
            $params['min_price'] = $minPrice;
        }
        if ($maxPrice !== null) {
            $sql .= ' AND p.price <= :max_price';
            $params['max_price'] = $maxPrice;
        }

        $sql .= ' ORDER BY ' . $this->sortSql($sort);
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function searchProducts(string $query, int $limit = 6): array
    {
        $query = trim($query);
        if ($query === '') return [];
        $limit = max(1, min($limit, 12));
        $needle = '%' . $query . '%';
        $stmt = Database::connection()->prepare(
            'SELECT p.id, p.sku, p.name, p.description, p.price, p.stock_qty, p.image_path, p.routine_step,
                    c.name AS category_name
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.status = "active" AND c.status = "active"
               AND (p.name LIKE :name OR p.description LIKE :description OR p.sku LIKE :sku OR c.name LIKE :category)
             ORDER BY
               CASE WHEN p.name LIKE :prefix THEN 0 ELSE 1 END,
               p.name ASC
             LIMIT ' . $limit
        );
        $stmt->execute([
            'name' => $needle,
            'description' => $needle,
            'sku' => $needle,
            'category' => $needle,
            'prefix' => $query . '%',
        ]);
        return $stmt->fetchAll();
    }

    public function searchDiscovery(string $query, int $limit = 8): array
    {
        $query = trim($query);
        if ($query === '') return [];
        $limit = max(1, min($limit, 12));
        $needle = '%' . $query . '%';
        $results = [];

        $categoryStmt = Database::connection()->prepare(
            'SELECT id, name FROM categories WHERE status = "active" AND name LIKE :query ORDER BY name ASC LIMIT ' . $limit
        );
        $categoryStmt->execute(['query' => $needle]);
        foreach ($categoryStmt->fetchAll() as $row) {
            $results[] = [
                'type' => 'category',
                'id' => (int) $row['id'],
                'title' => (string) $row['name'],
                'subtitle' => 'Product category',
            ];
        }

        $remaining = max(0, $limit - count($results));
        if ($remaining > 0) {
            $tagStmt = Database::connection()->prepare(
                'SELECT code, display_name
                 FROM tags
                 WHERE tag_type = "concern" AND display_name LIKE :query
                 ORDER BY display_name ASC
                 LIMIT ' . $remaining
            );
            $tagStmt->execute(['query' => $needle]);
            foreach ($tagStmt->fetchAll() as $row) {
                $results[] = [
                    'type' => 'concern',
                    'code' => (string) $row['code'],
                    'title' => (string) $row['display_name'],
                    'subtitle' => 'Hair concern',
                ];
            }
        }

        return $results;
    }

    public function categoryRows(): array
    {
        $sql = 'SELECT c.id, c.name, c.description, c.status, COUNT(p.id) AS product_count
                FROM categories c
                LEFT JOIN products p ON p.category_id = c.id
                GROUP BY c.id, c.name, c.description, c.status
                ORDER BY c.name ASC';
        return Database::connection()->query($sql)->fetchAll();
    }

    public function categoryById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, name, description, status FROM categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function categoryNameExists(string $name, ?int $exceptId = null): bool
    {
        // Use the database collation, matching the existing UNIQUE name constraint.
        $sql = 'SELECT id FROM categories WHERE name = :name';
        $params = ['name' => $name];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        $stmt = Database::connection()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    }

    public function saveCategory(?int $id, array $data, int $actorId): void
    {
        Database::transaction(function (PDO $pdo) use ($id, $data, $actorId): void {
            $creating = $id === null;
            $params = ['name' => $data['name'], 'description' => $data['description'] === '' ? null : $data['description'], 'status' => $data['status']];
            if ($creating) {
                $sql = 'INSERT INTO categories (name, description, status) VALUES (:name, :description, :status)';
            } else {
                $this->lockCategory($pdo, $id);
                $sql = 'UPDATE categories SET name = :name, description = :description, status = :status WHERE id = :id';
                $params['id'] = $id;
            }
            $pdo->prepare($sql)->execute($params);
            $categoryId = $creating ? (int) $pdo->lastInsertId() : $id;
            $this->auditCategory($pdo, $actorId, $creating ? 'category.create' : 'category.update', $categoryId);
        });
    }

    public function removeCategory(int $id, int $actorId, bool $archive): void
    {
        Database::transaction(function (PDO $pdo) use ($id, $actorId, $archive): void {
            // Lock the parent first: a concurrent product FK insert must wait for this transaction.
            $this->lockCategory($pdo, $id);
            if ($archive) {
                $sql = 'UPDATE categories SET status = "inactive" WHERE id = :id';
            } else {
                $linked = $pdo->prepare('SELECT id FROM products WHERE category_id = :id LIMIT 1 FOR UPDATE');
                $linked->execute(['id' => $id]);
                if ($linked->fetchColumn() !== false) {
                    throw new InvalidArgumentException('This category has products and cannot be deleted. Archive it instead.');
                }
                $sql = 'DELETE FROM categories WHERE id = :id';
            }
            $pdo->prepare($sql)->execute(['id' => $id]);
            $this->auditCategory($pdo, $actorId, $archive ? 'category.archive' : 'category.delete', $id);
        });
    }

    private function lockCategory(PDO $pdo, int $id): void
    {
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);
        if ($stmt->fetchColumn() === false) {
            throw new InvalidArgumentException('That category no longer exists. Refresh the list and try again.');
        }
    }

    private function auditCategory(PDO $pdo, int $actorId, string $action, int $id): void
    {
        // The mutation and its bounded, secret-free audit entry commit or roll back together.
        $stmt = $pdo->prepare('INSERT INTO audit_logs (actor_id, action, target_type, target_id, details) VALUES (:actor, :action, "category", :target, :details)');
        $stmt->execute(['actor' => $actorId, 'action' => $action, 'target' => $id, 'details' => 'Catalog / Category Management']);
    }
    
    public function managementRows(): array
    {
        // Show active and inactive products, including products with zero stock.
        // Keep deletion checks limited to tables used by this project.
        $sql = 'SELECT p.*, c.name AS category_name, c.status AS category_status,
                    NOT (
                        EXISTS (
                            SELECT 1 FROM cart_items ci
                            WHERE ci.product_id = p.id
                        )
                        OR EXISTS (
                            SELECT 1 FROM order_items oi
                            WHERE oi.product_id = p.id
                        )
                        OR EXISTS (
                            SELECT 1 FROM favorites f
                            WHERE f.product_id = p.id
                        )
                        OR EXISTS (
                            SELECT 1 FROM routine_items ri
                            WHERE ri.product_id = p.id
                        )
                    ) AS can_delete
                FROM products p
                INNER JOIN categories c ON c.id = p.category_id
                ORDER BY p.id DESC';

        return Database::connection()->query($sql)->fetchAll();
    }


    public static function availability(array $product): string
    {
        if ($product['status'] !== 'active') return 'Inactive';
        if (($product['category_status'] ?? 'active') !== 'active') return 'Inactive category';
        if ((int) $product['stock_qty'] === 0) return 'Out of stock';
        return (int) $product['stock_qty'] <= 5 ? 'Low stock' : 'In stock';
    }

    public function managementFind(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT p.*, c.name AS category_name, c.status AS category_status FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=:id');
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();
        if (!$product) return null;
        $tags = Database::connection()->prepare('SELECT tag_id FROM product_tags WHERE product_id=:id ORDER BY tag_id');
        $tags->execute(['id' => $id]);
        $product['tag_ids'] = array_map('strval', $tags->fetchAll(PDO::FETCH_COLUMN));
        return $product;
    }

    public function suitabilityTags(): array
    {
        return Database::connection()->query('SELECT id, tag_type, display_name FROM tags ORDER BY tag_type, display_name')->fetchAll();
    }

    
    public function managerRows(): array
    {
        $sql = 'SELECT p.id, p.category_id, p.sku, p.name, p.description, p.price, p.stock_qty,
                       p.image_path, p.status, p.routine_step, c.name AS category_name,
                       NOT EXISTS (SELECT 1 FROM cart_items ci WHERE ci.product_id=p.id) AND
                       NOT EXISTS (SELECT 1 FROM routine_items ri WHERE ri.product_id=p.id) AS can_delete
                FROM products p INNER JOIN categories c ON c.id = p.category_id
                ORDER BY p.updated_at DESC, p.id DESC';
        $rows = Database::connection()->query($sql)->fetchAll();
        $tagStmt = Database::connection()->query(
            'SELECT pt.product_id, t.tag_type, t.display_name
             FROM product_tags pt JOIN tags t ON t.id = pt.tag_id
             ORDER BY t.tag_type, t.display_name'
        );
        $tags = [];
        foreach ($tagStmt->fetchAll() as $tag) {
            $tags[(int)$tag['product_id']][] = (string)$tag['display_name'];
        }
        foreach ($rows as &$row) $row['tags'] = $tags[(int)$row['id']] ?? [];
        unset($row);
        return $rows;
    } 

    public function managerProductById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, category_id, sku, name, description, price, stock_qty, image_path, status, routine_step
             FROM products WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $tagStmt = Database::connection()->prepare('SELECT tag_type, code FROM product_tags pt JOIN tags t ON t.id = pt.tag_id WHERE pt.product_id = :id');
        $tagStmt->execute(['id' => $id]);
        $row['tag_ids'] = ['concern'=>[], 'texture'=>[], 'scalp'=>[], 'condition'=>[]];
        foreach ($tagStmt->fetchAll() as $tag) $row['tag_ids'][$tag['tag_type']][] = (string)$tag['code'];
        return $row;
    }

    public function managerCategories(): array
    {
        return Database::connection()->query('SELECT id, name, status FROM categories ORDER BY status DESC, name ASC')->fetchAll();
    }

    public function managerTags(): array
    {
        return Database::connection()->query('SELECT id, tag_type, code, display_name FROM tags ORDER BY tag_type, display_name')->fetchAll();
    }

    public function skuExists(string $sku, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM products WHERE sku = :sku';
        $params = ['sku'=>$sku];
        if ($exceptId !== null) { $sql .= ' AND id <> :id'; $params['id']=$exceptId; }
        $stmt = Database::connection()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    }

    public function saveProduct(?int $id, array $data, ?string $newImage, int $actorId): array
    {
        return Database::transaction(function (PDO $pdo) use ($id, $data, $newImage, $actorId): array {
            // Same category-first lock order as Category Management, retaining existing inactive links.
            $category = $pdo->prepare('SELECT status FROM categories WHERE id=:id FOR UPDATE');
            $category->execute(['id' => $data['category_id']]);
            $categoryStatus = $category->fetchColumn();
            $current = $id === null ? null : $this->lockProduct($pdo, $id);
            if ($categoryStatus === false || ($categoryStatus !== 'active' && (int) ($current['category_id'] ?? 0) !== (int) $data['category_id'])) {
                throw new InvalidArgumentException('Choose an active category; an existing inactive category may only be retained.');
            }
            if ($current !== null && (int) $current['stock_qty'] !== (int) $data['expected_stock']) {
                throw new InvalidArgumentException('Stock changed while this form was open. Reload the Edit form before saving.');
            }
            $tagIds = array_values(array_unique(array_map('intval', $data['tag_ids'])));
            if ($tagIds) {
                $checkTags = $pdo->prepare('SELECT id FROM tags WHERE id IN (' . implode(',', array_fill(0, count($tagIds), '?')) . ') FOR UPDATE');
                $checkTags->execute($tagIds);
                if (count($checkTags->fetchAll()) !== count($tagIds)) throw new InvalidArgumentException('A selected tag no longer exists. Reload the form.');
            }
            $params = [
                'name' => $data['name'], 'sku' => $data['sku'], 'category' => $data['category_id'],
                'description' => $data['description'] === '' ? null : $data['description'], 'price' => $data['price'],
                'stock' => $data['stock_qty'], 'status' => $data['status'],
                'step' => $data['routine_step'] === '' ? null : $data['routine_step'],
                'image' => $newImage ?? ($current['image_path'] ?? null),
            ];
            if ($id === null) {
                $sql = 'INSERT INTO products (name,sku,category_id,description,price,stock_qty,status,routine_step,image_path) VALUES (:name,:sku,:category,:description,:price,:stock,:status,:step,:image)';
            } else {
                $sql = 'UPDATE products SET name=:name,sku=:sku,category_id=:category,description=:description,price=:price,stock_qty=:stock,status=:status,routine_step=:step,image_path=:image WHERE id=:id';
                $params['id'] = $id;
            }
            $pdo->prepare($sql)->execute($params);
            $productId = $id ?? (int) $pdo->lastInsertId();
            $pdo->prepare('DELETE FROM product_tags WHERE product_id=:id')->execute(['id' => $productId]);
            $insertTag = $pdo->prepare('INSERT INTO product_tags (product_id,tag_id) VALUES (:product,:tag)');
            foreach ($tagIds as $tagId) $insertTag->execute(['product' => $productId, 'tag' => $tagId]);
            $this->auditProduct($pdo, $actorId, $id === null ? 'product.create' : 'product.update', $productId,
                'Catalog / Product Management; stock ' . (int) ($current['stock_qty'] ?? 0) . ' to ' . (int) $data['stock_qty']);
            return ['id' => $productId, 'old_image' => $current['image_path'] ?? null, 'image_path' => $params['image']];
        });
    }

    
    public function removeProduct(int $id, int $actorId, bool $archive): ?string
    {
        return Database::transaction(
            function (PDO $pdo) use ($id, $actorId, $archive): ?string {
                // Lock the product to prevent concurrent changes.
                $product = $this->lockProduct($pdo, $id);

                if ($archive) {
                    $stmt = $pdo->prepare(
                        'UPDATE products
                        SET status = "inactive"
                        WHERE id = :id'
                    );

                    $stmt->execute(['id' => $id]);

                    $this->auditProduct(
                        $pdo,
                        $actorId,
                        'product.archive',
                        $id,
                        'Catalog / Product Management'
                    );

                    return null;
                }

                // Only check tables that actually exist in this project.
                // Do NOT include product_reviews because that table is absent.
                $relatedTables = [
                    'cart_items',
                    'order_items',
                    'favorites',
                    'routine_items',
                ];

                foreach ($relatedTables as $table) {
                    $stmt = $pdo->prepare(
                        "SELECT product_id
                        FROM {$table}
                        WHERE product_id = :id
                        LIMIT 1"
                    );

                    $stmt->execute(['id' => $id]);

                    if ($stmt->fetchColumn() !== false) {
                        throw new InvalidArgumentException(
                            'This product has related customer or history records and cannot be deleted. Archive it instead.'
                        );
                    }
                }

                // Record the deletion in the audit log.
                $this->auditProduct(
                    $pdo,
                    $actorId,
                    'product.delete',
                    $id,
                    'Catalog / Product Management'
                );

                // Delete the product only after all checks pass.
                $stmt = $pdo->prepare(
                    'DELETE FROM products WHERE id = :id'
                );

                $stmt->execute(['id' => $id]);

                return $product['image_path'] ?? null;
            }
        );
    }


    public function changeStock(int $id, int $quantity, bool $add, ?int $expected, int $actorId): void
    {
        if ($quantity < ($add ? 1 : 0) || $quantity > self::MAX_STOCK) throw new InvalidArgumentException('Choose a valid stock quantity.');
        Database::transaction(function (PDO $pdo) use ($id, $quantity, $add, $expected, $actorId): void {
            $product = $this->lockProduct($pdo, $id);
            $before = (int) $product['stock_qty'];
            if (!$add && $expected !== $before) throw new InvalidArgumentException('Stock changed while this form was open. Reload the stock form before setting a quantity.');
            $after = $add ? $before + $quantity : $quantity;
            if ($after > self::MAX_STOCK) throw new InvalidArgumentException('Restock would exceed the maximum stock quantity.');
            $pdo->prepare('UPDATE products SET stock_qty=:stock WHERE id=:id')->execute(['stock' => $after, 'id' => $id]);
            $this->auditProduct($pdo, $actorId, $add ? 'inventory.restock' : 'inventory.set', $id, 'Catalog / Inventory; stock ' . $before . ' to ' . $after);
        });
    }

    public function imageIsReferenced(string $path): bool
    {
        $stmt = Database::connection()->prepare('SELECT id FROM products WHERE image_path=:path LIMIT 1');
        $stmt->execute(['path' => $path]);
        return $stmt->fetchColumn() !== false;
    }

    private function lockProduct(PDO $pdo, int $id): array
    {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id=:id FOR UPDATE');
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();
        if (!$product) throw new InvalidArgumentException('That product no longer exists. Refresh the list and try again.');
        return $product;
    }

    private function auditProduct(PDO $pdo, int $actorId, string $action, int $id, string $details): void
    {
        $stmt = $pdo->prepare('INSERT INTO audit_logs (actor_id,action,target_type,target_id,details) VALUES (:actor,:action,"product",:target,:details)');
        $stmt->execute(['actor' => $actorId, 'action' => $action, 'target' => $id, 'details' => $details]);
    }
    
    public function saveManagedProduct(?int $id, array $data, array $tagCodes, ?string $imagePath, int $actorId): int
    {
        return Database::transaction(function (PDO $pdo) use ($id, $data, $tagCodes, $imagePath, $actorId): int {
            $creating = $id === null;
            if (!$creating) {
                $lock = $pdo->prepare('SELECT id, image_path FROM products WHERE id = :id FOR UPDATE');
                $lock->execute(['id'=>$id]);
                $existing = $lock->fetch();
                if (!$existing) throw new InvalidArgumentException('That product no longer exists.');
                if ($imagePath === null) $imagePath = $existing['image_path'];
            }
            $params = [
                'category_id'=>(int)$data['category_id'], 'sku'=>$data['sku'], 'name'=>$data['name'],
                'description'=>$data['description'] === '' ? null : $data['description'],
                'price'=>$data['price'], 'status'=>$data['status'], 'routine_step'=>$data['routine_step'] ?: null,
                'image_path'=>$imagePath,
            ];
            if ($creating) {
                $sql = 'INSERT INTO products (category_id, sku, name, description, price, stock_qty, image_path, status, routine_step)
                        VALUES (:category_id, :sku, :name, :description, :price, :stock_qty, :image_path, :status, :routine_step)';
                $params['stock_qty'] = (int)$data['stock_qty'];
                $pdo->prepare($sql)->execute($params);
                $productId = (int)$pdo->lastInsertId();
            } else {
                $sql = 'UPDATE products SET category_id=:category_id, sku=:sku, name=:name, description=:description,
                        price=:price, image_path=:image_path, status=:status, routine_step=:routine_step WHERE id=:id';
                $params['id']=$id;
                $pdo->prepare($sql)->execute($params);
                $productId = $id;
            }
            $pdo->prepare('DELETE FROM product_tags WHERE product_id = :id')->execute(['id'=>$productId]);
            if ($tagCodes) {
                $tagStmt = $pdo->prepare('SELECT id FROM tags WHERE tag_type=:type AND code=:code');
                $linkStmt = $pdo->prepare('INSERT INTO product_tags (product_id, tag_id) VALUES (:product_id, :tag_id)');
                foreach ($tagCodes as $type=>$codes) {
                    foreach (array_unique($codes) as $code) {
                        $tagStmt->execute(['type'=>$type,'code'=>$code]);
                        $tagId=$tagStmt->fetchColumn();
                        if ($tagId !== false) $linkStmt->execute(['product_id'=>$productId,'tag_id'=>(int)$tagId]);
                    }
                }
            }
            $audit = $pdo->prepare('INSERT INTO audit_logs (actor_id, action, target_type, target_id, details) VALUES (:actor,:action,"product",:target,:details)');
            $audit->execute(['actor'=>$actorId,'action'=>$creating?'product.create':'product.update','target'=>$productId,'details'=>'Catalog / Product Management; SKU '.$data['sku']]);
            if ($creating && (int)$data['stock_qty'] > 0) {
                $audit->execute(['actor'=>$actorId,'action'=>'inventory.stock_set','target'=>$productId,'details'=>'Initial stock set to '.(int)$data['stock_qty']]);
            }
            return $productId;
        });
    } 

    
    public function deleteManagedProduct(int $id, int $actorId): void
    {
        $this->removeProduct($id, $actorId, false);
    }

    public function updateManagedStock(int $id, int $quantity, int $actorId, string $reason): void
    {
        Database::transaction(function (PDO $pdo) use ($id, $quantity, $actorId, $reason): void {
            $stmt = $pdo->prepare('SELECT id, sku, stock_qty FROM products WHERE id=:id FOR UPDATE');
            $stmt->execute(['id'=>$id]);
            $product=$stmt->fetch();
            if (!$product) throw new InvalidArgumentException('That product no longer exists.');
            $old=(int)$product['stock_qty'];
            $pdo->prepare('UPDATE products SET stock_qty=:qty WHERE id=:id')->execute(['qty'=>$quantity,'id'=>$id]);
            $audit=$pdo->prepare('INSERT INTO audit_logs (actor_id, action, target_type, target_id, details) VALUES (:actor,"inventory.stock_update","product",:target,:details)');
            $audit->execute(['actor'=>$actorId,'target'=>$id,'details'=>'SKU '.$product['sku'].'; stock '.$old.' -> '.$quantity.'; reason: '.mb_substr($reason,0,500)]);
        });
    } 

    public function recentCatalogActivity(int $limit = 8): array
    {
        $limit = max(1, min($limit, 20));
        $sql = 'SELECT a.action, a.target_type, a.target_id, a.details, a.created_at,
                       COALESCE(sp.full_name, u.username, "Former user") AS actor_name
                FROM audit_logs a LEFT JOIN users u ON u.id=a.actor_id
                LEFT JOIN staff_profiles sp ON sp.user_id=u.id
                WHERE a.target_type IN ("category","product")
                ORDER BY a.created_at DESC, a.id DESC LIMIT '.$limit;
        return Database::connection()->query($sql)->fetchAll();

    }

    public function findActive(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT p.id, p.sku, p.name, p.description, p.price, p.stock_qty, p.image_path, p.routine_step,
                    c.name AS category_name
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.id = :id AND p.status = "active" AND c.status = "active"
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();
        if (!$product) return null;

        $tagStmt = $pdo->prepare(
            'SELECT t.tag_type, t.code, t.display_name
             FROM product_tags pt
             INNER JOIN tags t ON t.id = pt.tag_id
             WHERE pt.product_id = :product_id
             ORDER BY t.tag_type ASC, t.display_name ASC'
        );
        $tagStmt->execute(['product_id' => $id]);

        $product['suitability_tags'] = ['concern' => [], 'texture' => [], 'scalp' => []];
        foreach ($tagStmt->fetchAll() as $tag) {
            $type = (string) $tag['tag_type'];
            if (isset($product['suitability_tags'][$type])) {
                $product['suitability_tags'][$type][] = (string) $tag['display_name'];
            }
        }

        return $product;
    }

    public function recommendationsForProduct(int $productId, ?int $limit = null): array
    {
        $limit = max(1, min($limit ?? self::DEFAULT_RECOMMENDATION_LIMIT, 8));
        $pdo = Database::connection();

        $sourceStmt = $pdo->prepare(
            'SELECT id, category_id, routine_step FROM products WHERE id = :id AND status = "active" LIMIT 1'
        );
        $sourceStmt->execute(['id' => $productId]);
        $source = $sourceStmt->fetch();
        if (!$source) return [];

        $weights = self::RECOMMENDATION_WEIGHTS;

        /* Recommendation scoring based on shared catalog tags. */
        $stmt = $pdo->prepare(
            'SELECT ranked.id, ranked.sku, ranked.name, ranked.description, ranked.price, ranked.stock_qty,
                    ranked.image_path, ranked.routine_step, ranked.category_name
             FROM (
                 SELECT p.id, p.sku, p.name, p.description, p.price, p.stock_qty, p.image_path, p.routine_step,
                        p.created_at, c.name AS category_name,
                        COALESCE(SUM(CASE
                            WHEN shared_tag.tag_type = "concern" THEN ' . $weights['concern'] . '
                            WHEN shared_tag.tag_type = "texture" THEN ' . $weights['texture'] . '
                            WHEN shared_tag.tag_type = "scalp" THEN ' . $weights['scalp'] . '
                            WHEN shared_tag.tag_type = "condition" THEN ' . $weights['other_tag'] . '
                            ELSE 0
                        END), 0) AS suitability_score,
                        CASE WHEN p.category_id = :source_category THEN ' . $weights['category'] . ' ELSE 0 END AS category_score,
                        CASE WHEN p.routine_step IS NOT NULL AND p.routine_step = :source_routine_step THEN ' . $weights['routine_step'] . ' ELSE 0 END AS routine_score,
                        CASE WHEN p.stock_qty > 0 THEN ' . $weights['in_stock'] . ' ELSE 0 END AS stock_score
                 FROM products p
                 INNER JOIN categories c ON c.id = p.category_id
                 LEFT JOIN product_tags shared
                   ON shared.product_id = p.id
                  AND shared.tag_id IN (
                        SELECT source_tags.tag_id
                        FROM product_tags source_tags
                        WHERE source_tags.product_id = :source_product_tags
                  )
                 LEFT JOIN tags shared_tag ON shared_tag.id = shared.tag_id
                 WHERE p.id <> :source_product_exclude
                   AND p.status = "active"
                   AND c.status = "active"
                 GROUP BY p.id, p.sku, p.name, p.description, p.price, p.stock_qty, p.image_path,
                          p.routine_step, p.category_id, c.name, p.created_at
             ) ranked
             ORDER BY
               (ranked.suitability_score + ranked.category_score + ranked.routine_score + ranked.stock_score) DESC,
               ranked.suitability_score DESC,
               ranked.category_score DESC,
               ranked.stock_score DESC,
               ranked.created_at DESC,
               ranked.id DESC
             LIMIT ' . $limit
        );
        $stmt->execute([
            'source_category' => (int) $source['category_id'],
            'source_routine_step' => $source['routine_step'],
            'source_product_tags' => $productId,
            'source_product_exclude' => $productId,
        ]);
        return $stmt->fetchAll();
    }

    private function sortSql(string $sort): string
    {
        return match ($sort) {
            'name_asc' => 'p.name ASC, p.id DESC',
            'price_asc' => 'p.price ASC, p.name ASC',
            'price_desc' => 'p.price DESC, p.name ASC',
            default => 'p.created_at DESC, p.id DESC',
        };
    }

    private function positiveInts(mixed $values): array
    {
        $values = is_array($values) ? $values : [$values];
        $result = [];
        foreach ($values as $value) {
            $int = filter_var($value, FILTER_VALIDATE_INT);
            if ($int !== false && $int > 0) $result[] = (int) $int;
        }
        return array_values(array_unique($result));
    }

    private function cleanCodes(mixed $values): array
    {
        $values = is_array($values) ? $values : [$values];
        $result = [];
        foreach ($values as $value) {
            $value = strtolower(trim((string) $value));
            if ($value !== '' && preg_match('/^[a-z0-9_\-]{1,50}$/', $value)) $result[] = $value;
        }
        return array_values(array_unique($result));
    }

    private function priceOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) return null;
        $price = (float) $value;
        if ($price < 0 || $price > 999999.99) return null;
        return round($price, 2);
    }

    private function placeholders(string $prefix, array $values, array &$params): array
    {
        $placeholders = [];
        foreach (array_values($values) as $index => $value) {
            $key = $prefix . '_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $value;
        }
        return $placeholders;
    }
}
