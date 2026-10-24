<?php

namespace App\Core;

use RuntimeException;

final class View
{
    public static function render(string $view, array $data = [], string $layout = 'app'): void
    {
        $viewFile = dirname(__DIR__) . '/Views/' . $view . '.php';
        $layoutFile = dirname(__DIR__) . '/Views/layouts/' . $layout . '.php';
        if (!is_file($viewFile) || !is_file($layoutFile)) {
            throw new RuntimeException('View template not found.');
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();
        if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&($_SERVER['HTTP_X_AVELA_CONTENT']??'')==='staff'&&$layout==='app'&&in_array(auth_user()['role']??'',['admin','catalog_manager'],true)){
            header('Cache-Control: no-store, private');header('Vary: X-Avela-Content');
            preg_match_all('~<script\b[^>]*src="([^"]+)"[^>]*>~i',$content,$assets);
            ob_start();require dirname(__DIR__).'/Views/partials/flashes.php';$notices=ob_get_clean();
            Session::clearOld();
            Response::json(['html'=>$notices.$content,'scripts'=>array_map(static fn($src)=>html_entity_decode($src,ENT_QUOTES,'UTF-8'),$assets[1]),'title'=>($pageTitle??'Avela').' · Avela','pageTitle'=>$pageTitle??'Avela'],http_response_code()?:200);
        }
        require $layoutFile;
        Session::clearOld();
    }
}
