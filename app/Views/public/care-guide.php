<?php require_once dirname(__DIR__).'/partials/handoff_art.php'; ?>
<?php $notes=[
 ['know-your-texture','Know your hair','Your texture is a starting point.','Get to know the natural shape of your hair.','home-portrait.webp'],
 ['wash-day','The essentials','A wash day with a little more clarity.','Start with a simple order. Make room for what your hair needs.','home-care.webp'],
 ['condition-with-care','The essentials','A little care for your lengths.','A considered approach to conditioning your lengths.','approach-detail.webp'],
 ['a-gentler-finish','Everyday care','The art of a gentler finish.','Small everyday habits, with a little more care.','approach-textures.webp'],
]; ?>
<main id="main-content" tabindex="-1" class="v2-reference">
 <div class="page-banner"><p class="eyebrow">THE AVELA CARE NOTES</p><h1>A little more<br>knowing.</h1><div class="banner-bottom"><p>Thoughtful notes for getting to know your hair. Start anywhere. Take what helps.</p><label class="guide-search-label"><span class="sr-only">Search care notes</span><input id="guide-search" type="search" placeholder="Find a care note" data-care-search></label></div></div>
 <section class="guide-listing" aria-label="Care notes"><div class="guide-filter-bar"><div class="guide-filters" aria-label="Filter care notes"><?php foreach(['All notes','Know your hair','The essentials','Everyday care'] as $index=>$category): ?><button type="button" data-care-category="<?= e($category) ?>" aria-pressed="<?= $index===0?'true':'false' ?>"><?= e($category) ?></button><?php endforeach; ?></div><span data-care-count aria-live="polite">4 notes</span></div>
 <div class="guide-grid"><?php foreach($notes as $i=>[$slug,$category,$title,$description,$image]): ?><a class="guide-card" data-care-note data-category="<?= e($category) ?>" href="<?= e(url('/care-guide/'.$slug)) ?>"><div class="guide-card-art"><span class="eyebrow">AVELA / CARE NOTES</span><?= handoff_guide_art(['texture','cleanse','condition','finish'][$i]) ?><span class="art-action" aria-hidden="true">↗</span></div><div class="guide-card-meta"><span><?= e($category) ?></span><span>2 MIN READ</span></div><h2><?= e($title) ?></h2><p><?= e($description) ?></p></a><?php endforeach; ?></div><p data-care-empty hidden>No care notes match your search. Try a different word.</p></section>
 <section class="guide-cta"><div><h2>Make a little room for you.</h2><p>Bring what you know about your hair into your own routine.</p></div><a class="button primary" href="<?= e(url('/assessment')) ?>">Build my hair profile </a></section>
</main>
