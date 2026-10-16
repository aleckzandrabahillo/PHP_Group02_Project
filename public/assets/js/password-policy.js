// Adapted from the reviewed registration-checklist draft; server validation remains authoritative.
function passwordRequirementState(value, minimum, maximumBytes, counts = {}) {
  const characters = Array.from(value);
  const matches = (pattern) => characters.filter((c) => pattern.test(c)).length;
  return {
    length: characters.length >= minimum,
    uppercase: matches(/[A-Z]/) >= (counts.uppercase || 1),
    lowercase: matches(/[a-z]/) >= (counts.lowercase || 1),
    number: matches(/[0-9]/) >= (counts.numbers || 1),
    special: matches(/[^A-Za-z0-9\x09-\x0d\x20]/u) >= (counts.special || 1),
    bytes: new TextEncoder().encode(value).length <= maximumBytes && !value.includes('\0'),
  };
}
document.querySelectorAll('[data-password-requirements]').forEach((input) => {
  const checklist = input.closest('label')?.querySelector('[data-password-checklist]');
  if (!checklist) return;
  checklist.hidden = true;
  const update = () => {
    checklist.hidden = false;
    const counts = Object.fromEntries(['uppercase','lowercase','numbers','special'].map((key) => [key, Number(input.dataset['password' + key[0].toUpperCase() + key.slice(1)])]));
    const state = passwordRequirementState(input.value, Number(input.dataset.passwordMinimum), Number(input.dataset.passwordMaximumBytes), counts);
    Object.entries(state).forEach(([rule, met]) => {
      const item = checklist.querySelector('[data-password-rule="' + rule + '"]');
      item?.classList.toggle('is-met', met);
      if (item) item.dataset.satisfied = String(met);
    });
    checklist.querySelector('[data-password-progress]').textContent = Object.values(state).filter(Boolean).length + ' of 6 password requirements satisfied.';
  };
  input.addEventListener('focus', update);
  input.addEventListener('input', update);
  if (input.value) update();
});
document.querySelector('[data-reveal-setup-key]')?.addEventListener('click', (event) => {
  const input = document.querySelector('#authenticator-setup-key');
  if (!input) return;
  const reveal = input.type === 'password';
  input.type = reveal ? 'text' : 'password';
  event.currentTarget.textContent = reveal ? 'Hide setup key' : 'Show setup key';
  event.currentTarget.setAttribute('aria-pressed', String(reveal));
});
