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

$auth = new AuthController($authService, $recaptcha);
$public = new PublicController($products, $favorites, $cart, $reviews);
$customer = new CustomerController($dashboard, $users, $products, $favorites, $cart, $routines, $otp);
$catalog = new CatalogController($dashboard, $users, $products);
$admin = new AdminController($dashboard, $users);

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
$router->get('/catalog/categories', [$catalog, 'categories']);
$router->post('/catalog/categories', [$catalog, 'manageCategory']);
$router->get('/catalog/inventory', fn() => $catalog->page('inventory','Inventory'));
$router->get('/catalog/profile', fn() => $catalog->page('profile','Profile'));

$router->get('/admin', [$admin, 'dashboard']);
$router->get('/admin/users', fn() => $admin->page('users','Users'));
$router->get('/admin/staff', fn() => $admin->page('staff','Staff & Roles'));
$router->get('/admin/orders', fn() => $admin->page('orders','Orders'));
$router->get('/admin/security', fn() => $admin->page('security','Security'));
$router->get('/admin/auth-logs', fn() => $admin->page('auth_logs','Authentication Logs'));
$router->get('/admin/audit-logs', fn() => $admin->page('audit_logs','Audit Logs'));
$router->get('/admin/profile', fn() => $admin->page('profile','Profile'));

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
