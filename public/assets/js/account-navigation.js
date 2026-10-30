// Progressive account navigation. Server routes still authorize every request;
// forms, assessment editing, recovery codes and non-account links navigate normally.
(() => {
  'use strict';
  const shell = document.querySelector('.account-shell');
  const sidebar = shell?.querySelector('.account-sidebar');
  if (!sidebar || !shell.querySelector('.account-main')) return;
  const allowed = path => ['/profile', '/orders', '/assessment', '/routine', '/settings', '/settings/security', '/account/password', '/account/mfa'].includes(path);
  const scripts = new Set(['password-policy.js', 'qr-encoder.js', 'mfa-setup.js', 'routine-guide.js', 'account-address.js']);
  let pending = null, serial = 0, position = 0, restoring = false;

  function dirty() {
    return [...shell.querySelectorAll('.account-main form[method="post"] input:not([type="hidden"]),.account-main form[method="post"] textarea,.account-main form[method="post"] select')]
      .filter(input => !input.disabled && input.getClientRects().length)
      .some(input => input.type === 'file' ? input.files.length :
        ['checkbox', 'radio'].includes(input.type) ? input.checked !== input.defaultChecked :
        input instanceof HTMLSelectElement ? [...input.options].some(option => option.selected !== option.defaultSelected) : input.value !== input.defaultValue);
  }

  async function loadAsset(node) {
    const target = new URL(node.src || node.href, location.href);
    if (target.origin !== location.origin) throw new Error('Unexpected account asset');
    const script = node.tagName === 'SCRIPT';
    if (script ? !scripts.has(target.pathname.split('/').pop()) || !target.pathname.endsWith('/assets/js/' + target.pathname.split('/').pop()) : !target.pathname.endsWith('/assets/css/mfa-setup.css')) throw new Error('Unexpected account asset');
    if ([...document.querySelectorAll(script ? 'script[src]' : 'link[rel="stylesheet"]')].some(existing => (existing.src || existing.href) === target.href)) return;
    await new Promise((resolve, reject) => {
      const asset = document.createElement(script ? 'script' : 'link');
      if (script) asset.src = target.href;
      else { asset.rel = 'stylesheet'; asset.href = target.href; }
      const timeout = setTimeout(() => { asset.remove(); reject(new Error('Account asset timed out')); }, 8000);
      asset.onload = () => { clearTimeout(timeout); resolve(); };
      asset.onerror = () => { clearTimeout(timeout); asset.remove(); reject(new Error('Account asset unavailable')); };
      document.head.append(asset);
    });
  }

  async function navigate(url, push = true, entry = null) {
    pending?.abort();
    const request = new AbortController(), job = ++serial;
    pending = request;
    const main = shell.querySelector('.account-main');
    const timer = setTimeout(() => request.abort(), 10000);
    main.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(url, {credentials: 'same-origin', cache: 'no-store', redirect: 'manual', signal: request.signal});
      if (response.redirected || !response.ok || !response.headers.get('Content-Type')?.includes('text/html')) throw new Error('Use normal navigation');
      const html = await response.text();
      if (job !== serial) return;
      if (html.length > 3000000) throw new Error('Unexpected account response');
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const next = doc.querySelector('.account-shell .account-main');
      const nextSidebar = doc.querySelector('.account-shell .account-sidebar');
      if (!next || nextSidebar?.dataset.accountUser !== sidebar.dataset.accountUser) throw new Error('Account context changed');
      doc.querySelectorAll('.customer-main>.notification-stack [data-flash],.customer-main>[data-error-summary]').forEach(node => next.prepend(node));
      // Load only known, same-origin page assets. Never evaluate fetched inline code.
      const pageScripts = [...next.querySelectorAll('script[src]')];
      for (const style of next.querySelectorAll('link[rel="stylesheet"]')) await loadAsset(style);
      if (job !== serial) return;
      next.querySelectorAll('script:not([type="application/json"]),link[rel="stylesheet"]').forEach(node => node.remove());
      // Let each dialog owner restore its scroll lock before its content is removed.
      for (const dialog of main.querySelectorAll('dialog[open]')) {
        if (window.AvelaMotion) await window.AvelaMotion.closeDialog(dialog);
        else dialog.close();
      }
      if (job !== serial) return;
      document.dispatchEvent(new CustomEvent('avela:beforecontent', {detail: {root: main}}));
      main.replaceChildren(...next.childNodes);
      main.className = next.className;
      main.tabIndex = -1;
      document.title = 'Avela';
      sidebar.querySelectorAll('.account-nav>a').forEach(link => {
        const match = [...nextSidebar.querySelectorAll('.account-nav>a')].find(item => item.href === link.href);
        if (match?.hasAttribute('aria-current')) link.setAttribute('aria-current', 'page');
        else link.removeAttribute('aria-current');
      });
      if (push) {
        history.replaceState({...history.state, accountScroll: scrollY}, '', location.href);
        history.pushState({avelaAccount: true, accountPosition: ++position}, '', url);
      } else position = entry?.accountPosition ?? position;
      for (const script of pageScripts) await loadAsset(script);
      if (job !== serial) return;
      document.dispatchEvent(new CustomEvent('avela:content', {detail: {root: main}}));
      main.focus({preventScroll: true});
      window.scrollTo({top: push ? Math.max(0, shell.offsetTop - 112) : (entry?.accountScroll ?? 0), behavior: 'instant'});
      window.AvelaMotion?.reveal(main, 'fast');
    } catch (error) {
      if (job === serial) location.assign(url);
    } finally {
      clearTimeout(timer);
      if (job === serial) main.removeAttribute('aria-busy');
    }
  }

  document.addEventListener('click', event => {
    const link = event.target.closest?.('.account-nav>a[href],.account-main a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target || link.hasAttribute('download')) return;
    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin || !allowed(url.pathname) || url.search || url.hash) return;
    if (url.href === location.href) { event.preventDefault(); return; }
    if (dirty() && !window.confirm('Leave this page without saving your changes?')) { event.preventDefault(); return; }
    event.preventDefault(); navigate(url);
  });
  history.replaceState({...history.state, avelaAccount: true, accountPosition: position}, '', location.href);
  window.addEventListener('popstate', event => {
    if (restoring) { restoring = false; return; }
    const url = new URL(location.href);
    if (Number.isInteger(event.state?.accountPosition) && dirty() && !window.confirm('Leave this page without saving your changes?')) {
      restoring = true; history.go(position - event.state.accountPosition); return;
    }
    if (allowed(url.pathname) && !url.search) navigate(url, false, event.state);
    else location.reload();
  });
})();
