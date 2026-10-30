<?php $showStoreFooter=false; require_once dirname(__DIR__).'/partials/handoff_art.php'; ?><section class="shop-banner favorites-banner"><div><p class="eyebrow">KEPT CLOSE</p><h1>Your good finds.</h1></div><div class="favorites-banner-side"><p>A little collection of the things that caught your eye.</p><span><?= (int)$favoritePage['total'] ?> favorites</span></div></section><section class="favorites-listing">
<form class="collection-filters favorites-filters" method="get" action="<?= e(url('/favorites')) ?>" data-auto-collection="favorites" role="search"><label class="field"><span>Search favorites</span><input type="search" name="q" value="<?= e($favoritePage['q']) ?>" maxlength="100" placeholder="Search products"></label><label class="field"><span>Sort by</span><select name="sort"><?php foreach(['saved'=>'Recently saved','name'=>'Name','price_asc'=>'Price: low to high','price_desc'=>'Price: high to low'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $favoritePage['sort']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><noscript><button type="submit" class="btn btn-secondary">Search</button></noscript></form>
<div data-collection-view="favorites">
<p class="muted small" data-favorite-result-count data-matching-total="<?= (int)$favoritePage['total'] ?>"><?= count($favoriteProducts) ?> shown · <?= (int)$favoritePage['total'] ?> matching favorites</p>
<?php if (!empty($favoriteProducts)): ?>
  <div class="shop-grid favorite-grid" data-continuous-list="favorites" data-stream-page="<?= (int)$favoritePage['page'] ?>" data-next-url="<?= e($favoritePage['page']<$favoritePage['pages']?url('/favorites?'.http_build_query(['page'=>$favoritePage['page']+1,'q'=>$favoritePage['q'],'sort'=>$favoritePage['sort']])):'') ?>">
    <?php foreach ($favoriteProducts as $product): ?>
      <?php $showExcerpt = true; require dirname(__DIR__) . '/partials/product_card.php'; ?>
    <?php endforeach; ?>
  </div>
<?php else: ?>
<div class="commerce-empty favorites-empty"><?= handoff_icon('heart') ?><p class="eyebrow">A LITTLE SPACE FOR WHAT YOU LOVE</p><h2><?= $favoritePage['q']!==''?'No matching finds.':'Good finds,<br>kept close.' ?></h2><p><?= $favoritePage['q']!==''?'Try another search or clear your filters.':'Tap the heart on anything that catches your eye. Your favorites will be waiting here.' ?></p><a class="button primary" href="<?= e(url('/shop')) ?>">Explore the collection </a></div>
<?php endif; ?>

<?php $moreUrl=$favoritePage['page']<$favoritePage['pages']?url('/favorites?'.http_build_query(['page'=>$favoritePage['page']+1,'q'=>$favoritePage['q'],'sort'=>$favoritePage['sort']])):'';require dirname(__DIR__).'/partials/continuous_status.php'; ?></div>
</section>
