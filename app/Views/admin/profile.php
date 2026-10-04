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
      <span><?= e($profile['role'] ?? '') ?></span>
      <span><?= e($profile['status'] ?? '') ?></span>
    </div>
  </div>
</div>
