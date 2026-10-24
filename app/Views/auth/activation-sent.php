<div class="form-head">
  <span class="eyebrow">ACCOUNT ACTIVATION</span>
  <h1><?= $expired?'Verify your email again':'Check your email' ?></h1>
  <?php if($expired): ?>
  <p>Your verification has expired. Sign in to request a new code and activation link.</p>
  <?php else: ?>
  <p>Your email is verified. We sent a separate activation link to <strong><?= e($maskedEmail) ?></strong>.</p>
  <p>Open that email and choose <strong>Activate account</strong>. The link works once and expires in 30 minutes.</p>
  <?php endif; ?>
</div>
<?php if(!$expired): ?>
<form method="post" action="<?= e(url('/otp/resend')) ?>" class="form-stack">
  <?= csrf_field() ?>
  <button class="btn btn-primary w-full" type="submit">Send a new activation link</button>
</form>
<?php endif; ?>
<p class="auth-switch"><a href="<?= e(url('/login')) ?>">Back to sign in</a></p>
