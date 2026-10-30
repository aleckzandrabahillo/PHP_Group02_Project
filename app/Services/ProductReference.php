<?php
declare(strict_types=1);
namespace App\Services;

/** Reference formulas are illustrative data, independent of suitability scoring. */
final class ProductReference
{
    public function forProduct(array $product): ?array
    {
        $file=dirname(__DIR__,2).'/database/avela_product_references_20261031.json';
        $data=json_decode((string)file_get_contents($file),true,32,JSON_THROW_ON_ERROR);
        if(($data['catalog_id']??'')!=='avela-reference-details-20261031')throw new \RuntimeException('Product references need review.');
        foreach($data['products'] as $entry){
            if($entry['sku']!==$product['sku'])continue;
            // A changed catalog identity must not inherit a different product's formula.
            if($entry['name']!==$product['name']||$entry['routine_step']!==$product['routine_step'])return null;
            if(!empty($entry['ingredients'])&&($entry['illustrative']!==true||mb_strlen($entry['ingredients'])>10000
                ||!filter_var($entry['source_url'],FILTER_VALIDATE_URL)||parse_url($entry['source_url'],PHP_URL_SCHEME)!=='https'))return null;
            return $entry;
        }
        return null;
    }
}
