<?php

namespace App\Controllers;

use App\Core\View;
use App\Middleware\AuthGuard;
use App\Models\Product;
use App\Models\User;
use App\Services\DashboardService;

final class CatalogController
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly User $users,
        private readonly Product $products
    ) {}

    public function dashboard(): void
    {
        $user = AuthGuard::roles(['catalog_manager','admin']);
        View::render('catalog/dashboard', [
            'pageTitle'=>'Catalog Dashboard',
            'stats'=>$this->dashboard->catalog(),
            'profile'=>$this->users->profileFor((int)$user['id'])
        ]);
    }

    public function page(string $view, string $title): void
    {
        $user = AuthGuard::roles(['catalog_manager','admin']);
        $data = ['pageTitle'=>$title, 'profile'=>$this->users->profileFor((int)$user['id'])];
        if ($view === 'products' || $view === 'inventory') {
            $data['products'] = $this->products->catalog();
        }
        if ($view === 'categories') {
            $data['categories'] = $this->products->categoryRows();
        }
        View::render('catalog/' . $view, $data);
    }
}
