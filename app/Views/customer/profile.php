<?php $profile = $profile ?? []; ?>
<section class="page-head"><div><span class="eyebrow">YOUR ACCOUNT</span><h1>Profile</h1><p>Keep your everyday details in one place.</p></div><a class="btn btn-secondary" href="<?= e(url('/account/mfa')) ?>">Authenticator settings</a></section>
<div class="profile-card">
  <form method="post" action="<?= e(url('/profile/image')) ?>" enctype="multipart/form-data" class="customer-avatar-form">
    <?= csrf_field() ?>
    <div class="avatar large">
      <?php if (!empty($profile['profile_image'])): ?><img src="<?= e(url('/' . ltrim($profile['profile_image'], '/'))) ?>" alt="Your profile picture"><?php else: ?><?= e(strtoupper(substr($profile['full_name'] ?? $profile['username'] ?? 'A',0,1))) ?><?php endif; ?>
    </div>
    <label class="field" for="profile_image"><span>Profile photograph</span><input type="file" id="profile_image" name="profile_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required></label>
    <button class="btn btn-secondary" type="submit">Update photograph</button>
  </form>
  <form method="post" action="<?= e(url('/profile/update')) ?>" class="form-stack customer-profile-form">
    <?= csrf_field() ?>
    <p class="muted">Signed in as @<?= e($profile['username'] ?? '') ?></p>
    <label class="field"><span>Full name</span><input name="full_name" value="<?= e($profile['full_name'] ?? '') ?>" maxlength="100" autocomplete="name" required></label>
    <label class="field"><span>Email address</span><input type="email" name="email" value="<?= e($profile['email'] ?? '') ?>" maxlength="255" autocomplete="email" required></label>
    <label class="field"><span>Contact number <small>(optional)</small></span><input type="tel" name="contact_no" value="<?= e($profile['contact_no'] ?? '') ?>" maxlength="30" autocomplete="tel" aria-describedby="profile-phone-help"></label>
    <small class="muted" id="profile-phone-help">Used for contact. Phone numbers are not verified.</small>
    <label class="field"><span>Delivery address <small>(optional)</small></span><textarea name="delivery_address" rows="3" maxlength="255" autocomplete="street-address"><?= e($profile['delivery_address'] ?? '') ?></textarea></label>
    <p class="muted small" id="profile-verification-help">Changes require a verification code sent to your current email address before they are saved.</p>
    <button class="btn btn-primary" type="submit" aria-describedby="profile-verification-help">Review changes with email verification</button>
  </form>
</div>
