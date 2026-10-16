(() => {
  const form = document.querySelector('[data-activation-form]');
  if (!form) return;
  const token = new URLSearchParams(location.hash.slice(1)).get('token') || '';
  // Fragments never reach server access logs; remove before any following navigation.
  history.replaceState(null, '', location.pathname);
  if (!/^[a-f0-9]{64}$/.test(token)) return;
  form.elements.activation_token.value = token;
  form.querySelector('button[type="submit"]').disabled = false;
  form.querySelector('[data-activation-status]').textContent = 'Your link is ready. Choose Activate account to continue.';
  form.addEventListener('submit', () => {
    form.querySelector('button[type="submit"]').disabled = true;
  });
})();
