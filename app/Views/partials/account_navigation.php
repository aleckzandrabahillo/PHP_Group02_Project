<?php
require_once __DIR__.'/handoff_art.php';
// The account identity always belongs to the signed-in user, independently of
// the hair-profile/routine data in the enclosing view. Keep partial variables local.
(static function (array $profile): void {
    $displayName=($profile['full_name']??'')?:($profile['username']??'Your account');
?>
<aside class="account-sidebar" data-account-user="<?= (int)$profile['id'] ?>">
  <div class="account-identity">
    <?php $photoPostTo='/profile/image';require __DIR__.'/profile_photo.php'; ?>
    <h2><?= e($displayName) ?></h2><p>@<?= e($profile['username']) ?></p>
  </div>
  <nav class="account-nav" aria-label="Your account">
    <?php foreach(['/profile'=>'Profile','/orders'=>'Orders','/assessment'=>'Hair Profile','/routine'=>'My Routine','/settings'=>'Account Settings'] as $path=>$label):
      $selected=is_active_path($path)||($path==='/settings'&&(is_active_path('/account/password')||is_active_path('/account/mfa')));
    ?><a href="<?= e(url($path)) ?>" <?= $selected?'aria-current="page"':'' ?>><?= e($label) ?></a><?php endforeach; ?>
    <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button type="submit">Sign out</button></form>
  </nav>
</aside>
<?php })($accountProfile ?? ['id'=>(int)auth_user()['id'],'username'=>auth_user()['username'],'full_name'=>auth_user()['username'],'profile_image'=>null]); ?>
