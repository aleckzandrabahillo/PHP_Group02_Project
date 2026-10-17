<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Middleware\AuthGuard;
use App\Models\User;
use App\Models\Order;
use App\Services\AuditService;
use App\Services\DashboardService;
use App\Services\LogService;
use PDO;

final class AdminController
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly User $users,
        private readonly AuditService $audit,
        private readonly Order $orders,
        private readonly LogService $logs,
    ) {}

    // ---- shared helpers -------------------------------------------------------------

    /** Admin-only gate. Returns the signed-in admin. */
    private function admin(): array
    {
        return AuthGuard::roles(['admin']);
    }

    /** Admin-only gate plus CSRF check, for every POST action. */
    private function mutate(): array
    {
        $user = $this->admin();
        Csrf::enforce(isset($_POST['_token']) && is_string($_POST['_token']) ? $_POST['_token'] : null);
        return $user;
    }

    /** Flash a message and redirect. $path must be an internal path, never user input. */
    private function back(string $path, string $type, string $message): never
    {
        Session::flash($type, $message);
        Response::redirect($path);
    }

    /** Validates a posted "return" path against the pages allowed to send the user back here. */
    private function safeReturn(mixed $path): string
    {
        $allowed = ['/admin/users', '/admin/staff'];
        if (!is_string($path) || !in_array(parse_url($path, PHP_URL_PATH), $allowed, true)) {
            return '/admin/staff';
        }
        return $path;
    }

    /** @return array{0:int,1:int,2:int} [limit, offset, page] */
    private function pager(): array
    {
        $limit = AuditService::DEFAULT_LIMIT;
        $page = max(1, (int) filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));
        return [$limit, ($page - 1) * $limit, $page];
    }

    /** A trimmed string query parameter ('' if missing or not a string). */
    private function query(string $key): string
    {
        return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : '';
    }

    /** Renders admin/$view with the page title and the signed-in admin's profile. */
    private function render(array $admin, string $view, string $title, array $data = []): void
    {
        View::render('admin/' . $view, [
            'pageTitle' => $title,
            'profile' => $this->users->profileFor((int) $admin['id']),
        ] + $data);
    }

    // ---- pages ----------------------------------------------------------------------

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

    public function staff(): void
    {
        $admin = $this->admin();
        [$limit, $offset, $page] = $this->pager();
        $term = $this->query('q');
        $status = $this->query('status');

        $result = $this->users->searchStaff($term, $status, $limit, $offset);

        $this->render($admin, 'staff', 'Staff & Roles', $result + [
            'page' => $page,
            'limit' => $limit,
            'filters' => ['q' => $term, 'status' => $status],
        ]);
    }

    public function staffCreate(): void
    {
        $actor = $this->mutate();
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $role = (string) ($_POST['role'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        try {
            if ($fullName === '' || mb_strlen($fullName) > 120) {
                throw new \InvalidArgumentException('Enter a valid full name.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
                throw new \InvalidArgumentException('Enter a valid email address.');
            }
            if (!preg_match('/^[a-zA-Z0-9_.]{3,40}$/D', $username)) {
                throw new \InvalidArgumentException('Usernames must be 3-40 characters: letters, numbers, dot or underscore.');
            }
            if (!in_array($role, ['admin', 'catalog_manager'], true)) {
                throw new \InvalidArgumentException('Choose a valid staff role.');
            }
            if (strlen($password) < 12) {
                throw new \InvalidArgumentException('Temporary passwords need at least 12 characters.');
            }
            if ($this->users->emailExists($email)) {
                throw new \InvalidArgumentException('That email is already registered.');
            }
            if ($this->users->usernameExists($username)) {
                throw new \InvalidArgumentException('That username is already taken.');
            }

            $this->users->createStaff($role, $email, $username, password_hash($password, PASSWORD_DEFAULT), $fullName);
            Session::flash('success', 'Staff account created.');
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        } catch (\Throwable) {
            Session::flash('error', 'The staff account could not be created.');
        }
        Response::redirect('/admin/staff');
    }

    public function staffRole(): void
    {
        $actor = $this->mutate();
        $id = (int) ($_POST['id'] ?? 0);
        $role = (string) ($_POST['role'] ?? '');

        if ($id === (int) $actor['id']) {
            $this->back('/admin/staff', 'error', 'You cannot change your own role.');
        }

        try {
            $this->users->changeStaffRole((int) $actor['id'], $id, $role);
            $this->back('/admin/staff', 'success', 'Role updated.');
        } catch (\InvalidArgumentException|\DomainException $e) {
            $this->back('/admin/staff', 'error', $e->getMessage());
        } catch (\Throwable) {
            $this->back('/admin/staff', 'error', 'The role could not be updated.');
        }
    }

    public function unlockUser(): void
    {
        $actor = $this->mutate();
        $id = (int) ($_POST['id'] ?? 0);
        $return = $this->safeReturn($_POST['return'] ?? null);

        if ($id <= 0) {
            $this->back($return, 'error', 'Invalid account.');
        }
        $this->users->adminUnlock((int) $actor['id'], $id);
        $this->back($return, 'success', 'Account unlocked. Its status, password and MFA requirements are unchanged.');
    }

    public function updateUserStatus(): void
    {
        $actor = $this->mutate();
        $id = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        $return = $this->safeReturn($_POST['return'] ?? null);

        if ($id === (int) $actor['id']) {
            $this->back($return, 'error', 'You cannot change your own status.');
        }

        try {
            $this->users->setStatus((int) $actor['id'], $id, $status);
            $this->back($return, 'success', 'Status updated.');
        } catch (\InvalidArgumentException $e) {
            $this->back($return, 'error', $e->getMessage());
        } catch (\Throwable) {
            $this->back($return, 'error', 'The status could not be updated.');
        }
    }

    public function orders(): void
    {
        $admin = $this->admin();
        [$limit, $offset, $page] = $this->pager();
        $search = $this->query('q');
        $status = $this->query('status');

        $result = $this->orders->search($search, $status, $limit, $offset);

        $this->render($admin, 'orders', 'Orders', $result + [
            'page' => $page,
            'limit' => $limit,
            'filters' => ['q' => $search, 'status' => $status],
            'statuses' => Order::statuses(),
        ]);
    }

    public function orderView(): void
    {
        $admin = $this->admin();
        $id = (int) ($_GET['id'] ?? 0);
        $order = $id > 0 ? $this->orders->find($id) : null;

        if ($order === null) {
            http_response_code(404);
            View::render('errors/404', ['pageTitle' => 'Page not found'], 'plain');
            return;
        }

        $this->render($admin, 'order_detail', 'Order #' . $order['id'], [
            'order' => $order,
            'nextStatuses' => Order::nextStatuses((string) $order['status']),
            'history' => $this->audit->forTarget('order', (int) $order['id']),
        ]);
    }

    public function updateOrderStatus(): void
    {
        $actor = $this->mutate();
        $id = (int) ($_POST['id'] ?? 0);
        $to = (string) ($_POST['status'] ?? '');

        if ($id <= 0) {
            $this->back('/admin/orders', 'error', 'Invalid order.');
        }

        // The status change and its audit entry are saved together or not at all.
        $result = $this->orders->transition($id, $to, function (PDO $pdo, string $from, string $newStatus) use ($actor, $id): void {
            AuditService::record($pdo, (int) $actor['id'], 'order.status', 'order', $id, ['from' => $from, 'to' => $newStatus]);
        });

        if (!empty($result['missing'])) {
            $this->back('/admin/orders', 'error', $result['message']);
        }
        $this->back('/admin/orders/view?id=' . $id, $result['ok'] ? 'success' : 'error', $result['message']);
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

    public function authLogs(): void
    {
        $admin = $this->admin();
        [$limit, $offset, $page] = $this->pager();
        $q = $this->query('q');
        $event = $this->query('event');
        $result = $this->query('result');

        $data = $this->logs->search($q, $event, $result, $limit, $offset);

        // Requested page is past the end: fall back to the last real page.
        if ($data['rows'] === [] && $data['total'] > 0) {
            $page = (int) ceil($data['total'] / $limit);
            $data = $this->logs->search($q, $event, $result, $limit, ($page - 1) * $limit);
        }

        $this->render($admin, 'auth_logs', 'Authentication Logs', $data + [
            'page' => $page,
            'limit' => $limit,
            'filters' => ['q' => $q, 'event' => $event, 'result' => $result],
            'events' => $this->logs->events(),
        ]);
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
