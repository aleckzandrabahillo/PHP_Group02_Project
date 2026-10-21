<?php if(auth_user()): ?>
<script type="application/json" id="session-watch-config"><?= json_encode(['status'=>url('/account/session'),'activity'=>url('/account/session/activity'),'token'=>\App\Core\Csrf::token(),'login'=>url('/login')],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<script src="<?= e(asset('js/session-watch.js')) ?>" defer></script>
<?php endif; ?>
