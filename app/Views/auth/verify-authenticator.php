<span class="eyebrow">ONE MORE STEP</span><h1>2-step verification</h1>
<p class="muted">Enter the six-digit code from your app, or use an unused recovery code.</p>
<form class="form-stack" method="post" action="<?= e(url('/verify-authenticator')) ?>">
  <?= csrf_field() ?>
  <label class="field">App or recovery code<input type="text" name="authenticator_code" maxlength="64" required autocomplete="one-time-code" spellcheck="false"></label>
  <button class="btn btn-primary w-full" type="submit">Verify and sign in</button>
</form>
<form class="mfa-cancel" method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="text-button" type="submit">Cancel sign-in</button></form>
