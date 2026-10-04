<?php use App\Core\Session; $errors = Session::get('errors', []); ?>
<div class="form-head">
  <span class="eyebrow">WELCOME BACK</span>
  <h2>Sign in to Avela</h2>
  <p>Use your email or username and password.</p>
</div>

<form method="post" action="<?= e(url('/login')) ?>" class="form-stack" novalidate>
  <?= csrf_field() ?>

  <label class="field <?= isset($errors['identifier']) ? 'has-error' : '' ?>">
    <span>Email or username</span>
    <input name="identifier" autocomplete="username" value="<?= old('identifier') ?>" required aria-invalid="<?= isset($errors['identifier']) ? 'true' : 'false' ?>">
    <small class="field-error"><?= e($errors['identifier'] ?? '') ?></small>
  </label>

  <label class="field <?= isset($errors['password']) ? 'has-error' : '' ?>">
    <span>Password</span>
    <div class="password-wrap">
      <input id="login-password" data-password-input type="password" name="password" autocomplete="current-password" required aria-invalid="<?= isset($errors['password']) ? 'true' : 'false' ?>">
      <button class="password-toggle" type="button" data-password-toggle="login-password" hidden aria-pressed="false" aria-label="Show password" title="Show password">
        <svg class="password-icon password-icon-show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg>
        <svg class="password-icon password-icon-hide" viewBox="0 0 24 24" aria-hidden="true" hidden><path d="M3 3l18 18M10.6 6.2A10.9 10.9 0 0 1 12 6c6.1 0 9.5 6 9.5 6a16.1 16.1 0 0 1-3.2 3.8M6.4 6.4C3.8 8.2 2.5 12 2.5 12s3.4 6 9.5 6c1.5 0 2.8-.3 4-.8M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
      </button>
    </div>
    <small class="field-error"><?= e($errors['password'] ?? '') ?></small>
  </label>

  <div class="captcha-block <?= isset($errors['captcha']) ? 'has-error' : '' ?>">
    <div class="captcha-label"><span>Security check</span><button class="text-button captcha-refresh" type="button" data-csrf="<?= e(\App\Core\Csrf::token()) ?>">New code</button></div>
    <img class="captcha-image" src="<?= e(url('/captcha.svg')) ?>" alt="CAPTCHA code">
    <input name="captcha" maxlength="5" autocomplete="off" autocapitalize="characters" placeholder="Type the 5-character code" required aria-invalid="<?= isset($errors['captcha']) ? 'true' : 'false' ?>">
    <small class="field-error"><?= e($errors['captcha'] ?? '') ?></small>
  </div>

  <button class="btn btn-primary w-full" type="submit">Sign in</button>
</form>

<p class="auth-switch">New to Avela? <a href="<?= e(url('/register')) ?>">Create an account</a></p>
