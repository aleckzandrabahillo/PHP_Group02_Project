<?php

namespace App\Core;

final class CheckoutValidator
{
    public static function validate(array $input, ?array $profile = null): array
    {
        $delivery = DeliveryValidator::validate($input);
        $input = [...$input, ...$delivery['values']];
        $values = ['full_name' => '', 'contact_no' => '', 'delivery_address' => '', 'payment_method' => 'cod'];
        $errors = $delivery['errors'];
        $source = $input['address_source'] ?? 'different';
        if (!is_string($source) || !in_array($source, ['saved', 'different'], true)) {
            $errors['address_source'] = 'Choose your saved address or a different delivery address.';
            $source = 'different';
        }
        // Structured delivery fields stay editable; no checkout input updates the permanent profile.
        foreach ($values as $field => $default) {
            $value = $input[$field] ?? '';
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
                $errors[$field] = 'Enter a valid text value.';
                continue;
            }
            $values[$field] = trim($value);
        }
        foreach (['full_name' => 120, 'contact_no' => 25, 'delivery_address' => 500] as $field => $max) {
            if (isset($errors[$field])) continue;
            $controlValue = $field === 'delivery_address' ? str_replace(["\r\n", "\n"], ' ', $values[$field]) : $values[$field];
            if (preg_match('/^\s*$/u', $values[$field]) || mb_strlen($values[$field], 'UTF-8') > $max
                || preg_match('/[\x00-\x1F\x7F]/u', $controlValue)) {
                $errors[$field] = match ($field) {
                    'full_name' => 'Enter a full name of at most 120 characters.',
                    'contact_no' => 'Enter a valid Philippine mobile number of at most 25 characters.',
                    default => 'Enter a complete delivery address of at most 500 characters.',
                };
            }
        }
        if (!isset($errors['contact_no'])) {
            if (!preg_match('/^\+?[0-9 ()-]+$/D', $values['contact_no'])
                || ($phone = Validator::normalizePhilippineMobile($values['contact_no'])) === null) {
                $errors['contact_no'] = 'Enter a Philippine mobile number such as 09XXXXXXXXX.';
            } else {
                $values['contact_no'] = $phone;
            }
        }
        if ($values['payment_method'] !== 'cod' && !($values['payment_method'] === 'maya_sandbox' && \App\Services\MayaSandboxConfig::available())) {
            $errors['payment_method'] = 'Choose an available payment option.';
        }
        foreach(['discount_code'=>32,'discount_revision'=>32] as $field=>$limit){
            $raw=$input[$field]??'';
            if(!is_string($raw)||strlen($raw)>$limit||!mb_check_encoding($raw,'UTF-8')){$errors[$field]='Enter a valid discount value.';$raw='';}
            $values[$field]=trim($raw);
        }
        $values = [...$values, ...$delivery['values'], 'address_source'=>$source];
        return ['values' => $values, 'errors' => $errors];
    }
}
