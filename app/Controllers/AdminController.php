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
        $data = ['pageTitle'=>$title, 'profile'=>$this->users->profileFor((int)$user['id'])];
        if ($view === 'audit_logs') {
            $data['auditLogs'] = Database::connection()->query(
                'SELECT a.id, a.action, a.target_type, a.target_id, a.details, a.created_at,
                        COALESCE(sp.full_name, u.username, "Former user") AS actor_name
                 FROM audit_logs a LEFT JOIN users u ON u.id=a.actor_id
                 LEFT JOIN staff_profiles sp ON sp.user_id=u.id
                 ORDER BY a.created_at DESC, a.id DESC LIMIT 200'
            )->fetchAll();
        }
        View::render('admin/' . $view, $data);
    }
}
