<section class="page-head"><div><span class="eyebrow">ACCOUNT SECURITY</span><h1><?= (auth_user()['role'] ?? '') === 'customer' ? 'Two-step verification' : 'Authenticator' ?></h1><p>An authenticator app generates temporary codes for an additional layer of sign-in protection.</p></div></section>
<section class="panel security-panel">
  <?php if ($enabledAt): ?>
    <h2>Your authenticator is enabled</h2>
    <p>After your password and email code, sign-in also requires an authenticator code or one-use recovery code.</p>
    <p class="muted small">Enabled <?= e($enabledAt) ?> · <?= (int)$remaining ?> recovery codes remaining.</p>
    <h3>Remove this authenticator</h3>
    <p class="muted small">Confirm your password and a current authenticator or unused recovery code. Email MFA stays enabled.</p>
    <form class="form-stack" method="post" action="<?= e(url('/account/mfa')) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="disable">
      <label class="field">Current password<input type="password" name="password" required autocomplete="current-password"></label>
      <label class="field">Authenticator or recovery code<input type="text" name="authenticator_code" required maxlength="64" autocomplete="off" spellcheck="false"></label>
      <div><button class="btn btn-secondary" type="submit">Remove authenticator and sign out</button></div>
    </form>
  <?php elseif ($setupSecret): ?>
    <h2>Connect your authenticator app</h2>
    <p>In Google Authenticator or another compatible app, choose <strong>Add account → Enter a setup key</strong>. Use your Avela username and select <strong>Time based</strong>.</p>
    <label class="field" for="authenticator-setup-key">Setup key — keep private<input id="authenticator-setup-key" type="password" value="<?= e($setupSecret) ?>" readonly autocomplete="off" spellcheck="false"></label>
    <button type="button" class="btn btn-secondary" data-reveal-setup-key aria-controls="authenticator-setup-key" aria-pressed="false">Show setup key</button>
    <p class="muted small">This setup expires after five minutes. Never send this key or a verification code to another person. No external QR service receives the key.</p>
    <form class="form-stack" method="post" action="<?= e(url('/account/mfa')) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="confirm">
      <label class="field">Email verification code<input type="text" name="email_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="off"></label>
      <label class="field">Authenticator code<input type="text" name="authenticator_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="off"></label>
      <div><button class="btn btn-primary" type="submit">Confirm authenticator</button></div>
    </form>
    <form class="mfa-cancel" method="post" action="<?= e(url('/account/mfa')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><button class="text-button" type="submit">Cancel setup</button></form>
  <?php else: ?>
    <h2>Add another layer of sign-in protection</h2>
    <p>Enrollment is optional. It does not replace the existing email verification. You will receive private one-use recovery codes after confirming setup.</p>
    <?php if (!$configured): ?><p role="status" class="muted">Authenticator setup is currently unavailable. Contact an administrator.</p>
    <?php else: ?>
      <form class="form-stack" method="post" action="<?= e(url('/account/mfa')) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="begin">
        <label class="field">Current password<input type="password" name="password" required autocomplete="current-password"></label>
        <div><button class="btn btn-primary" type="submit">Start authenticator setup</button></div>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</section>
<script src="<?= e(asset('js/password-policy.js')) ?>" defer></script>
