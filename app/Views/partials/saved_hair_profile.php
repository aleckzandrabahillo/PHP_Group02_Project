<?php
$savedAnswers=\App\Models\HairProfile::values($hairProfile);
$savedConcerns=[];
foreach($savedAnswers['concerns'] as $code)if(isset($choices['concerns'][$code]))$savedConcerns[]=$choices['concerns'][$code];
$savedTreatments=[];
foreach($savedAnswers['treatments'] as $code)if(isset($choices['treatments'][$code]))$savedTreatments[]=$choices['treatments'][$code];
$savedTimestamp=strtotime((string)($hairProfile['updated_at']??''));
?>
<section class="saved-hair-profile" aria-labelledby="saved-profile-title">
  <div class="saved-profile-content">
    <header class="saved-profile-heading">
      <p class="saved-profile-state">Your saved assessment<?php if($savedTimestamp!==false): ?><span>Updated <time datetime="<?= e(date('c',$savedTimestamp)) ?>"><?= e(date('j M Y',$savedTimestamp)) ?></time></span><?php endif; ?></p>
      <h1 id="saved-profile-title">Your hair profile.</h1>
      <p>The starting point for your Avela matches.</p>
    </header>
    <dl class="saved-profile-facts">
      <?php foreach(['hair_texture'=>'Texture','scalp_type'=>'Scalp','hair_condition'=>'Lengths & ends'] as $field=>$label): ?>
      <div><dt><?= e($label) ?></dt><dd><?= e($choices[$field][$hairProfile[$field]]??'Not specified') ?></dd></div>
      <?php endforeach; ?>
    </dl>
    <?php if($savedConcerns): ?><div class="saved-profile-priorities"><h2>Your priorities</h2><p><?= e(implode(' · ',$savedConcerns)) ?></p></div><?php endif; ?>
    <?php if($savedTreatments): ?><details class="saved-profile-details"><summary>Treatments & styling <span class="menu-chevron" aria-hidden="true">⌄</span></summary><p><?= e(implode(' · ',$savedTreatments)) ?></p></details><?php endif; ?>
    <div class="saved-profile-actions">
      <a class="btn btn-primary" href="<?= e(url('/recommendations')) ?>">View my results <span aria-hidden="true">→</span></a>
      <a class="saved-profile-retake" href="<?= e(url('/assessment?edit=1')) ?>">Retake assessment</a>
    </div>
    <p class="saved-profile-note">Your current answers stay saved until you submit a new assessment.</p>
  </div>
  <div class="saved-profile-image"><img src="<?= e(asset('images/editorial/assessment-lather.jpg')) ?>" alt="" width="1200" height="800" decoding="async"></div>
</section>
