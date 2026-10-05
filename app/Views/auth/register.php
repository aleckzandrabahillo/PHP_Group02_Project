<?php use App\Core\Session; $errors = Session::get('errors', []); ?>

<div class="form-head">
  <span class="eyebrow">CREATE YOUR ACCOUNT</span>
  <h2>Create your Avela account</h2>
  <p>Create an account to keep your shopping and hair profile in one place.</p>
</div>

<form method="post" action="<?= e(url('/register')) ?>" class="form-stack" novalidate>
  <?= csrf_field() ?>

  <div class="field-grid two">
    <label class="field <?= isset($errors['full_name']) ? 'has-error' : '' ?>">
      <span>Full name</span>
      <input name="full_name" autocomplete="name" maxlength="120" value="<?= old('full_name') ?>" required aria-invalid="<?= isset($errors['full_name']) ? 'true' : 'false' ?>">
      <small class="field-error"><?= e($errors['full_name'] ?? '') ?></small>
    </label>

    <label class="field <?= isset($errors['username']) ? 'has-error' : '' ?>">
      <span>Username</span>
      <input name="username" autocomplete="username" maxlength="40" value="<?= old('username') ?>" required aria-invalid="<?= isset($errors['username']) ? 'true' : 'false' ?>">
      <small class="field-error"><?= e($errors['username'] ?? '') ?></small>
    </label>
  </div>

  <label class="field <?= isset($errors['email']) ? 'has-error' : '' ?>">
    <span>Email address</span>
    <input type="email" name="email" autocomplete="email" maxlength="190" value="<?= old('email') ?>" required aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>">
    <small class="field-error"><?= e($errors['email'] ?? '') ?></small>
  </label>

  <label class="field <?= isset($errors['contact_no']) ? 'has-error' : '' ?>">
    <span>Contact number</span>
    <input name="contact_no" autocomplete="tel" inputmode="tel" maxlength="18" value="<?= old('contact_no') ?>" required aria-invalid="<?= isset($errors['contact_no']) ? 'true' : 'false' ?>">
    <small class="field-error"><?= e($errors['contact_no'] ?? '') ?></small>
  </label>

  <div class="field-grid two">
    <label class="field <?= isset($errors['password']) ? 'has-error' : '' ?>">
      <span>Password</span>
      <div class="password-wrap">
        <input id="register-password" data-password-input type="password" name="password" autocomplete="new-password" required aria-invalid="<?= isset($errors['password']) ? 'true' : 'false' ?>" aria-describedby="register-password-help register-password-error">
        <button class="password-toggle" type="button" data-password-toggle="register-password" hidden aria-pressed="false" aria-label="Show password" title="Show password">
          <svg class="password-icon password-icon-show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg>
          <svg class="password-icon password-icon-hide" viewBox="0 0 24 24" aria-hidden="true" hidden><path d="M3 3l18 18M10.6 6.2A10.9 10.9 0 0 1 12 6c6.1 0 9.5 6 9.5 6a16.1 16.1 0 0 1-3.2 3.8M6.4 6.4C3.8 8.2 2.5 12 2.5 12s3.4 6 9.5 6c1.5 0 2.8-.3 4-.8M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
        </button>
      </div>
      <small id="register-password-help" class="help">At least 12 characters with uppercase, lowercase, a number, and a special character.</small>
      <small id="register-password-error" class="field-error"><?= e($errors['password'] ?? '') ?></small>
    </label>

    <label class="field <?= isset($errors['password_confirmation']) ? 'has-error' : '' ?>">
      <span>Confirm password</span>
      <div class="password-wrap">
        <input id="register-password-confirm" data-password-input type="password" name="password_confirmation" autocomplete="new-password" required aria-invalid="<?= isset($errors['password_confirmation']) ? 'true' : 'false' ?>">
        <button class="password-toggle" type="button" data-password-toggle="register-password-confirm" hidden aria-pressed="false" aria-label="Show password" title="Show password">
          <svg class="password-icon password-icon-show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg>
          <svg class="password-icon password-icon-hide" viewBox="0 0 24 24" aria-hidden="true" hidden><path d="M3 3l18 18M10.6 6.2A10.9 10.9 0 0 1 12 6c6.1 0 9.5 6 9.5 6a16.1 16.1 0 0 1-3.2 3.8M6.4 6.4C3.8 8.2 2.5 12 2.5 12s3.4 6 9.5 6c1.5 0 2.8-.3 4-.8M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
        </button>
      </div>
      <small class="field-error"><?= e($errors['password_confirmation'] ?? '') ?></small>
    </label>
  </div>

  <div class="captcha-block <?= isset($errors['captcha']) ? 'has-error' : '' ?>">
    <div
      class="g-recaptcha"
      data-sitekey="<?= e($recaptchaSiteKey ?? '') ?>">
    </div>

    <small class="field-error">
        <?= e($errors['captcha'] ?? '') ?>
    </small>
</div>

  <button class="btn btn-primary w-full" type="submit">Create account</button>
</form>

<p class="auth-switch">Already have an account? <a href="<?= e(url('/login')) ?>">Sign in</a></p>
