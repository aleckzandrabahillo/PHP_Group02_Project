<?php
$currentUserForFavorite = auth_user();
$currentRoleForFavorite = $currentUserForFavorite['role'] ?? null;
$favoriteProductId = (int) ($favoriteProductId ?? ($product['id'] ?? 0));
$isFavorite = in_array($favoriteProductId, $favoriteIds ?? [], true);
$favoriteLabel = $isFavorite ? 'Remove from favorites' : 'Add to favorites';
$favoriteProductName = (string) ($favoriteProductName ?? ($product['name'] ?? 'this product'));
?>
<?php if ($currentRoleForFavorite === 'customer'): ?>
  <form class="favorite-form" method="post" action="<?= e(url('/favorites/toggle')) ?>" data-favorite-form data-product-id="<?= $favoriteProductId ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="product_id" value="<?= $favoriteProductId ?>">
    <input type="hidden" name="return_to" value="<?= e(current_relative_uri()) ?>">
    <button class="favorite-button <?= $isFavorite ? 'is-favorite' : '' ?>" type="submit" aria-label="<?= e($favoriteLabel) ?>" title="<?= e($favoriteLabel) ?>" aria-pressed="<?= $isFavorite ? 'true' : 'false' ?>">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/></svg>
    </button>
  </form>
<?php elseif ($currentRoleForFavorite === null): ?>
  <a class="favorite-button" href="<?= e(url('/login')) ?>" aria-label="Sign in to save <?= e($favoriteProductName) ?>" title="Sign in to save">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/></svg>
  </a>
<?php endif; ?>
<?php unset($currentUserForFavorite, $currentRoleForFavorite, $favoriteProductId, $isFavorite, $favoriteLabel, $favoriteProductName); ?>
