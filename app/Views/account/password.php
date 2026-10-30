<?php $customerPassword = (auth_user()['role'] ?? '') === 'customer'; ?>
<?php if ($customerPassword): ?><section class="account-shell"><?php require dirname(__DIR__).'/partials/account_navigation.php'; ?><div class="account-main account-security-main"><?php endif; ?>
<?php if ($customerPassword): ?><nav class="product-breadcrumb" aria-label="Breadcrumb"><a href="<?= e(url('/settings')) ?>">Account settings</a><span aria-hidden="true">/</span><span>Change password</span></nav><?php endif; ?>
<section class="page-head account-security-heading"><div><?php if (!$customerPassword): ?><span class="eyebrow">ACCOUNT SECURITY</span><?php endif; ?><h1>Change password</h1></div></section>
<section class="panel security-panel">
  <?php if (!empty($passwordDeadline)): ?><p class="muted">Please update your password by <?= e(date('F j, Y', $passwordDeadline)) ?>.</p><?php endif; ?>
  <form class="form-stack" method="post" action="<?= e(url('/account/password')) ?>" novalidate>
    <?= csrf_field() ?>
    <label class="field">Current password<input type="password" name="current_password" autocomplete="current-password" required maxlength="256"></label>
    <label class="field">New password
      <input id="new-password" type="password" name="password" autocomplete="new-password" required data-password-requirements
        data-password-minimum="<?= (int)$passwordPolicy['password_min_length'] ?>" data-password-maximum-bytes="72"
        data-password-uppercase="<?= (int)$passwordPolicy['password_min_uppercase'] ?>" data-password-lowercase="<?= (int)$passwordPolicy['password_min_lowercase'] ?>"
        data-password-numbers="<?= (int)$passwordPolicy['password_min_numbers'] ?>" data-password-special="<?= (int)$passwordPolicy['password_min_special'] ?>"
        aria-describedby="new-password-help">
      <?php $passwordInputId='new-password';require dirname(__DIR__).'/partials/password_requirements.php'; ?>
    </label>
    <label class="field">Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password" required></label>
    <div><button class="btn btn-primary" type="submit">Save new password</button></div>
  </form>
</section>
<script src="<?= e(asset('js/password-policy.js')) ?>" defer></script>

<?php if ($customerPassword): ?></div></section><?php endif; ?>
