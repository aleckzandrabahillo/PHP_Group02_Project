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
}
