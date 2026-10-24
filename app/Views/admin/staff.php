<section class="page-head"><div><h1>Staff &amp; roles</h1><p>Manage your store team and their access.</p></div></section>
<details class="panel staff-create-account" <?= $formErrors?'open':'' ?>><summary>Add staff account</summary>
<?php if($formErrors): ?><div class="alert alert-error" role="alert"><p>Review the staff account details.</p><ul><?php foreach($formErrors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="<?= e(url('/admin/staff/create')) ?>" class="form-stack" novalidate>
<?= csrf_field() ?>
<div class="field-grid two">
<?php foreach(['full_name'=>['Full name',120,'text'],'email'=>['Email',190,'email'],'username'=>['Username',40,'text']] as $field=>[$label,$max,$type]): ?>
<label class="field"><span><?= e($label) ?></span><input name="<?= e($field) ?>" type="<?= e($type) ?>" maxlength="<?= $max ?>" required value="<?= e($formValues[$field]??'') ?>" aria-invalid="<?= isset($formErrors[$field])?'true':'false' ?>"></label>
<?php endforeach; ?>
<label class="field"><span>Role</span><select name="role"><?php foreach(['catalog_manager'=>'Catalog Manager','admin'=>'Administrator'] as $role=>$label): ?><option value="<?= e($role) ?>" <?= ($formValues['role']??'catalog_manager')===$role?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
</div>
<label class="field"><span>Password</span><input type="password" id="staff-password" name="password" required autocomplete="new-password" data-password-requirements data-password-minimum="<?= (int)$passwordPolicy['password_min_length'] ?>" data-password-maximum-bytes="72" data-password-uppercase="<?= (int)$passwordPolicy['password_min_uppercase'] ?>" data-password-lowercase="<?= (int)$passwordPolicy['password_min_lowercase'] ?>" data-password-numbers="<?= (int)$passwordPolicy['password_min_numbers'] ?>" data-password-special="<?= (int)$passwordPolicy['password_min_special'] ?>" aria-describedby="staff-password-help" aria-invalid="<?= isset($formErrors['password'])?'true':'false' ?>"></label>
<?php $passwordInputId='staff-password';require dirname(__DIR__).'/partials/password_requirements.php'; ?>
<p class="muted small">Staff sign in with their password and email code, followed by 2-step verification if enabled.</p>
<button class="btn btn-primary" type="submit">Create staff account</button>
</form></details>
<?php $staffMode=true;require dirname(__DIR__).'/partials/admin_accounts.php'; ?>
<script src="<?= e(asset('js/password-policy.js')) ?>" defer></script>
