(() => {
  'use strict';
  let dispose = () => {}, mounted = null;
  function initialize() {
    const setup = document.querySelector('[data-mfa-setup]');
    if (setup && setup === mounted) return;
    dispose(); mounted = setup;
    if (!setup) return;
    const find = selector => setup.querySelector(selector);
    const key = find('#authenticator-setup-key'), frame = find('[data-mfa-qr]');
    const dialog = find('#mfa-qr-dialog'), openButton = find('[data-mfa-open-qr]');
    const copy = find('[data-mfa-copy]'), announcement = find('[data-mfa-announcement]');
    const panels = [...setup.querySelectorAll('[data-mfa-step]')];
    const fitKey = () => {
      if (!key?.style || key.getClientRects?.().length === 0) return;
      key.style.height = 'auto';
      key.style.height = `${key.scrollHeight + Math.max(0, key.offsetHeight - key.clientHeight)}px`;
    };
    let uri = frame?.getAttribute('data-provisioning-uri') || '';
    frame?.removeAttribute('data-provisioning-uri');
    const expires = Number(setup.dataset.expiresAt) * 1000;
    let timer, copyTimer, expired = false, disposed = false, warned = false, version = 0;
    let oldOverflow = '';
    const listeners = [];
    const on = (node, event, handler) => { node.addEventListener(event, handler); listeners.push(() => node.removeEventListener(event, handler)); };
    const closeQR = (immediate = false) => {
      if (!dialog.open) return;
      if (immediate) dialog.close();
      else if (window.AvelaMotion) window.AvelaMotion.closeDialog(dialog);
      else dialog.close();
    };
    const clear = () => {
      clearInterval(timer); clearTimeout(copyTimer); uri = '';
      frame?.replaceChildren();
      if (key) { key.value = ''; key.textContent = ''; key.removeAttribute('value'); }
      setup.querySelectorAll('[data-mfa-confirm] input:not([type=hidden])').forEach(input => { input.value = ''; });
    };
    const expire = () => {
      if (expired || disposed) return;
      expired = true; version++;
      const wasFocused = setup.contains(document.activeElement);
      clear(); closeQR(true);
      panels.forEach(panel => panel.getAnimations?.().forEach(animation => animation.cancel()));
      setup.querySelectorAll('[data-mfa-active]').forEach(node => { node.hidden = true; });
      setup.querySelectorAll('[data-mfa-confirm] input:not([type=hidden]), [data-mfa-confirm] button').forEach(node => { node.disabled = true; });
      copy.disabled = openButton.disabled = true;
      find('[data-mfa-next]').hidden = true;
      const message = find('[data-mfa-expired]'); message.hidden = false;
      announcement.textContent = 'Your setup time has ended. Start again to continue.';
      if (wasFocused) message.focus({ preventScroll: true });
    };
    const tick = () => {
      if (!Number.isFinite(expires) || Date.now() >= expires) { expire(); return; }
      const seconds = Math.ceil((expires - Date.now()) / 1000);
      find('[data-mfa-expiry]').textContent = `Time left ${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
      if (seconds <= 60 && !warned) { warned = true; announcement.textContent = 'One minute or less remains to finish setup.'; }
    };
    const showStep = (step, focus = true) => {
      tick(); if (expired || disposed) return;
      version++; setup.dataset.currentStep = String(step);
      panels.forEach(panel => { panel.getAnimations?.().forEach(animation => animation.cancel()); panel.hidden = panel.dataset.mfaStep !== String(step); });
      find('[data-mfa-progress]').textContent = `Step ${step} of 2`;
      find('[data-mfa-next]').hidden = step !== 1;
      fitKey();
      const panel = panels.find(node => !node.hidden);
      if (focus) { window.AvelaMotion?.reveal(panel); panel.querySelector('h2')?.focus({ preventScroll: true }); setup.scrollIntoView?.({ block: 'start', behavior: 'smooth' }); }
    };
    const renderQR = () => {
      if (frame.children.length) return;
      try {
        if (!window.qrcode || !uri.startsWith('otpauth://totp/') || uri.length > 2048) throw new Error('Unavailable');
        const qr = window.qrcode(0, 'M'); qr.addData(uri, 'Byte'); qr.make();
        const count = qr.getModuleCount(), border = 4, size = count + border * 2;
        const ns = 'http://www.w3.org/2000/svg', svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', `0 0 ${size} ${size}`); svg.setAttribute('role', 'img');
        svg.setAttribute('aria-label', 'Scan this QR code to set up 2-step verification');
        const background = document.createElementNS(ns, 'rect');
        background.setAttribute('width', String(size)); background.setAttribute('height', String(size)); background.setAttribute('fill', '#fff'); svg.append(background);
        const path = document.createElementNS(ns, 'path'), parts = [];
        for (let row = 0; row < count; row++) for (let col = 0; col < count; col++) if (qr.isDark(row, col)) parts.push(`M${col + border},${row + border}h1v1h-1z`);
        path.setAttribute('d', parts.join('')); path.setAttribute('fill', '#000'); svg.append(path);
        frame.replaceChildren(svg); find('[data-mfa-qr-status]').textContent = '';
      } catch { find('[data-mfa-qr-status]').textContent = 'The QR code is unavailable. Go back and copy the key instead.'; }
    };
    on(copy, 'click', async () => {
      tick(); if (expired || disposed) return;
      const job = version;
      try {
        if (!navigator.clipboard?.writeText) throw new Error('Unavailable');
        await navigator.clipboard.writeText(key.value.replace(/\s+/g, ''));
        tick();
        if (expired || disposed || job !== version) return;
        find('[data-mfa-copy-label]').textContent = 'Copied'; announcement.textContent = 'Setup key copied.';
        clearTimeout(copyTimer); copyTimer = setTimeout(() => { find('[data-mfa-copy-label]').textContent = 'Copy key'; }, 2200);
      } catch { if (!expired && !disposed) { key.focus(); key.select(); announcement.textContent = 'Select the key and copy it using your device’s copy option.'; } }
    });
    on(openButton, 'click', () => {
      tick(); if (expired || disposed || dialog.open) return;
      renderQR(); oldOverflow = document.body.style.overflow; document.body.style.overflow = 'hidden';
      if (window.AvelaMotion) window.AvelaMotion.openDialog(dialog); else dialog.showModal();
      find('[data-mfa-close-qr]').focus();
    });
    on(find('[data-mfa-close-qr]'), 'click', () => closeQR());
    on(dialog, 'cancel', event => { event.preventDefault(); closeQR(); });
    on(dialog, 'close', () => { document.body.style.overflow = oldOverflow; frame.replaceChildren(); if (!expired && !disposed) openButton.focus({ preventScroll: true }); });
    on(find('[data-mfa-next]'), 'click', () => showStep(2));
    on(find('[data-mfa-back]'), 'click', () => showStep(1));
    on(find('[data-mfa-confirm]'), 'submit', event => { tick(); if (expired || disposed) event.preventDefault(); });
    on(document, 'visibilitychange', tick);
    setup.querySelectorAll('[data-mfa-copy], [data-mfa-open-qr], [data-mfa-next], [data-mfa-back]').forEach(node => { node.hidden = false; });
    let measuredWidth = -1;
    const resize = typeof ResizeObserver === 'function' ? new ResizeObserver(entries => {
      const width = entries[0]?.contentRect.width;
      if (width !== measuredWidth) { measuredWidth = width; fitKey(); }
    }) : null;
    resize?.observe(key);
    on(window, 'resize', fitKey);
    document.fonts?.ready.then(() => { if (!disposed && !expired) fitKey(); });
    dispose = () => { disposed = true; version++; resize?.disconnect(); clear(); closeQR(true); listeners.forEach(remove => remove()); };
    tick();
    if (!expired) { showStep(setup.dataset.currentStep === '2' ? 2 : 1, false); timer = setInterval(tick, 1000); }
  }
  document.addEventListener('avela:beforecontent', () => { dispose(); mounted = null; });
  document.addEventListener('avela:content', initialize);
  window.addEventListener('pagehide', () => dispose());
  window.addEventListener('pageshow', event => { if (event.persisted && document.querySelector('[data-mfa-setup]')) location.reload(); });
  initialize();
})();
