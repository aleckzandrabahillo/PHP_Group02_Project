<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ProductValidator;
use App\Models\User;
use DomainException;
use PDO;
use Throwable;

/** CLI-oriented, insert-only dataset operation; never an HTTP authentication path. */
final class DemoCatalogImporter
{
    public function __construct(private readonly PDO $pdo) {}

    public static function readManifest(string $path): array
    {
        $text = @file_get_contents($path);
        if ($text === false || strlen($text) > 2 * 1024 * 1024) {
            throw new DomainException('Manifest is unreadable or exceeds 2 MB.');
        }
        try {
            $manifest = json_decode($text, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new DomainException('Manifest must be valid JSON.');
        }
        if (!is_array($manifest)) throw new DomainException('Manifest must be a JSON object.');
        return $manifest;
    }

    public function run(array $manifest, bool $apply = false, ?string $actor = null, array $confirmation = []): array
    {
        $this->checkManifest($manifest);
        if ($this->pdo->inTransaction()) throw new DomainException('Importer requires its own transaction.');
        $server = $this->pdo->query('SELECT DATABASE() AS db, @@port AS port, @@hostname AS server')->fetch();
        $target = [
            'host' => (string) env('DB_HOST', '127.0.0.1'), 'port' => (int) $server['port'],
            'database' => (string) $server['db'], 'server' => (string) $server['server'],
        ];
        if ($apply && (($confirmation['host'] ?? null) !== $target['host']
            || ($confirmation['port'] ?? null) !== $target['port']
            || ($confirmation['database'] ?? null) !== $target['database']
            || (int) env('DB_PORT', 3306) !== $target['port']
            || (string) env('DB_DATABASE', '') !== $target['database'])) {
            throw new DomainException('Write target confirmation does not match the connected database. Run a dry-run first.');
        }
        $lock = 'avela.demo.' . substr(hash('sha256', $target['database']), 0, 40);
        $locked = false;
        try {
            if ($apply) {
                $stmt = $this->pdo->prepare('SELECT GET_LOCK(:name, 5)');
                $stmt->execute(['name' => $lock]);
                $locked = (int) $stmt->fetchColumn() === 1;
                if (!$locked) throw new DomainException('Another catalog import is running. Retry later.');
            } else {
                $this->pdo->exec('SET TRANSACTION READ ONLY');
            }
            $this->pdo->beginTransaction();
            $suffix = $apply ? ' FOR UPDATE' : '';
            // Match catalog lock order: category -> product -> tag; actor eligibility is locked last.
            $categories = $this->pdo->query('SELECT id,name,status FROM categories ORDER BY id' . $suffix)->fetchAll();
            $products = $this->pdo->query('SELECT * FROM products ORDER BY id' . $suffix)->fetchAll();
            $tags = $this->pdo->query('SELECT id,tag_type,code,display_name FROM tags ORDER BY id' . $suffix)->fetchAll();
            $links = $this->pdo->query('SELECT product_id,tag_id FROM product_tags ORDER BY product_id,tag_id' . $suffix)->fetchAll();
            $actorId = null;
            if ($apply) {
                if (!is_string($actor) || !preg_match('/^[A-Za-z0-9._-]{4,40}$/D', $actor)) {
                    throw new DomainException('Apply requires an existing staff username through --actor.');
                }
                $stmt = $this->pdo->prepare('SELECT id,role,status,locked_until,lock_requires_admin FROM users WHERE username=:username FOR UPDATE');
                $stmt->execute(['username' => $actor]);
                $user = $stmt->fetch() ?: null;
                if (!User::canAuthenticate($user) || !in_array($user['role'], ['admin', 'catalog_manager'], true)) {
                    throw new DomainException('Actor must be an active, unlocked Administrator or Catalog Manager.');
                }
                $actorId = (int) $user['id'];
            }
            $plan = $this->plan($manifest['products'], $categories, $products, $tags, $links);
            if ($apply) {
                foreach ($plan as $item) $this->insert($item, $actorId);
                if ((int) $this->pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() !== 20) {
                    throw new DomainException('Catalog changed during import; expected exactly 20 products.');
                }
                $this->pdo->commit();
            } else {
                $this->pdo->rollBack();
            }
            return [
                'mode' => $apply ? 'apply' : 'dry-run', 'target' => $target,
                'total_before' => count($products), 'total_after' => count($products) + count($plan),
                'planned_inserts' => count($plan), 'inserted' => $apply ? count($plan) : 0,
                'unchanged' => count($products), 'planned_skus' => array_column($plan, 'sku'),
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        } finally {
            if ($locked) {
                // Cleanup failure after commit must not turn a successful import into a false failure.
                try {
                    $stmt = $this->pdo->prepare('SELECT RELEASE_LOCK(:name)');
                    $stmt->execute(['name' => $lock]);
                } catch (Throwable $e) {}
            }
        }
    }

    private function checkManifest(array $manifest): void
    {
        if (($manifest['catalog_id'] ?? null) !== 'avela-demo-v1' || ($manifest['version'] ?? null) !== 1
            || ($manifest['currency'] ?? null) !== 'PHP' || !is_array($manifest['products'] ?? null)
            || !array_is_list($manifest['products']) || count($manifest['products']) !== 20) {
            throw new DomainException('Expected the version 1 PHP demonstration manifest with exactly 20 products.');
        }
        $seen = []; $names = []; $counts = ['existing' => 0, 'new' => 0];
        $required = ['kind','sku','name','category','description','price','stock_qty','status','routine_step','tags','image_path','suggested_image'];
        foreach ($manifest['products'] as $index => $row) {
            $label = 'Manifest product ' . ($index + 1);
            if (!is_array($row) || array_diff($required, array_keys($row)) || array_diff(array_keys($row), $required)) {
                throw new DomainException($label . ': expected only the documented product fields.');
            }
            foreach (['kind','sku','name','category','description','price','status','routine_step','suggested_image'] as $field) {
                if (!is_string($row[$field])) throw new DomainException($label . ': ' . $field . ' must be text.');
            }
            if (!isset($counts[$row['kind']])) throw new DomainException($label . ': kind must be existing or new.');
            $counts[$row['kind']]++;
            if (!preg_match('/^AVE-[A-Z0-9-]{1,46}$/D', $row['sku']) || strlen($row['sku']) > 50) {
                throw new DomainException($label . ': use an uppercase AVE- SKU of at most 50 characters.');
            }
            if (isset($seen[$row['sku']])) throw new DomainException($label . ': duplicate SKU in manifest.');
            $seen[$row['sku']] = true;
            $name = mb_strtolower(trim($row['name']), 'UTF-8');
            if (isset($names[$name])) throw new DomainException($label . ': duplicate product name in manifest.');
            $names[$name] = true;
            if (!is_int($row['stock_qty']) || $row['stock_qty'] < 0 || $row['stock_qty'] > \App\Models\Product::MAX_STOCK) {
                throw new DomainException($label . ': opening stock must be a valid unsigned whole number.');
            }
            if (!preg_match('/^[0-9]{1,8}\.[0-9]{2}$/D', $row['price']) || trim($row['description']) === '') {
                throw new DomainException($label . ': use a two-decimal price and a nonempty description.');
            }
            if ($row['status'] !== 'active' || $row['image_path'] !== null
                || $row['suggested_image'] !== 'avela-' . strtolower($row['sku']) . '.webp') {
                throw new DomainException($label . ': active status, NULL image path and the documented suggested filename are required.');
            }
            if (!is_array($row['tags']) || !array_is_list($row['tags']) || $row['tags'] === []) {
                throw new DomainException($label . ': choose a nonempty list of existing suitability tags.');
            }
            $tagSeen = [];
            foreach ($row['tags'] as $tag) {
                if (!is_string($tag) || !preg_match('/^(texture|scalp|concern|condition):[a-z0-9_]+$/D', $tag) || isset($tagSeen[$tag])) {
                    throw new DomainException($label . ': invalid or duplicate suitability tag.');
                }
                $tagSeen[$tag] = true;
            }
        }
        if ($counts !== ['existing' => 6, 'new' => 14]) throw new DomainException('Manifest must preserve six existing products and define fourteen new products.');
    }

    private function plan(array $rows, array $categories, array $products, array $tags, array $links): array
    {
        $categoryIds = array_column($categories, 'id', 'name');
        $tagIds = []; foreach ($tags as $tag) $tagIds[$tag['tag_type'] . ':' . $tag['code']] = (string) $tag['id'];
        $bySku = []; foreach ($products as $product) $bySku[mb_strtoupper(rtrim($product['sku']), 'UTF-8')] = $product;
        $existingTags = []; foreach ($links as $link) $existingTags[(int) $link['product_id']][] = (string) $link['tag_id'];
        $expectedSkus = array_column($rows, 'sku');
        if (count($products) < 6 || count($products) > 20 || array_diff(array_keys($bySku), $expectedSkus)) {
            throw new DomainException('Unexpected or missing products prevent an exact 20-product demonstration catalog. Nothing will be overwritten or removed.');
        }
        $plan = [];
        foreach ($rows as $row) {
            $sku = $row['sku'];
            $current = $bySku[$sku] ?? null;
            if ($current === null && $row['kind'] === 'existing') throw new DomainException('Expected existing seed SKU ' . $sku . ' is missing.');
            $categoryId = $categoryIds[$row['category']] ?? null;
            foreach ($categories as $category) {
                if ($categoryId !== null && (int) $category['id'] === (int) $categoryId && $category['status'] !== 'active') {
                    throw new DomainException('SKU ' . $sku . ': its category must be active for the demonstration catalog.');
                }
            }
            $selected = [];
            foreach ($row['tags'] as $tag) {
                if (!isset($tagIds[$tag])) throw new DomainException('SKU ' . $sku . ': a requested suitability tag does not exist.');
                $selected[] = $tagIds[$tag];
            }
            $input = $row;
            $input['stock_qty'] = (string) $row['stock_qty'];
            $input['category_id'] = (string) ($categoryIds[$row['category']] ?? '');
            $input['tag_ids'] = $selected;
            $input['expected_stock'] = (string) ($current['stock_qty'] ?? '');
            $validation = ProductValidator::validate($input, $categories, $tags, $current);
            if ($validation['errors']) {
                $field = array_key_first($validation['errors']);
                throw new DomainException('SKU ' . $sku . ': ' . $field . ': ' . $validation['errors'][$field]);
            }
            $values = $validation['values'];
            // The manifest must be canonical; do not silently trim or reinterpret product text.
            foreach (['name','sku','description','price','status','routine_step'] as $field) {
                if ($values[$field] !== $row[$field]) throw new DomainException('SKU ' . $sku . ': ' . $field . ' must not have surrounding whitespace.');
            }
            if ($current !== null) {
                $differences = [];
                foreach (['sku','name','description','price','status'] as $field) {
                    if ((string) $current[$field] !== $values[$field]) $differences[] = $field;
                }
                foreach (['category_id','stock_qty'] as $field) {
                    if ((string) $current[$field] !== $values[$field]) $differences[] = $field;
                }
                if (($current['routine_step'] ?? '') !== $values['routine_step']) $differences[] = 'routine_step';
                if ($current['image_path'] !== null) $differences[] = 'image_path';
                $actualTags = $existingTags[(int) $current['id']] ?? [];
                sort($actualTags); sort($selected);
                if ($actualTags !== $selected) $differences[] = 'tags';
                if ($differences) throw new DomainException('Conflicting SKU ' . $sku . ': ' . implode(', ', $differences) . '. No existing record will be changed.');
            } else {
                $plan[] = $values;
            }
        }
        if (count($products) + count($plan) !== 20) throw new DomainException('Expected exactly 20 products after import.');
        return $plan;
    }

    private function insert(array $row, int $actorId): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO products (category_id,sku,name,description,price,stock_qty,image_path,status,routine_step) VALUES (:category,:sku,:name,:description,:price,:stock,NULL,:status,:step)');
        $stmt->execute([
            'category' => $row['category_id'], 'sku' => $row['sku'], 'name' => $row['name'],
            'description' => $row['description'], 'price' => $row['price'], 'stock' => $row['stock_qty'],
            'status' => $row['status'], 'step' => $row['routine_step'] === '' ? null : $row['routine_step'],
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $tag = $this->pdo->prepare('INSERT INTO product_tags (product_id,tag_id) VALUES (:product,:tag)');
        foreach ($row['tag_ids'] as $tagId) $tag->execute(['product' => $id, 'tag' => $tagId]);
        $audit = $this->pdo->prepare('INSERT INTO audit_logs (actor_id,action,target_type,target_id,details) VALUES (:actor,"product.create","product",:target,:details)');
        $audit->execute(['actor' => $actorId, 'target' => $id, 'details' => 'Catalog / Product Management; demonstration dataset avela-demo-v1']);
    }
}
