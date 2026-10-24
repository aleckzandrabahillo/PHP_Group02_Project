<?php
declare(strict_types=1);
namespace App\Services;
use InvalidArgumentException;
use RuntimeException;
final class ImageUploadPolicy
{
 public const MAX_BYTES=2621440;
 public static function config():array{return require dirname(__DIR__,2).'/config/uploads.php';}
 public static function maxBytes():int{$value=self::config()['max_bytes'];if(!is_int($value)||$value<1||$value>self::MAX_BYTES)throw new RuntimeException('Image upload policy unavailable.');return $value;}
 public static function label():string{return rtrim(rtrim(number_format(self::maxBytes()/1048576,2,'.',''),'0'),'.').' MB';}
 /** Decode before moving; metadata alone is not proof of a valid image. */
 public static function decode(string $file,string $mime):\GdImage
 {
  if(!extension_loaded('gd'))throw new RuntimeException('Image processing is temporarily unavailable. Please try again later.');
  $warning=false;set_error_handler(static function()use(&$warning):bool{$warning=true;return true;});
  try{$image=match($mime){'image/jpeg'=>imagecreatefromjpeg($file),'image/png'=>imagecreatefrompng($file),'image/webp'=>imagecreatefromwebp($file),default=>false};}finally{restore_error_handler();}
  if(!$image||$warning){if($image)imagedestroy($image);throw new InvalidArgumentException('This image could not be read. Choose an undamaged JPG, PNG or WebP.');}
  return $image;
 }
 public static function store(string $temporary,string $destination,string $mime):void
 {
  // PHP's private upload temporary file is scanned before any public file is written.
  UploadScanner::assertClean($temporary);
  $image=self::decode($temporary,$mime);$config=self::config();$output=null;
  try{
   $width=imagesx($image);$height=imagesy($image);
   // Leave PNG/WebP bytes and transparency intact. Never enlarge small pictures.
   if($mime==='image/jpeg'&&max($width,$height)>$config['jpeg_resize_side']){
    $scale=$config['jpeg_resize_side']/max($width,$height);$output=imagescale($image,max(1,(int)round($width*$scale)),max(1,(int)round($height*$scale)),IMG_BICUBIC_FIXED);
    if(!$output||!imagejpeg($output,$destination,$config['jpeg_quality']))throw new RuntimeException('The image could not be saved.');
    if(filesize($destination)>self::maxBytes()){unlink($destination);throw new InvalidArgumentException('Choose an image of '.self::label().' or smaller.');}
   }elseif(!move_uploaded_file($temporary,$destination))throw new RuntimeException('Image storage is unavailable.');
  }catch(\Throwable $e){if(is_file($destination))unlink($destination);throw $e;}
  finally{imagedestroy($image);if($output)imagedestroy($output);}
 }
}
