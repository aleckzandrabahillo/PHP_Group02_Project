<section class="page-head"><div><h1>Favorites</h1></div></section>
<form class="collection-filters" method="get" action="<?= e(url('/favorites')) ?>" data-auto-collection="favorites" role="search"><label class="field"><span>Search favorites</span><input type="search" name="q" value="<?= e($favoritePage['q']) ?>" maxlength="100" placeholder="Search products"></label><label class="field"><span>Sort by</span><select name="sort"><?php foreach(['saved'=>'Recently saved','name'=>'Name','price_asc'=>'Price: low to high','price_desc'=>'Price: high to low'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $favoritePage['sort']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><noscript><button type="submit" class="btn btn-secondary">Search</button></noscript></form>
<div data-collection-view="favorites">
<p class="muted small" data-favorite-result-count data-matching-total="<?= (int)$favoritePage['total'] ?>"><?= count($favoriteProducts) ?> shown · <?= (int)$favoritePage['total'] ?> matching favorites</p>
<?php if (!empty($favoriteProducts)): ?>
  <div class="product-grid catalog-grid favorite-grid" data-continuous-list="favorites" data-stream-page="<?= (int)$favoritePage['page'] ?>" data-next-url="<?= e($favoritePage['page']<$favoritePage['pages']?url('/favorites?'.http_build_query(['page'=>$favoritePage['page']+1,'q'=>$favoritePage['q'],'sort'=>$favoritePage['sort']])):'') ?>">
    <?php foreach ($favoriteProducts as $product): ?>
      <?php $showExcerpt = false; require dirname(__DIR__) . '/partials/product_card.php'; ?>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <?php
  $emptyTitle = $favoritePage['q']!==''?'No matching favorites':'No favorites yet';
  $emptyMessage = 'Save products you want to compare or come back to later.';
  $emptyActionLabel = 'Browse products';
  $emptyActionUrl = url('/shop');
  require dirname(__DIR__) . '/partials/empty_state.php';
  ?>
<?php endif; ?>

<?php $moreUrl=$favoritePage['page']<$favoritePage['pages']?url('/favorites?'.http_build_query(['page'=>$favoritePage['page']+1,'q'=>$favoritePage['q'],'sort'=>$favoritePage['sort']])):'';require dirname(__DIR__).'/partials/continuous_status.php'; ?></div>