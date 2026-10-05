<section class="page-head">
  <div>
    <span class="eyebrow">PROFILE</span>
    <h1 class="sr-only">Profile</h1>
  </div>
</section>

<div class="profile-card">

  <form
    method="POST"
    action="/profile/image"
    enctype="multipart/form-data"
    class="profile-avatar-form"
  >
    <?= csrf_field() ?>

    <label
      class="profile-avatar-edit"
      for="profile_image"
      title="Change profile picture"
    >
      <div class="avatar large">
        <?php if (!empty($profile['profile_image'])): ?>
          <img
            src="<?= e(url('/' . ltrim($profile['profile_image'], '/'))) ?>"
            alt="Profile picture"
          >
        <?php else: ?>
          <?= e(strtoupper(substr($profile['full_name'] ?? $profile['username'] ?? 'A', 0, 1))) ?>
        <?php endif; ?>
      </div>

      <span class="profile-avatar-pen" aria-hidden="true">✎</span>
    </label>

    <input
      type="file"
      id="profile_image"
      name="profile_image"
      accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
      hidden
      onchange="this.form.submit()"
    >
  </form>

  <div class="profile-info">

    <form
      method="POST"
      action="<?= e(url('/profile/update')) ?>"
      id="profile-update-form"
    >
      <?= csrf_field() ?>

      <!-- Full Name: existing displayed element -->
      <h2
        id="profile-full-name"
        class="profile-full-name"
        contenteditable="false"
      ><?= e($profile['full_name'] ?? '') ?></h2>

      <!-- Email: existing displayed element -->
      <p
        id="profile-email"
        class="profile-editable profile-email"
        contenteditable="false"
      ><?= e($profile['email'] ?? '') ?></p>

      <div class="profile-meta">

        <!-- Username: NOT editable -->
        <span>
          @<?= e($profile['username'] ?? '') ?>
        </span>

        <!-- Contact Number: existing displayed element -->
        <span
          id="profile-contact"
          class="profile-editable profile-contact"
          contenteditable="false"
        ><?= e($profile['contact_no'] ?: 'No contact number yet') ?></span>

        <!-- Delivery Address: existing displayed element -->
        <span
          id="profile-address"
          class="profile-editable profile-address"
          contenteditable="false"
        ><?= e($profile['delivery_address'] ?: 'No delivery address yet') ?></span>

      </div>

      <!-- Hidden fields: no visible textboxes -->
      <input type="hidden" name="full_name" id="profile-full-name-input">
      <input type="hidden" name="email" id="profile-email-input">
      <input type="hidden" name="contact_no" id="profile-contact-input">
      <input type="hidden" name="delivery_address" id="profile-address-input">

      <div class="profile-actions">
        <button
          type="button"
          id="profile-edit-button"
          class="profile-edit-button"
        >
          Edit
        </button>
      </div>

    </form>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const editButton = document.getElementById('profile-edit-button');
  const form = document.getElementById('profile-update-form');

  const fields = [
    document.getElementById('profile-full-name'),
    document.getElementById('profile-email'),
    document.getElementById('profile-contact'),
    document.getElementById('profile-address')
  ];

  const hiddenInputs = {
    fullName: document.getElementById('profile-full-name-input'),
    email: document.getElementById('profile-email-input'),
    contactNo: document.getElementById('profile-contact-input'),
    address: document.getElementById('profile-address-input')
  };

  let editing = false;

  editButton.addEventListener('click', function () {

    if (!editing) {
      editing = true;

      fields.forEach(function (field) {
        field.contentEditable = 'true';
      });

      editButton.textContent = 'Update';

      fields[0].focus();

      const selection = window.getSelection();
      const range = document.createRange();

      range.selectNodeContents(fields[0]);
      range.collapse(false);

      selection.removeAllRanges();
      selection.addRange(range);

      return;
    }

    hiddenInputs.fullName.value = fields[0].textContent.trim();
    hiddenInputs.email.value = fields[1].textContent.trim();
    hiddenInputs.contactNo.value = fields[2].textContent.trim();
    hiddenInputs.address.value = fields[3].textContent.trim();

    form.submit();
  });
});
</script>