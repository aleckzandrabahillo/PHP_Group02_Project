<div id="<?= e($passwordInputId) ?>-help" class="password-requirements" data-password-checklist>
  <p>Password requirements</p>
  <ul>
    <li data-password-rule="length">At least <?= (int)$passwordPolicy['password_min_length'] ?> characters</li>
    <li data-password-rule="uppercase"><?= (int)$passwordPolicy['password_min_uppercase'] ?> uppercase letter(s) (A–Z)</li>
    <li data-password-rule="lowercase"><?= (int)$passwordPolicy['password_min_lowercase'] ?> lowercase letter(s) (a–z)</li>
    <li data-password-rule="number"><?= (int)$passwordPolicy['password_min_numbers'] ?> number(s) (0–9)</li>
    <li data-password-rule="special"><?= (int)$passwordPolicy['password_min_special'] ?> special character(s)</li>
    <li data-password-rule="bytes">No more than 72 UTF-8 bytes</li>
  </ul>
  <p class="sr-only" role="status" data-password-progress></p>
</div>
