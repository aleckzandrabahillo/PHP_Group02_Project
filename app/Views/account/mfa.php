<?php $customerSecurity = (auth_user()['role'] ?? '') === 'customer'; ?>
<?php if ($customerSecurity): ?><section class="account-shell"><?php require dirname(__DIR__).'/partials/account_navigation.php'; ?><div class="account-main account-security-main"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/mfa-setup.css')) ?>">
<?php if ($customerSecurity): ?><nav class="product-breadcrumb" aria-label="Breadcrumb"><a href="<?= e(url('/settings')) ?>">Account settings</a><span aria-hidden="true">/</span><span>2-step verification</span></nav><?php endif; ?>
<section class="page-head account-security-heading mfa-heading"><div><h1>2-step verification</h1></div></section>
<section class="panel security-panel mfa-settings" aria-labelledby="mfa-title">
  <?php if ($enabledAt): ?>
    <div class="mfa-status"><span class="mfa-status-dot" aria-hidden="true"></span>2-step verification is on</div>
    <h2 id="mfa-title">Your account, better protected.</h2>
    <p>After your password, enter the code from your phone.</p>
    <p class="muted small">Enabled <?= e($enabledAt) ?> · <?= (int)$remaining ?> recovery codes remaining.</p>
    <details class="mfa-manual"><summary>Turn off 2-step verification</summary><div class="mfa-manual-content">
      <p>Confirm your password and a code from your app, or an unused recovery code. You will sign in with your password until you set up an authenticator again.</p>
      <form class="form-stack" method="post" action="<?= e(url('/account/mfa')) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="disable">
        <label class="field">Current password<input type="password" name="password" required autocomplete="current-password"></label>
        <label class="field">App or recovery code<input type="text" name="authenticator_code" required maxlength="64" autocomplete="off" spellcheck="false"></label>
        <div><button class="btn btn-secondary" type="submit">Turn off and sign out</button></div>
      </form>
    </div></details>
  <?php elseif ($setupSecret): ?>
    <div data-mfa-setup data-expires-at="<?= (int)($setupExpiresAt ?? 0) ?>" data-current-step="<?= (int)($setupStep ?? 1) ?>">
      <div class="mfa-progress" data-mfa-active><span data-mfa-progress>Step 1 of 2</span><span class="mfa-countdown" role="timer" aria-live="off" aria-label="Setup time remaining" data-mfa-expiry>Time left 05:00</span></div>
      <p class="sr-only" role="status" data-mfa-announcement></p>
      <div data-mfa-active>
        <div data-mfa-step="1">
          <div class="mfa-intro"><h2 id="mfa-title" tabindex="-1">Set up 2-step verification.</h2><p>Open Google Authenticator on your phone, tap <strong>+</strong>, then <strong>Scan a QR code</strong> or copy the key.</p></div>
          <label class="field mfa-key-field" for="authenticator-setup-key"><span>Setup key</span><textarea id="authenticator-setup-key" rows="1" readonly autocomplete="off" autocapitalize="off" spellcheck="false" aria-describedby="mfa-manual-help"><?= e(implode(' ', str_split($setupSecret, 4))) ?></textarea></label>
          <div class="mfa-key-actions">
            <button type="button" class="btn btn-secondary" data-mfa-copy hidden><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="12" height="13" rx="2"/><path d="M16 8V3H3v13h5"/></svg><span data-mfa-copy-label>Copy key</span></button>
            <button type="button" class="btn btn-secondary" data-mfa-open-qr hidden aria-haspopup="dialog" aria-controls="mfa-qr-dialog"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h6v6H3zM15 3h6v6h-6zM3 15h6v6H3zM15 15h3v3h3v3h-6zM21 12v3M12 3v3M12 12h3M3 12h3M12 18v3"/></svg>View QR code</button>
          </div>
          <p class="muted small mfa-manual-help" id="mfa-manual-help">To enter it by hand, choose <strong>Enter a setup key</strong> in your app. Name it Avela and choose <strong>Time based</strong>.</p>
        </div>
        <form class="form-stack mfa-confirm" method="post" action="<?= e(url('/account/mfa')) ?>" data-mfa-confirm data-mfa-step="2">
          <?= csrf_field() ?><input type="hidden" name="action" value="confirm">
          <div class="mfa-intro"><h2 id="mfa-confirm-title" tabindex="-1">Confirm it’s you.</h2><p>Enter the code from your email and the code shown in your app.</p></div>
          <div class="mfa-code-fields">
            <div><label class="field"><span>Email verification code</span><input type="text" name="email_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" aria-describedby="mfa-email-help"></label><small id="mfa-email-help">Sent to your account’s email address.</small></div>
            <div><label class="field"><span>Code from your app</span><input type="text" name="authenticator_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" aria-describedby="mfa-code-help"></label><small id="mfa-code-help">The six-digit code shown in your app.</small></div>
          </div>
          <div class="mfa-confirm-row"><button type="button" class="btn btn-secondary" data-mfa-back hidden>Back</button><button class="btn btn-primary" type="submit">Turn on 2-step verification</button></div>
        </form>
      </div>
      <div class="mfa-expired" data-mfa-expired hidden tabindex="-1" role="status"><h2>Your setup time has ended.</h2><p>Start again to set up 2-step verification.</p><a class="btn btn-primary" href="<?= e(url('/account/mfa')) ?>" data-mfa-restart>Start again</a></div>
      <div class="mfa-setup-actions"><form class="mfa-cancel" method="post" action="<?= e(url('/account/mfa')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><button class="text-button" type="submit">Cancel setup</button></form><button class="btn btn-primary" type="button" data-mfa-next hidden>Continue</button></div>
      <dialog id="mfa-qr-dialog" class="account-dialog mfa-qr-dialog" aria-labelledby="mfa-qr-title"><h2 id="mfa-qr-title">Scan with your phone</h2><p>Open Google Authenticator, tap <strong>+</strong>, then <strong>Scan a QR code</strong>.</p><div class="mfa-qr-frame"><div class="mfa-qr" data-mfa-qr data-provisioning-uri="<?= e($setupUri ?? '') ?>"></div></div><p data-mfa-qr-status role="status"></p><button type="button" class="btn btn-secondary" data-mfa-close-qr>Done</button></dialog>
    </div>
  <?php else: ?>
    <div class="mfa-intro"><h2 id="mfa-title">Protect your sign-in.</h2><p>Add a code from your phone as an extra sign-in step.</p></div>
    <?php if (!$configured): ?><p role="status" class="muted">2-step verification is currently unavailable. Please contact Avela.</p>
    <?php else: ?>
      <form class="form-stack mfa-begin" method="post" action="<?= e(url('/account/mfa')) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="begin">
        <label class="field"><span>Current Avela password</span><input type="password" name="password" required autocomplete="current-password"></label>
        <div><button class="btn btn-primary" type="submit">Set up 2-step verification</button></div>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php if ($setupSecret): ?><script src="<?= e(asset('js/qr-encoder.js')) ?>" defer></script><?php endif; ?>
<script src="<?= e(asset('js/mfa-setup.js')) ?>" defer></script>
<script src="<?= e(asset('js/password-policy.js')) ?>" defer></script>

<?php if ($customerSecurity): ?></div></section><?php endif; ?>
