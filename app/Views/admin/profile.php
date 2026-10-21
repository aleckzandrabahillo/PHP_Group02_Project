<section class="page-head">
  <div>
    <span class="eyebrow">PROFILE</span>
    <h1>Administrator account</h1>
    <p>View your administrator account information.</p>
  </div>
</section>

<div class="profile-card">
  <div class="avatar large"><?= e(strtoupper(substr($profile['full_name'] ?? 'A', 0, 1))) ?></div>
  <div>
    <h2><?= e($profile['full_name'] ?? '') ?></h2>
    <p><?= e($profile['email'] ?? '') ?></p>
    <div class="profile-meta">
      <span><?= e(($profile['role']??'')==='admin'?'Administrator':'Catalog Manager') ?></span>
      <span><?= e($profile['status'] ?? '') ?></span>
    </div>
  </div>
</div>

<section class="panel staff-account-actions" aria-label="Account actions"><h2>Account security</h2><div class="quick-links"><a href="<?= e(url('/account/password')) ?>">Change password</a><a href="<?= e(url('/account/mfa')) ?>">Authenticator settings</a></div><form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="btn btn-secondary" type="submit">Sign out</button></form></section>