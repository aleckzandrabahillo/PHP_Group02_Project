<div class="form-head">
  <span class="eyebrow">EMAIL VERIFICATION</span>
  <h2>Activate your account</h2>
  <p>Confirm the activation link from your Avela email. This does not sign you in.</p>
</div>
<form method="post" action="<?= e(url('/activate')) ?>" class="form-stack" data-activation-form>
  <?= csrf_field() ?>
  <input type="hidden" name="activation_token" value="" autocomplete="off">
  <p role="status" data-activation-status>Open the complete activation link from your email.</p>
  <button class="btn btn-primary w-full" type="submit" disabled>Activate account</button>
</form>
<noscript><p>Enable JavaScript to use the private activation link, or enter the numeric code in your pending activation session.</p></noscript>
<p><a href="<?= e(url('/login')) ?>">Return to sign in</a></p>
<script src="<?= e(asset('js/activation.js')) ?>" defer></script>
