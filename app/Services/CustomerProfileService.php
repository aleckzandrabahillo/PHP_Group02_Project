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
                || ($flow['current_email']??'')!==$user['email'])return ['ok'=>false,'reason'=>'account_unavailable'];
            $result=$this->otp->verify($id,'profile_update',$code);
            if(!$result['ok'])return $result;
            (new User())->updateCustomerProfile($id,$flow['full_name'],$flow['email'],$flow['contact_no'],$flow['delivery_address']?:null);
            return $result;
        });
    }
}
