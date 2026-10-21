<?php
use App\Core\Session;
$flashes = Session::flashes();
$errors = Session::get('errors', []);
$showErrorSummary = is_array($errors) && $errors && !isset($errors['otp']) && !str_contains($content ?? '', 'data-error-summary');
Session::forget('errors');
?>
<div class="notification-stack"><?php foreach ($flashes as $flash): ?>
<?php $flash['type']=$flash['type']==='critical'?'error':$flash['type'];$transient = !in_array($flash['type'], ['error','critical'], true); ?>
<div class="alert alert-<?= e($flash['type']) ?>" role="<?= in_array($flash['type'], ['warning', 'error'], true) ? 'alert' : 'status' ?>" data-flash <?= $transient ? 'data-flash-transient' : '' ?>><span><?= e($flash['message']) ?></span><button class="flash-dismiss" type="button" aria-label="Dismiss notification" data-flash-dismiss>×</button></div>
<?php endforeach; ?></div>
<?php if ($showErrorSummary): ?>
<div class="alert alert-error" role="alert" tabindex="-1" data-error-summary>Please check the highlighted fields and try again.</div>
<?php endif; ?>
