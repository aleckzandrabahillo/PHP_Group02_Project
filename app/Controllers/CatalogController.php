<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Middleware\AuthGuard;
use App\Models\Product;
use App\Models\User;
use App\Services\DashboardService;
use InvalidArgumentException;
use PDOException;

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
        View::render('catalog/' . $view, $data);
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
