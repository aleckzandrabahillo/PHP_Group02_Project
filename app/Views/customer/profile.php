<section class="page-head">
  <div>
    <span class="eyebrow">PROFILE</span>
    <h1 class="sr-only">Profile</h1>
  </div>
</section>

<div class="profile-card">
  <div class="avatar large"><?= e(strtoupper(substr($profile['full_name'] ?? $profile['username'] ?? 'A', 0, 1))) ?></div>
  <div>
    <h2><?= e($profile['full_name'] ?? '') ?></h2>
    <p><?= e($profile['email'] ?? '') ?></p>
    <div class="profile-meta">
      <span>@<?= e($profile['username'] ?? '') ?></span>
      <span><?= e($profile['contact_no'] ?: 'No contact number yet') ?></span>
      <span><?= e($profile['delivery_address'] ?: 'No delivery address yet') ?></span>
    </div>
  </div>
</div>
