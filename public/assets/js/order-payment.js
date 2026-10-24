(() => {
  function mount(root = document) {
    if (typeof fetch !== 'function' || typeof FormData !== 'function') return;
    root.querySelectorAll('[data-payment-check]').forEach(form => {
      if (form.dataset.mounted) return;
      form.dataset.mounted = 'true';
      const section = form.closest('section');
      const button = form.querySelector('button');
      const feedback = section.querySelector('[data-payment-feedback]');
      let busy = false, attempts = 0, timer;
      const check = async event => {
        event?.preventDefault();
        if (busy || !form.isConnected) return;
        clearTimeout(timer); busy = true; attempts++;
        button.disabled = true; button.textContent = 'Checking payment…';
        if (feedback) feedback.textContent = 'Checking your payment securely…';
        try {
          const response = await fetch(form.action, {method: 'POST', credentials: 'same-origin',
            headers: {'Accept': 'application/json'}, body: new FormData(form),
            ...(typeof AbortSignal !== 'undefined' && typeof AbortSignal.timeout === 'function' ? {signal: AbortSignal.timeout(25000)} : {})});
          if (!response.ok || !response.headers.get('Content-Type')?.includes('application/json')) throw new Error();
          const result = await response.json();
          const label = section.querySelector('[data-payment-label]');
          if (label) label.textContent = result.label;
          if (feedback) feedback.textContent = result.message;
          if (result.ok && !['pending', 'initiating'].includes(result.state)) {
            // Reload only after authenticated server evidence has been saved, never a URL result.
            window.location.reload(); return;
          }
          if (attempts < 3 && ['pending', 'initiating'].includes(result.state)) {
            timer = setTimeout(() => { if (!document.hidden) check(); }, Math.max(30, result.retry_after || 30) * 1000);
          }
        } catch (_) {
          if (feedback) feedback.textContent = 'We could not check your payment yet. Please try again shortly.';
        } finally {
          busy = false; button.disabled = false; button.textContent = 'Check payment status';
        }
      };
      form.addEventListener('submit', check);
      if (form.dataset.autoCheck === 'true' && !document.hidden) check();
      window.addEventListener('pagehide', () => clearTimeout(timer), {once: true});
    });
  }
  mount();
  document.addEventListener('avela:content', event => mount(event.detail?.root || document));
})();
