<?php

declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Models\User;

final class CustomerProfileService
{
    public function __construct(private readonly OtpService $otp) {}

    public function confirm(int $id, array $flow, #[\SensitiveParameter] string $code): array
    {
        return Database::transaction(function()use($id,$flow,$code):array{
            $user=(new User())->findById($id,true);
            if(!User::canAuthenticate($user)||$user['role']!=='customer'||($flow['user_id']??0)!==$id
                || ($flow['current_email']??'')!==$user['email']
                || (isset($flow['before_username'])&&$flow['before_username']!==$user['username']))return ['ok'=>false,'reason'=>'account_unavailable'];
            $result=$this->otp->verify($id,'profile_update',$code);
            if(!$result['ok'])return $result;
            $profile=(new User())->profileFor($id);
            (new User())->updateCustomerProfile($id,
                isset($flow['before_full_name'])&&(string)$profile['full_name']===$flow['before_full_name']?$flow['full_name']:$profile['full_name'],
                $flow['email'],
                isset($flow['before_contact_no'])&&(string)$profile['contact_no']===$flow['before_contact_no']?$flow['contact_no']:$profile['contact_no'],
                $profile['delivery_address']?:null,
                $flow['username']??null);
            return $result;
        });
    }
}
