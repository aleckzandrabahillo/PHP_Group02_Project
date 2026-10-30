<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

require dirname(__DIR__) . '/bootstrap.php';

// PHP discards the entire multipart body above post_max_size; give a useful 413.
if (($_SERVER['REQUEST_METHOD']??'')==='POST') {
    $configured=ini_get('post_max_size');$unit=strtolower(substr($configured,-1));$maximum=(float)$configured*(['g'=>1073741824,'m'=>1048576,'k'=>1024][$unit]??1);
    if($maximum>0&&(float)($_SERVER['CONTENT_LENGTH']??0)>$maximum){http_response_code(413);header('Content-Type: text/plain; charset=UTF-8');exit('This upload is too large. Choose an image of '.\App\Services\ImageUploadPolicy::label().' or smaller and try again.');}
}

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
$activation = new \App\Controllers\ActivationController(new \App\Services\ActivationService($otp, $mail, $logs));
$passwords = new \App\Controllers\PasswordController();
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
$router->get('/care-guide', fn() => $public->careGuide());
$router->get('/care-guide/know-your-texture', fn() => $public->careGuide('know-your-texture'));
$router->get('/care-guide/wash-day', fn() => $public->careGuide('wash-day'));
$router->get('/care-guide/condition-with-care', fn() => $public->careGuide('condition-with-care'));
$router->get('/care-guide/a-gentler-finish', fn() => $public->careGuide('a-gentler-finish'));
$router->get('/privacy', [$public, 'privacy']);
$router->get('/help-faq', [$public, 'help']);
$router->get('/refund-policy', fn()=>$public->policy('refund'));
$router->get('/terms', fn()=>$public->policy('terms'));
$router->get('/search', [$public, 'search']);
$router->get('/search/suggest', [$public, 'searchSuggestions']);
$router->get('/product', [$public, 'product']);
$router->get('/register', [$auth, 'registerForm']);
$router->post('/register', [$auth, 'register']);
$router->get('/activate', [$activation, 'page']);
$router->post('/activate', [$activation, 'activate']);


$router->get('/verify-otp', [$auth, 'otpForm']);
$router->post('/verify-otp', [$auth, 'verifyOtp']);
$router->post('/otp/resend', [$auth, 'resendOtp']);
$router->post('/logout', [$auth, 'logout']);
$router->get('/account/session', fn()=>\App\Middleware\AuthGuard::sessionStatus());
$router->post('/account/session/activity', fn()=>\App\Middleware\AuthGuard::sessionStatus(true));
$router->get('/verify-authenticator', [$mfa, 'verifyForm']);
$router->post('/verify-authenticator', [$mfa, 'verify']);
$router->get('/account/mfa', [$mfa, 'settings']);
$router->get('/account/password', [$passwords, 'page']);
$router->post('/account/password', [$passwords, 'change']);
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
$router->post('/checkout/pricing', [$customer, 'pricing']);
$discounts = new \App\Controllers\DiscountController();
$router->get('/catalog/discounts', [$discounts, 'page']);
$router->post('/catalog/discounts', [$discounts, 'manage']);
$router->post('/checkout/shipping', [$customer, 'shipping']);
$maya = new \App\Controllers\MayaController();
$router->get('/payments/maya/return', [$maya, 'returned']);
$router->post('/payments/maya/refresh', [$maya, 'refresh']);
$router->post('/payments/maya/continue', [$maya, 'continuePayment']);
$router->post('/payments/maya/webhook', [$maya, 'webhook']);
$router->post('/cart/add', [$customer, 'addToCart']);
$router->post('/cart/update', [$customer, 'updateCart']);
$router->post('/cart/remove', [$customer, 'removeFromCart']);
$router->get('/orders', [$customer, 'orders']);
$router->post('/orders/confirmation', [$customer, 'sendConfirmation']);
$router->get('/orders/view', [$customer, 'order']);
$router->get('/settings', fn() => $customer->page('settings','Account settings'));
$router->get('/settings/security', [$mfa, 'customerSettings']);
$router->get('/profile', fn() => $customer->page('profile','Profile'));
$router->post('/profile/image', [$customer, 'updateProfileImage']);
$router->post('/profile/address', fn() => (new \App\Controllers\AddressController())->save());
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
$router->post('/account/profile', fn() => (new \App\Controllers\StaffProfileController())->update());
$router->post('/account/profile/image', fn() => (new \App\Controllers\StaffProfileController())->image());
$router->get('/account/profile/verify-otp', fn() => (new \App\Controllers\StaffProfileController())->verify());
$router->post('/account/profile/verify-otp', fn() => (new \App\Controllers\StaffProfileController())->verify());
$router->get('/catalog/profile', fn() => $catalog->page('profile','Profile'));

$router->get('/admin', [$admin, 'dashboard']);
$router->get('/admin/users', [$admin, 'customers']);
$router->post('/admin/users/status', fn() => $admin->accountChange('status'));
$router->post('/admin/users/unlock', [$admin, 'accountUnlock']);
$router->get('/admin/staff', [$admin, 'staff']);
$router->post('/admin/staff/create', [$admin, 'staffCreate']);
$router->post('/admin/staff/role', fn() => $admin->accountChange('role'));
$router->get('/admin/orders', [$admin, 'orders']);
$router->get('/admin/orders/view', [$admin, 'orderView']);
$router->post('/admin/orders/status', [$admin, 'orderStatus']);
$router->get('/admin/security', [$security, 'page']);
$router->post('/admin/security', [$security, 'save']);
$router->get('/admin/auth-logs', fn() => $admin->logs('auth'));
$router->get('/admin/audit-logs', fn() => $admin->logs('audit'));
$router->get('/admin/profile', fn() => $admin->page('profile','Profile'));

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
