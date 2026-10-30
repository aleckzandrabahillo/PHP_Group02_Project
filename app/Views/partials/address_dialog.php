<?php $addressValues=\App\Core\DeliveryValidator::prefill([...$profile,'delivery_address'=>'']); ?>
<dialog class="account-dialog" id="address-dialog" aria-labelledby="address-title">
 <div class="account-dialog-heading"><h2 id="address-title">Add delivery address</h2></div>
 <form method="post" action="<?= e(url('/profile/address')) ?>" data-address-form>
  <?= csrf_field() ?><input type="hidden" name="address_id" value="-1"><input type="hidden" name="revision" value="">
  <div class="alert alert-error" role="alert" tabindex="-1" data-address-error hidden></div>
  <div class="checkout-field-grid">
   <label class="checkout-field checkout-field--full"><span>Country</span><select name="country" autocomplete="country" required><option value="PH">Philippines</option></select><small class="field-error" data-address-field="country"></small></label>
   <?php foreach(['first_name'=>['First name','given-name',60],'last_name'=>['Last name','family-name',60],'address_line'=>['Address','address-line1',200],'barangay'=>['Barangay','address-line2',100],'postal_code'=>['Postal code','postal-code',4],'city'=>['City / Municipality','address-level2',100]] as $field=>[$label,$autocomplete,$maximum]): ?>
   <label class="checkout-field <?= in_array($field,['address_line','barangay','contact_no'],true)?'checkout-field--full':'' ?>"><span><?= e($label) ?></span><input id="address-<?= e($field) ?>" name="<?= e($field) ?>" value="<?= e($addressValues[$field]??'') ?>" autocomplete="<?= e($autocomplete) ?>" maxlength="<?= $maximum ?>" <?= $field==='contact_no'?'type="tel"':'' ?> <?= $field==='postal_code'?'inputmode="numeric" pattern="[1-9][0-9]{3}"':'' ?> required aria-describedby="address-<?= e($field) ?>-error"><small class="field-error" id="address-<?= e($field) ?>-error" data-address-field="<?= e($field) ?>"></small></label>
   <?php endforeach; ?>
   <?php $locationValues=$addressValues;$locationPrefix='address';require __DIR__.'/region_field.php'; ?>
   <label class="checkout-field checkout-field--full"><span>Phone</span><input id="address-contact_no" name="contact_no" type="tel" autocomplete="tel" maxlength="25" value="<?= e($addressValues['contact_no']??'') ?>" required aria-describedby="address-contact_no-error"><small class="field-error" id="address-contact_no-error" data-address-field="contact_no"></small></label>
  </div>
  <label class="address-default-choice"><input type="checkbox" name="make_default" value="1"> Use as my default address</label>
  <div class="account-form-actions"><button type="button" class="text-link" data-dialog-close>Cancel</button><button type="submit" class="btn btn-primary">Save address</button></div>
 </form>
</dialog>
