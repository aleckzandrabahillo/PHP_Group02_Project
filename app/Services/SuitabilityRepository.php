<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class SuitabilityRepository
{
    public function candidates(PDO $pdo, bool $lock = false): array
    {
        $root = dirname(__DIR__, 2);
        $canonical=[];$reviewed=[];$sources=[];
        foreach ([['database/demo_catalog.json','database/suitability_evidence.json'],
                  ['database/avela_catalog_20261030.json','database/avela_suitability_20261030.json']] as [$catalogPath,$evidencePath]) {
            $source=file_get_contents($root.'/'.$catalogPath);
            $evidence=json_decode(file_get_contents($root.'/'.$evidencePath),true,32,JSON_THROW_ON_ERROR);
            if(!is_string($evidence['catalog_sha256']??null)||!hash_equals($evidence['catalog_sha256'],hash('sha256',$source)))throw new RuntimeException('Suitability sources need review.');
            $catalog=json_decode($source,true,32,JSON_THROW_ON_ERROR);
            foreach($catalog['products'] as $item){
                if(isset($canonical[$item['sku']]))throw new RuntimeException('Duplicate catalog identity.');
                $canonical[$item['sku']]=$item;$sources[$item['sku']]=['path'=>$catalogPath,'sha256'=>$evidence['catalog_sha256']];
            }
            foreach($evidence['products'] as $item){
                if(isset($reviewed[$item['sku']]))throw new RuntimeException('Duplicate suitability review.');
                if(!isset($sources[$item['sku']])||$sources[$item['sku']]['path']!==$catalogPath)throw new RuntimeException('Review belongs to a different catalog.');
                $reviewed[$item['sku']]=$item;
            }
        }
        $products = $pdo->query('SELECT p.*,c.name category_name,c.status category_status
            FROM products p JOIN categories c ON c.id=p.category_id ORDER BY p.id' . ($lock ? ' FOR UPDATE' : ''))->fetchAll();
        $links = $pdo->query('SELECT pt.product_id,t.tag_type,t.code FROM product_tags pt JOIN tags t ON t.id=pt.tag_id
            ORDER BY pt.product_id,t.tag_type,t.code' . ($lock ? ' FOR UPDATE' : ''))->fetchAll();
        $tags = [];
        foreach ($links as $tag) $tags[(int) $tag['product_id']][] = $tag['tag_type'] . ':' . $tag['code'];
        $out = [];
        foreach ($products as $product) {
            $entry = $reviewed[$product['sku']] ?? null;
            $expected = $canonical[$product['sku']] ?? null;
            if (!$entry || !$expected || ($entry['status'] ?? '') !== 'reviewed_demo'
                || ($entry['source_type'] ?? '') !== 'approved_demonstration_design'
                || ($entry['source_reference'] ?? '') !== ($sources[$product['sku']]['path']??'') . '#' . $product['sku']
                || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $entry['reviewed_at'] ?? '')
                || !is_array($entry['tags'] ?? null)) continue;
            // A changed identity/purpose/description needs a new review. Copy never earns a score.
            foreach (['sku', 'name', 'description', 'routine_step'] as $field) {
                if ((string) ($product[$field] ?? '') !== (string) ($expected[$field] ?? '')) continue 2;
            }
            if ($product['category_name'] !== $expected['category']) continue;
            if (array_diff($entry['tags'], $expected['tags'])) continue;
            $currentTags = $tags[(int) $product['id']] ?? [];
            $product['verified_tags'] = array_values(array_intersect($currentTags, $entry['tags']));
            $product['evidence'] = ['status' => 'reviewed_demo', 'source_type' => $entry['source_type'],
                'reference' => $entry['source_reference'], 'reviewed_at' => $entry['reviewed_at']];
            $product['treatment_support'] = $entry['treatment_support'] ?? [];
            $product['accessory_purpose'] = $entry['accessory_purpose'] ?? '';
            $product['metadata_signature'] = hash('sha256', json_encode([
                $product['sku'], $product['name'], $product['description'], $product['category_name'],
                $product['routine_step'], $currentTags, $entry, $sources[$product['sku']]['sha256']], JSON_THROW_ON_ERROR));
            $out[] = $product;
        }
        return $out;
    }
}
