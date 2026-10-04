<?php
$visualKey = product_visual_key((string) ($visualCategory ?? ''));
$placeholderClass = 'product-placeholder product-placeholder--' . $visualKey . (!empty($placeholderLarge) ? ' product-placeholder--large' : '');
?>
<img class="<?= e($placeholderClass) ?>" src="<?= e(product_placeholder_url((string) ($visualCategory ?? ''))) ?>" alt="" aria-hidden="true" loading="lazy">
