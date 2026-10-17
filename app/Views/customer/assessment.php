<section class="page-head"><div>
  <span class="eyebrow">YOUR HAIR PROFILE</span><h1>Good care starts with knowing your hair.</h1>
  <p>Tell us about your scalp and lengths separately. Oily roots and dry ends can coexist.</p>
</div></section>
<div class="hair-profile-layout">
  <form class="hair-profile-form" method="post" action="<?= e(url('/assessment')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="profile_version" value="<?= e($profileVersion) ?>">
    <nav class="assessment-sections" aria-label="Assessment sections">
      <a href="#hair-hair_texture"><span>01</span> Your hair</a>
      <a href="#hair-scalp_type"><span>02</span> Scalp &amp; lengths</a>
      <a href="#hair-concerns"><span>03</span> Your concerns</a>
    </nav>
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
    $hints = ['hair_texture' => 'Choose your closest match, or Not sure.', 'scalp_type' => 'Think about your roots, rather than the ends of your hair.',
        'hair_condition' => 'This can differ from your scalp.', 'concerns' => 'Optional. Select any cosmetic concerns that apply.',
        'treatments' => 'Optional. Select any that apply; leave blank if none.'];
    ?>
    <?php foreach ($titles as $field => $title): $multiple = in_array($field, ['concerns', 'treatments'], true); ?>
      <fieldset id="hair-<?= e($field) ?>" class="hair-fieldset" aria-describedby="hint-<?= e($field) ?> error-<?= e($field) ?>">
        <legend><?= e($title) ?></legend><p id="hint-<?= e($field) ?>" class="muted"><?= e($hints[$field]) ?></p>
        <div class="hair-choice-grid"><?php foreach ($choices[$field] as $code => $label): ?>
          <label class="hair-choice">
            <input type="<?= $multiple ? 'checkbox' : 'radio' ?>" name="<?= e($field) ?><?= $multiple ? '[]' : '' ?>" value="<?= e($code) ?>"
              <?= ($multiple ? in_array($code, $values[$field], true) : $values[$field] === $code) ? 'checked' : '' ?> <?= !$multiple ? 'required' : '' ?>
              aria-invalid="<?= isset($errors[$field]) ? 'true' : 'false' ?>"><span><?= e($label) ?></span>
          </label>
        <?php endforeach; ?></div><p class="field-error" id="error-<?= e($field) ?>"><?= e($errors[$field] ?? '') ?></p>
      </fieldset>
    <?php endforeach; ?>
    <div class="field">
      <label for="hair-concern_text">Anything else about your hair? <span class="muted">(optional)</span></label>
      <p id="hint-concern_text" class="muted">English or Taglish is fine: “My scalp is oily but my ends are dry.” Please leave out names, contact details and medical information.</p>
      <textarea id="hair-concern_text" name="concern_text" rows="4" maxlength="500" aria-describedby="hint-concern_text error-concern_text"
        aria-invalid="<?= isset($errors['concern_text']) ? 'true' : 'false' ?>" placeholder="Describe how your hair usually feels…"><?= e($values['concern_text']) ?></textarea>
      <small class="field-error" id="error-concern_text"><?= e($errors['concern_text'] ?? '') ?></small>
    </div>
    <p class="muted small">Your answers are saved privately to your account. This is a cosmetic shopping profile, not a medical assessment.</p>
    <button class="btn btn-primary" id="profile-submit" type="submit"><?= $hairProfile ? 'Update hair profile' : 'Save hair profile' ?></button>
  </form>
  <aside class="hair-profile-summary">
    <img class="assessment-editorial" src="<?= e(asset('images/editorial/assessment-lather.jpg')) ?>" alt="A close-up of lather in a hand" width="1200" height="800" loading="lazy" decoding="async">
    <span class="eyebrow"><?= $hairProfile ? 'SAVED TO YOUR ACCOUNT' : 'A LITTLE CONTEXT GOES A LONG WAY' ?></span>
    <h2><?= $hairProfile ? 'Your profile, at a glance.' : 'Scalp and lengths have different needs.' ?></h2>
    <?php if ($hairProfile): ?>
      <dl><?php foreach (['hair_texture' => 'Texture', 'scalp_type' => 'Scalp', 'hair_condition' => 'Lengths'] as $field => $label): ?>
        <dt><?= e($label) ?></dt><dd><?= e($choices[$field][$hairProfile[$field]] ?? 'Not specified') ?></dd>
      <?php endforeach; ?></dl>
      <h3>Saved characteristics</h3>
      <?php if ($hairProfile['tags']): ?><ul class="hair-tag-list">
        <?php $seenTags = []; foreach ($hairProfile['tags'] as $tag): $key = $tag['type'] . ':' . $tag['code']; if (isset($seenTags[$key])) continue; $seenTags[$key] = true; ?>
          <li><?= e(ucfirst($tag['type'])) ?>: <?= e($tag['display_name']) ?> <small><?= $tag['source'] === 'nlp' ? 'From your description' : 'From your choices' ?></small></li>
        <?php endforeach; ?></ul>
      <?php else: ?><p>No specific concerns selected. You can update this whenever you like.</p><?php endif; ?>
      <?php if ($analysis): ?>
        <?php foreach ($analysis['warnings'] as $warning): ?><p class="muted small"><?= e($warning) ?></p><?php endforeach; ?>
        <?php if ($analysis['matches']): ?><details><summary>How your description was interpreted</summary>
          <p class="muted small">Inspectable phrases and synonyms with basic negation rules. Structured answers take precedence.</p>
          <ul><?php foreach ($analysis['matches'] as $match): ?>
            <li>“<?= e($match['phrase']) ?>” — <?= $match['negated'] ? 'negated; not added' : e(str_replace('_', ' ', $match['code'])) ?>
              <small class="muted">(<?= e($match['rule']) ?>)</small></li>
          <?php endforeach; ?></ul>
        </details><?php endif; ?>
      <?php endif; ?>
    <?php else: ?><p>Start with what you know. “Not sure” is a useful answer, and you can revisit your profile later.</p>
      <p class="muted small">Optional text is interpreted by simple language rules. It does not diagnose scalp conditions or predict product results.</p>
    <?php endif; ?>
    <a class="text-link" href="<?= e(url('/shop')) ?>">Explore the catalog →</a>
    <?php if ($hairProfile): ?><a class="btn btn-primary" href="<?= e(url('/recommendations')) ?>">See your matches</a><?php endif; ?>
  </aside>
</div>
