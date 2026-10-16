<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Middleware\AuthGuard;
use App\Models\Cart;
use App\Models\Favorite;
use App\Models\Product;
use App\Models\Routine;
use App\Models\User;
use App\Services\DashboardService;
use InvalidArgumentException;
use App\Services\OtpService;
use App\Core\CheckoutValidator;
use App\Models\Order;
use App\Services\CheckoutService;

final class CustomerController
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly User $users,
        private readonly Product $products,
        private readonly Favorite $favorites,
        private readonly Cart $cart,
        private readonly Routine $routines,
        private readonly OtpService $otp,
        private readonly CheckoutService $checkoutService,
        private readonly Order $orders,
    ) {}  

    public function dashboard(): void
    {
        $user = AuthGuard::roles(['customer']);
        $data = $this->commonData((int) $user['id']);
        View::render('customer/dashboard', [
            'pageTitle' => 'My Avela',
            'stats' => $this->dashboard->customer((int) $user['id']),
            ...$data,
        ]);
    }

    public function page(string $view, string $title): void
    {
        $user = AuthGuard::roles(['customer']);
        header('Cache-Control: no-store, private');
        $extra = [];
        if ($view === 'profile') {
            $mfa = new \App\Services\MfaService($this->users, $this->otp, new \App\Services\LogService());
            $extra['authenticatorEnrolled'] = $mfa->factor((int) $user['id']) !== null;
        }
        View::render('customer/' . $view, [
            ...$extra,
            'pageTitle' => $title,
            ...$this->commonData((int) $user['id']),
        ]);
    }

    public function favorites(): void
    {
        $user = AuthGuard::roles(['customer']);
        $userId = (int) $user['id'];
        View::render('customer/favorites', [
            'pageTitle' => 'Favorites',
            'favoriteProducts' => $this->favorites->productsForUser($userId),
            ...$this->commonData($userId),
        ]);
    }

    public function cart(): void
    {
        $user = AuthGuard::roles(['customer']);
        $userId = (int) $user['id'];
        View::render('customer/cart', [
            'pageTitle' => 'Shopping Bag',
            'cart' => $this->cart->contentsForUser($userId),
            ...$this->commonData($userId),
        ]);
    }

    public function checkout(): void
    {
        header('Cache-Control: no-store, private');
        $user = AuthGuard::roles(['customer']);
        $this->renderCheckout((int) $user['id']);
    }

    public function placeOrder(): void
    {
        header('Cache-Control: no-store, private');
        $user = AuthGuard::roles(['customer']);
        Csrf::enforce(is_string($_POST['_token'] ?? null) ? $_POST['_token'] : null);
        $userId = (int) $user['id'];
        $checked = CheckoutValidator::validate($_POST);
        $key = is_string($_POST['checkout_key'] ?? null) ? $_POST['checkout_key'] : '';
        try {
            // Replays always resolve to this customer's existing order, even after a new checkout.
            if (preg_match('/^[a-f0-9]{64}$/D', $key) && ($id = $this->orders->submitted($userId, $key)) !== null) {
                http_response_code(303);
                Response::redirect('/orders/view?id=' . $id);
            }
            $intent = Session::get('checkout_intent');
            if (!is_array($intent) || ($intent['user_id'] ?? 0) !== $userId
                || !is_string($intent['key'] ?? null) || !hash_equals($intent['key'], $key)
                || (int) ($intent['issued_at'] ?? 0) < time() - 1800) {
                $checked['errors']['form'] = 'Checkout expired or changed. Review this fresh form before submitting again.';
            }
            if ($checked['errors']) {
                http_response_code(422);
                $this->renderCheckout($userId, $checked['values'], $checked['errors']);
                return;
            }
            $id = $this->checkoutService->place($userId, $checked['values'], $key, $intent['signature']);
            Session::flash('success', 'Your order has been received. No online payment was collected.');
            http_response_code(303);
            Response::redirect('/orders/view?id=' . $id);
        } catch (InvalidArgumentException $e) {
            http_response_code(409);
            $this->renderCheckout($userId, $checked['values'], ['form' => $e->getMessage()]);
        } catch (\Throwable) {
            http_response_code(503);
            $this->renderCheckout($userId, $checked['values'], ['form' => 'We could not place your order. Your bag was preserved; please try again later.']);
        }
    }

    public function orders(): void
    {
        header('Cache-Control: no-store, private');
        $user = AuthGuard::roles(['customer']);
        $page = is_string($_GET['page'] ?? null) && preg_match('/^[0-9]{1,8}$/D', $_GET['page']) ? (int) $_GET['page'] : 1;
        View::render('customer/orders', ['pageTitle' => 'My Orders',
            'history' => $this->orders->forCustomer((int) $user['id'], $page),
            ...$this->commonData((int) $user['id'])]);
    }

    public function order(): void
    {
        header('Cache-Control: no-store, private');
        $user = AuthGuard::roles(['customer']);
        $id = is_string($_GET['id'] ?? null) && preg_match('/^[1-9][0-9]{0,17}$/D', $_GET['id']) ? (int) $_GET['id'] : 0;
        $order = $this->orders->owned($id, (int) $user['id']);
        if ($order === null) {
            http_response_code(404);
            View::render('errors/404', ['pageTitle' => 'Order not found'], 'plain');
            return;
        }
        View::render('customer/order', ['pageTitle' => 'Order #' . $order['id'], 'order' => $order,
            ...$this->commonData((int) $user['id'])]);
    }

    private function renderCheckout(int $userId, array $values = [], array $errors = []): void
    {
        try {
            $cart = $this->checkoutService->quote($userId);
        } catch (InvalidArgumentException $e) {
            Session::flash('warning', $e->getMessage());
            Response::redirect('/cart');
        } catch (\Throwable) {
            http_response_code(503);
            View::render('errors/500', ['pageTitle' => 'Checkout unavailable',
                'message' => 'Checkout is temporarily unavailable. Your bag has been preserved.'], 'plain');
            return;
        }
        if (!$cart['items'] || (!$cart['checkout_ready'] && !$errors)) {
            Session::flash('warning', $cart['items'] ? 'Review unavailable items in your bag before checkout.' : 'Your bag is empty.');
            Response::redirect('/cart');
        }
        $profile = $this->users->profileFor($userId);
        if (!$values) $values = ['full_name' => $profile['full_name'] ?? '', 'contact_no' => $profile['contact_no'] ?? '',
            'delivery_address' => $profile['delivery_address'] ?? '', 'payment_method' => 'cod'];
        $intent = Session::get('checkout_intent');
        if (!is_array($intent) || ($intent['user_id'] ?? 0) !== $userId || ($intent['signature'] ?? '') !== $cart['signature']
            || (int) ($intent['issued_at'] ?? 0) < time() - 1800) {
            $intent = ['user_id' => $userId, 'key' => bin2hex(random_bytes(32)), 'signature' => $cart['signature'], 'issued_at' => time()];
            Session::put('checkout_intent', $intent);
        }
        View::render('customer/checkout', ['pageTitle' => 'Checkout', 'cart' => $cart, 'profile' => $profile,
            'values' => $values, 'errors' => $errors, 'checkoutKey' => $intent['key']], 'checkout');
    }

    public function routine(): void
    {
        header('Cache-Control: no-store, private');
        $user = AuthGuard::roles(['customer']);
        $userId = (int) $user['id'];
        View::render('customer/routine', [
            'pageTitle' => 'My Routine',
            'routineState' => $this->routines->stateForUser($userId),
            ...$this->commonData($userId),
        ]);
    }

    public function addToCart(): void
    {
        $user = AuthGuard::roles(['customer']);
        Csrf::enforce($_POST['_token'] ?? null);
        $productId = $this->positiveInt($_POST['product_id'] ?? null);
        $quantity = $this->positiveInt($_POST['quantity'] ?? 1);
        $returnTo = $this->safeReturnPath((string) ($_POST['return_to'] ?? '/cart'), '/cart');

        if ($productId === null || $quantity === null) {
            Session::flash('warning', 'Choose a valid product and quantity.');
            Response::redirect($returnTo);
        }

        try {
            $this->cart->add((int) $user['id'], $productId, $quantity);
            Session::flash('success', 'Added to your bag.');
        } catch (InvalidArgumentException $e) {
            Session::flash('warning', $e->getMessage());
        }

        Response::redirect($returnTo);
    }

    public function updateCart(): void
    {
        $user = AuthGuard::roles(['customer']);
        Csrf::enforce($_POST['_token'] ?? null);
        $userId = (int) $user['id'];
        $productId = $this->positiveInt($_POST['product_id'] ?? null);
        $quantity = $this->positiveInt($_POST['quantity'] ?? null);
        $wantsJson = $this->wantsJson();

        if ($productId === null || $quantity === null) {
            if ($wantsJson) {
                Response::json([
                    'ok' => false,
                    'message' => 'Choose a valid quantity.',
                    ...$this->cartResponseData($userId, $productId),
                ], 422);
            }
            Session::flash('warning', 'Choose a valid quantity.');
            Response::redirect('/cart');
        }

        try {
            $this->cart->update($userId, $productId, $quantity);
            if ($wantsJson) {
                Response::json([
                    'ok' => true,
                    'message' => 'Bag updated.',
                    ...$this->cartResponseData($userId, $productId),
                ]);
            }
            Session::flash('success', 'Bag updated.');
        } catch (InvalidArgumentException $e) {
            if ($wantsJson) {
                Response::json([
                    'ok' => false,
                    'message' => $e->getMessage(),
                    ...$this->cartResponseData($userId, $productId),
                ], 422);
            }
            Session::flash('warning', $e->getMessage());
        }
        Response::redirect('/cart');
    }

    public function removeFromCart(): void
    {
        $user = AuthGuard::roles(['customer']);
        Csrf::enforce($_POST['_token'] ?? null);
        $productId = $this->positiveInt($_POST['product_id'] ?? null);
        if ($productId !== null) {
            $this->cart->remove((int) $user['id'], $productId);
            Session::flash('success', 'Removed from your bag.');
        }
        Response::redirect('/cart');
    }

    public function favoriteState(): void
    {
        $user = AuthGuard::roles(['customer']);
        header('Cache-Control: no-store, private');
        $ids = $this->favorites->idsForUser((int) $user['id']);
        Response::json(['ok' => true, 'ids' => $ids, 'count' => count($ids)]);
    }

    public function toggleFavorite(): void
    {
        $user = AuthGuard::roles(['customer']);
        Csrf::enforce(is_string($_POST['_token'] ?? null) ? $_POST['_token'] : null);

        $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        if ($productId === false || $productId < 1) {
            $this->favoriteResponse(false, 'Product not found.', false, (int) $user['id']);
        }

        try {
            $saved = $this->favorites->toggle((int) $user['id'], (int) $productId);
            $message = $saved ? 'Saved to favorites.' : 'Removed from favorites.';
            $this->favoriteResponse(true, $message, $saved, (int) $user['id']);
        } catch (InvalidArgumentException) {
            $this->favoriteResponse(false, 'That product is no longer available.', false, (int) $user['id']);
        }
    }

    private function commonData(int $userId): array
    {
        $options = $this->products->filterOptions();
        return [
            'profile' => $this->users->profileFor($userId),
            'storeNav' => [
                'categories' => $options['categories'] ?? [],
                'concerns' => $options['concern'] ?? [],
            ],
            'favoriteIds' => $this->favorites->idsForUser($userId),
            'favoriteCount' => $this->favorites->countForUser($userId),
            'cartCount' => $this->cart->countForUser($userId),
        ];
    }

    private function favoriteResponse(bool $ok, string $message, bool $saved, int $userId): never
    {
        $count = $this->favorites->countForUser($userId);
        $acceptsJson = str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
        if ($acceptsJson) {
            Response::json([
                'ok' => $ok,
                'favorite' => $saved,
                'count' => $count,
                'message' => $message,
            ], $ok ? 200 : 422);
        }

        Session::flash($ok ? 'success' : 'warning', $message);
        Response::redirect($this->safeReturnPath((string) ($_POST['return_to'] ?? '/favorites'), '/favorites'));
    }


    private function wantsJson(): bool
    {
        return str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
    }

    private function cartResponseData(int $userId, ?int $productId): array
    {
        $cart = $this->cart->contentsForUser($userId);
        $item = null;
        if ($productId !== null) {
            foreach ($cart['items'] as $candidate) {
                if ((int) $candidate['product_id'] === $productId) {
                    $item = [
                        'productId' => (int) $candidate['product_id'],
                        'quantity' => (int) $candidate['quantity'],
                        'lineTotal' => (float) $candidate['line_total'],
                    ];
                    break;
                }
            }
        }

        return [
            'item' => $item,
            'itemCount' => (int) $cart['item_count'],
            'subtotal' => (float) $cart['subtotal'],
            'checkoutReady' => (bool) $cart['checkout_ready'],
        ];
    }

    private function positiveInt(mixed $value): ?int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        return ($int !== false && $int > 0) ? (int) $int : null;
    }

    private function safeReturnPath(string $path, string $fallback): string
    {
        $path = trim($path);
        if ($path === '' || preg_match('/[\r\n]/', $path) || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return $fallback;
        }
        $parts = parse_url($path);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            return $fallback;
        }
        return $path;
    }
    public function updateProfileImage(): void
    {
        $user = AuthGuard::roles(['customer']);
        Csrf::enforce($_POST['_token'] ?? null);
        $userId = (int) $user['id'];
        
        if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('warning', 'Please choose a valid profile picture.');
            Response::redirect('/profile');
            }
            
            $file = $_FILES['profile_image'];
            if ($file['size'] > 2 * 1024 * 1024) {
                Session::flash('warning', 'Profile picture must not exceed 2 MB.');
                Response::redirect('/profile');
                }
                
                if (!is_uploaded_file($file['tmp_name'])) {
                    Session::flash('warning', 'Invalid file upload.');
                    Response::redirect('/profile');
                    }
                    
                    $imageInfo = @getimagesize($file['tmp_name']);
                    
                    if ($imageInfo === false) {
                        
                    Session::flash('warning', 'The uploaded file is not a valid image.');
                    Response::redirect('/profile');
                    }
                    
                    $mime = $imageInfo['mime'] ?? '';
                    $allowedTypes = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        ];
                        
                        if (!isset($allowedTypes[$mime])) {
                            
                        Session::flash('warning', 'Only JPG, PNG, and WebP images are allowed.');
                        Response::redirect('/profile');
                        }
                        
                        $extension = $allowedTypes[$mime];
                        
                        $uploadDirectory = dirname(__DIR__, 2) . '/public/uploads/profile';
                        
                        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true)) {
                        
                        Session::flash('warning', 'Unable to prepare the profile picture folder.');
                        Response::redirect('/profile'); }

                        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
                        $destination = $uploadDirectory . '/' . $filename;

                        if (!move_uploaded_file($file['tmp_name'], $destination)) {
                            
                        Session::flash('warning', 'Unable to save the profile picture.');
                        Response::redirect('/profile');
                        }
                        
                        $profileImagePath = 'uploads/profile/' . $filename;

                        try {
                            $oldImage=$this->users->updateProfileImage($userId, $profileImagePath);
                        } catch (\Throwable) {
                            @unlink($destination);
                            Session::flash('warning','Your profile photograph could not be saved. Please try again.');
                            Response::redirect('/profile');
                        }
                        if(is_string($oldImage)&&preg_match('~^uploads/profile/[a-f0-9]{32}\.(jpg|png|webp)$~D',$oldImage)){
                            $oldFile=dirname(__DIR__,2).'/public/'.$oldImage;
                            if(is_file($oldFile)&&!is_link($oldFile)&&realpath(dirname($oldFile))===realpath($uploadDirectory))@unlink($oldFile);
                        }

                        Session::flash('success', 'Profile picture updated successfully.');
                        Response::redirect('/profile');
}

public function profileOtpForm(): void
    {
        $user = AuthGuard::roles(['customer']);

        $flow = Session::get('profile_update_flow');

        if (!is_array($flow) || (int) ($flow['user_id'] ?? 0) !== (int) $user['id']) {
            Session::flash('warning', 'There is no profile update waiting for verification.');
            Response::redirect('/profile');
        }

        $email = (string) ($flow['current_email'] ?? '');

        if ($email === '') {
            Session::flash('warning', 'The profile update verification session is invalid.');
            Session::forget('profile_update_flow');
            Response::redirect('/profile');
        }

        View::render('customer/profile-verify-otp', [
            'pageTitle' => 'Confirm Profile Update',
            'maskedEmail' => $this->maskEmail($email),
            'purpose' => 'profile_update',
        ], 'auth');
    }

    public function updateProfile(): void
    {
        $user = AuthGuard::roles(['customer']);
        Csrf::enforce(is_string($_POST['_token'] ?? null) ? $_POST['_token'] : null);

        $userId = (int) $user['id'];

        $input = [];
        $errors = [];
        foreach (['full_name', 'email', 'contact_no', 'delivery_address'] as $field) {
            $raw = $_POST[$field] ?? '';
            if (!is_string($raw)) $errors[$field] = 'Enter a valid text value.';
            $input[$field] = is_string($raw) ? trim($raw) : '';
        }
        $fullName = $input['full_name'];
        $email = strtolower($input['email']);
        $contactNo = $input['contact_no'];
        $deliveryAddress = $input['delivery_address'];
        if ($fullName === '' || mb_strlen($fullName) > 100) $errors['full_name'] = 'Enter your full name, up to 100 characters.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) $errors['email'] = 'Enter a valid email address, up to 190 characters.';
        $normalizedContact=\App\Core\Validator::normalizePhilippineMobile($contactNo);
        if ($normalizedContact===null || strlen($contactNo)>25) $errors['contact_no'] = 'Enter a valid Philippine mobile number, such as 09XXXXXXXXX.';
        if (mb_strlen($deliveryAddress) > 255) $errors['delivery_address'] = 'Keep the delivery address within 255 characters.';
        if ($errors) {
            Session::keepOld($input);
            Session::put('errors', $errors);
            Response::redirect('/profile?edit=1');
        }
        $contactNo=$normalizedContact;

        $currentProfile = $this->users->profileFor($userId);

        $currentFullName = trim((string) ($currentProfile['full_name'] ?? ''));
        $currentEmail = strtolower(trim((string) ($currentProfile['email'] ?? '')));
        $currentContactNo = trim((string) ($currentProfile['contact_no'] ?? ''));
        $currentDeliveryAddress = trim((string) ($currentProfile['delivery_address'] ?? ''));

        $hasChanges =
            $fullName !== $currentFullName
            || $email !== $currentEmail
            || $contactNo !== $currentContactNo
            || $deliveryAddress !== $currentDeliveryAddress;

        if (!$hasChanges) {
            Session::flash('warning', 'No profile changes were made.');
            Response::redirect('/profile');
        }

        if ($email !== $currentEmail && $this->users->emailExistsForOtherUser($email, $userId)) {
            Session::keepOld($input);
            Session::put('errors', ['email' => 'That email address is already in use.']);
            Response::redirect('/profile?edit=1');
        }

        Session::put('profile_update_flow', [
            'user_id' => $userId,
            'current_email' => $currentEmail,
            'full_name' => $fullName,
            'email' => $email,
            'contact_no' => $contactNo,
            'delivery_address' => $deliveryAddress,
            'sent_at' => time(),
        ]);

        try {
            $issued = $this->otp->issue($userId, $currentEmail, 'profile_update');
            if (!$issued['issued']) {
                Session::forget('profile_update_flow');
                Session::keepOld($input);
                Session::flash('warning', "Please wait {$issued['retry_after']} seconds before requesting another code.");
                Response::redirect('/profile?edit=1');
            }
        } catch (\Throwable $e) {
            Session::forget('profile_update_flow');
            Session::keepOld($input);
            Session::flash('warning', 'We could not send the verification code. Please try again.');
            Response::redirect('/profile?edit=1');
        }

        Session::flash(
            'success',
            'A verification code was sent to your current email address.'
        );

        Response::redirect('/profile/verify-otp');
    }

        private function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);

        if (count($parts) !== 2) {
            return $email;
        }

        [$local, $domain] = $parts;

        if ($local === '') {
            return $email;
        }

        $visible = substr($local, 0, 1);

        return $visible . str_repeat('*', max(2, strlen($local) - 1)) . '@' . $domain;
    }
    public function verifyProfileOtp(): void
    {
        $user=AuthGuard::roles(['customer']);
        Csrf::enforce($_POST['_token']??null);
        $raw=$_POST['otp']??'';
        $code=is_string($raw)?preg_replace('/\s+/','',$raw):'';
        if(!preg_match('/^[0-9]{6}$/D',$code)){
            Session::put('errors',['otp'=>'Enter the 6-digit code.']);
            Response::redirect('/profile/verify-otp');
        }
        $id=(int)$user['id'];
        $flow=Session::get('profile_update_flow');
        if(!is_array($flow)||(int)($flow['user_id']??0)!==$id){
            Session::flash('warning','Your profile update verification session has expired.');
            Response::redirect('/profile');
        }
        try {
            $result=(new \App\Services\CustomerProfileService($this->otp))->confirm($id,$flow,$code);
        } catch(\Throwable) {
            Session::flash('warning','Your profile could not be saved. No partial change was committed; retry verification before the code expires.');
            Response::redirect('/profile/verify-otp');
        }
        if(!$result['ok']){
            $message=match($result['reason']??''){
                'expired'=>'The verification code expired. Request a new profile update.',
                'attempt_limit'=>'Too many incorrect attempts. Request a new profile update.',
                'account_unavailable'=>'The account or email changed. Start a new profile update.',
                default=>'The verification code is invalid or already used.',
            };
            Session::put('errors',['otp'=>$message]);
            Response::redirect('/profile/verify-otp');
        }
        $current=Session::get('auth_user');
        if(is_array($current)){$current['email']=$flow['email'];Session::put('auth_user',$current);}
        Session::forget('profile_update_flow');Session::put('errors',[]);
        Session::flash('success','Your profile has been updated successfully.');
        Response::redirect('/profile');
    }
}
