<?php
use App\Core\Session;
$flashes = Session::flashes();
$errors = Session::get('errors', []);
$showErrorSummary = is_array($errors) && $errors && !isset($errors['otp']) && !str_contains($content ?? '', 'data-error-summary');
Session::forget('errors');
?>
<?php foreach ($flashes as $flash): ?>
<?php $transient = $flash['type'] === 'success' && in_array($flash['message'], ['You have been signed out.', 'Saved to favorites.', 'Removed from favorites.', 'Added to your bag.', 'Bag updated.', 'Removed from your bag.', 'Your profile has been updated successfully.', 'Profile picture updated successfully.'], true); ?>
<div class="alert alert-<?= e($flash['type']) ?>" role="<?= in_array($flash['type'], ['warning', 'error'], true) ? 'alert' : 'status' ?>" data-flash <?= $transient ? 'data-flash-transient' : '' ?>><span><?= e($flash['message']) ?></span><?php if ($transient): ?><button class="flash-dismiss" type="button" aria-label="Dismiss notification" data-flash-dismiss>×</button><?php endif; ?></div>
<?php endforeach; ?>
<?php if ($showErrorSummary): ?>
<div class="alert alert-error" role="alert" tabindex="-1" data-error-summary>Please check the highlighted fields and try again.</div>
<?php endif; ?>
