<?php
/* Presentation artwork from the approved handoff; no catalog or account data. */
if (!function_exists('handoff_shop_links')) {
 function handoff_shop_links(array $categories, array $filters = []): array {
  $links=[];
  foreach (['cleanse'=>'Cleanse','condition'=>'Condition','treat'=>'Treat','finish'=>'Style'] as $step=>$label) {
   $links[]=['label'=>$label,'url'=>url('/shop?routine_step[]='.$step),'selected'=>in_array($step,$filters['routine_steps']??[],true)];
  }
  // Tools are a real catalog category, not a supported recommendation step.
  foreach ($categories as $category) {
   if (preg_match('/\btools?\b/i',(string)($category['name']??'')) && (int)($category['id']??0)>0) {
    $links[]=['label'=>'Tools','url'=>url('/shop?category[]='.(int)$category['id']),'selected'=>in_array((int)$category['id'],$filters['categories']??[],true)];break;
   }
  }
  return $links;
 }
}
if (!function_exists('handoff_icon')) {
 function handoff_icon(string $name): string {
  $paths=['sliders'=>'<path d="M4 7h7m5 0h4M4 17h3m5 0h8"/><circle cx="13.5" cy="7" r="2.5"/><circle cx="9.5" cy="17" r="2.5"/>','list'=>'<path d="M10 6h10M10 12h10M10 18h10M4 5l1 1 2-2M4 11l1 1 2-2M4 17l1 1 2-2"/>','spark'=>'<path d="m12 3 2.4 6.6L21 12l-6.6 2.4L12 21l-2.4-6.6L3 12l6.6-2.4L12 3Z"/>','search'=>'<circle cx="10.8" cy="10.8" r="6.6"/><path d="m16 16 4.5 4.5"/>','account'=>'<circle cx="12" cy="7.5" r="3.2"/><path d="M5.5 21c0-5.3 2.3-8 6.5-8s6.5 2.7 6.5 8"/>','heart'=>'<path d="m12 21-8.6-8.5a5.7 5.7 0 0 1 8-8L12 5l.6-.5a5.7 5.7 0 0 1 8 8Z"/>','bag'=>'<path d="M6 8h12l1 13H5L6 8Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/>','close'=>'<path d="m6 6 12 12M18 6 6 18"/>','menu'=>'<path d="M4 8h16M4 16h16"/>','pin'=>'<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>'];
  return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['bag']).'</svg>';
 }
 function handoff_texture(string $type): string {
  $s='<svg viewBox="0 0 145 225" fill="none" stroke="#6d5e4f" stroke-width="1.35" stroke-linecap="round" aria-hidden="true">';
  for($i=0;$i<7;$i++){ $b=29+$i*12;$d='M'.$b.' 13';
   $d.=match($type){'straight'=>' C'.($b-10).' 70 '.($b+11).' 132 '.($b-1).' 204','wavy'=>str_repeat(' c -27 22 27 27 0 48',4),'curly'=>str_repeat(' c33 3 30 31 0 33 c-13 1 -14 8 0 9',5),default=>str_repeat(' c23 0 23 18 0 18 c-10 0 -10 5 0 5',9)};
   $s.='<path d="'.$d.'" opacity="'.(.5+$i*.065).'"/>';
  } return $s.'</svg>';
 }
 function handoff_care_art(string $type): string {
  $paths=['cleanse'=>'<path d="M34 15h32v11H34zM43 8h14v7H43zM39 26v8c0 5-11 8-11 17v56c0 4 3 7 7 7h30c4 0 7-3 7-7V51c0-9-11-12-11-17v-8M28 60h44M28 94h44M41 74h18M45 81h10"/>','condition'=>'<path d="M24 19h52l-7 80H31l-7-80ZM31 99h38v13H31zM28 28h44M34 57h32M39 66h22M43 75h14"/>','finish'=>'<path d="M39 23h22v18H39zM43 8h14v15H43zM48 8V4h22v7h-9M39 41c-7 2-10 6-10 12v54c0 4 3 7 7 7h28c4 0 7-3 7-7V53c0-6-3-10-10-12M29 64h42M29 96h42M42 76h16M45 84h10"/>'];
  return '<svg class="guide-product-art" viewBox="0 0 100 125" fill="none" stroke="currentColor" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$type]??$paths['finish']).'</svg>';
 }
 function handoff_guide_art(string $type): string {
  return $type==='texture'?'<div class="guide-textures">'.handoff_texture('straight').handoff_texture('wavy').handoff_texture('curly').'</div>':handoff_care_art($type);
 }
}
