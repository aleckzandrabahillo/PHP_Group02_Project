<?php

declare(strict_types=1);
namespace App\Services;

final class PrivacyContact
{
    public static function configured(): ?string
    {
        $email=env('PRIVACY_CONTACT_EMAIL','');
        if(env('PRIVACY_CONTACT_VERIFIED',false)!==true||!is_string($email)||strlen($email)>190
            ||!filter_var($email,FILTER_VALIDATE_EMAIL)||preg_match('/[\\r\\n]/',$email))return null;
        return $email;
    }

    public static function href(string $email): string
    {
        return 'mailto:'.rawurlencode($email).'?subject=Privacy%20inquiry%20about%20Avela';
    }
}
