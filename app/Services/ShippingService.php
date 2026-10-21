<?php
declare(strict_types=1);
namespace App\Services;

use InvalidArgumentException;

final class ShippingService
{
    // Avela's review rate card, not a courier API or an address-verification service.
    public const REGIONS = [
        'NCR'=>'National Capital Region (NCR)', 'CAR'=>'Cordillera Administrative Region (CAR)',
        'R1'=>'Ilocos Region', 'R2'=>'Cagayan Valley', 'R3'=>'Central Luzon',
        'R4A'=>'CALABARZON', 'MIMAROPA'=>'MIMAROPA', 'R5'=>'Bicol Region',
        'R6'=>'Western Visayas', 'NIR'=>'Negros Island Region', 'R7'=>'Central Visayas',
        'R8'=>'Eastern Visayas', 'R9'=>'Zamboanga Peninsula', 'R10'=>'Northern Mindanao',
        'R11'=>'Davao Region', 'R12'=>'SOCCSKSARGEN', 'R13'=>'Caraga',
        'BARMM'=>'Bangsamoro Autonomous Region in Muslim Mindanao',
    ];

    public static function options(string $postalCode,string $city='',string $region=''): array
    {
        $postalCode=trim($postalCode);
        if(!preg_match('/^[1-9][0-9]{3}$/D',$postalCode))throw new InvalidArgumentException('Enter a four-digit Philippine postal code.');
        $config=self::configuration();
        $name=mb_strtolower(trim($city),'UTF-8');$name=strtr($name,['ñ'=>'n']);
        $name=preg_replace('/\s+/u',' ',trim(preg_replace('/^city of\s+|\s+city$/u','',$name)));
        $region=strtoupper(trim($region));
        // Known Pasig postal area is sufficient for callers using an existing saved postal quote.
        if($name===''&&$region===''&&(int)$postalCode>=1600&&(int)$postalCode<=1612){$name='pasig';$region='NCR';}
        if($name===''||!isset(self::REGIONS[$region]))throw new InvalidArgumentException('Enter your city and select a region to calculate delivery.');
        if($region==='NCR'){
            if((int)$postalCode>=2000||!in_array($name,$config['metro_cities'],true))throw new InvalidArgumentException('Check the Metro Manila city and postal code.');
            if($name==='pasig'&&((int)$postalCode<1600||(int)$postalCode>1612))throw new InvalidArgumentException('Check the Pasig postal code.');
            $tier=$name==='pasig'?'pasig':(in_array($name,$config['adjacent_cities'],true)?'adjacent':'metro_manila');
        }else{
            if((int)$postalCode<2000)throw new InvalidArgumentException('Check the postal code and region.');
            $tier=null;foreach(['nearby_regions'=>'nearby_luzon','luzon_regions'=>'other_luzon','visayas_regions'=>'visayas','mindanao_regions'=>'mindanao'] as $group=>$value)if(in_array($region,$config[$group],true)){$tier=$value;break;}
            if($tier===null)throw new InvalidArgumentException('Delivery pricing is not available for this destination.');
        }
        $base=$config['tiers'][$tier];
        return ['standard'=>['label'=>'Standard delivery','cents'=>$base,'zone'=>$tier,'origin'=>$config['origin'],'calculation'=>'configured_area_tier'],
            'priority'=>['label'=>'Priority delivery','cents'=>$base+$config['priority_surcharge_cents'],'zone'=>$tier,'origin'=>$config['origin'],'calculation'=>'configured_area_tier']];
    }

    public static function quote(string $postalCode,string $method,string $city='',string $region=''): array
    {
        $options=self::options($postalCode,$city,$region);
        if(!isset($options[$method]))throw new InvalidArgumentException('Choose an available shipping method.');
        $quote=$options[$method];return [...$quote,'method'=>$method,'fee'=>self::decimal($quote['cents'])];
    }

    public static function configuration(): array
    {
        return require dirname(__DIR__,2).'/config/shipping.php';
    }

    public static function decimal(int $cents): string
    {
        return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);
    }
}
