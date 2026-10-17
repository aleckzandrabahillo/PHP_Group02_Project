<section class="page-head"><div><span class="eyebrow">ACCOUNT SECURITY</span><h1>Change password</h1><p>Confirm your current password. After saving, sign in again on each device with your usual verification.</p></div></section>
<section class="panel security-panel">
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
    <div><button class="btn btn-primary" type="submit">Change password and sign out</button></div>
  </form>
  <p><a href="<?= e(url('/account/mfa')) ?>">Manage two-step verification</a></p>
</section>
<script src="<?= e(asset('js/password-policy.js')) ?>" defer></script>
