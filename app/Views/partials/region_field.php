<?php $area=\App\Services\PhilippineLocations::selected($locationValues);$areas=\App\Services\PhilippineLocations::areas(); ?>
<label class="checkout-field checkout-field--full"><span>Region</span><select id="<?= e($locationPrefix) ?>-region" name="region" autocomplete="address-level1" required aria-invalid="<?= isset($errors['region'])||isset($errors['province'])?'true':'false' ?>"><option value="">Select region</option>
<?php foreach($areas as $name=>$geographicRegion): ?><option value="<?= e($name) ?>" <?= $area===$name?'selected':'' ?>><?= e($name) ?></option><?php endforeach; ?>
<?php if($area!==''&&!isset($areas[$area])&&isset(\App\Services\ShippingService::REGIONS[$area])): ?><option value="" selected disabled>Choose your delivery region</option><?php endif; ?>
</select><small class="field-error" data-address-field="region"><?= e($errors['region']??$errors['province']??'') ?></small></label>
<input type="hidden" name="location_version" value="2">