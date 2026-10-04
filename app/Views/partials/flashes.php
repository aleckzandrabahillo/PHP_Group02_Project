<?php
use App\Core\Session;
$flashes = Session::flashes();
$errors = Session::get('errors', []);
$showErrorSummary = is_array($errors) && $errors && !isset($errors['otp']);
Session::forget('errors');
?>
<?php foreach ($flashes as $flash): ?>
<div class="alert alert-<?= e($flash['type']) ?>" role="status" data-flash <?= ($flash['type'] ?? '') === 'success' ? 'data-auto-dismiss="3200"' : '' ?>><?= e($flash['message']) ?></div>
<?php endforeach; ?>
<?php if ($showErrorSummary): ?>
<div class="alert alert-error" role="alert">Please check the highlighted fields and try again.</div>
<?php endif; ?>
