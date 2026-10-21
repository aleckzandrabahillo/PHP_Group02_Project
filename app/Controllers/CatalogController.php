<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\ProductValidator;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Middleware\AuthGuard;
use App\Models\Product;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\ProductImageService;
use InvalidArgumentException;
use PDOException;

final class CatalogController
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly User $users,
        private readonly Product $products,
        private readonly ProductImageService $images = new ProductImageService()
    ) {}

    public function dashboard(): void
    {
        $user = AuthGuard::roles(['catalog_manager','admin']);
        header('Cache-Control: no-store, private');
        try{$days=\App\Services\DashboardAnalytics::days($_GET['days']??'30');}
        catch(\InvalidArgumentException){http_response_code(422);$days=30;\App\Core\Session::flash('warning','Choose a valid reporting period.');}
        $analytics=(new \App\Services\DashboardAnalytics(\App\Core\Database::connection()))->report($days);
        View::render('catalog/dashboard', [
            'analytics'=>$analytics,'pageTitle'=>'Catalog Dashboard',
            'stats'=>$this->dashboard->catalog(),
            'activity'=>$this->products->recentCatalogActivity(),
            'profile'=>$this->users->profileFor((int)$user['id'])
        ]);
    }

    public function page(string $view, string $title): void
    {
        if ($view === 'products') { $this->products(); return; }
        if ($view === 'inventory') { $this->inventory(); return; }
        $user = AuthGuard::roles(['catalog_manager','admin']);
        $data = ['pageTitle'=>$title, 'profile'=>$this->users->profileFor((int)$user['id'])];
        View::render('catalog/' . $view, $data);
    }

    public function products(): void
    {
        $user = AuthGuard::roles(['catalog_manager', 'admin']);
        $current = null;
        $errors = [];
        $form = ProductValidator::defaults();
        $showForm = ($_GET['form'] ?? '') === 'add';
        if (isset($_GET['edit'])) {
            $id = $this->categoryId($_GET['edit']);
            $current = $id === null ? null : $this->products->managementFind($id);
            if ($current === null) {
                http_response_code(404);
                $errors['form'] = 'That product no longer exists. Choose a product from the list.';
                $showForm = false;
            } else {
                foreach ($form as $key => $default) $form[$key] = $current[$key] ?? $default;
                $form['expected_stock'] = (string) $current['stock_qty'];
                $showForm = true;
            }
        }
        $this->renderProducts($user, $form, $errors, $current, $showForm);
    }

    public function manageProduct(): void
    {
        $user = AuthGuard::roles(['catalog_manager', 'admin']);
        Csrf::enforce(is_string($_POST['_token'] ?? null) ? $_POST['_token'] : null);
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        $id = $this->categoryId($_POST['id'] ?? null);
        $saving = in_array($action, ['create', 'update'], true);
        $errors = [];
        $form = ProductValidator::defaults();
        $current = $action === 'update' && $id !== null ? $this->products->managementFind($id) : null;
        if (!in_array($action, ['create', 'update', 'archive', 'reactivate', 'delete'], true)) {
            $errors['form'] = 'Choose a valid product action.';
        } elseif ($action !== 'create' && ($id === null || ($action === 'update' && $current === null))) {
            $errors['form'] = 'Choose an existing product from the list.';
        }
        if ($saving) {
            $validated = ProductValidator::validate($_POST, $this->products->categoryRows(), $this->products->suitabilityTags(), $current);
            $form = $validated['values'];
            $errors += $validated['errors'];
        }
        $newImage = null;
        $oldImage = null;
        $uploading = false;
        if (!$errors) {
            try {
                if ($saving && $this->products->skuExists($form['sku'], $action === 'update' ? $id : null)) {
                    $errors['sku'] = 'That SKU is already used by another product, including inactive products.';
                } elseif ($saving) {
                    $uploading = true;
                    $newImage = $this->images->store($_FILES['image'] ?? null);
                    $uploading = false;
                    $saved = $this->products->saveProduct($action === 'update' ? $id : null, $form, $newImage, (int) $user['id']);
                    $oldImage = $saved['old_image'];
                } elseif ($action === 'reactivate') {
                    $this->products->reactivateProduct($id, (int) $user['id']);
                } else {
                    $oldImage = $this->products->removeProduct($id, (int) $user['id'], $action === 'archive');
                }
            } catch (InvalidArgumentException $e) {
                $errors[$uploading ? 'image' : 'form'] = $e->getMessage();
            } catch (PDOException $e) {
                $code = (int) ($e->errorInfo[1] ?? 0);
                if ($saving && $code === 1062) {
                    $errors['sku'] = 'That SKU is already used by another product, including inactive products.';
                } elseif ($action === 'delete' && $code === 1451) {
                    $errors['form'] = 'This product has related records and cannot be deleted. Archive it instead.';
                } else {
                    $errors['form'] = 'The product change could not be saved. Please try again.';
                }
            } catch (\Throwable $e) {
                $errors[$uploading ? 'image' : 'form'] = $uploading
                    ? 'The image could not be saved. Choose the file again or try later.'
                    : 'The product change could not be saved. Please try again.';
            }
            if (!$errors) {
                // File retirement is post-commit; failure must not falsely report a failed DB save.
                if ($oldImage !== null && ($action === 'delete' || ($newImage !== null && $newImage !== $oldImage))) {
                    $this->retireProductImage($oldImage);
                }
                $messages = ['create' => 'Product created.', 'update' => 'Product updated.', 'archive' => 'Product archived.', 'reactivate'=>'Product reactivated.', 'delete' => 'Unused product deleted.'];
                Session::flash('success', $messages[$action]);
                Response::redirect('/catalog/products');
            }
        }
        if ($newImage !== null && !$this->images->discard($newImage)) {
            error_log('Avela failed to clean up an uncommitted product image.');
        }
        if (isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $errors['image'] ??= 'Choose the image again after correcting the form.';
        }
        http_response_code(422);
        $this->renderProducts($user, $form, $errors, $current, $saving && ($action === 'create' || $current !== null));
    }

    public function inventory(): void
    {
        $user = AuthGuard::roles(['catalog_manager', 'admin']);
        $current = null;
        $errors = [];
        if (isset($_GET['stock'])) {
            $id = $this->categoryId($_GET['stock']);
            $current = $id === null ? null : $this->products->managementFind($id);
            if ($current === null) {
                http_response_code(404);
                $errors['form'] = 'That product no longer exists. Choose a product from the list.';
            }
        }
        $this->renderInventory($user, $current, ['mode' => 'add', 'stock_qty' => '', 'expected_stock' => (string) ($current['stock_qty'] ?? ''), 'reason'=>''], $errors);
    }

    public function updateStock(): void
    {
        $user = AuthGuard::roles(['catalog_manager', 'admin']);
        Csrf::enforce(is_string($_POST['_token'] ?? null) ? $_POST['_token'] : null);
        $id = $this->categoryId($_POST['id'] ?? null);
        $current = $id === null ? null : $this->products->managementFind($id);
        $form = [];
        foreach (['mode', 'stock_qty', 'expected_stock','reason'] as $key) $form[$key] = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
        $quantity = ProductValidator::quantity($form['stock_qty']);
        $expected = ProductValidator::quantity($form['expected_stock']);
        $errors = [];
        if ($current === null) $errors['form'] = 'Choose an existing product from the list.';
        if (!in_array($form['mode'], ['add', 'set'], true)) $errors['mode'] = 'Choose Restock or Set quantity.';
        if ($quantity === null || ($form['mode'] === 'add' && $quantity === 0)) {
            $errors['stock_qty'] = 'Enter a whole number up to 4,294,967,295; restocking requires at least 1.';
        }
        if ($expected === null) $errors['form'] = 'Reload the stock form before saving changes.';
        if (ProductValidator::stockReason($form['reason']) === null) {
            $errors['reason'] = 'Enter a single-line reason of 1–200 characters.';
        }
        if (!$errors) {
            try {
                $this->products->changeStock($id, $quantity, $form['mode'] === 'add', $expected, (int) $user['id'], $form['reason']);
                Session::flash('success', $form['mode'] === 'add' ? 'Product restocked.' : 'Stock quantity updated.');
                Response::redirect('/catalog/inventory');
            } catch (InvalidArgumentException $e) {
                $errors['form'] = $e->getMessage();
            } catch (\Throwable $e) {
                $errors['form'] = 'The stock change could not be saved. Please try again.';
            }
        }
        http_response_code(422);
        $this->renderInventory($user, $current, $form, $errors);
    }

    private function retireProductImage(string $path): void
    {
        try {
            if ($this->products->imageIsReferenced($path) || $this->images->discard($path)) return;
        } catch (\Throwable $e) {
            // Preserve the committed change and report cleanup separately, without exposing paths.
        }
        Session::flash('warning', 'The product was saved, but an old image could not be cleaned up. Please ask staff to review image storage.');
    }

    private function renderProducts(array $user, array $form, array $errors, ?array $current, bool $showForm): void
    {
        View::render('catalog/products', [
            'pageTitle' => 'Products', 'profile' => $this->users->profileFor((int) $user['id']),
            'products' => $this->products->managementRows(), 'categories' => $this->products->categoryRows(),
            'tags' => $this->products->suitabilityTags(), 'form' => $form, 'errors' => $errors,
            'currentProduct' => $current, 'showForm' => $showForm,
        ]);
    }

    private function renderInventory(array $user, ?array $current, array $form, array $errors): void
    {
        View::render('catalog/inventory', [
            'pageTitle' => 'Inventory', 'profile' => $this->users->profileFor((int) $user['id']),
            'products' => $this->products->managementRows(), 'currentProduct' => $current, 'form' => $form, 'errors' => $errors,
        ]);
    }

    public function categories(): void
    {
        $user = AuthGuard::roles(['catalog_manager', 'admin']);
        $form = ['name' => '', 'description' => '', 'status' => 'active'];
        $errors = [];
        $editId = null;
        $showForm = ($_GET['form'] ?? '') === 'add';

        if (isset($_GET['edit'])) {
            $editId = $this->categoryId($_GET['edit']);
            $category = $editId === null ? null : $this->products->categoryById($editId);
            if ($category === null) {
                http_response_code(404);
                $errors['form'] = 'That category no longer exists. Choose a category from the list.';
                $editId = null;
            } else {
                $form = $category;
                $showForm = true;
            }
        }

        $this->renderCategories($user, $form, $errors, $editId, $showForm);
    }

    public function manageCategory(): void
    {
        $user = AuthGuard::roles(['catalog_manager', 'admin']);
        Csrf::enforce(is_string($_POST['_token'] ?? null) ? $_POST['_token'] : null);

        // Collection: only the expected POST fields participate in a category operation.
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        $id = $this->categoryId($_POST['id'] ?? null);
        $saving = in_array($action, ['create', 'update'], true);
        $form = ['name' => '', 'description' => '', 'status' => 'active'];
        $errors = [];
        if (!in_array($action, ['create', 'update', 'archive', 'delete'], true)) {
            $errors['form'] = 'Choose a valid category action.';
        } elseif ($action !== 'create' && $id === null) {
            $errors['form'] = 'Choose a valid category from the list.';
        }

        if ($saving) {
            foreach (array_keys($form) as $field) {
                $value = $_POST[$field] ?? '';
                if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
                    $errors[$field] = 'Enter a valid text value.';
                    $form[$field] = '';
                } else {
                    $form[$field] = trim($value, " \t\n\r");
                }
            }
            // Validation matches categories.name (100), description (500), and its status enum.
            if (!isset($errors['name'])) {
                if (preg_match('/^\s*$/u', $form['name'])) {
                    $errors['name'] = 'Enter a category name.';
                } elseif (mb_strlen($form['name'], 'UTF-8') > 100) {
                    $errors['name'] = 'Category names must be 100 characters or fewer.';
                } elseif (preg_match('/[\x00-\x1F\x7F]/u', $form['name'])) {
                    $errors['name'] = 'Use a category name without control characters.';
                }
            }
            if (!isset($errors['description']) && mb_strlen($form['description'], 'UTF-8') > 500) {
                $errors['description'] = 'Descriptions must be 500 characters or fewer.';
            } elseif (!isset($errors['description']) && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $form['description'])) {
                $errors['description'] = 'Use a description without control characters.';
            }
            if (!in_array($form['status'], ['active', 'inactive'], true)) {
                $errors['status'] = 'Choose Active or Inactive.';
            }
        }

        if (!$errors) {
            try {
                if ($saving && $this->products->categoryNameExists($form['name'], $action === 'update' ? $id : null)) {
                    $errors['name'] = 'A category with that name already exists, including inactive categories.';
                } elseif ($saving) {
                    $this->products->saveCategory($action === 'update' ? $id : null, $form, (int) $user['id']);
                    Session::flash('success', $action === 'create' ? 'Category created.' : 'Category updated.');
                } else {
                    $this->products->removeCategory($id, (int) $user['id'], $action === 'archive');
                    Session::flash('success', $action === 'archive' ? 'Category archived. Its products are hidden from public browsing.' : 'Unused category deleted.');
                }
                if (!$errors) {
                    Response::redirect('/catalog/categories');
                }
            } catch (InvalidArgumentException $e) {
                $errors['form'] = $e->getMessage();
            } catch (PDOException $e) {
                // Unique/FK constraints remain the final guard against concurrent requests.
                $code = (int) ($e->errorInfo[1] ?? 0);
                if ($code === 1062 && $saving) {
                    $errors['name'] = 'A category with that name already exists, including inactive categories.';
                } elseif ($code === 1451 && $action === 'delete') {
                    $errors['form'] = 'This category has products and cannot be deleted. Archive it instead.';
                } else {
                    $errors['form'] = 'The category could not be saved. Please try again.';
                }
            } catch (\Throwable $e) {
                $errors['form'] = 'The category could not be saved. Please try again.';
            }
        }

        // Invalid requests never process; render encoded values and field errors for correction.
        http_response_code(422);
        $this->renderCategories($user, $form, $errors, $action === 'update' ? $id : null, $saving && ($action === 'create' || $id !== null));
    }

    private function categoryId(mixed $value): ?int
    {
        if (!is_string($value)) return null;
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : $id;
    }

    private function renderCategories(array $user, array $form, array $errors, ?int $editId, bool $showForm): void
    {
        View::render('catalog/categories', [
            'pageTitle' => 'Categories',
            'profile' => $this->users->profileFor((int) $user['id']),
            'categories' => $this->products->categoryRows(),
            'form' => $form, 'errors' => $errors, 'editId' => $editId, 'showForm' => $showForm,
        ]);
    }
}
