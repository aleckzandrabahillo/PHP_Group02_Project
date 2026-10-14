<section class="page-head">
    <div>
        <span class="eyebrow">CATALOG</span>
        <h1>Inventory</h1>
        <p>Check current stock levels and product availability.</p>
    </div>
</section>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error" role="alert">
        <?= e($errors['form'] ?? 'Please correct the highlighted fields. Stock was not changed.') ?>
    </div>
<?php endif; ?>

<?php if ($currentProduct !== null): ?>
    <section
        id="stock-form"
        class="panel catalog-form"
        aria-labelledby="stock-form-title"
    >
        <h2 id="stock-form-title">
            Update Stock · <?= e($currentProduct['name']) ?>
        </h2>

        <p class="muted">
            Current quantity:
            <?= (int) $currentProduct['stock_qty'] ?>
            ·
            <?= e(\App\Models\Product::availability($currentProduct)) ?>
        </p>

        <form
            class="form-stack"
            method="post"
            action="<?= e(url('/catalog/inventory')) ?>"
        >
            <?= csrf_field() ?>

            <input
                type="hidden"
                name="id"
                value="<?= (int) $currentProduct['id'] ?>"
            >

            <label class="field <?= isset($errors['stock_qty']) ? 'has-error' : '' ?>">
                <span>New stock quantity</span>

                <input
                    type="number"
                    name="stock_qty"
                    value="<?= e($form['stock_qty'] ?? (string) $currentProduct['stock_qty']) ?>"
                    min="0"
                    max="4294967295"
                    step="1"
                    required
                    aria-invalid="<?= isset($errors['stock_qty']) ? 'true' : 'false' ?>"
                    aria-describedby="stock-quantity-help stock-quantity-error"
                >

                <small id="stock-quantity-help" class="help">
                    Enter the new total stock quantity. Use 0 if the product is out of stock.
                </small>

                <small id="stock-quantity-error" class="field-error">
                    <?= e($errors['stock_qty'] ?? '') ?>
                </small>
            </label>

            <label class="field <?= isset($errors['reason']) ? 'has-error' : '' ?>">
                <span>Reason for stock update</span>

                <textarea
                    name="reason"
                    rows="3"
                    maxlength="500"
                    required
                    placeholder="e.g. New supplier delivery, damaged items, stock count correction"
                    aria-invalid="<?= isset($errors['reason']) ? 'true' : 'false' ?>"
                    aria-describedby="stock-reason-help stock-reason-error"
                ><?= e($form['reason'] ?? '') ?></textarea>

                <small id="stock-reason-help" class="help">
                    Explain why the inventory quantity is changing. Maximum 500 characters.
                </small>

                <small id="stock-reason-error" class="field-error">
                    <?= e($errors['reason'] ?? '') ?>
                </small>
            </label>

            <div class="catalog-actions">
                <button class="btn btn-primary" type="submit">
                    Save Stock
                </button>

                <a
                    class="btn btn-secondary"
                    href="<?= e(url('/catalog/inventory')) ?>"
                >
                    Cancel
                </a>
            </div>
        </form>
    </section>
<?php endif; ?>

<div class="table-card catalog-inventory-table">
    <div class="data-table">
        <div class="data-row header">
            <span>Product</span>
            <span>SKU</span>
            <span>Stock</span>
            <span>Availability</span>
            <span>Actions</span>
        </div>

        <?php foreach (($products ?? []) as $product): ?>
            <div class="data-row">
                <span class="inventory-product-info">
                    <strong><?= e($product['name']) ?></strong>

                    <?php if (isset($product['status'])): ?>
                        <small
                            class="inventory-product-status <?= $product['status'] === 'active' ? 'is-active' : 'is-inactive' ?>"
                        >
                            <?= e(ucfirst($product['status'])) ?>
                        </small>
                    <?php endif; ?>
                </span>

                <span><?= e($product['sku']) ?></span>

                <span><?= (int) $product['stock_qty'] ?></span>

                <span>
                    <?= e(\App\Models\Product::availability($product)) ?>
                </span>

                <span>
                    <a
                        class="btn btn-secondary btn-sm"
                        href="<?= e(url('/catalog/inventory?stock=' . (int) $product['id'] . '#stock-form')) ?>"
                    >
                        Update stock
                    </a>
                </span>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($products)): ?>
        <div class="table-empty">
            <h3>No inventory yet</h3>
            <p>Stock information will appear here after products are added.</p>
        </div>
    <?php endif; ?>
</div>
