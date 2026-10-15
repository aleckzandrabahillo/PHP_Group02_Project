<div class="staff-list-tools" data-staff-tools hidden>
  <label class="field">Search <?= e($staffListLabel) ?><input type="search" data-staff-search placeholder="<?= e($staffSearchHint) ?>" autocomplete="off"></label>
  <label class="field">Filter <?= e($staffListLabel) ?><select data-staff-status><option value="">All <?= e($staffFilterLabel) ?></option>
    <?php foreach($staffStatusOptions as $value=>$label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
  </select></label>
  <label class="field">Sort <?= e($staffListLabel) ?><select data-staff-sort><option value="original">Default order</option><option value="name">Name: A–Z</option><option value="quantity"><?= e($staffQuantityLabel) ?></option></select></label>
  <button class="btn btn-secondary" type="button" data-staff-reset>Clear filters</button>
</div>
<div class="staff-list-summary" data-staff-tools hidden>
  <p class="muted small" data-staff-count role="status" aria-live="polite"></p>
  <nav aria-label="<?= e(ucfirst($staffListLabel)) ?> pages">
    <button class="btn btn-secondary btn-sm" type="button" data-staff-previous>Previous</button>
    <span data-staff-page></span>
    <button class="btn btn-secondary btn-sm" type="button" data-staff-next>Next</button>
  </nav>
</div>
<p class="staff-list-empty" data-staff-empty hidden>No matching <?= e($staffListLabel) ?>. Try another search or clear the filters.</p>
