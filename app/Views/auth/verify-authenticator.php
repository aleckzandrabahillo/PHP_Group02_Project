<span class="eyebrow">ONE MORE STEP</span><h1>Verify your authenticator</h1>
<p class="muted">Your email code is confirmed. Enter the six-digit code from your authenticator app, or an unused recovery code.</p>
<form class="form-stack" method="post" action="<?= e(url('/verify-authenticator')) ?>">
  <?= csrf_field() ?>
  <label class="field">Authenticator or recovery code<input type="text" name="authenticator_code" maxlength="64" required autocomplete="one-time-code" spellcheck="false"></label>
  <button class="btn btn-primary w-full" type="submit">Verify and sign in</button>
</form>
<form class="mfa-cancel" method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="text-button" type="submit">Cancel sign-in</button></form>
