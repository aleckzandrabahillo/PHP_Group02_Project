<?php /* Server-rendered, escaped account-owned address cards, also returned by the AJAX endpoint. */ ?>
<?php if(!$addresses): ?><p class="no-address">No delivery addresses added.</p><?php endif; ?>
<?php foreach($addresses as $address): ?>
<article class="address-card" data-address-card>
 <div class="address-card-heading"><strong><?= e(trim(($address['first_name']??'').' '.($address['last_name']??''))) ?></strong><?php if($address['is_default']): ?><span class="address-default">Default</span><?php endif; ?></div>
 <p><?= e($address['delivery_address']??'') ?></p><p><?= e($address['contact_no']??'') ?></p>
 <div class="address-card-actions">
  <button type="button" class="text-link" data-address-open data-address="<?= e(json_encode($address,JSON_THROW_ON_ERROR)) ?>">Edit</button>
  <?php foreach(($address['is_default']?['delete'=>'Delete']:['default'=>'Set as default','delete'=>'Delete']) as $action=>$label): ?>
  <form method="post" action="<?= e(url('/profile/address')) ?>" data-address-action><?= csrf_field() ?><input type="hidden" name="address_id" value="<?= (int)$address['id'] ?>"><input type="hidden" name="revision" value="<?= e($address['revision']) ?>"><input type="hidden" name="action" value="<?= $action ?>"><button type="submit" class="text-link"><?= $label ?></button></form>
  <?php endforeach; ?>
 </div>
</article>
<?php endforeach; ?>
