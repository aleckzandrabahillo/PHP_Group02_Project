<?php

namespace App\Controllers;

use App\Core\View;
use App\Middleware\AuthGuard;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DashboardService;

final class AdminController
{
    public function __construct(private readonly DashboardService $dashboard, private readonly User $users, private readonly AuditService $audit) {}

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

    public function auditLogs(): void
    {
        $user = AuthGuard::roles(['admin']);

        $term = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
        $limit = AuditService::DEFAULT_LIMIT;
        $page = max(1, (int) filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));

        $result = $this->audit->search($term, $limit, ($page - 1) * $limit);

        // Requested page is past the end: fall back to the last real page.
        if ($result['rows'] === [] && $result['total'] > 0) {
            $page = (int) ceil($result['total'] / $limit);
            $result = $this->audit->search($term, $limit, ($page - 1) * $limit);
        }

        View::render('admin/audit_logs', [
            'pageTitle' => 'Audit Logs',
            'profile' => $this->users->profileFor((int) $user['id']),
            'logs' => $result['rows'],
            'total' => $result['total'],
            'limit' => $limit,
            'page' => $page,
            'term' => $term,
        ]);
    }
}
