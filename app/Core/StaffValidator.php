<?php
declare(strict_types=1);
namespace App\Core;

final class StaffValidator
{
    public static function validate(array $input): array
    {
        $values=[];$errors=[];
        foreach(['full_name','email','username','role','password'] as $field) {
            $value=$input[$field]??'';
            if(!is_string($value)||!mb_check_encoding($value,'UTF-8')||preg_match('/[\x00-\x1f\x7f]/',$value)) {
                $errors[$field]='Enter a valid text value.';$value='';
            }
            $values[$field]=$field==='password'?$value:trim($value);
        }
        if(mb_strlen($values['full_name'])<2||mb_strlen($values['full_name'])>120)$errors['full_name']='Use a name between 2 and 120 characters.';
        if(strlen($values['email'])>190||!filter_var($values['email'],FILTER_VALIDATE_EMAIL))$errors['email']='Enter a valid email address.';
        if(!preg_match('/^[A-Za-z0-9._-]{4,40}$/D',$values['username']))$errors['username']='Use 4–40 letters, numbers, dots, underscores or hyphens.';
        if(!in_array($values['role'],['admin','catalog_manager'],true))$errors['role']='Choose a staff role.';
        if($error=Validator::passwordError($values['password']))$errors['password']=$error;
        $values['email']=strtolower($values['email']);
        return ['values'=>$values,'errors'=>$errors];
    }
}
