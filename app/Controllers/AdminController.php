<?php

namespace App\Controllers;

use App\Core\View;
use App\Middleware\AuthGuard;
use App\Models\User;
use App\Services\DashboardService;

final class AdminController
{
    public function __construct(private readonly DashboardService $dashboard, private readonly User $users) {}

    public function dashboard(): void
    {
        $user = AuthGuard::roles(['admin']);
        View::render('admin/dashboard', ['pageTitle'=>'Admin Dashboard', 'stats'=>$this->dashboard->admin(), 'profile'=>$this->users->profileFor((int)$user['id'])]);
    }

    public function page(string $view, string $title): void
    {
        $user = AuthGuard::roles(['admin']);
        View::render('admin/' . $view, ['pageTitle'=>$title, 'profile'=>$this->users->profileFor((int)$user['id'])]);
    }

    public function logs(string $kind): void
    {
        AuthGuard::roles(['admin']);
        header('Cache-Control: no-store, private');
        header('Referrer-Policy: same-origin');
        try {
            $data=(new \App\Services\AdminLogReader())->read($kind,$_GET);
        } catch(\InvalidArgumentException $e) {
            http_response_code(422);
            $data=['error'=>$e->getMessage()];
        } catch(\Throwable) {
            http_response_code(503);
            $data=['error'=>'Log records are temporarily unavailable. Please try again later.'];
        }
        $data+=['kind'=>$kind,'records'=>[],'events'=>[],'filters'=>array_fill_keys(['q','event','result','from','to','sort'],''),'total'=>0,'page'=>1,'pages'=>1];
        $data['pageTitle']=$kind==='auth'?'Authentication Logs':'Audit Logs';
        View::render('admin/'.($kind==='auth'?'auth_logs':'audit_logs'),$data);
    }
}
