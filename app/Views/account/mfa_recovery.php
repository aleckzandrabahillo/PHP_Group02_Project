<section class="page-head"><div><span class="eyebrow">ACCOUNT SECURITY</span><h1>Recovery codes</h1></div></section>
<section class="panel security-panel">
  <?php if ($codes): ?>
    <h2>Save these codes privately</h2><p>Use a recovery code when you cannot get a sign-in code from your phone. Each code works once and is shown here only once.</p>
    <ul class="recovery-codes"><?php foreach($codes as $code): ?><li><code><?= e($code) ?></code></li><?php endforeach; ?></ul>
    <p class="muted small">Do not share these codes in messages, screenshots or source files. You will not be able to retrieve them after leaving this page.</p>
  <?php else: ?><p>Recovery codes are no longer displayed. Your 2-step verification settings show how many remain.</p><?php endif; ?>
  <a class="btn btn-secondary" href="<?= e(url('/account/mfa')) ?>">Return to 2-step verification</a>
</section>
