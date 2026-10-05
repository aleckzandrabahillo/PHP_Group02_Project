<?php use App\Core\Session; $errors = Session::get('errors', []); ?>

<div class="form-head">
  <span class="eyebrow">PROFILE UPDATE</span>
  <h2>Confirm your profile update</h2>
  <p>We sent a 6-digit code to <strong><?= e($maskedEmail) ?></strong>.</p>
</div>

<form method="post" action="<?= e(url('/profile/verify-otp')) ?>" class="form-stack" novalidate>
  <?= csrf_field() ?>

  <label class="field <?= isset($errors['otp']) ? 'has-error' : '' ?>">
    <span>6-digit code</span>
    <input
      class="otp-input"
      inputmode="numeric"
      pattern="[0-9]{6}"
      maxlength="6"
      name="otp"
      autocomplete="one-time-code"
      autofocus
      required
      aria-invalid="<?= isset($errors['otp']) ? 'true' : 'false' ?>"
      aria-describedby="otp-error"
    >
    <small id="otp-error" class="field-error"><?= e($errors['otp'] ?? '') ?></small>
  </label>

  <button class="btn btn-primary w-full" type="submit">
    Verify code
  </button>
</form>

<?php if (env('APP_ENV', 'production') === 'development' && env('MAIL_DRIVER', 'log') === 'log'): ?>
  <div class="dev-note">
    <strong>Local development:</strong>
    the OTP is written to <code>storage/logs/dev_mail.log</code>.
  </div>
<?php endif; ?>