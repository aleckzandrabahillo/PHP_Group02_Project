<?php
declare(strict_types=1);
namespace App\Services;
final class PhilippineLocations
{
 // PSA PSGC, 30 September 2026. Sulu is in Region IX; NIR includes Siquijor.
 // https://psa.gov.ph/classification/psgc/provinces?vcode=82 (CC BY 4.0)
 public const PROVINCES=[
 'CAR'=>['Abra','Apayao','Benguet','Ifugao','Kalinga','Mountain Province'],
 'R1'=>['Ilocos Norte','Ilocos Sur','La Union','Pangasinan'],
 'R2'=>['Batanes','Cagayan','Isabela','Nueva Vizcaya','Quirino'],
 'R3'=>['Aurora','Bataan','Bulacan','Nueva Ecija','Pampanga','Tarlac','Zambales'],
 'R4A'=>['Batangas','Cavite','Laguna','Quezon','Rizal'],
 'MIMAROPA'=>['Marinduque','Occidental Mindoro','Oriental Mindoro','Palawan','Romblon'],
 'R5'=>['Albay','Camarines Norte','Camarines Sur','Catanduanes','Masbate','Sorsogon'],
 'R6'=>['Aklan','Antique','Capiz','Guimaras','Iloilo'],
 'NIR'=>['Negros Occidental','Negros Oriental','Siquijor'],
 'R7'=>['Bohol','Cebu'],
 'R8'=>['Biliran','Eastern Samar','Leyte','Northern Samar','Samar','Southern Leyte'],
 'R9'=>['Sulu','Zamboanga del Norte','Zamboanga del Sur','Zamboanga Sibugay'],
 'R10'=>['Bukidnon','Camiguin','Lanao del Norte','Misamis Occidental','Misamis Oriental'],
 'R11'=>['Davao de Oro','Davao del Norte','Davao del Sur','Davao Occidental','Davao Oriental'],
 'R12'=>['Cotabato','Sarangani','South Cotabato','Sultan Kudarat'],
 'R13'=>['Agusan del Norte','Agusan del Sur','Dinagat Islands','Surigao del Norte','Surigao del Sur'],
 'BARMM'=>['Basilan','Lanao del Sur','Maguindanao del Norte','Maguindanao del Sur','Tawi-Tawi'],
 ];
 public static function areas():array
 {
  $areas=['Metro Manila'=>'NCR'];foreach(self::PROVINCES as $region=>$provinces)foreach($provinces as $name)$areas[$name]=$region;
  ksort($areas,SORT_NATURAL|SORT_FLAG_CASE);return $areas;
 }
 /** One visible ecommerce Region field; keep the geographic region in storage. */
 public static function normalize(array $input):array
 {
  $area=$input['region']??null;
  if(is_string($area)&&isset(self::areas()[$area])){$input['region']=self::areas()[$area];$input['province']=$area;}
  return $input;
 }
 public static function selected(array $values):string
 {
  $province=$values['province']??'';if(is_string($province)&&isset(self::areas()[$province]))return $province;
  return ($values['region']??'')==='NCR'?'Metro Manila':(string)($values['region']??'');
 }
 public static function options(string $region):array
 {
  if(!isset(ShippingService::REGIONS[$region]))return [];
  if($region==='NCR')return ['Metro Manila'];
  return [...self::PROVINCES[$region],'Independent city / area (no province)'];
 }
 public static function valid(string $region,string $province):bool{return in_array($province,self::options($region),true);}
}
