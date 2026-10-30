<?php
$savedAnswers=\App\Models\HairProfile::values($hairProfile);
$savedConcerns=[];
foreach($savedAnswers['concerns'] as $code)if(isset($choices['concerns'][$code]))$savedConcerns[]=$choices['concerns'][$code];
$savedTreatments=[];
foreach($savedAnswers['treatments'] as $code)if(isset($choices['treatments'][$code]))$savedTreatments[]=$choices['treatments'][$code];
$savedTimestamp=strtotime((string)($hairProfile['updated_at']??''));
?>
<?php require_once __DIR__.'/handoff_art.php'; ?>
<section class="account-shell">
  <?php require __DIR__.'/account_navigation.php'; ?>
  <div class="account-main profile-main">
    <header class="profile-intro">
      <div><p class="eyebrow">A LITTLE PICTURE OF YOUR HAIR</p><h1 class="account-page-title">Hair Profile</h1></div>
    </header>
    <div class="profile-composition">
      <div class="profile-texture">
        <p class="eyebrow">YOUR NATURAL PATTERN</p>
        <?= handoff_texture($hairProfile['hair_texture']==='unsure'?'wavy':$hairProfile['hair_texture']) ?>
        <h2><?= e($choices['hair_texture'][$hairProfile['hair_texture']]??'Not specified') ?></h2>
        <p>Your texture is a starting point.</p>
      </div>
      <div class="profile-details">
        <div class="profile-details-top">
          <span>YOUR HAIR PROFILE</span>
          <?php if($savedTimestamp!==false): ?><time datetime="<?= e(date('c',$savedTimestamp)) ?>">Updated <?= e(date('j M Y',$savedTimestamp)) ?></time><?php endif; ?>
        </div>
        <dl>
          <?php foreach(['scalp_type'=>'Scalp','hair_condition'=>'Lengths & ends'] as $field=>$label): ?>
            <div><dt><?= e($label) ?></dt><dd><?= e($choices[$field][$hairProfile[$field]]??'Not specified') ?></dd></div>
          <?php endforeach; ?>
          <div><dt>Treatments & styling</dt><dd><?= e($savedTreatments?implode(', ',$savedTreatments):'None recorded') ?></dd></div>
          <div><dt>Everyday focus</dt><dd><?= e($savedConcerns?implode(', ',$savedConcerns):'No specific concerns recorded') ?></dd></div>
        </dl>
      </div>
    </div>
    <div class="profile-next">
      <div><h3>Bring a little structure.</h3><p>A routine with clear steps and reasons, shaped by your profile.</p></div>
      <div class="profile-actions">
        <a class="button secondary" href="<?= e(url('/assessment?edit=1')) ?>">Retake assessment</a>
        <a class="button primary" href="<?= e(url('/recommendations')) ?>">Explore my recommendations</a>
      </div>
    </div>
    <p class="profile-privacy">Your profile is saved privately to your account. General cosmetic guidance, not medical advice.</p>
  </div>
</section>
