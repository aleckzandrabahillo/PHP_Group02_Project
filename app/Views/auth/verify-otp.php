<?php use App\Core\Session; $errors = Session::get('errors', []); ?>
<div class="form-head">
  <span class="eyebrow"><?= e($purpose === 'activation' ? 'VERIFY YOUR EMAIL' : 'SECURE SIGN IN') ?></span>
  <h1><?= e($purpose === 'activation' ? 'Verify your email' : 'Enter your sign-in code') ?></h1>
  <p>We sent a 6-digit code to <strong><?= e($maskedEmail) ?></strong>.</p>
  <?php if ($purpose === 'activation'): ?><p>After you verify this code, we will send a separate activation link.</p><?php endif; ?>
</div>

<form method="post" action="<?= e(url('/verify-otp')) ?>" class="form-stack" novalidate>
  <?= csrf_field() ?>
  <label class="field <?= isset($errors['otp']) ? 'has-error' : '' ?>">
    <span>6-digit code</span>
    <input class="otp-input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" name="otp" autocomplete="one-time-code" autofocus required aria-invalid="<?= isset($errors['otp']) ? 'true' : 'false' ?>" aria-describedby="otp-error">
    <small id="otp-error" class="field-error"><?= e($errors['otp'] ?? '') ?></small>
  </label>
  <button class="btn btn-primary w-full" type="submit">Verify code</button>
</form>

<form method="post" action="<?= e(url('/otp/resend')) ?>" class="resend-form">
  <?= csrf_field() ?>
  <button class="text-button" type="submit">Send a new code</button>
</form>
