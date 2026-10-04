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

final class CustomerController
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly User $users,
        private readonly Product $products,
        private readonly Favorite $favorites,
        private readonly Cart $cart,
        private readonly Routine $routines,
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
}
