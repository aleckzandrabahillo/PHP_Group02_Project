<?php
declare(strict_types=1);
namespace App\Core;

use App\Services\ShippingService;

final class DeliveryValidator
{
    public const LIMITS=['country'=>2,'first_name'=>60,'last_name'=>60,'address_line'=>200,'barangay'=>100,'postal_code'=>4,'city'=>100,'region'=>16,'shipping_method'=>16];

    public static function validate(array $input): array
    {
        $input=\App\Services\PhilippineLocations::normalize($input);
        $values=[];$errors=[];
        foreach(self::LIMITS as $field=>$limit){
            $raw=$input[$field]??'';
            if(!is_string($raw)||!mb_check_encoding($raw,'UTF-8')){$values[$field]='';$errors[$field]='Enter a valid value.';continue;}
            $value=trim($raw);$values[$field]=$value;
            if($value===''||preg_match('/^\s*$/u',$value)||mb_strlen($value)>$limit||preg_match('/[\x00-\x1F\x7F]/u',$value))$errors[$field]='Enter '.str_replace('_',' ',$field).' within '.$limit.' characters.';
        }
        if($values['country']!=='PH')$errors['country']='Delivery is available within the Philippines.';
        if(!isset(ShippingService::REGIONS[$values['region']]))$errors['region']='Choose your region.';
        if(!preg_match('/^[1-9][0-9]{3}$/D',$values['postal_code']))$errors['postal_code']='Enter a four-digit Philippine postal code.';
        $province=$input['province']??'';
        $values['province']=is_string($province)&&mb_check_encoding($province,'UTF-8')?trim($province):'';
        if(!is_string($province)||!\App\Services\PhilippineLocations::valid($values['region'],$values['province']))$errors['province']='Choose a province or area within your region.';
        $values['full_name']=trim($values['first_name'].' '.$values['last_name']);
        if(mb_strlen($values['full_name'])>120)$errors['first_name']='Keep the recipient name within 120 characters.';
        $values['delivery_address']=implode(', ',array_filter([$values['address_line'],$values['barangay'],$values['city'],$values['province'],ShippingService::REGIONS[$values['region']]??'',$values['postal_code'],'Philippines'],fn($part)=>$part!==''));
        if(mb_strlen($values['delivery_address'])>500)$errors['address_line']='Shorten the complete delivery address to 500 characters.';
        $shipping=null;
        if(!isset($errors['postal_code'])){
            try{$shipping=ShippingService::quote($values['postal_code'],$values['shipping_method'],$values['city'],$values['region']);}
            catch(\InvalidArgumentException $e){$errors['shipping_method']=$e->getMessage();}
        }
        $values['shipping_fee']=$shipping['fee']??'0.00';
        return ['values'=>$values,'errors'=>$errors];
    }

    public static function prefill(array $profile): array
    {
        $name=trim((string)($profile['full_name']??''));$parts=preg_split('/\s+/u',$name,-1,PREG_SPLIT_NO_EMPTY)?:[];
        $last=count($parts)>1?array_pop($parts):'';
        return ['country'=>'PH','first_name'=>implode(' ',$parts),'last_name'=>$last,
            'address_line'=>(string)($profile['delivery_address']??''),'barangay'=>'','postal_code'=>'','city'=>'','region'=>'',
            'province'=>'','shipping_method'=>'standard','contact_no'=>(string)($profile['contact_no']??''),'payment_method'=>'cod'];
    }
}
