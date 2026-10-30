<?php

namespace App\Controllers;

use App\Core\Response;
use App\Core\View;
use App\Models\Cart;
use App\Models\Favorite;
use App\Models\Product;
use App\Models\Review;

final class PublicController
{
    public function __construct(
        private readonly Product $products,
        private readonly Favorite $favorites,
        private readonly Cart $cart,
        private readonly Review $reviews,
    ) {}

    public function landing(): void
    {
        $options = $this->products->filterOptions();
        $customerContext = $this->customerContext();

        View::render('public/home', [
            'pageTitle' => 'Avela',
            'featuredProducts' => $this->products->featured(4),
            'categories' => $options['categories'],
            'concerns' => array_slice($options['concern'] ?? [], 0, 5),
            'storeNav' => $this->storeNavigation($options),
            ...$customerContext,
        ], 'public');
    }

    public function about(): void
    {
        $options = $this->products->filterOptions();
        View::render('public/about', [
            'pageTitle' => 'About',
            'storeNav' => $this->storeNavigation($options),
            ...$this->customerContext(),
        ], 'public');
    }

    public function help(): void
    {
        View::render('public/help', [
            'pageTitle'=>'Help & care', 'privacyEmail'=>\App\Services\PrivacyContact::configured(),
            'storeNav'=>$this->storeNavigation($this->products->filterOptions()), ...$this->customerContext(),
        ], 'public');
    }

    public function privacy(): void
    {
        $options = $this->products->filterOptions();
        View::render('public/privacy', [
            'pageTitle'=>'Privacy & cookies', 'privacyEmail'=>\App\Services\PrivacyContact::configured(),
            'storeNav'=>$this->storeNavigation($options), ...$this->customerContext(),
        ], 'public');
    }

    /** Public editorial content; account and recommendation actions keep their own guards. */
    public function careGuide(string $slug = ''): void
    {
        $articles = ['know-your-texture', 'wash-day', 'condition-with-care', 'a-gentler-finish'];
        if ($slug !== '' && !in_array($slug, $articles, true)) {
            http_response_code(404);
            View::render('errors/404', ['pageTitle' => 'Page not found'], 'plain');
            return;
        }
        View::render($slug === '' ? 'public/care-guide' : 'public/care-' . $slug, [
            'pageTitle' => 'Care guide',
            'storeNav' => $this->storeNavigation($this->products->filterOptions()),
            ...$this->customerContext(),
        ], 'public');
    }

    public function policy(string $kind): void
    {
        $titles=['refund'=>'Refund policy','terms'=>'Terms of service'];
        if(!isset($titles[$kind]))throw new \InvalidArgumentException('Unknown policy.');
        View::render('public/policy',['pageTitle'=>$titles[$kind],'policyKind'=>$kind,
            'privacyEmail'=>\App\Services\PrivacyContact::configured(),
            'storeNav'=>$this->storeNavigation($this->products->filterOptions()),...$this->customerContext()],'public');
    }

    public function shop(): void
    {
        $options = $this->products->filterOptions();
        $search = substr(trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''), 0, 100);
        $filters = $this->normalizeFilters($_GET, $options);
        $sort = $this->normalizeSort(is_string($_GET['sort'] ?? null) ? $_GET['sort'] : 'newest');
        $rawPage = $_GET['page'] ?? '1';
        $page = is_string($rawPage) && preg_match('/^[0-9]{1,8}$/D', $rawPage) ? (int)$rawPage : 1;
        $catalog = $this->products->catalogPage($search, $filters, $sort, $page);
        $products = $catalog['items'];
        $pageLinks = [];
        foreach (['Previous'=>$catalog['page']-1,'Next'=>$catalog['page']+1] as $label=>$number) {
            if ($number >= 1 && $number <= $catalog['pages']) $pageLinks[$label] = $this->shopUrl([...$this->queryParams($search,$filters,$sort),'page'=>$number]);
        }
        $activeFilters = $this->activeFilterChips($search, $filters, $options, $sort);
        $sortLinks = $this->sortLinks($search, $filters, $sort);
        $categoryParams = $this->queryParams($search, $filters, $sort);
        unset($categoryParams['category']);
        $categoryLinks = ['all' => $this->shopUrl($categoryParams)];
        foreach ($options['categories'] as $category) {
            $categoryLinks[(int) $category['id']] = $this->shopUrl([...$categoryParams, 'category' => [(int) $category['id']]]);
        }

        View::render('public/shop', [
            'pageTitle' => 'Shop',
            'products' => $products,
            'categories' => $options['categories'],
            'filterOptions' => $options,
            'filters' => $filters,
            'search' => $search,
            'sort' => $sort,
            'sortLinks' => $sortLinks,
            'categoryLinks' => $categoryLinks,
            'shopQuery' => $this->queryParams('', $filters, $sort),
            'activeFilters' => $activeFilters,
            'filterCount' => $this->filterCount($filters),
            'resultCount' => $catalog['total'],
            'catalogPage' => $catalog['page'], 'catalogPages' => $catalog['pages'], 'pageLinks' => $pageLinks,
            'storeNav' => $this->storeNavigation($options),
            ...$this->customerContext(),
        ], 'public');
    }

    public function product(): void
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            http_response_code(404);
            View::render('errors/404', ['pageTitle' => 'Product not found'], 'plain');
            return;
        }

        $product = $this->products->findActive($id);
        if (!$product) {
            http_response_code(404);
            View::render('errors/404', ['pageTitle' => 'Product not found'], 'plain');
            return;
        }

        $recentIds = array_slice(array_values(array_filter((array) \App\Core\Session::get('recent_products', []), static fn($value) => is_int($value) && $value > 0 && $value !== $id)), 0, 8);
        $recentProducts = [];
        foreach ($recentIds as $recentId) {
            if ($recent = $this->products->findActive($recentId)) $recentProducts[] = $recent;
        }
        \App\Core\Session::put('recent_products', array_slice([$id, ...$recentIds], 0, 8));
        $options = $this->products->filterOptions();
        View::render('public/product', [
            'pageTitle' => $product['name'],
            'product' => $product,
              'productReference' => (new \App\Services\ProductReference())->forProduct($product),
            'recommendations' => $this->products->recommendationsForProduct((int) $product['id']),
            'recentProducts' => $recentProducts,
            'storeNav' => $this->storeNavigation($options),
            ...$this->customerContext(),
        ], 'public');
    }

    public function search(): void
    {
        $query = substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $options = $this->products->filterOptions();
        $data = $this->searchResults($query, 8);

        View::render('public/search', [
            'pageTitle' => $query !== '' ? 'Search' : 'Search Avela',
            'query' => $query,
            'storeNav' => $this->storeNavigation($options),
            ...$data,
            ...$this->customerContext(),
        ], 'public');
    }

    public function searchSuggestions(): void
    {
        $query = substr(trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''), 0, 100);
        $allResultsUrl = isset($_GET['sort'])
            ? $this->shopUrl($this->queryParams($query, $this->normalizeFilters($_GET, $this->products->filterOptions()), $this->normalizeSort(is_string($_GET['sort']) ? $_GET['sort'] : 'newest')))
            : url('/search?q=' . rawurlencode($query));
        if (strlen($query) < 2) {
            Response::json(['ok' => true, 'query' => $query, 'products' => [], 'discover' => [], 'pages' => [], 'allResultsUrl' => $allResultsUrl]);
        }

        $data = $this->searchResults($query, 6);
        $products = array_map(static function (array $product): array {
            $imageUrl = product_image_url($product['image_path'] ?? null);
            return [
                'title' => (string) $product['name'],
                'subtitle' => (string) $product['category_name'] . ' · ₱' . number_format((float) $product['price'], 2),
                'priceLabel' => '₱' . number_format((float) $product['price'], 2),
                'url' => url('/product?id=' . (int) $product['id']),
                'imageUrl' => $imageUrl ?? product_placeholder_url((string) $product['category_name']),
                'imageAlt' => $imageUrl !== null ? (string) $product['name'] : '',
            ];
        }, $data['productResults']);

        Response::json([
            'ok' => true,
            'query' => $query,
            'products' => $products,
            'discover' => $data['discoveryResults'],
            'pages' => $data['pageResults'],
            'allResultsUrl' => $allResultsUrl,
        ]);
    }

    private function searchResults(string $query, int $limit): array
    {
        if ($query === '') {
            return ['productResults' => [], 'discoveryResults' => [], 'pageResults' => []];
        }

        $discovery = array_map(static function (array $item): array {
            if (($item['type'] ?? '') === 'category') {
                $item['url'] = url('/shop?category[]=' . (int) ($item['id'] ?? 0));
            } elseif (($item['type'] ?? '') === 'concern') {
                $item['url'] = url('/shop?concern[]=' . rawurlencode((string) ($item['code'] ?? '')));
            } else {
                $item['url'] = url('/shop');
            }
            return $item;
        }, $this->products->searchDiscovery($query, $limit));

        return [
            'productResults' => $this->products->searchProducts($query, $limit),
            'discoveryResults' => $discovery,
            'pageResults' => $this->pageSearchResults($query),
        ];
    }

    private function pageSearchResults(string $query): array
    {
        $needle = strtolower(trim($query));
        if ($needle === '') return [];

        $pages = [
            ['title' => 'Shop', 'subtitle' => 'Browse the full Avela hair care catalog.', 'url' => url('/shop'), 'keywords' => 'shop products catalog hair care'],
            ['title' => 'Hair Assessment', 'subtitle' => 'Save your hair characteristics and interpret an optional concern description.', 'url' => url('/assessment'), 'keywords' => 'hair assessment profile texture scalp concerns personalized'],
            ['title' => 'About Avela', 'subtitle' => 'Learn how Avela combines e-commerce with optional personalization.', 'url' => url('/about'), 'keywords' => 'about avela store ecommerce personalization'],
            ['title' => 'Care guide', 'subtitle' => 'Thoughtful notes for getting to know your hair.', 'url' => url('/care-guide'), 'keywords' => 'care guide notes articles'],
            ['title' => 'Your texture is a starting point', 'subtitle' => 'Get to know the natural shape of your hair.', 'url' => url('/care-guide/know-your-texture'), 'keywords' => 'care guide texture straight wavy curly coily'],
            ['title' => 'A wash day with a little more clarity', 'subtitle' => 'Start with a simple order.', 'url' => url('/care-guide/wash-day'), 'keywords' => 'care guide shampoo wash cleanse'],
            ['title' => 'Condition with care', 'subtitle' => 'A little attention for your lengths.', 'url' => url('/care-guide/condition-with-care'), 'keywords' => 'care guide conditioner conditioning'],
            ['title' => 'A gentler finish', 'subtitle' => 'Small everyday habits, with a little more care.', 'url' => url('/care-guide/a-gentler-finish'), 'keywords' => 'care guide finish drying everyday'],
        ];

        $current = auth_user();
        if (($current['role'] ?? null) === 'customer') {
            $pages[] = ['title' => 'Favorites', 'subtitle' => 'View the hair care products you saved.', 'url' => url('/favorites'), 'keywords' => 'favorites saved wishlist heart'];
            $pages[] = ['title' => 'My Orders', 'subtitle' => 'View orders placed from your account.', 'url' => url('/orders'), 'keywords' => 'orders purchases history'];
            $pages[] = ['title' => 'Profile', 'subtitle' => 'View your Avela account details.', 'url' => url('/profile'), 'keywords' => 'profile account details'];
        }

        return array_values(array_filter($pages, static function (array $page) use ($needle): bool {
            return str_contains(strtolower($page['title'] . ' ' . $page['subtitle'] . ' ' . $page['keywords']), $needle);
        }));
    }

    private function customerContext(): array
    {
        $current = auth_user();
        if (($current['role'] ?? null) !== 'customer') {
            return ['favoriteIds' => [], 'favoriteCount' => 0, 'cartCount' => 0];
        }

        header('Cache-Control: no-store, private');
        $userId = (int) $current['id'];
        return [
            'favoriteIds' => $this->favorites->idsForUser($userId),
            'favoriteCount' => $this->favorites->countForUser($userId),
            'cartCount' => $this->cart->countForUser($userId),
            ];
    }

    private function storeNavigation(array $options): array
    {
        return [
            'categories' => $options['categories'] ?? [],
            'concerns' => $options['concern'] ?? [],
        ];
    }

    private function normalizeFilters(array $input, array $options): array
    {
        $allowedCategoryIds = array_map('intval', array_column($options['categories'] ?? [], 'id'));
        $categories = array_values(array_intersect(
            $this->normalizeIntList($input['category'] ?? []),
            $allowedCategoryIds
        ));

        $concerns = $this->normalizeCodeList($input['concern'] ?? [], array_column($options['concern'] ?? [], 'code'));
        $textures = $this->normalizeCodeList($input['texture'] ?? [], array_column($options['texture'] ?? [], 'code'));
        $scalps = $this->normalizeCodeList($input['scalp'] ?? [], array_column($options['scalp'] ?? [], 'code'));
        $routineSteps = $this->normalizeCodeList($input['routine_step'] ?? [], ['cleanse', 'condition', 'treat', 'finish']);

        $availability = (string) ($input['availability'] ?? '');
        if (!in_array($availability, ['in_stock', 'out_of_stock'], true)) $availability = '';

        $minPrice = $this->normalizePrice($input['min_price'] ?? null);
        $maxPrice = $this->normalizePrice($input['max_price'] ?? null);
        if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
            [$minPrice, $maxPrice] = [$maxPrice, $minPrice];
        }

        return [
            'categories' => $categories,
            'concerns' => $concerns,
            'textures' => $textures,
            'scalps' => $scalps,
            'routine_steps' => $routineSteps,
            'availability' => $availability,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
        ];
    }

    private function normalizeSort(string $sort): string
    {
        return in_array($sort, ['newest', 'name_asc', 'price_asc', 'price_desc'], true) ? $sort : 'newest';
    }

    private function normalizeIntList(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];
        $result = [];
        foreach ($values as $item) {
            $int = filter_var($item, FILTER_VALIDATE_INT);
            if ($int !== false && $int > 0) $result[] = (int) $int;
        }
        return array_values(array_unique($result));
    }

    private function normalizeCodeList(mixed $value, array $allowed): array
    {
        $values = is_array($value) ? $value : [$value];
        $allowed = array_map('strval', $allowed);
        $result = [];
        foreach ($values as $item) {
            $code = strtolower(trim((string) $item));
            if (in_array($code, $allowed, true)) $result[] = $code;
        }
        return array_values(array_unique($result));
    }

    private function normalizePrice(mixed $value): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) return null;
        $price = round((float) $value, 2);
        return ($price >= 0 && $price <= 999999.99) ? $price : null;
    }

    private function filterCount(array $filters): int
    {
        return count($filters['categories'])
            + count($filters['concerns'])
            + count($filters['textures'])
            + count($filters['scalps'])
            + count($filters['routine_steps'])
            + ($filters['availability'] !== '' ? 1 : 0)
            + ($filters['min_price'] !== null ? 1 : 0)
            + ($filters['max_price'] !== null ? 1 : 0);
    }

    private function activeFilterChips(string $search, array $filters, array $options, string $sort): array
    {
        $chips = [];
        $base = $this->queryParams($search, $filters, $sort);

        if ($search !== '') {
            $params = $base;
            unset($params['q']);
            $chips[] = ['label' => 'Search: ' . $search, 'url' => $this->shopUrl($params)];
        }

        $categoryLabels = [];
        foreach ($options['categories'] ?? [] as $row) $categoryLabels[(int) $row['id']] = (string) $row['name'];
        foreach ($filters['categories'] as $value) {
            $params = $base;
            $params['category'] = array_values(array_diff($filters['categories'], [$value]));
            if ($params['category'] === []) unset($params['category']);
            $chips[] = ['label' => $categoryLabels[$value] ?? 'Category', 'url' => $this->shopUrl($params)];
        }

        foreach ([
            'concerns' => ['param' => 'concern', 'options' => $options['concern'] ?? []],
            'textures' => ['param' => 'texture', 'options' => $options['texture'] ?? []],
            'scalps' => ['param' => 'scalp', 'options' => $options['scalp'] ?? []],
            'routine_steps' => ['param' => 'routine_step', 'options' => $options['routine_step'] ?? []],
        ] as $filterKey => $meta) {
            $labels = [];
            foreach ($meta['options'] as $row) $labels[(string) $row['code']] = (string) $row['display_name'];
            foreach ($filters[$filterKey] as $value) {
                $params = $base;
                $remaining = array_values(array_diff($filters[$filterKey], [$value]));
                if ($remaining === []) unset($params[$meta['param']]); else $params[$meta['param']] = $remaining;
                $chips[] = ['label' => $labels[$value] ?? ucfirst(str_replace('_', ' ', $value)), 'url' => $this->shopUrl($params)];
            }
        }

        if ($filters['availability'] !== '') {
            $params = $base;
            unset($params['availability']);
            $chips[] = [
                'label' => $filters['availability'] === 'in_stock' ? 'In stock' : 'Out of stock',
                'url' => $this->shopUrl($params),
            ];
        }

        if ($filters['min_price'] !== null) {
            $params = $base;
            unset($params['min_price']);
            $chips[] = ['label' => 'Min ₱' . number_format((float) $filters['min_price'], 0), 'url' => $this->shopUrl($params)];
        }
        if ($filters['max_price'] !== null) {
            $params = $base;
            unset($params['max_price']);
            $chips[] = ['label' => 'Max ₱' . number_format((float) $filters['max_price'], 0), 'url' => $this->shopUrl($params)];
        }

        return $chips;
    }

    private function sortLinks(string $search, array $filters, string $activeSort): array
    {
        $labels = [
            'newest' => 'Newest',
            'name_asc' => 'Name A–Z',
            'price_asc' => 'Price: Low to High',
            'price_desc' => 'Price: High to Low',
        ];
        $links = [];
        foreach ($labels as $value => $label) {
            $params = $this->queryParams($search, $filters, $value);
            if ($value === 'newest') unset($params['sort']);
            $links[] = [
                'value' => $value,
                'label' => $label,
                'active' => $activeSort === $value,
                'url' => $this->shopUrl($params),
            ];
        }
        return $links;
    }

    private function queryParams(string $search, array $filters, string $sort): array
    {
        $params = [];
        if ($search !== '') $params['q'] = $search;
        if ($sort !== 'newest') $params['sort'] = $sort;
        if ($filters['categories'] !== []) $params['category'] = $filters['categories'];
        if ($filters['concerns'] !== []) $params['concern'] = $filters['concerns'];
        if ($filters['textures'] !== []) $params['texture'] = $filters['textures'];
        if ($filters['scalps'] !== []) $params['scalp'] = $filters['scalps'];
        if ($filters['routine_steps'] !== []) $params['routine_step'] = $filters['routine_steps'];
        if ($filters['availability'] !== '') $params['availability'] = $filters['availability'];
        if ($filters['min_price'] !== null) $params['min_price'] = $filters['min_price'];
        if ($filters['max_price'] !== null) $params['max_price'] = $filters['max_price'];
        return $params;
    }

    private function shopUrl(array $params): string
    {
        return url('/shop') . ($params !== [] ? '?' . http_build_query($params) : '');
    }
}
