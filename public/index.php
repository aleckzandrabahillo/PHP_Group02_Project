<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
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
use App\Models\Order;
use App\Services\CheckoutService;
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
$customer = new CustomerController($dashboard, $users, $products, $favorites, $cart, $routines, $otp, new CheckoutService(), new Order());
$catalog = new CatalogController($dashboard, $users, $products);
$admin = new AdminController($dashboard, $users);
$security = new \App\Controllers\SecurityController(new \App\Services\SecurityManagement());
$mfa = new \App\Controllers\MfaController(new \App\Services\MfaService($users, $otp, $logs), $authService);
$hairProfile = new \App\Controllers\HairProfileController(new \App\Models\HairProfile(), new \App\Services\ConcernInterpreter());
$recommendations = new \App\Controllers\RecommendationController();

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
$router->get('/verify-authenticator', [$mfa, 'verifyForm']);
$router->post('/verify-authenticator', [$mfa, 'verify']);
$router->get('/account/mfa', [$mfa, 'settings']);
$router->post('/account/mfa', [$mfa, 'manage']);
$router->get('/account/mfa/recovery', [$mfa, 'recovery']);

$router->get('/customer', [$customer, 'dashboard']);
$router->get('/assessment', [$hairProfile, 'assessment']);
$router->post('/assessment', [$hairProfile, 'save']);
$router->get('/routine', [$customer, 'routine']);
$router->get('/recommendations', [$recommendations, 'matches']);
$router->post('/routine', [$recommendations, 'save']);
$router->get('/cart', [$customer, 'cart']);
$router->get('/checkout', [$customer, 'checkout']);
$router->post('/checkout', [$customer, 'placeOrder']);
$router->post('/cart/add', [$customer, 'addToCart']);
$router->post('/cart/update', [$customer, 'updateCart']);
$router->post('/cart/remove', [$customer, 'removeFromCart']);
$router->get('/orders', [$customer, 'orders']);
$router->get('/orders/view', [$customer, 'order']);
$router->get('/profile', fn() => $customer->page('profile','Profile'));
$router->post('/profile/image', [$customer, 'updateProfileImage']);
$router->post('/profile/update', [$customer, 'updateProfile']);
$router->get('/profile/verify-otp', [$customer, 'profileOtpForm']);
$router->post('/profile/verify-otp', [$customer, 'verifyProfileOtp']);
$router->get('/favorites', [$customer, 'favorites']);
$router->get('/favorites/state', [$customer, 'favoriteState']);
$router->post('/favorites/toggle', [$customer, 'toggleFavorite']);

$router->get('/catalog', [$catalog, 'dashboard']);
$router->get('/catalog/products', [$catalog, 'products']);
$router->post('/catalog/products', [$catalog, 'manageProduct']);
$router->get('/catalog/categories', [$catalog, 'categories']);
$router->post('/catalog/categories', [$catalog, 'manageCategory']);
$router->get('/catalog/inventory', [$catalog, 'inventory']);
$router->post('/catalog/inventory', [$catalog, 'updateStock']);
$router->get('/catalog/profile', fn() => $catalog->page('profile','Profile'));

$router->get('/admin', [$admin, 'dashboard']);
$router->get('/admin/users', fn() => $admin->page('users','Users'));
$router->get('/admin/staff', fn() => $admin->page('staff','Staff & Roles'));
$router->get('/admin/orders', fn() => $admin->page('orders','Orders'));
$router->get('/admin/security', [$security, 'page']);
$router->post('/admin/security', [$security, 'save']);
$router->get('/admin/auth-logs', fn() => $admin->page('auth_logs','Authentication Logs'));
$router->get('/admin/audit-logs', fn() => $admin->page('audit_logs','Audit Logs'));
$router->get('/admin/profile', fn() => $admin->page('profile','Profile'));

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
