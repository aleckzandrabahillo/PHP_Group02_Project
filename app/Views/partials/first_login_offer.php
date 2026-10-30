<?php
use App\Core\Session;
$offerUser = auth_user();
$showFirstLoginOffer = ($offerUser['role'] ?? '') === 'customer'
    && Session::get('first_login_mfa_offer') === (int) $offerUser['id'];
if ($showFirstLoginOffer):
    Session::forget('first_login_mfa_offer');
?>
<dialog class="account-dialog first-login-offer" id="first-login-offer" aria-labelledby="first-login-offer-title" data-dialog-auto="true">
  <div class="account-dialog-heading"><h2 id="first-login-offer-title">A little extra protection.</h2><button class="icon-button" type="button" data-ux-close aria-label="Close 2-step verification suggestion">×</button></div>
  <p>Add an authenticator to protect your sign-in with 2-step verification. You can also set this up later in Account settings.</p>
  <div class="dialog-actions"><button class="btn btn-secondary" type="button" data-ux-close>Maybe later</button><a class="btn btn-primary" href="<?= e(url('/account/mfa')) ?>">Set up 2-step verification</a></div>
</dialog>
<?php endif; unset($offerUser, $showFirstLoginOffer); ?>
