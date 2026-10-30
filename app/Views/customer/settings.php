<?php require_once dirname(__DIR__).'/partials/handoff_art.php'; ?><section class="account-shell"><?php require dirname(__DIR__).'/partials/account_navigation.php'; ?><div class="account-main"><header class="account-section-heading"><h1 class="account-page-title">Account Settings</h1></header>
<div class="settings-layout">

  <a class="settings-entry" href="<?= e(url('/settings/security')) ?>">
    <span><strong>2-step verification</strong></span>
  </a>
  <a class="settings-entry" href="<?= e(url('/account/password')) ?>">
    <span><strong>Change password</strong></span>
  </a>

</div>
</div></section>