<?php $showStoreFooter=false;
use App\Core\Session;
$profile = $profile ?? [];
$errors = Session::get('errors', []);
Session::forget('errors');
$editing = ($_GET['edit'] ?? '') === '1' || (bool) $errors;
$displayName = ($profile['full_name'] ?? '') ?: ($profile['username'] ?? 'Your account');
$fields = ['full_name' => 'Full name', 'username' => 'Username', 'email' => 'Email address', 'contact_no' => 'Contact number'];
?>
<section class="account-shell"><?php require dirname(__DIR__).'/partials/account_navigation.php'; ?><div class="account-main"><h1 class="visually-hidden">Your profile</h1><section class="account-section"><div class="account-section-heading"><h2 id="account-details-title">Account details</h2><button class="button secondary compact-button" type="button" data-dialog-open="profile-edit-dialog">Edit</button></div><div class="account-fields">
<?php foreach(['full_name'=>'Full name','username'=>'Username','email'=>'Email address','contact_no'=>'Contact number'] as $field=>$label): ?><div class="account-field"><span><?= e($label) ?></span><strong><?= trim((string)($profile[$field]??''))!==''?e(($field==='username'?'@':'').$profile[$field]):'Not added yet' ?></strong></div><?php endforeach; ?></div></section>
<section class="account-section"><div class="account-section-heading"><h2>Delivery addresses</h2><button class="button secondary compact-button" type="button" data-address-open data-address-new>Add address</button></div><p class="address-feedback" role="status" data-address-feedback></p><div class="address-list" data-address-list><?php require dirname(__DIR__).'/partials/address_cards.php'; ?></div></section>
<?php require dirname(__DIR__).'/partials/address_dialog.php'; ?><script src="<?= e(asset('js/account-address.js')) ?>" defer></script>
<dialog class="account-dialog" id="profile-edit-dialog" aria-labelledby="profile-edit-title" data-dialog-auto="<?= $editing?'true':'false' ?>"><div class="account-dialog-heading"><h2 id="profile-edit-title">Edit account details</h2><span class="avela-help"><button type="button" class="info-trigger" aria-label="About changing your email" aria-describedby="profile-email-help">i</button><span role="tooltip" class="info-tooltip" id="profile-email-help">Changing your email requires a code sent to your current email address.</span></span></div>      <form method="post" action="<?= e(url('/profile/update')) ?>" class="form-stack customer-profile-form" data-unsaved-form>
        <?= csrf_field() ?>
        <input type="hidden" name="expected_username" value="<?= e((string)($profile['username']??'')) ?>">
        <?php if ($errors): ?><div class="alert alert-error" role="alert" tabindex="-1" data-error-summary><strong>Please check your details.</strong><ul><?php foreach ($errors as $field => $message): ?><li><a href="#profile-<?= e($field) ?>"><?= e($message) ?></a></li><?php endforeach; ?></ul></div><?php endif; ?>
        <?php foreach ($fields as $field => $label): $error = $errors[$field] ?? ''; $value = Session::old($field, (string) ($profile[$field] ?? '')); ?>
          <label class="field <?= $error ? 'has-error' : '' ?>" for="profile-<?= e($field) ?>"><span><?= e($label) ?><?= $field === 'delivery_address' ? ' (optional)' : '' ?></span>
            <?php if ($field === 'delivery_address'): ?><textarea id="profile-<?= e($field) ?>" name="<?= e($field) ?>" rows="3" maxlength="500" autocomplete="street-address" aria-invalid="<?= $error ? 'true' : 'false' ?>" aria-describedby="profile-<?= e($field) ?>-error"><?= e($value) ?></textarea>
            <?php else: ?><input id="profile-<?= e($field) ?>" name="<?= e($field) ?>" type="<?= $field === 'email' ? 'email' : ($field === 'contact_no' ? 'tel' : 'text') ?>" value="<?= e($value) ?>" maxlength="<?= $field === 'full_name' ? '100' : ($field === 'username' ? '40' : ($field === 'contact_no' ? '25' : '190')) ?>" autocomplete="<?= $field === 'full_name' ? 'name' : ($field === 'username' ? 'username' : ($field === 'contact_no' ? 'tel' : 'email')) ?>" required aria-invalid="<?= $error ? 'true' : 'false' ?>" aria-describedby="profile-<?= e($field) ?>-error"><?php endif; ?>
            <small class="field-error" id="profile-<?= e($field) ?>-error"><?= e($error) ?></small>
          </label>
        <?php endforeach; ?>
        <?php if(isset($errors['delivery_address'])): ?><p class="field-error" id="profile-delivery_address" tabindex="-1"><?= e($errors['delivery_address']) ?> Use the delivery address form on your profile.</p><?php endif; ?>

        <div class="account-form-actions"><button class="text-link" type="button" data-ux-close>Cancel</button><button class="btn btn-primary" type="submit">Save changes</button></div>
      </form></dialog>
</div></section>
