<?php $locationValues=$locationValues??[];$locationPrefix=$locationPrefix??'checkout'; ?>
<input type="hidden" name="location_version" value="2">
<label class="checkout-field checkout-field--full" data-province-field><span>Province / metropolitan area</span>
 <select id="<?= e($locationPrefix) ?>-province" name="province" required autocomplete="address-level3" aria-invalid="<?= isset($errors['province'])?'true':'false' ?>" data-province-select>
  <option value="">Select province or area</option>
  <?php foreach(\App\Services\ShippingService::REGIONS as $regionCode=>$regionName): ?><optgroup label="<?= e($regionName) ?>" data-region="<?= e($regionCode) ?>">
  <?php foreach(\App\Services\PhilippineLocations::options($regionCode) as $province): ?><option value="<?= e($province) ?>" <?= ($locationValues['province']??'')===$province&&($locationValues['region']??'')===$regionCode?'selected':'' ?>><?= e($province) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
 </select><small class="field-error" id="<?= e($locationPrefix) ?>-province-error" data-address-field="province"><?= e($errors['province']??'') ?></small>
</label>
