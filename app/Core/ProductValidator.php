<?php

namespace App\Core;

use App\Models\Product;

final class ProductValidator
{
    public static function defaults(): array
    {
        return ['name' => '', 'sku' => '', 'category_id' => '', 'description' => '', 'price' => '',
            'stock_qty' => '0', 'status' => 'active', 'routine_step' => '', 'tag_ids' => [], 'expected_stock' => '', 'stock_reason' => ''];
    }

    public static function quantity(mixed $value): ?int
    {
        if (!is_string($value) || !preg_match('/^[0-9]+$/D', $value)) return null;
        $digits = ltrim($value, '0');
        if (strlen($digits) > 10 || (strlen($digits) === 10 && strcmp($digits, (string) Product::MAX_STOCK) > 0)) return null;
        return (int) ($digits === '' ? '0' : $digits);
    }

    public static function validate(array $input, array $categories, array $tags, ?array $current): array
    {
        $values = self::defaults();
        $errors = [];
        // Collection is restricted to product form fields; no submitted image path is trusted.
        foreach ($values as $field => $default) {
            if ($field === 'tag_ids') continue;
            $value = $input[$field] ?? '';
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
                $errors[$field] = 'Enter a valid text value.';
            } else {
                $values[$field] = trim($value, " \t\n\r");
            }
        }
        foreach (['name' => 160, 'sku' => 50] as $field => $limit) {
            if (isset($errors[$field])) continue;
            $label = $field === 'sku' ? 'SKU' : 'Product name';
            if (preg_match('/^\s*$/u', $values[$field])) {
                $errors[$field] = $label . ' is required.';
            } elseif (mb_strlen($values[$field], 'UTF-8') > $limit) {
                $errors[$field] = $label . ' must be ' . $limit . ' characters or fewer.';
            } elseif (preg_match('/[\x00-\x1F\x7F]/u', $values[$field])) {
                $errors[$field] = $label . ' must not contain control characters.';
            }
        }
        if (!isset($errors['description']) && (strlen($values['description']) > 65535
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $values['description']))) {
            $errors['description'] = 'Description is too long or contains unsupported control characters.';
        }
        if (!isset($errors['price']) && !preg_match('/^[0-9]{1,8}(?:\.[0-9]{1,2})?$/D', $values['price'])) {
            $errors['price'] = 'Enter a price from 0 to 99,999,999.99 with at most two decimal places.';
        }
        if (self::quantity($values['stock_qty']) === null) {
            $errors['stock_qty'] = 'Stock must be a whole number from 0 to 4,294,967,295.';
        }
        if ($current !== null && self::quantity($values['expected_stock']) === null) {
            $errors['expected_stock'] = 'Reload the Edit form before saving stock changes.';
        }
        if (!in_array($values['status'], ['active', 'inactive'], true)) $errors['status'] = 'Choose Active or Inactive.';
        if (!in_array($values['routine_step'], ['', 'cleanse', 'condition', 'treat', 'finish'], true)) {
            $errors['routine_step'] = 'Choose a listed routine step or None.';
        }
        $category = null;
        foreach ($categories as $candidate) {
            if ((string) $candidate['id'] === $values['category_id']) $category = $candidate;
        }
        if ($category === null || ($category['status'] !== 'active' && (int) ($current['category_id'] ?? 0) !== (int) $category['id'])) {
            $errors['category_id'] = 'Choose an existing active category. An existing inactive category may only be retained.';
        }
        // There is one supported form contract: flat numeric tag_ids[]. Reject stale grouped forms.
        if (array_key_exists('tags', $input)) {
            $errors['tag_ids'] = 'Reload the product form and choose the listed suitability tags.';
        }
        $submittedTags = $input['tag_ids'] ?? [];
        $allowedIds = array_map('strval', array_column($tags, 'id'));
        if (!is_array($submittedTags) || !array_is_list($submittedTags) || count($submittedTags) > count($allowedIds)) {
            $errors['tag_ids'] = 'Choose only the listed suitability tags.';
        } else {
            foreach ($submittedTags as $id) {
                if (!is_string($id) || !in_array($id, $allowedIds, true)) {
                    $errors['tag_ids'] = 'Choose only the listed suitability tags.';
                } else {
                    $values['tag_ids'][] = $id;
                }
            }
            $values['tag_ids'] = array_values(array_unique($values['tag_ids']));
        }
        $stockChanging = $current !== null && self::quantity($values['stock_qty']) !== null
            && (int) $values['stock_qty'] !== (int) $current['stock_qty'];
        if (($stockChanging || $values['stock_reason'] !== '') && self::stockReason($values['stock_reason']) === null) {
            $errors['stock_reason'] = 'Enter a single-line reason of 1–200 characters for the stock adjustment.';
        }
        return ['values' => $values, 'errors' => $errors];
    }

    public static function stockReason(mixed $value): ?string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) return null;
        $value = trim($value);
        return $value === '' || mb_strlen($value, 'UTF-8') > 200 || preg_match('/[\x00-\x1f\x7f]/', $value) ? null : $value;
    }
}
