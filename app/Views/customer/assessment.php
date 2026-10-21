<section class="page-head assessment-page-head"><div>
  <span class="eyebrow">HAIR ASSESSMENT</span>
  <h1><?= $hairProfile && !$editing ? 'Your saved hair profile' : 'Find your starting point.' ?></h1>
</div></section>
<?php if ($hairProfile && !$editing): ?>
<section class="assessment-entry" aria-label="Your saved assessment">
  <p>Your answers are saved. Explore your current matches or revisit your assessment.</p>
  <dl class="results-characteristics"><?php foreach (['hair_texture'=>'Texture','scalp_type'=>'Scalp','hair_condition'=>'Lengths & ends'] as $field=>$label): ?>
    <div><dt><?= e($label) ?></dt><dd><?= e($choices[$field][$hairProfile[$field]] ?? 'Not specified') ?></dd></div>
  <?php endforeach; ?></dl>
  <div class="account-form-actions"><a class="btn btn-primary" href="<?= e(url('/recommendations')) ?>">View my results <span aria-hidden="true">→</span></a>
    <a class="btn btn-secondary" href="<?= e(url('/assessment?edit=1')) ?>">Retake assessment</a></div>
  <p class="muted small">Retaking does not replace your saved answers until you submit the form.</p>
</section>
<?php else: ?>
<details class="assessment-sections">
  <summary>Jump to a section</summary>
  <nav class="assessment-jump-links" aria-label="Assessment sections">
    <a href="#assessment-1">Hair</a>
    <a href="#assessment-2">Scalp &amp; lengths</a>
    <a href="#assessment-3">Concerns</a>
    <a href="#assessment-4">Details</a>
  </nav>
</details>
<div class="hair-profile-layout">
  <form class="hair-profile-form" method="post" action="<?= e(url('/assessment')) ?>" data-assessment-form>
    <?= csrf_field() ?>
    <input type="hidden" name="profile_version" value="<?= e($profileVersion) ?>">

    <?php if ($errors): ?>
      <div class="alert alert-error" id="profile-errors" role="alert" tabindex="-1" data-error-summary>
        <h2>Please check your answers</h2><ul><?php foreach ($errors as $field => $message): ?>
          <li><a href="#<?= e($field === 'form' ? 'profile-submit' : 'hair-' . $field) ?>"><?= e($message) ?></a></li>
        <?php endforeach; ?></ul>
      </div>
    <?php endif; ?>
    <?php
    $titles = ['hair_texture' => 'Your natural texture', 'scalp_type' => 'How your scalp usually feels',
        'hair_condition' => 'How your lengths and ends usually feel', 'concerns' => 'What would you like help with?', 'treatments' => 'Recent treatments or styling'];
    ?>
    <?php $groups = ['Hair characteristics' => ['hair_texture'], 'Scalp and lengths' => ['scalp_type', 'hair_condition'], 'Concerns' => ['concerns'], 'Additional details' => ['treatments']]; ?>
    <?php $sectionNumber = 0; foreach ($groups as $group => $fields): $sectionNumber++; ?><section class="assessment-group" id="assessment-<?= $sectionNumber ?>" aria-labelledby="assessment-heading-<?= $sectionNumber ?>"><h2 id="assessment-heading-<?= $sectionNumber ?>" tabindex="-1"><span aria-hidden="true"><?= sprintf('%02d', $sectionNumber) ?></span><?= e($group) ?></h2><div class="assessment-fields">
    <?php foreach ($fields as $field): $title = $titles[$field]; $multiple = in_array($field, ['concerns', 'treatments'], true); ?>
      <fieldset id="hair-<?= e($field) ?>" class="hair-fieldset" aria-describedby="error-<?= e($field) ?>">
        <legend><?= e($title) ?><?= $multiple ? ' <span class="muted small">(optional)</span>' : '' ?></legend>
        <div class="hair-choice-grid"><?php foreach ($choices[$field] as $code => $label): ?>
          <label class="hair-choice">
            <input type="<?= $multiple ? 'checkbox' : 'radio' ?>" name="<?= e($field) ?><?= $multiple ? '[]' : '' ?>" value="<?= e($code) ?>"
              <?= ($multiple ? in_array($code, $values[$field], true) : $values[$field] === $code) ? 'checked' : '' ?> <?= !$multiple ? 'required' : '' ?>
              aria-invalid="<?= isset($errors[$field]) ? 'true' : 'false' ?>"><span><?= e($label) ?></span>
          </label>
        <?php endforeach; ?></div><p class="field-error" id="error-<?= e($field) ?>"><?= e($errors[$field] ?? '') ?></p>
      </fieldset>
    <?php endforeach; ?>
    <?php if ($group === 'Additional details'): ?><div class="field">
      <label for="hair-concern_text">Anything else about your hair? <span class="muted">(optional)</span></label>
      <p id="hint-concern_text" class="muted">Leave out names, contact details and medical information.</p>
      <textarea id="hair-concern_text" name="concern_text" rows="4" maxlength="500" aria-describedby="hint-concern_text error-concern_text"
        aria-invalid="<?= isset($errors['concern_text']) ? 'true' : 'false' ?>" placeholder="Describe how your hair usually feels…"><?= e($values['concern_text']) ?></textarea>
      <small class="field-error" id="error-concern_text"><?= e($errors['concern_text'] ?? '') ?></small>
    </div>
    <?php endif; ?></div></section><?php endforeach; ?>
    <div class="assessment-actions">
      <p class="muted small">Your answers stay with your account. Matches guide cosmetic product discovery, not medical care.</p>
      <button class="btn btn-primary" id="profile-submit" type="submit"><?= $hairProfile ? 'Update and see results' : 'See my results' ?><span aria-hidden="true">→</span></button>
    </div>
  </form>
  <aside class="hair-profile-summary assessment-summary" aria-label="Profile overview">
    <img class="assessment-editorial" src="<?= e(asset('images/editorial/assessment-lather.jpg')) ?>" alt="A close-up of lather in a hand" width="1200" height="800" loading="lazy" decoding="async">
    <span class="eyebrow"><?= $hairProfile ? 'SAVED TO YOUR ACCOUNT' : 'YOUR STARTING POINT' ?></span>
    <h2><?= $hairProfile ? 'Your saved profile' : 'Roots and ends can differ.' ?></h2>
    <?php if ($hairProfile): ?>
      <dl><?php foreach (['hair_texture' => 'Texture', 'scalp_type' => 'Scalp', 'hair_condition' => 'Lengths'] as $field => $label): ?>
        <dt><?= e($label) ?></dt><dd><?= e($choices[$field][$hairProfile[$field]] ?? 'Not specified') ?></dd>
      <?php endforeach; ?></dl>
      <p class="muted small">Your saved answers remain here until you submit an update.</p>
    <?php else: ?><p>Describe your scalp separately from your lengths. “Not sure” is always an option.</p>
    <?php endif; ?>
    <a class="text-link" href="<?= e(url('/shop')) ?>">Explore the catalog →</a>
    <?php if ($hairProfile): ?><a class="text-link" href="<?= e(url('/recommendations')) ?>">View saved results →</a><?php endif; ?>
  </aside>
</div>
<?php endif; ?>
