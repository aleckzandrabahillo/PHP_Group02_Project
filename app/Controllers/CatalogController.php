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
            'activity'=>$this->products->recentCatalogActivity(),
            'profile'=>$this->users->profileFor((int)$user['id'])
        ]);
    }

    public function page(string $view, string $title): void
    {
        $user = AuthGuard::roles(['catalog_manager','admin']);
        $data = ['pageTitle'=>$title, 'profile'=>$this->users->profileFor((int)$user['id'])];
        if ($view === 'products' || $view === 'inventory') {
            $data['products'] = $this->products->managerRows();
        }
        if ($view === 'products') {
            $data['categories'] = $this->products->managerCategories();
            $data['tags'] = $this->products->managerTags();
            $data['errors'] = [];
            $data['showForm'] = false;
            $data['editId'] = null;
            $data['form'] = $this->emptyProductForm();
            if (($_GET['form'] ?? '') === 'add') $data['showForm'] = true;
            if (isset($_GET['edit'])) {
                $id = $this->positiveId($_GET['edit']);
                $row = $id ? $this->products->managerProductById($id) : null;
                if ($row) { $data['showForm'] = true; $data['editId'] = $id; $data['form'] = $row; }
                else { Session::flash('error', 'Product not found.'); }
            }
        }
        if ($view === 'categories') {
            $data['categories'] = $this->products->categoryRows();
        }
        View::render('catalog/' . $view, $data);
    }



    private function emptyProductForm(): array
    {
        return ['category_id'=>'','sku'=>'','name'=>'','description'=>'','price'=>'','stock_qty'=>'0','status'=>'active','routine_step'=>'','tag_ids'=>['concern'=>[],'texture'=>[],'scalp'=>[],'condition'=>[]]];
    }

    private function positiveId(mixed $value): ?int
    {
        if (!is_string($value) && !is_int($value)) return null;
        $id = filter_var((string)$value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        return $id === false ? null : (int)$id;
    }

    public function manageProduct(): void
    {
        $user = AuthGuard::roles(['catalog_manager','admin']);
        Csrf::enforce(is_string($_POST['_token'] ?? null) ? $_POST['_token'] : null);
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        $id = $this->positiveId($_POST['id'] ?? null);
        if (!in_array($action, ['create','update','deactivate','activate','delete'], true) || ($action !== 'create' && $id === null)) {
            Session::flash('error','Invalid product action.');
            Response::redirect('/catalog/products');
        }
        if ($action === 'delete') {
            try { $this->products->deleteManagedProduct((int)$id,(int)$user['id']); Session::flash('success','Product deleted.'); }
            catch (\Throwable $e) { Session::flash('error',$e instanceof InvalidArgumentException ? $e->getMessage() : 'Product could not be deleted.'); }
            Response::redirect('/catalog/products');
        }
        if (in_array($action,['deactivate','activate'],true)) {
            try {
                $row = $this->products->managerProductById((int)$id);
                if (!$row) throw new InvalidArgumentException('Product not found.');
                $data = $row;
                $data['status'] = $action === 'activate' ? 'active' : 'inactive';
                $this->products->saveManagedProduct($id,$data,$row['tag_ids'] ?? [],null,(int)$user['id']);
                Session::flash('success',$action === 'activate' ? 'Product activated.' : 'Product deactivated.');
            } catch (\Throwable $e) { Session::flash('error','Could not update product status.'); }
            Response::redirect('/catalog/products');
        }

        $form = $this->emptyProductForm();
        $errors = [];
        foreach (['category_id','sku','name','description','price','stock_qty','status','routine_step'] as $field) {
            $v = $_POST[$field] ?? '';
            $form[$field] = is_string($v) ? trim($v) : '';
        }
        $form['tag_ids'] = [];
        foreach (['concern','texture','scalp','condition'] as $type) {
            $values = $_POST['tags'][$type] ?? [];
            $form['tag_ids'][$type] = is_array($values) ? array_values(array_filter($values,'is_string')) : [];
        }
        $categoryId = $this->positiveId($form['category_id']);
        if (!$categoryId) $errors['category_id']='Choose a category.';
        else {
            $cat = $this->products->categoryById($categoryId);
            if (!$cat || $cat['status'] !== 'active') $errors['category_id']='Choose an active category.';
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,49}$/',$form['sku'])) $errors['sku']='Use 2–50 letters, numbers, dots, underscores, or hyphens for the SKU.';
        if ($form['name']==='' || mb_strlen($form['name'])>160) $errors['name']='Product name is required (maximum 160 characters).';
        if (mb_strlen($form['description'])>5000) $errors['description']='Description must be 5,000 characters or fewer.';
        if (!is_numeric($form['price']) || (float)$form['price']<0 || !preg_match('/^\\d{1,8}(?:\\.\\d{1,2})?$/',$form['price'])) $errors['price']='Enter a valid non-negative price with up to 2 decimal places.';
        if (!in_array($form['status'],['active','inactive'],true)) $errors['status']='Choose a valid status.';
        if (!in_array($form['routine_step'],['','cleanse','condition','treat','finish'],true)) $errors['routine_step']='Choose a valid routine step.';
        if ($action==='create' && filter_var($form['stock_qty'],FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>2147483647]])===false) $errors['stock_qty']='Stock must be a whole number of 0 or greater.';
        if ($this->products->skuExists($form['sku'],$action==='update'?$id:null)) $errors['sku']='That SKU is already in use.';
        $knownTags = [];
        foreach ($this->products->managerTags() as $tag) $knownTags[$tag['tag_type']][]=$tag['code'];
        foreach ($form['tag_ids'] as $type=>$codes) {
            foreach ($codes as $code) if (!in_array($code,$knownTags[$type] ?? [],true)) $errors['tags']='One or more selected tags are invalid.';
        }
        $uploadedPath = null;
        $file = $_FILES['image'] ?? null;
        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) $errors['image']='Image upload failed. Please try again.';
            elseif (($file['size'] ?? 0) > 3*1024*1024) $errors['image']='Image must be 3 MB or smaller.';
            else {
                $tmp = (string)($file['tmp_name'] ?? '');
                $mime = $tmp !== '' && is_uploaded_file($tmp) ? (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) : false;
                $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                if (!$mime || !isset($extensions[$mime]) || @getimagesize($tmp) === false) $errors['image']='Upload a valid JPG, PNG, or WebP image.';
                else {
                    $dir = dirname(__DIR__,2).'/public/uploads/products';
                    if (!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) $errors['image']='Upload folder could not be created.';
                    else {
                        $filename = bin2hex(random_bytes(16)).'.'.$extensions[$mime];
                        if (!move_uploaded_file($tmp,$dir.'/'.$filename)) $errors['image']='Image could not be saved.';
                        else $uploadedPath='uploads/products/'.$filename;
                    }
                }
            }
        }
        if (!$errors) {
            try {
                $data = ['category_id'=>$categoryId,'sku'=>$form['sku'],'name'=>$form['name'],'description'=>$form['description'],
                    'price'=>number_format((float)$form['price'],2,'.',''),'stock_qty'=>(int)$form['stock_qty'],'status'=>$form['status'],'routine_step'=>$form['routine_step']];
                $this->products->saveManagedProduct($action==='update'?$id:null,$data,$form['tag_ids'],$uploadedPath,(int)$user['id']);
                Session::flash('success',$action==='create'?'Product created.':'Product updated.');
                Response::redirect('/catalog/products');
            } catch (PDOException $e) {
                if ((int)($e->errorInfo[1]??0)===1062) $errors['sku']='That SKU is already in use.';
                else $errors['form']='The product could not be saved. Check the form and try again.';
            } catch (\Throwable $e) { $errors['form']='The product could not be saved. Check the form and try again.'; }
        }
        http_response_code(422);
        View::render('catalog/products',['pageTitle'=>'Products','profile'=>$this->users->profileFor((int)$user['id']),
            'products'=>$this->products->managerRows(),'categories'=>$this->products->managerCategories(),'tags'=>$this->products->managerTags(),
            'errors'=>$errors,'showForm'=>true,'editId'=>$action==='update'?$id:null,'form'=>$form]);
    }

    public function manageStock(): void
    {
        $user = AuthGuard::roles(['catalog_manager','admin']);
        Csrf::enforce(is_string($_POST['_token'] ?? null) ? $_POST['_token'] : null);
        $id=$this->positiveId($_POST['id'] ?? null);
        $qty=$_POST['stock_qty'] ?? null;
        $reason=is_string($_POST['reason'] ?? null) ? trim($_POST['reason']) : '';
        if (!$id || !is_string($qty) || filter_var($qty,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>2147483647]])===false || $reason==='' || mb_strlen($reason)>500) {
            Session::flash('error','Enter a valid stock quantity and a reason (up to 500 characters).');
            Response::redirect('/catalog/inventory');
        }
        try {
            $this->products->updateManagedStock($id,(int)$qty,(int)$user['id'],$reason);
            Session::flash('success','Stock updated and audit event recorded.');
        } catch (\Throwable $e) { Session::flash('error','Stock could not be updated. Refresh and try again.'); }
        Response::redirect('/catalog/inventory');
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
