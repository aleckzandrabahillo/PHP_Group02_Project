<?php

namespace App\Models;

use App\Core\Database;

final class Product
{
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
    public function catalog(string $search = '', array $filters = [], string $sort = 'newest', bool $includeInactive = false): array
    {
        $sql = 'SELECT p.id, p.sku, p.name, p.description, p.price, p.stock_qty, p.image_path, p.routine_step,
                       p.status, c.name AS category_name
                FROM products p
                INNER JOIN categories c ON c.id = p.category_id';
        $sql .= $includeInactive
            ? ' WHERE 1 = 1'
            : ' WHERE p.status = "active" AND c.status = "active"';
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
