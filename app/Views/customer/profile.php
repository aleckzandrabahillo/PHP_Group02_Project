<?php
use App\Core\Session;
$profile = $profile ?? [];
$errors = Session::get('errors', []);
$editing = ($_GET['edit'] ?? '') === '1' || (bool) $errors;
$displayName = ($profile['full_name'] ?? '') ?: ($profile['username'] ?? 'Your account');
$fields = ['full_name' => 'Full name', 'email' => 'Email address', 'contact_no' => 'Contact number', 'delivery_address' => 'Delivery address'];
?>
<section class="page-head profile-heading">
  <div><span class="eyebrow">YOUR ACCOUNT</span><h1><?= $editing ? 'Edit profile' : 'Your profile' ?></h1><p><?= $editing ? 'Review your details before requesting email verification.' : 'Your everyday details, all in one place.' ?></p></div>
  <?php if (!$editing): ?><a class="btn btn-secondary" href="<?= e(url('/profile?edit=1')) ?>">Edit profile <span aria-hidden="true">↗</span></a><?php endif; ?>
</section>
<div class="account-layout">
  <aside class="account-overview">
    <div class="avatar large account-avatar">
      <?php if (!empty($profile['profile_image'])): ?><img src="<?= e(url('/' . ltrim($profile['profile_image'], '/'))) ?>" alt="Your profile photograph"><?php else: ?><span aria-hidden="true"><?= e(mb_strtoupper(mb_substr($displayName, 0, 1))) ?></span><?php endif; ?>
    </div>
    <h2><?= e($displayName) ?></h2><p class="muted">@<?= e($profile['username'] ?? '') ?></p>
    <nav class="account-links" aria-label="Your account">
      <a href="<?= e(url('/orders')) ?>">Orders <span aria-hidden="true">↗</span></a>
      <a href="<?= e(url('/favorites')) ?>">Favorites <span aria-hidden="true">↗</span></a>
      <a href="<?= e(url('/assessment')) ?>">Hair profile <span aria-hidden="true">↗</span></a>
      <a href="<?= e(url('/routine')) ?>">Saved routine <span aria-hidden="true">↗</span></a>
    </nav>
  </aside>
  <div class="account-details">
    <?php if (!$editing): ?>
      <section aria-labelledby="account-details-title">
        <h2 id="account-details-title">Account details</h2>
        <dl class="account-detail-list">
          <?php foreach ($fields as $field => $label): ?><div><dt><?= e($label) ?></dt><dd><?= trim((string) ($profile[$field] ?? '')) !== '' ? nl2br(e($profile[$field])) : 'Not added yet' ?></dd></div><?php endforeach; ?>
        </dl>
        <p class="muted small">Contact numbers are used for delivery and are not verified.</p>
      </section>
    <?php else: ?>
      <form method="post" action="<?= e(url('/profile/update')) ?>" class="form-stack customer-profile-form" data-unsaved-form>
        <?= csrf_field() ?>
        <?php if ($errors): ?><div class="alert alert-error" role="alert" tabindex="-1" data-error-summary><strong>Please check your details.</strong><ul><?php foreach ($errors as $field => $message): ?><li><a href="#profile-<?= e($field) ?>"><?= e($message) ?></a></li><?php endforeach; ?></ul></div><?php endif; ?>
        <?php foreach ($fields as $field => $label): $error = $errors[$field] ?? ''; $value = Session::old($field, (string) ($profile[$field] ?? '')); ?>
          <label class="field <?= $error ? 'has-error' : '' ?>" for="profile-<?= e($field) ?>"><span><?= e($label) ?><?= in_array($field, ['contact_no', 'delivery_address'], true) ? ' (optional)' : '' ?></span>
            <?php if ($field === 'delivery_address'): ?><textarea id="profile-<?= e($field) ?>" name="<?= e($field) ?>" rows="3" maxlength="255" autocomplete="street-address" aria-invalid="<?= $error ? 'true' : 'false' ?>" aria-describedby="profile-<?= e($field) ?>-error"><?= e($value) ?></textarea>
            <?php else: ?><input id="profile-<?= e($field) ?>" name="<?= e($field) ?>" type="<?= $field === 'email' ? 'email' : ($field === 'contact_no' ? 'tel' : 'text') ?>" value="<?= e($value) ?>" maxlength="<?= $field === 'full_name' ? '100' : ($field === 'contact_no' ? '30' : '255') ?>" autocomplete="<?= $field === 'full_name' ? 'name' : ($field === 'contact_no' ? 'tel' : 'email') ?>" <?= $field !== 'contact_no' ? 'required' : '' ?> aria-invalid="<?= $error ? 'true' : 'false' ?>" aria-describedby="profile-<?= e($field) ?>-error"><?php endif; ?>
            <small class="field-error" id="profile-<?= e($field) ?>-error"><?= e($error) ?></small>
          </label>
        <?php endforeach; ?>
        <p class="verification-note" id="profile-verification-help">Saving starts email verification. Your details change only after you enter the code sent to your current email address.</p>
        <div class="account-form-actions"><button class="btn btn-primary" type="submit" aria-describedby="profile-verification-help">Save and verify</button><a class="text-link" href="<?= e(url('/profile')) ?>" data-profile-cancel>Cancel</a></div>
      </form>
      <details class="profile-photo-editor">
        <summary>Change profile photograph <span class="muted">(optional)</span></summary>
        <form method="post" action="<?= e(url('/profile/image')) ?>" enctype="multipart/form-data" class="customer-avatar-form">
          <?= csrf_field() ?>
          <p class="muted small">Your photograph is saved separately from your account details.</p>
          <label class="field" for="profile_image"><span>Choose a JPG, PNG or WebP</span><input type="file" id="profile_image" name="profile_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required></label>
          <button class="btn btn-secondary" type="submit">Update photograph</button>
        </form>
      </details>
    <?php endif; ?>
    <section class="account-security" aria-labelledby="account-security-title">
      <span class="eyebrow">SIGN-IN PROTECTION</span><h2 id="account-security-title">Two-step verification</h2>
      <p>An authenticator app generates temporary codes for additional account protection.</p>
      <p class="security-enrollment-state"><?= !empty($authenticatorEnrolled) ? 'Authenticator app connected' : 'Authenticator app not connected' ?></p>
      <a class="text-link" href="<?= e(url('/account/mfa')) ?>">Manage two-step verification →</a>
      <a class="text-link" href="<?= e(url('/account/password')) ?>">Change password →</a>
    </section>
  </div>
</div>
