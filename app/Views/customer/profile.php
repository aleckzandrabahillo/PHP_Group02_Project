<?php
use App\Core\Session;
$profile = $profile ?? [];
$errors = Session::get('errors', []);
$editing = ($_GET['edit'] ?? '') === '1' || (bool) $errors;
$displayName = ($profile['full_name'] ?? '') ?: ($profile['username'] ?? 'Your account');
$fields = ['full_name' => 'Full name', 'email' => 'Email address', 'contact_no' => 'Contact number'];
?>
<section class="page-head profile-heading">
  <div><h1><?= $editing ? 'Edit profile' : 'Your profile' ?></h1></div>

</section>
<div class="account-layout">
  <aside class="account-overview">
    <div class="avatar large account-avatar">
      <?php if (!empty($profile['profile_image'])): ?><img src="<?= e(url('/' . ltrim($profile['profile_image'], '/'))) ?>" alt="Your profile photograph"><?php else: ?><span aria-hidden="true"><?= e(mb_strtoupper(mb_substr($displayName, 0, 1))) ?></span><?php endif; ?>
    </div>

    <h2><?= e($displayName) ?></h2><p class="muted">@<?= e($profile['username'] ?? '') ?></p>
    <nav class="account-links" aria-label="Your account">
      <a href="<?= e(url('/orders')) ?>">Orders <span aria-hidden="true">↗</span></a>
      <a href="<?= e(url('/favorites')) ?>">Favorites <span aria-hidden="true">↗</span></a>
      <a href="<?= e(url('/assessment')) ?>">Hair profile <span aria-hidden="true">↗</span></a>
      <a href="<?= e(url('/routine')) ?>">Saved routine <span aria-hidden="true">↗</span></a>
      <a href="<?= e(url('/settings')) ?>">Account settings <span aria-hidden="true">↗</span></a>
    </nav>
    <form method="post" action="<?= e(url('/logout')) ?>" class="account-signout"><?= csrf_field() ?><button class="btn btn-secondary btn-sm" type="submit">Sign out</button></form>
  </aside>
  <div class="account-details">
    <?php if (!$editing): ?>
      <section aria-labelledby="account-details-title">
        <div class="account-section-heading"><h2 id="account-details-title">Account details <a class="profile-pencil icon-button" href="<?= e(url('/profile?edit=1')) ?>" aria-label="Edit account details"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 5 4 4M4 20l4-1L20 7a2.8 2.8 0 0 0-4-4L4 15z"/></svg></a></h2></div>
        <dl class="account-detail-list">
          <?php foreach (array_diff_key($fields,['delivery_address'=>true]) as $field => $label): ?><div><dt><?= e($label) ?></dt><dd><?= trim((string) ($profile[$field] ?? '')) !== '' ? nl2br(e($profile[$field])) : 'Not added yet' ?></dd></div><?php endforeach; ?>
        </dl>
        <div class="profile-address-section"><div class="account-section-heading"><h2>Delivery address</h2><button class="account-profile-edit" type="button" data-address-open><?= empty($profile['delivery_address'])?'Add address':'Edit address' ?></button></div>
        <div class="profile-address-card"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg><p data-profile-address><?= !empty($profile['delivery_address'])?nl2br(e($profile['delivery_address'])):'No address added' ?></p></div></div>
        <?php require dirname(__DIR__).'/partials/address_dialog.php'; ?>
        <script src="<?= e(asset('js/account-address.js')) ?>" defer></script>
      </section>
    <?php else: ?>
      <form method="post" action="<?= e(url('/profile/update')) ?>" class="form-stack customer-profile-form" data-unsaved-form>
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
        <p class="verification-note" id="profile-verification-help">Name and contact changes save directly. Changing your email requires a code sent to your current email address.</p>
        <div class="account-form-actions"><button class="btn btn-primary" type="submit" aria-describedby="profile-verification-help">Save changes</button><a class="text-link" href="<?= e(url('/profile')) ?>" data-profile-cancel>Cancel</a></div>
      </form>
      <details class="profile-photo-editor">
        <summary>Change profile photograph <span class="muted">(optional)</span></summary>
        <form method="post" action="<?= e(url('/profile/image')) ?>" enctype="multipart/form-data" class="customer-avatar-form">
          <?= csrf_field() ?>
          <p class="muted small">Your photograph is saved separately from your account details.</p>
          <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
          <label class="field" for="profile_image"><span>Choose a JPG, PNG or WebP, up to 5 MB</span><input type="file" id="profile_image" name="profile_image" data-image-upload accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required></label>
          <button class="btn btn-secondary" type="submit">Update photograph</button>
        </form>
      </details>
    <?php endif; ?>

  </div>
</div>
