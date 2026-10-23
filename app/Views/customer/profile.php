<?php
use App\Core\Session;
$profile = $profile ?? [];
$errors = Session::get('errors', []);
Session::forget('errors');
$editing = ($_GET['edit'] ?? '') === '1' || (bool) $errors;
$displayName = ($profile['full_name'] ?? '') ?: ($profile['username'] ?? 'Your account');
$fields = ['full_name' => 'Full name', 'email' => 'Email address', 'contact_no' => 'Contact number'];
?>
<section class="page-head profile-heading"><h1>Your profile</h1></section>
<div class="account-layout">  <aside class="account-overview">
    <?php $photoPostTo='/profile/image';require dirname(__DIR__).'/partials/profile_photo.php'; ?>

    <h2><?= e($displayName) ?></h2><p class="muted">@<?= e($profile['username'] ?? '') ?></p>
    <nav class="account-links" aria-label="Your account">
      <a href="<?= e(url('/orders')) ?>">Orders <span aria-hidden="true">↗</span></a>
      <a href="<?= e(url('/favorites')) ?>">Favorites <span aria-hidden="true">↗</span></a>
      <a href="<?= e(url('/assessment')) ?>">Hair profile <span aria-hidden="true">↗</span></a>
      <a href="<?= e(url('/routine')) ?>">Saved routine <span aria-hidden="true">↗</span></a>
      <a href="<?= e(url('/settings')) ?>">Account settings <span aria-hidden="true">↗</span></a>
    </nav>
    <form method="post" action="<?= e(url('/logout')) ?>" class="account-signout"><?= csrf_field() ?><button class="btn btn-secondary btn-sm" type="submit">Sign out</button></form>
  </aside><div class="account-details">      <section aria-labelledby="account-details-title">
        <div class="account-section-heading"><h2 id="account-details-title">Account details </h2><button class="account-profile-edit" type="button" data-dialog-open="profile-edit-dialog">Edit</button></div>
        <dl class="account-detail-list">
          <?php foreach (array_diff_key($fields,['delivery_address'=>true]) as $field => $label): ?><div><dt><?= e($label) ?></dt><dd><?= trim((string) ($profile[$field] ?? '')) !== '' ? nl2br(e($profile[$field])) : 'Not added yet' ?></dd></div><?php endforeach; ?>
        </dl>
        <div class="profile-address-section"><div class="account-section-heading"><h2>Delivery address</h2><button class="account-profile-edit" type="button" data-address-open><?= empty($profile['delivery_address'])?'Add address':'Edit address' ?></button></div>
        <div class="profile-address-card"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg><p data-profile-address><?= !empty($profile['delivery_address'])?nl2br(e($profile['delivery_address'])):'No address added' ?></p></div></div>
        <?php require dirname(__DIR__).'/partials/address_dialog.php'; ?>
        <script src="<?= e(asset('js/account-address.js')) ?>" defer></script>
      </section></div></div><dialog class="account-dialog" id="profile-edit-dialog" aria-labelledby="profile-edit-title" data-dialog-auto="<?= $editing?'true':'false' ?>"><div class="account-dialog-heading"><h2 id="profile-edit-title">Edit account details</h2><span class="avela-help"><button type="button" class="info-trigger" aria-label="About changing your email" aria-describedby="profile-email-help">i</button><span role="tooltip" class="info-tooltip" id="profile-email-help">Changing your email requires a code sent to your current email address.</span></span></div>      <form method="post" action="<?= e(url('/profile/update')) ?>" class="form-stack customer-profile-form" data-unsaved-form>
        <?= csrf_field() ?>
        <?php if ($errors): ?><div class="alert alert-error" role="alert" tabindex="-1" data-error-summary><strong>Please check your details.</strong><ul><?php foreach ($errors as $field => $message): ?><li><a href="#profile-<?= e($field) ?>"><?= e($message) ?></a></li><?php endforeach; ?></ul></div><?php endif; ?>
        <?php foreach ($fields as $field => $label): $error = $errors[$field] ?? ''; $value = Session::old($field, (string) ($profile[$field] ?? '')); ?>
          <label class="field <?= $error ? 'has-error' : '' ?>" for="profile-<?= e($field) ?>"><span><?= e($label) ?><?= $field === 'delivery_address' ? ' (optional)' : '' ?></span>
            <?php if ($field === 'delivery_address'): ?><textarea id="profile-<?= e($field) ?>" name="<?= e($field) ?>" rows="3" maxlength="500" autocomplete="street-address" aria-invalid="<?= $error ? 'true' : 'false' ?>" aria-describedby="profile-<?= e($field) ?>-error"><?= e($value) ?></textarea>
            <?php else: ?><input id="profile-<?= e($field) ?>" name="<?= e($field) ?>" type="<?= $field === 'email' ? 'email' : ($field === 'contact_no' ? 'tel' : 'text') ?>" value="<?= e($value) ?>" maxlength="<?= $field === 'full_name' ? '100' : ($field === 'contact_no' ? '25' : '190') ?>" autocomplete="<?= $field === 'full_name' ? 'name' : ($field === 'contact_no' ? 'tel' : 'email') ?>" required aria-invalid="<?= $error ? 'true' : 'false' ?>" aria-describedby="profile-<?= e($field) ?>-error"><?php endif; ?>
            <small class="field-error" id="profile-<?= e($field) ?>-error"><?= e($error) ?></small>
          </label>
        <?php endforeach; ?>
        <?php if(isset($errors['delivery_address'])): ?><p class="field-error" id="profile-delivery_address" tabindex="-1"><?= e($errors['delivery_address']) ?> Use the delivery address form on your profile.</p><?php endif; ?>

        <div class="account-form-actions"><button class="text-link" type="button" data-ux-close>Cancel</button><button class="btn btn-primary" type="submit">Save changes</button></div>
      </form></dialog>