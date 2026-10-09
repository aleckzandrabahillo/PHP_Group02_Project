<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Middleware\AuthGuard;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DashboardService;
use App\Services\LogService;
use App\Services\SecuritySettings;

final class AdminController
{
    private const PER_PAGE = 25;

    // Numeric settings editable on the Security page.
    private const NUMERIC_SETTINGS = [
        'password_min_length',
        'max_login_attempts',
        'lockout_minutes',
        'session_timeout_minutes',
    ];

    // Boolean (checkbox) settings editable on the Security page.
    private const TOGGLE_SETTINGS = [
        'staff_mfa_required',
        'captcha_enabled',
    ];

    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly User $users,
        private readonly Product $products,      // shared catalog model (also used by CatalogController)
        private readonly Order $orders,
        private readonly LogService $logs,
        private readonly AuditService $audit,
        private readonly SecuritySettings $settings
    ) {}

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** Admin-only guard for GET pages. */
    private function admin(): array
    {
        return AuthGuard::roles(['admin']);
    }

    /** Admin-only guard + CSRF check for every state-changing POST. */
    private function mutate(): array
    {
        $user = $this->admin();
        Csrf::enforce($_POST['_token'] ?? null);
        return $user;
    }

    private function render(array $user, string $view, string $title, array $data = []): void
    {
        View::render('admin/' . $view, [
            'pageTitle' => $title,
            'profile'   => $this->users->profileFor((int) $user['id']),
        ] + $data);
    }

    private function back(string $path, string $type, string $message): never
    {
        Session::flash($type, $message);
        Response::redirect($path);
    }

    private function notFound(): never
    {
        http_response_code(404);
        View::render('errors/404', ['pageTitle' => 'Page not found'], 'plain');
        exit;
    }

    private function query(string $key): string
    {
        return trim((string) ($_GET[$key] ?? ''));
    }

    /** @return array{0:int,1:int,2:int} [limit, offset, page] */
    private function pager(): array
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        return [self::PER_PAGE, ($page - 1) * self::PER_PAGE, $page];
    }

    /** Only allow redirects back to known admin list pages (prevents open redirects). */
    private function safeReturn(string $default): string
    {
        $return = (string) ($_POST['return'] ?? '');
        return in_array($return, ['/admin/users', '/admin/staff'], true) ? $return : $default;
    }

    // ------------------------------------------------------------------
    // Dashboard & profile
    // ------------------------------------------------------------------

    public function dashboard(): void
    {
        $user = $this->admin();
        $this->render($user, 'dashboard', 'Admin Dashboard', [
            'stats'         => $this->dashboard->admin(),
            'catalogStats'  => $this->dashboard->catalog(),                                   // shared with catalog module
            'stockAlerts'   => array_slice($this->products->catalog('', ['availability' => 'out_of_stock'], 'newest', true), 0, 5),
            'recentLogs'    => $this->logs->recent(5),
        ]);
    }

    /** Simple static pages (profile). */
    public function page(string $view, string $title): void
    {
        $user = $this->admin();
        $this->render($user, $view, $title);
    }

    // ------------------------------------------------------------------
    // Customers
    // ------------------------------------------------------------------

    public function users(): void
    {
        $user = $this->admin();
        [$limit, $offset, $page] = $this->pager();
        $q = $this->query('q');
        $status = $this->query('status');

        $result = $this->users->listAccounts(['customer'], $q, $status, $limit, $offset);

        $this->render($user, 'users', 'Users', $result + [
            'page'    => $page,
            'limit'   => $limit,
            'filters' => ['q' => $q, 'status' => $status],
        ]);
    }

    public function setCustomerStatus(): void
    {
        $actor = $this->mutate();
        $id = (int) ($_POST['id'] ?? 0);
        $to = (string) ($_POST['status'] ?? '');

        if ($this->users->setCustomerStatus($id, $to)) {
            $this->audit->record((int) $actor['id'], 'account.status', 'user', $id, "status → $to");
            $this->back('/admin/users', 'success', 'Customer status updated.');
        }
        $this->back('/admin/users', 'warning', 'Nothing changed. The customer may already have that status.');
    }

    /** Clears failed attempts and the lockout timer (customers or staff). */
    public function unlockUser(): void
    {
        $actor = $this->mutate();
        $id = (int) ($_POST['id'] ?? 0);
        $return = $this->safeReturn('/admin/users');

        if ($id > 0 && $this->users->unlock($id)) {
            $this->audit->record((int) $actor['id'], 'account.unlock', 'user', $id, 'Cleared lockout and failed attempts');
            $this->logs->auth($id, 'admin_unlock', 'success', ['by' => (int) $actor['id']]);
            $this->back($return, 'success', 'Account unlocked.');
        }
        $this->back($return, 'warning', 'That account was not locked.');
    }

    // ------------------------------------------------------------------
    // Staff & roles
    // ------------------------------------------------------------------

    public function staff(): void
    {
        $user = $this->admin();
        [$limit, $offset, $page] = $this->pager();
        $q = $this->query('q');
        $status = $this->query('status');

        $result = $this->users->listAccounts(['admin', 'catalog_manager'], $q, $status, $limit, $offset);

        $this->render($user, 'staff', 'Staff & Roles', $result + [
            'page'    => $page,
            'limit'   => $limit,
            'filters' => ['q' => $q, 'status' => $status],
            'selfId'  => (int) $user['id'],
        ]);
    }

    public function createStaff(): void
    {
        $actor = $this->mutate();
        $errors = Validator::staff($_POST);
        $email = trim((string) ($_POST['email'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));

        if (!$errors) {
            if ($this->users->emailExists($email)) {
                $errors['email'] = 'That email is already registered.';
            }
            if ($this->users->usernameExists($username)) {
                $errors['username'] = 'That username is already taken.';
            }
        }

        if ($errors) {
            Session::keepOld($_POST);          // strips password fields
            Session::put('errors', $errors);
            Response::redirect('/admin/staff');
        }

        try {
            $id = $this->users->createStaff(
                (string) $_POST['role'],
                $email,
                $username,
                password_hash((string) $_POST['password'], PASSWORD_DEFAULT),
                trim((string) $_POST['full_name'])
            );
        } catch (\PDOException $e) {
            // Unique-key race between the exists-check and the insert.
            $this->back('/admin/staff', 'error', 'That email or username was just taken. Try another.');
        }

        $this->audit->record((int) $actor['id'], 'staff.create', 'user', $id, "role={$_POST['role']}; email=$email");
        $this->back('/admin/staff', 'success', 'Staff account created. They will receive an OTP at sign-in.');
    }

    /** Role and/or status change. All safety rules are enforced inside User::updateStaff(). */
    public function updateStaff(): void
    {
        $actor = $this->mutate();
        $id = (int) ($_POST['id'] ?? 0);
        $role = ($_POST['role'] ?? '') !== '' ? (string) $_POST['role'] : null;
        $status = ($_POST['status'] ?? '') !== '' ? (string) $_POST['status'] : null;

        $result = $this->users->updateStaff((int) $actor['id'], $id, $role, $status);

        if ($result['ok']) {
            $this->audit->record((int) $actor['id'], 'staff.update', 'user', $id, implode('; ', $result['changes'] ?? []));
        }
        $this->back('/admin/staff', $result['ok'] ? 'success' : 'error', $result['message']);
    }

    // ------------------------------------------------------------------
    // Orders
    // ------------------------------------------------------------------

    public function orders(): void
    {
        $user = $this->admin();
        [$limit, $offset, $page] = $this->pager();
        $q = $this->query('q');
        $status = $this->query('status');

        $result = $this->orders->search($q, $status, $limit, $offset);

        $this->render($user, 'orders', 'Orders', $result + [
            'page'     => $page,
            'limit'    => $limit,
            'filters'  => ['q' => $q, 'status' => $status],
            'statuses' => array_keys(Order::FLOW),
        ]);
    }

    public function orderView(): void
    {
        $user = $this->admin();
        $order = $this->orders->find((int) ($_GET['id'] ?? 0));
        if (!$order) {
            $this->notFound();
        }

        $this->render($user, 'order_detail', 'Order #' . $order['id'], [
            'order'        => $order,
            'nextStatuses' => Order::FLOW[$order['status']] ?? [],
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

        $result = $this->orders->transition($id, $to);
        if ($result['ok']) {
            $this->audit->record((int) $actor['id'], 'order.status', 'order', $id, "{$result['from']} → $to");
        }
        $this->back('/admin/orders/view?id=' . $id, $result['ok'] ? 'success' : 'error', $result['message']);
    }

    // ------------------------------------------------------------------
    // Security settings
    // ------------------------------------------------------------------

    public function security(): void
    {
        $user = $this->admin();
        $values = [];
        foreach (array_merge(self::NUMERIC_SETTINGS, self::TOGGLE_SETTINGS) as $key) {
            $values[$key] = SecuritySettings::get($key);
        }
        $this->render($user, 'security', 'Security', ['settings' => $values]);
    }

    public function updateSecurity(): void
    {
        $actor = $this->mutate();

        $input = [];
        foreach (self::NUMERIC_SETTINGS as $key) {
            $raw = trim((string) ($_POST[$key] ?? ''));
            if ($raw === '' || !ctype_digit($raw)) {
                $this->back('/admin/security', 'error', 'All numeric settings must be whole numbers.');
            }
            $input[$key] = (int) $raw;
        }
        foreach (self::TOGGLE_SETTINGS as $key) {
            $input[$key] = isset($_POST[$key]) ? 1 : 0;      // unchecked boxes are not submitted
        }

        // Require explicit confirmation before weakening MFA or CAPTCHA.
        $weakens = (SecuritySettings::get('staff_mfa_required') === 1 && $input['staff_mfa_required'] === 0)
                || (SecuritySettings::get('captcha_enabled') === 1 && $input['captcha_enabled'] === 0);
        if ($weakens && empty($_POST['confirm_weaken'])) {
            $this->back('/admin/security', 'error', 'Confirm that you want to turn off MFA or CAPTCHA before saving.');
        }

        $changes = $this->settings->update($input, (int) $actor['id'], $this->audit);

        if ($changes) {
            $this->back('/admin/security', 'success', 'Security settings updated.');
        }
        $this->back('/admin/security', 'warning', 'No settings were changed. Check that each value is within its allowed range.');
    }

    // ------------------------------------------------------------------
    // Logs
    // ------------------------------------------------------------------

    public function authLogs(): void
    {
        $user = $this->admin();
        [$limit, $offset, $page] = $this->pager();
        $q = $this->query('q');
        $event = $this->query('event');
        $result = $this->query('result');

        $data = $this->logs->search($q, $event, $result, $limit, $offset);

        $this->render($user, 'auth_logs', 'Authentication Logs', $data + [
            'page'    => $page,
            'limit'   => $limit,
            'filters' => ['q' => $q, 'event' => $event, 'result' => $result],
        ]);
    }

    public function auditLogs(): void
    {
        $user = $this->admin();
        [$limit, $offset, $page] = $this->pager();
        $q = $this->query('q');

        $data = $this->audit->search($q, $limit, $offset);

        $this->render($user, 'audit_logs', 'Audit Logs', $data + [
            'page'    => $page,
            'limit'   => $limit,
            'filters' => ['q' => $q],
        ]);
    }
}