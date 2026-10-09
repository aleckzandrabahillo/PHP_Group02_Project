<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require dirname(__DIR__) . '/bootstrap.php';

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\CatalogController;
use App\Controllers\CustomerController;
use App\Controllers\PublicController;
use App\Models\Order;
use App\Core\Router;
use App\Models\User;
use App\Models\Product;
use App\Models\Favorite;
use App\Models\Cart;
use App\Models\Routine;
use App\Models\Review;
use App\Services\AuthService;
use App\Services\DashboardService;
use App\Services\LogService;
use App\Services\MailService;
use App\Services\OtpService;
use App\Services\RecaptchaService;
use App\Services\AuditService;
use App\Services\SecuritySettings;

$users = new User();
$products = new Product();
$favorites = new Favorite();
$cart = new Cart();
$routines = new Routine();
$reviews = new Review();
$logs = new LogService();
$mail = new MailService();
$otp = new OtpService($mail, $logs);
$authService = new AuthService($users, $otp, $logs);
$dashboard = new DashboardService();
$recaptcha = new RecaptchaService();
$audit = new AuditService();
$security = new SecuritySettings();

$auth = new AuthController($authService, $recaptcha);
$public = new PublicController($products, $favorites, $cart, $reviews);
$customer = new CustomerController($dashboard, $users, $products, $favorites, $cart, $routines, $otp);
$catalog = new CatalogController($dashboard, $users, $products);
$orders   = new Order();
$audit    = new AuditService();
$settings = new SecuritySettings();
$admin    = new AdminController($dashboard, $users, $products, $orders, $logs, $audit, $settings);

$router = new Router();
$router->get('/', [$public, 'landing']);
$router->get('/login', [$auth, 'loginForm']);
$router->post('/login', [$auth, 'login']);
$router->get('/shop', [$public, 'shop']);
$router->get('/about', [$public, 'about']);
$router->get('/search', [$public, 'search']);
$router->get('/search/suggest', [$public, 'searchSuggestions']);
$router->get('/product', [$public, 'product']);
$router->get('/register', [$auth, 'registerForm']);
$router->post('/register', [$auth, 'register']);


$router->get('/verify-otp', [$auth, 'otpForm']);
$router->post('/verify-otp', [$auth, 'verifyOtp']);
$router->post('/otp/resend', [$auth, 'resendOtp']);
$router->post('/logout', [$auth, 'logout']);

$router->get('/customer', [$customer, 'dashboard']);
$router->get('/assessment', fn() => $customer->page('assessment','Hair Assessment'));
$router->get('/routine', [$customer, 'routine']);
$router->get('/cart', [$customer, 'cart']);
$router->get('/checkout', [$customer, 'checkout']);
$router->post('/cart/add', [$customer, 'addToCart']);
$router->post('/cart/update', [$customer, 'updateCart']);
$router->post('/cart/remove', [$customer, 'removeFromCart']);
$router->get('/orders', fn() => $customer->page('orders','My Orders'));
$router->get('/profile', fn() => $customer->page('profile','Profile'));
$router->post('/profile/image', [$customer, 'updateProfileImage']);
$router->post('/profile/update', [$customer, 'updateProfile']);
$router->get('/profile/verify-otp', [$customer, 'profileOtpForm']);
$router->post('/profile/verify-otp', [$customer, 'verifyProfileOtp']);
$router->get('/favorites', [$customer, 'favorites']);
$router->post('/favorites/toggle', [$customer, 'toggleFavorite']);

$router->get('/catalog', [$catalog, 'dashboard']);
$router->get('/catalog/products', fn() => $catalog->page('products','Products'));
$router->get('/catalog/categories', fn() => $catalog->page('categories','Categories'));
$router->get('/catalog/inventory', fn() => $catalog->page('inventory','Inventory'));
$router->get('/catalog/profile', fn() => $catalog->page('profile','Profile'));

$router->get ('/admin',               [$admin, 'dashboard']);
$router->get ('/admin/users',         [$admin, 'users']);
$router->post('/admin/users/status',  [$admin, 'setCustomerStatus']);
$router->post('/admin/users/unlock',  [$admin, 'unlockUser']);
$router->get ('/admin/staff',         [$admin, 'staff']);
$router->post('/admin/staff/create',  [$admin, 'createStaff']);
$router->post('/admin/staff/update',  [$admin, 'updateStaff']);
$router->get ('/admin/orders',        [$admin, 'orders']);
$router->get ('/admin/orders/view',   [$admin, 'orderView']);
$router->post('/admin/orders/status', [$admin, 'updateOrderStatus']);
$router->get ('/admin/security',      [$admin, 'security']);
$router->post('/admin/security',      [$admin, 'updateSecurity']);
$router->get ('/admin/auth-logs',     [$admin, 'authLogs']);
$router->get ('/admin/audit-logs',    [$admin, 'auditLogs']);
$router->get ('/admin/profile',       fn() => $admin->page('profile', 'Profile'));

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
