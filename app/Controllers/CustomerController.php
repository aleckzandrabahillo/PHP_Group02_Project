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
        View::render('customer/' . $view, [
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
        $user = AuthGuard::roles(['customer']);
        $userId = (int) $user['id'];
        $cart = $this->cart->contentsForUser($userId);

        if (empty($cart['items'])) {
            Session::flash('warning', 'Your bag is empty.');
            Response::redirect('/cart');
        }

        if (empty($cart['checkout_ready'])) {
            Session::flash('warning', 'Review unavailable items in your bag before checkout.');
            Response::redirect('/cart');
        }

        View::render('customer/checkout', [
            'pageTitle' => 'Checkout',
            'cart' => $cart,
            'profile' => $this->users->profileFor($userId),
        ], 'checkout');
    }

    public function routine(): void
    {
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

    public function toggleFavorite(): void
    {
        $user = AuthGuard::roles(['customer']);
        Csrf::enforce($_POST['_token'] ?? null);

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

                        $this->users->updateProfileImage($userId, $profileImagePath);

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
        Csrf::enforce($_POST['_token'] ?? null);

        $userId = (int) $user['id'];

        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $contactNo = trim((string) ($_POST['contact_no'] ?? ''));
        $deliveryAddress = trim((string) ($_POST['delivery_address'] ?? ''));

        if ($fullName === '' || mb_strlen($fullName) > 100) {
            Session::flash('warning', 'Please enter a valid full name.');
            Response::redirect('/profile');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            Session::flash('warning', 'Please enter a valid email address.');
            Response::redirect('/profile');
        }

        if ($contactNo !== '') {
            if (mb_strlen($contactNo) > 30 || !preg_match('/^[0-9+\-\s().]+$/', $contactNo)) {
                Session::flash('warning', 'Please enter a valid contact number.');
                Response::redirect('/profile');
            }
        }

        if ($deliveryAddress !== '' && mb_strlen($deliveryAddress) > 255) {
            Session::flash('warning', 'Delivery address is too long.');
            Response::redirect('/profile');
        }

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
            Session::flash('warning', 'That email address is already in use.');
            Response::redirect('/profile');
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
            $this->otp->issue($userId, $currentEmail, 'profile_update');
        } catch (\Throwable $e) {
            Session::forget('profile_update_flow');
            Session::flash('warning', 'We could not send the verification code. Please try again.');
            Response::redirect('/profile');
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
        $user = AuthGuard::roles(['customer']);
        Csrf::enforce($_POST['_token'] ?? null);

        $userId = (int) $user['id'];
        $code = preg_replace('/\D+/', '', (string) ($_POST['otp'] ?? ''));

        if (strlen($code) !== 6) {
            Session::put('errors', ['otp' => 'Enter the 6-digit code.']);
            Response::redirect('/profile/verify-otp');
        }

        $flow = Session::get('profile_update_flow');

        if (!is_array($flow) || (int) ($flow['user_id'] ?? 0) !== $userId) {
            Session::flash('warning', 'Your profile update verification session has expired.');
            Response::redirect('/profile');
        }

        $result = $this->otp->verify($userId, 'profile_update', $code);

        if (!$result['ok']) {
            $message = match ($result['reason'] ?? '') {
                'expired' => 'The verification code has expired. Please request a new code.',
                'attempt_limit' => 'Too many incorrect attempts. Please request a new code.',
                'missing' => 'No active verification code was found. Please request a new code.',
                default => 'The verification code is incorrect. Please try again.',
                };

                Session::put('errors', ['otp' => $message]);
                Response::redirect('/profile/verify-otp');
                }
                
                try {
                $this->users->updateCustomerProfile(
                $userId,
                (string) $flow['full_name'],
                (string) $flow['email'],
                (string) $flow['contact_no'],
                $flow['delivery_address'] !== ''
                    ? (string) $flow['delivery_address']
                    : null
            );

            $authUser = Session::get('auth_user');
            if (is_array($authUser)) { 
                $authUser['email'] = (string) $flow['email'];
                Session::put('auth_user', $authUser);
                }

        } catch (\Throwable $e) {
            Session::flash(
                'warning',
                'Your profile could not be updated. Please try again.'
            );
            Response::redirect('/profile');
        }

        Session::forget('profile_update_flow');
        Session::put('errors', []);

        Session::flash('success', 'Your profile has been updated successfully.');
        Response::redirect('/profile');
    }
}