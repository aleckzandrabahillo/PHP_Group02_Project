<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\HairProfileValidator;
final class RecommendationCopy
{
 public static function reason(array $reason):string
 {
  [$type,$code]=array_pad(explode('.',(string)($reason['code']??''),2),2,'');
  if($type==='texture'&&isset(HairProfileValidator::CHOICES['hair_texture'][$code]))return 'Listed for '.strtolower(HairProfileValidator::CHOICES['hair_texture'][$code]).' hair.';
  if($type==='scalp')return match($code){'oily_scalp'=>'Listed for oily roots.','dry_scalp'=>'Listed for a dry-feeling scalp.','normal_scalp'=>'Listed for a balanced scalp.',default=>'Based on listed scalp suitability.'};
  if($type==='concern'&&isset(HairProfileValidator::CHOICES['concerns'][$code]))return 'Matches your '.strtolower(HairProfileValidator::CHOICES['concerns'][$code]).' concern.';
  return 'Based on listed product suitability.';
 }
}
