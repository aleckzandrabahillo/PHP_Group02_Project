function passwordToggleFor(input) {
  if (!input?.id) return null;
  try {
    return document.querySelector(`[data-password-toggle="${CSS.escape(input.id)}"]`);
  } catch (_) {
    return null;
  }
}

function syncPasswordToggle(input) {
  if (!(input instanceof HTMLInputElement) || !input.matches('[data-password-input]')) return;
  const button = passwordToggleFor(input);
  if (!button) return;

  const hasValue = input.value.length > 0;
  button.hidden = !hasValue;

  if (!hasValue) {
    input.type = 'password';
    button.setAttribute('aria-pressed', 'false');
    button.setAttribute('aria-label', 'Show password');
    button.setAttribute('title', 'Show password');
  }

  const isVisible = input.type === 'text';
  const showIcon = button.querySelector('.password-icon-show');
  const hideIcon = button.querySelector('.password-icon-hide');
  if (showIcon) showIcon.hidden = isVisible;
  if (hideIcon) hideIcon.hidden = !isVisible;

  if (hasValue) {
    const label = isVisible ? 'Hide password' : 'Show password';
    button.setAttribute('aria-label', label);
    button.setAttribute('title', label);
    button.setAttribute('aria-pressed', isVisible ? 'true' : 'false');
  }
}

function syncAllPasswordToggles() {
  document.querySelectorAll('[data-password-input]').forEach(syncPasswordToggle);
}

function clearFieldError(control) {
  const field = control.closest('.field, .captcha-block');
  if (!field?.classList.contains('has-error')) return;
  field.classList.remove('has-error');
  control.setAttribute('aria-invalid', 'false');
  const error = field.querySelector('.field-error');
  if (error) error.textContent = '';
}

const viewportLocks = new Set();
let lockedScrollY = 0;

function lockViewport(key) {
  if (viewportLocks.has(key)) return;
  if (viewportLocks.size === 0) {
    lockedScrollY = window.scrollY;
    const scrollbarWidth = Math.max(0, window.innerWidth - document.documentElement.clientWidth);
    document.body.style.setProperty('--scroll-lock-offset', `-${lockedScrollY}px`);
    document.body.style.setProperty('--scrollbar-compensation', `${scrollbarWidth}px`);
    document.body.classList.add('overlay-scroll-locked');
  }
  viewportLocks.add(key);
}

function unlockViewport(key) {
  viewportLocks.delete(key);
  if (viewportLocks.size > 0) return;
  document.body.classList.remove('overlay-scroll-locked');
  document.body.style.removeProperty('--scroll-lock-offset');
  document.body.style.removeProperty('--scrollbar-compensation');
  window.scrollTo(0, lockedScrollY);
}

function setupAutoDismissFlashes() {
  document.querySelectorAll('[data-flash][data-auto-dismiss]').forEach((flash) => {
    if (!(flash instanceof HTMLElement) || flash.dataset.dismissReady === 'true') return;
    flash.dataset.dismissReady = 'true';
    const delay = Math.max(1500, Number.parseInt(flash.dataset.autoDismiss || '3200', 10) || 3200);
    window.setTimeout(() => {
      flash.classList.add('is-dismissing');
      window.setTimeout(() => flash.remove(), 220);
    }, delay);
  });
}

let filterReturnFocus = null;
let filterCloseTimer = null;

function openFilterDrawer(trigger) {
  const drawer = document.querySelector('[data-filter-drawer]');
  const backdrop = document.querySelector('[data-filter-backdrop]');
  if (!drawer || !backdrop) return;

  window.clearTimeout(filterCloseTimer);
  closeGlobalSearch({restoreFocus: false, immediate: true});
  closeMegaMenu(false);
  filterReturnFocus = trigger instanceof HTMLElement ? trigger : null;
  filterReturnFocus?.setAttribute('aria-expanded', 'true');
  drawer.hidden = false;
  backdrop.hidden = false;
  drawer.setAttribute('aria-hidden', 'false');
  document.body.classList.add('drawer-open');
  lockViewport('filter');

  requestAnimationFrame(() => {
    drawer.classList.add('is-open');
    backdrop.classList.add('is-open');
    const firstFocusable = drawer.querySelector('button, input, select, a[href]');
    if (firstFocusable instanceof HTMLElement) firstFocusable.focus();
  });
}

function closeFilterDrawer({restoreFocus = true, immediate = false} = {}) {
  const drawer = document.querySelector('[data-filter-drawer]');
  const backdrop = document.querySelector('[data-filter-backdrop]');
  if (!drawer || !backdrop || drawer.hidden) return;

  window.clearTimeout(filterCloseTimer);
  drawer.classList.remove('is-open');
  backdrop.classList.remove('is-open');
  drawer.setAttribute('aria-hidden', 'true');

  const finalize = () => {
    drawer.hidden = true;
    backdrop.hidden = true;
    document.body.classList.remove('drawer-open');
    unlockViewport('filter');
    if (restoreFocus && filterReturnFocus instanceof HTMLElement) {
      filterReturnFocus.setAttribute('aria-expanded', 'false');
      filterReturnFocus.focus({preventScroll: true});
    } else if (filterReturnFocus instanceof HTMLElement) {
      filterReturnFocus.setAttribute('aria-expanded', 'false');
    }
  };

  if (immediate) finalize();
  else filterCloseTimer = window.setTimeout(finalize, 220);
}

const headerSurfaceReasons = new Set();

function closeHeaderDetails() {
  document.querySelectorAll('.account-menu[open], .mobile-nav-menu[open]').forEach((details) => {
    details.removeAttribute('open');
  });
}

function setHeaderSurface(reason, active) {
  const header = document.querySelector('[data-site-header]');
  if (!(header instanceof HTMLElement)) return;

  if (active) headerSurfaceReasons.add(reason);
  else headerSurfaceReasons.delete(reason);

  header.classList.toggle('is-overlay-surface', headerSurfaceReasons.size > 0);
  if (!active) syncStoreHeader();
}

let megaOpenTimer = null;
let megaCloseTimer = null;
const MEGA_HOVER_OPEN_MS = 90;
const MEGA_HOVER_CLOSE_MS = 220;

function setupMegaMenu() {
  const root = document.querySelector('[data-mega-menu]');
  const trigger = root?.querySelector('[data-mega-trigger]');
  const panel = root?.querySelector('[data-mega-panel]');
  if (!(root instanceof HTMLElement) || !(trigger instanceof HTMLButtonElement) || !(panel instanceof HTMLElement)) return;

  let pinned = false;

  const cancelOpen = () => {
    window.clearTimeout(megaOpenTimer);
    megaOpenTimer = null;
  };

  const cancelClose = () => {
    window.clearTimeout(megaCloseTimer);
    megaCloseTimer = null;
  };

  const open = (pin = false) => {
    cancelOpen();
    cancelClose();
    closeGlobalSearch({restoreFocus: false, immediate: true});
    closeFilterDrawer({restoreFocus: false, immediate: true});
    closeHeaderDetails();
    if (pin) pinned = true;
    root.classList.add('is-open');
    trigger.setAttribute('aria-expanded', 'true');
    panel.setAttribute('aria-hidden', 'false');
    setHeaderSurface('mega-menu', true);
  };

  const close = (restoreFocus = false) => {
    cancelOpen();
    cancelClose();
    pinned = false;
    root.classList.remove('is-open');
    trigger.setAttribute('aria-expanded', 'false');
    panel.setAttribute('aria-hidden', 'true');
    setHeaderSurface('mega-menu', false);
    if (restoreFocus) trigger.focus({preventScroll: true});
  };

  const scheduleOpen = () => {
    if (root.classList.contains('is-open')) {
      cancelClose();
      return;
    }
    cancelOpen();
    megaOpenTimer = window.setTimeout(() => open(false), MEGA_HOVER_OPEN_MS);
  };

  const scheduleClose = () => {
    cancelOpen();
    if (pinned) return;
    cancelClose();
    megaCloseTimer = window.setTimeout(() => close(false), MEGA_HOVER_CLOSE_MS);
  };

  trigger.addEventListener('mouseenter', scheduleOpen);
  trigger.addEventListener('mouseleave', scheduleClose);
  panel.addEventListener('mouseenter', () => { cancelOpen(); cancelClose(); });
  panel.addEventListener('mouseleave', scheduleClose);

  trigger.addEventListener('click', () => {
    if (root.classList.contains('is-open') && pinned) {
      close(false);
      return;
    }
    open(true);
  });

  panel.addEventListener('click', (event) => {
    if (event.target instanceof Element && event.target.closest('a[href]')) close(false);
  });

  root.addEventListener('focusout', () => {
    window.setTimeout(() => {
      if (!pinned && !root.contains(document.activeElement)) scheduleClose();
    }, 0);
  });

  document.querySelectorAll('.store-nav-links > a').forEach((link) => {
    link.addEventListener('mouseenter', () => close(false));
    link.addEventListener('click', () => close(false));
  });

  document.addEventListener('pointerdown', (event) => {
    const target = event.target;
    if (!(target instanceof Node) || !root.classList.contains('is-open')) return;
    if (!root.contains(target)) close(false);
  });

  root.dataset.megaReady = 'true';
  root._avelaCloseMega = close;
}

function closeMegaMenu(restoreFocus = false) {
  const root = document.querySelector('[data-mega-menu]');
  if (root?.classList.contains('is-open') && typeof root._avelaCloseMega === 'function') {
    root._avelaCloseMega(restoreFocus);
  }
}

let searchReturnFocus = null;
let searchTimer = null;
let searchCloseTimer = null;
let searchAbortController = null;

function openGlobalSearch(trigger) {
  const drawer = document.querySelector('[data-global-search-drawer]');
  const backdrop = document.querySelector('[data-global-search-backdrop]');
  if (!(drawer instanceof HTMLElement) || !(backdrop instanceof HTMLElement)) return;

  window.clearTimeout(searchCloseTimer);
  closeMegaMenu(false);
  closeFilterDrawer({restoreFocus: false, immediate: true});
  closeHeaderDetails();
  searchReturnFocus = trigger instanceof HTMLElement ? trigger : null;
  drawer.hidden = false;
  backdrop.hidden = false;
  drawer.setAttribute('aria-hidden', 'false');
  setHeaderSurface('search', true);

  requestAnimationFrame(() => {
    drawer.classList.add('is-open');
    backdrop.classList.add('is-open');
    const input = drawer.querySelector('[data-global-search-input]');
    if (input instanceof HTMLInputElement) input.focus();
  });
}

function closeGlobalSearch({restoreFocus = true, immediate = false} = {}) {
  const drawer = document.querySelector('[data-global-search-drawer]');
  const backdrop = document.querySelector('[data-global-search-backdrop]');
  if (!(drawer instanceof HTMLElement) || !(backdrop instanceof HTMLElement) || drawer.hidden) return;

  window.clearTimeout(searchCloseTimer);
  drawer.classList.remove('is-open');
  backdrop.classList.remove('is-open');
  drawer.setAttribute('aria-hidden', 'true');
  searchAbortController?.abort();
  setHeaderSurface('search', false);

  const finalize = () => {
    drawer.hidden = true;
    backdrop.hidden = true;
    if (restoreFocus && searchReturnFocus instanceof HTMLElement) {
      searchReturnFocus.focus({preventScroll: true});
    }
  };

  if (immediate) finalize();
  else searchCloseTimer = window.setTimeout(finalize, 220);
}

function appendHighlightedText(target, text, query) {
  const source = String(text || '');
  const needle = String(query || '').trim();
  if (!needle) {
    target.textContent = source;
    return;
  }

  const lowerSource = source.toLocaleLowerCase();
  const lowerNeedle = needle.toLocaleLowerCase();
  let cursor = 0;
  let index = lowerSource.indexOf(lowerNeedle);
  while (index !== -1) {
    if (index > cursor) target.append(document.createTextNode(source.slice(cursor, index)));
    const mark = document.createElement('mark');
    mark.textContent = source.slice(index, index + needle.length);
    target.append(mark);
    cursor = index + needle.length;
    index = lowerSource.indexOf(lowerNeedle, cursor);
  }
  if (cursor < source.length) target.append(document.createTextNode(source.slice(cursor)));
}

function makeSuggestionGroup(title, items, query, productGroup = false) {
  if (!Array.isArray(items) || items.length === 0) return null;
  const group = document.createElement('section');
  group.className = 'search-suggestion-group';
  const heading = document.createElement('h3');
  heading.textContent = title;
  const list = document.createElement('div');
  list.className = 'search-suggestion-list';

  items.forEach((item) => {
    const link = document.createElement('a');
    link.href = item.url || '#';

    if (productGroup && item.imageUrl) {
      const thumb = document.createElement('span');
      thumb.className = 'search-suggestion-thumb';
      const image = document.createElement('img');
      image.src = item.imageUrl;
      image.alt = item.imageAlt || '';
      image.loading = 'lazy';
      thumb.append(image);
      link.append(thumb);
    }

    const copy = document.createElement('span');
    copy.className = 'search-suggestion-copy';
    const strong = document.createElement('strong');
    appendHighlightedText(strong, item.title || '', query);
    const small = document.createElement('small');
    appendHighlightedText(small, item.subtitle || '', query);
    copy.append(strong, small);
    link.append(copy);
    list.append(link);
  });

  group.append(heading, list);
  return group;
}

function renderSearchSuggestions(container, data) {
  container.replaceChildren();
  const query = data.query || '';
  const grid = document.createElement('div');
  grid.className = 'search-suggestion-grid';
  const groups = [
    makeSuggestionGroup('PRODUCTS', data.products, query, true),
    makeSuggestionGroup('DISCOVER', data.discover, query),
    makeSuggestionGroup('PAGES', data.pages, query),
  ].filter(Boolean);

  if (groups.length === 0) {
    const empty = document.createElement('p');
    empty.className = 'search-hint';
    empty.textContent = 'No matching results yet. Try another word or phrase.';
    grid.append(empty);
  } else {
    groups.forEach((group) => grid.append(group));
  }
  container.append(grid);

  if (data.allResultsUrl) {
    const all = document.createElement('a');
    all.className = 'search-all-link';
    all.href = data.allResultsUrl;
    all.textContent = 'View all search results';
    container.append(all);
  }
}

async function loadSearchSuggestions(input) {
  const drawer = input.closest('[data-global-search-drawer]');
  const results = drawer?.querySelector('[data-global-search-results]');
  const endpoint = drawer?.dataset.searchEndpoint;
  if (!(results instanceof HTMLElement) || !endpoint) return;

  const query = input.value.trim();
  if (query.length === 0) {
    results.innerHTML = '<p class="search-hint">Search products, categories, hair concerns, or pages.</p>';
    return;
  }

  searchAbortController?.abort();
  searchAbortController = new AbortController();

  try {
    const response = await fetch(`${endpoint}?q=${encodeURIComponent(query)}`, {
      headers: {'Accept': 'application/json'},
      credentials: 'same-origin',
      signal: searchAbortController.signal,
    });
    if (!response.ok) throw new Error('Search request failed');
    const data = await response.json();
    renderSearchSuggestions(results, data);
  } catch (error) {
    if (error?.name === 'AbortError') return;
    results.innerHTML = '<p class="search-hint">Search is temporarily unavailable. Press Enter to view the full search page.</p>';
  }
}

function syncFavoriteUi(productId, favorite, count) {
  document.querySelectorAll(`[data-favorite-form][data-product-id="${CSS.escape(String(productId))}"] .favorite-button`).forEach((button) => {
    button.classList.toggle('is-favorite', favorite);
    button.setAttribute('aria-pressed', favorite ? 'true' : 'false');
    const label = favorite ? 'Remove from favorites' : 'Add to favorites';
    button.setAttribute('aria-label', label);
    button.setAttribute('title', label);
  });

  document.querySelectorAll('[data-favorite-count]').forEach((badge) => {
    badge.textContent = String(count);
    badge.hidden = count < 1;
  });
}

async function submitFavorite(form) {
  const button = form.querySelector('.favorite-button');
  if (!(button instanceof HTMLButtonElement)) return;
  button.disabled = true;

  try {
    const response = await fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: {'Accept': 'application/json'},
      credentials: 'same-origin',
    });
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.message || 'Favorite update failed');

    const productId = form.dataset.productId || '';
    syncFavoriteUi(productId, Boolean(data.favorite), Number(data.count || 0));

    if (!data.favorite && window.location.pathname.endsWith('/favorites')) {
      const card = form.closest('.product-card');
      card?.remove();
      if (!document.querySelector('.favorite-grid .product-card')) window.location.reload();
    }
  } catch (_) {
    form.submit();
  } finally {
    button.disabled = false;
  }
}

function adjustQuantity(button, delta) {
  const control = button.closest('[data-quantity-control]');
  const input = control?.querySelector('input[type="number"]');
  if (!(input instanceof HTMLInputElement) || input.disabled) return;
  const min = Number(input.min || 1);
  const max = Number(input.max || 99);
  const current = Number.parseInt(input.value || String(min), 10);
  const safeCurrent = Number.isFinite(current) ? current : min;
  input.value = String(Math.min(max, Math.max(min, safeCurrent + delta)));
  input.dispatchEvent(new Event('change', {bubbles: true}));
}

const cartUpdateStates = new WeakMap();
const CART_UPDATE_DEBOUNCE_MS = 260;

function formatPeso(value) {
  const amount = Number(value);
  return `₱${(Number.isFinite(amount) ? amount : 0).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
}

function cartUpdateStateFor(form) {
  let state = cartUpdateStates.get(form);
  if (!state) {
    state = {timer: null, inFlight: false};
    cartUpdateStates.set(form, state);
  }
  return state;
}

function normalizeCartQuantity(input) {
  const min = Number.parseInt(input.min || '1', 10) || 1;
  const max = Number.parseInt(input.max || '99', 10) || 99;
  const raw = Number.parseInt(input.value, 10);
  const fallback = Number.parseInt(input.dataset.confirmedQuantity || String(min), 10) || min;
  const normalized = Math.min(max, Math.max(min, Number.isFinite(raw) ? raw : fallback));
  input.value = String(normalized);
  return normalized;
}

function updateCartSummary(data) {
  const count = Number(data.itemCount || 0);
  document.querySelectorAll('[data-cart-count]').forEach((badge) => {
    badge.textContent = String(count);
    badge.hidden = count < 1;
  });

  const countNode = document.querySelector('[data-cart-summary-count]');
  if (countNode) countNode.textContent = String(count);
  const subtotalNode = document.querySelector('[data-cart-summary-subtotal]');
  if (subtotalNode) subtotalNode.textContent = formatPeso(data.subtotal);

  const checkout = document.querySelector('[data-cart-checkout]');
  if (checkout instanceof HTMLAnchorElement && typeof data.checkoutReady === 'boolean') {
    checkout.classList.toggle('is-disabled', !data.checkoutReady);
    if (data.checkoutReady) {
      checkout.href = checkout.dataset.checkoutUrl || '#';
      checkout.removeAttribute('aria-disabled');
      checkout.removeAttribute('tabindex');
    } else {
      checkout.removeAttribute('href');
      checkout.setAttribute('aria-disabled', 'true');
      checkout.setAttribute('tabindex', '-1');
    }
  }
}

function showCartFeedback(form, message = '') {
  const feedback = form.querySelector('[data-cart-feedback]');
  if (!(feedback instanceof HTMLElement)) return;
  feedback.textContent = message;
  feedback.hidden = message === '';
}

async function sendCartQuantityUpdate(form) {
  if (!(form instanceof HTMLFormElement)) return;
  const state = cartUpdateStateFor(form);
  if (state.inFlight) return;

  const input = form.querySelector('input[name="quantity"]');
  if (!(input instanceof HTMLInputElement) || input.disabled) return;
  const requestedQuantity = normalizeCartQuantity(input);

  state.inFlight = true;
  form.classList.add('is-updating');
  form.setAttribute('aria-busy', 'true');
  showCartFeedback(form);

  try {
    const response = await fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: {'Accept': 'application/json'},
      credentials: 'same-origin',
    });
    const data = await response.json();

    if (data.item && Number.isFinite(Number(data.item.quantity))) {
      const serverQuantity = Number(data.item.quantity);
      const currentQuantity = Number.parseInt(input.value, 10);
      input.dataset.confirmedQuantity = String(serverQuantity);
      if (!Number.isFinite(currentQuantity) || currentQuantity === requestedQuantity) {
        input.value = String(serverQuantity);
      }
      const itemRoot = form.closest('[data-cart-item]');
      const lineTotal = itemRoot?.querySelector('[data-cart-line-total]');
      if (lineTotal) lineTotal.textContent = formatPeso(data.item.lineTotal);
    }
    updateCartSummary(data);

    if (!response.ok || !data.ok) {
      showCartFeedback(form, data.message || 'Your bag could not be updated.');
    }
  } catch (_) {
    const confirmed = Number.parseInt(input.dataset.confirmedQuantity || '1', 10) || 1;
    const currentQuantity = Number.parseInt(input.value, 10);
    if (!Number.isFinite(currentQuantity) || currentQuantity === requestedQuantity) input.value = String(confirmed);
    showCartFeedback(form, 'Your bag could not be updated. Try again.');
  } finally {
    state.inFlight = false;
    form.classList.remove('is-updating');
    form.removeAttribute('aria-busy');

    const confirmed = Number.parseInt(input.dataset.confirmedQuantity || '1', 10) || 1;
    if (normalizeCartQuantity(input) !== confirmed) {
      window.clearTimeout(state.timer);
      state.timer = window.setTimeout(() => sendCartQuantityUpdate(form), 120);
    }
  }
}

function queueCartQuantityUpdate(form, immediate = false) {
  if (!(form instanceof HTMLFormElement)) return;
  const input = form.querySelector('input[name="quantity"]');
  if (!(input instanceof HTMLInputElement) || input.disabled) return;
  normalizeCartQuantity(input);

  const state = cartUpdateStateFor(form);
  const confirmed = Number.parseInt(input.dataset.confirmedQuantity || '1', 10) || 1;
  if (!state.inFlight && Number.parseInt(input.value, 10) === confirmed) {
    window.clearTimeout(state.timer);
    showCartFeedback(form);
    return;
  }

  window.clearTimeout(state.timer);
  state.timer = window.setTimeout(
    () => sendCartQuantityUpdate(form),
    immediate ? 0 : CART_UPDATE_DEBOUNCE_MS,
  );
}

function activateProductTab(button) {
  const root = button.closest('[data-product-tabs]');
  const key = button.dataset.productTab;
  if (!(root instanceof HTMLElement) || !key) return;

  root.querySelectorAll('[data-product-tab]').forEach((tab) => {
    const active = tab === button;
    tab.classList.toggle('active', active);
    tab.setAttribute('aria-selected', active ? 'true' : 'false');
    tab.setAttribute('tabindex', active ? '0' : '-1');
  });
  root.querySelectorAll('[data-product-panel]').forEach((panel) => {
    const active = panel.dataset.productPanel === key;
    panel.classList.toggle('active', active);
    panel.hidden = !active;
  });
}

function trapFocus(event, container) {
  const focusable = [...container.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), a[href], summary')]
    .filter((element) => element instanceof HTMLElement && element.offsetParent !== null);
  if (focusable.length === 0) return;
  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}

document.addEventListener('DOMContentLoaded', () => {
  syncAllPasswordToggles();
  setupMegaMenu();
  setupAutoDismissFlashes();
  window.setTimeout(syncAllPasswordToggles, 150);
  window.setTimeout(syncAllPasswordToggles, 800);

  const firstInvalid = document.querySelector('[aria-invalid="true"]');
  if (firstInvalid instanceof HTMLElement) firstInvalid.focus({preventScroll: false});
});

document.addEventListener('pageshow', () => {
  syncAllPasswordToggles();
  syncStoreHeader();
});

document.addEventListener('input', (event) => {
  const control = event.target;
  if (!(control instanceof HTMLInputElement) && !(control instanceof HTMLTextAreaElement) && !(control instanceof HTMLSelectElement)) return;
  clearFieldError(control);
  if (control instanceof HTMLInputElement && control.matches('[data-password-input]')) syncPasswordToggle(control);
  if (control instanceof HTMLInputElement && control.matches('[data-global-search-input]')) {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => loadSearchSuggestions(control), 220);
  }
});

document.addEventListener('change', (event) => {
  const control = event.target;
  if (control instanceof HTMLInputElement && control.matches('[data-password-input]')) syncPasswordToggle(control);
  if (control instanceof HTMLInputElement && control.matches('[data-cart-auto-update] input[name="quantity"]')) {
    queueCartQuantityUpdate(control.form);
  }
  if (control instanceof HTMLSelectElement && control.matches('[data-auto-submit]')) {
    control.form?.requestSubmit();
  }
});

document.addEventListener('focusin', (event) => {
  if (event.target instanceof HTMLInputElement && event.target.matches('[data-password-input]')) syncPasswordToggle(event.target);
});

document.addEventListener('focusout', (event) => {
  if (event.target instanceof HTMLInputElement && event.target.matches('[data-password-input]')) {
    window.setTimeout(() => syncPasswordToggle(event.target), 0);
  }
});

document.addEventListener('submit', (event) => {
  const form = event.target;
  if (!(form instanceof HTMLFormElement)) return;

  if (form.matches('[data-favorite-form]')) {
    event.preventDefault();
    submitFavorite(form);
    return;
  }

  if (form.matches('[data-cart-auto-update]')) {
    event.preventDefault();
    queueCartQuantityUpdate(form, true);
  }
});

document.addEventListener('keydown', (event) => {
  const searchDrawer = document.querySelector('[data-global-search-drawer]');
  const filterDrawer = document.querySelector('[data-filter-drawer]');

  if (event.key === 'Escape') {
    if (searchDrawer instanceof HTMLElement && !searchDrawer.hidden) {
      closeGlobalSearch();
      return;
    }
    const filterOpen = filterDrawer instanceof HTMLElement && !filterDrawer.hidden;
    if (filterOpen) { closeFilterDrawer(); return; }
    closeMegaMenu(true);
    document.querySelectorAll('[data-sort-menu][open]').forEach((menu) => menu.removeAttribute('open'));
    return;
  }

  if (event.key === 'Tab') {
    if (searchDrawer instanceof HTMLElement && !searchDrawer.hidden) {
      trapFocus(event, searchDrawer);
    } else if (filterDrawer instanceof HTMLElement && !filterDrawer.hidden) {
      trapFocus(event, filterDrawer);
    }
  }
});

document.addEventListener('click', async (event) => {
  const target = event.target instanceof Element ? event.target : null;
  if (!target) return;

  document.querySelectorAll('.account-menu[open]').forEach((menu) => {
    if (!menu.contains(target)) menu.removeAttribute('open');
  });

  document.querySelectorAll('[data-sort-menu][open]').forEach((menu) => {
    if (!menu.contains(target)) menu.removeAttribute('open');
  });

  const quantityMinus = target.closest('[data-quantity-minus]');
  if (quantityMinus instanceof HTMLButtonElement) { adjustQuantity(quantityMinus, -1); return; }
  const quantityPlus = target.closest('[data-quantity-plus]');
  if (quantityPlus instanceof HTMLButtonElement) { adjustQuantity(quantityPlus, 1); return; }

  const productTab = target.closest('[data-product-tab]');
  if (productTab instanceof HTMLButtonElement) { activateProductTab(productTab); return; }

  const toggle = target.closest('[data-password-toggle]');
  if (toggle instanceof HTMLButtonElement) {
    const input = document.getElementById(toggle.dataset.passwordToggle || '');
    if (input instanceof HTMLInputElement && input.value.length > 0) {
      input.type = input.type === 'password' ? 'text' : 'password';
      syncPasswordToggle(input);
      input.focus({preventScroll: true});
      const length = input.value.length;
      try { input.setSelectionRange(length, length); } catch (_) {}
    }
    return;
  }

  const refresh = target.closest('.captcha-refresh');
  if (refresh instanceof HTMLButtonElement) {
    refresh.disabled = true;
    try {
      const body = new URLSearchParams({_token: refresh.dataset.csrf || ''});
      const endpoint = refresh.dataset.endpoint || 'captcha/refresh';
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body,
        credentials: 'same-origin'
      });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error('CAPTCHA refresh failed');
      const image = document.querySelector('.captcha-image');
      if (image instanceof HTMLImageElement) image.src = data.url;
      const input = document.querySelector('.captcha-block input[name="captcha"]');
      if (input instanceof HTMLInputElement) {
        input.value = '';
        clearFieldError(input);
        input.focus();
      }
    } catch (_) {
      window.location.reload();
    } finally {
      refresh.disabled = false;
    }
    return;
  }

  const searchOpen = target.closest('[data-global-search-open]');
  if (searchOpen) {
    openGlobalSearch(searchOpen);
    return;
  }
  if (target.closest('[data-global-search-close]') || target.matches('[data-global-search-backdrop]')) {
    closeGlobalSearch();
    return;
  }

  const openFilters = target.closest('[data-filter-open]');
  if (openFilters) {
    openFilterDrawer(openFilters);
    return;
  }
  if (target.closest('[data-filter-close]') || target.matches('[data-filter-backdrop]')) {
    closeFilterDrawer();
    return;
  }

  const sidebarToggle = target.closest('.sidebar-toggle');
  if (sidebarToggle) document.querySelector('.staff-sidebar')?.classList.toggle('open');
});

function syncStoreHeader() {
  const header = document.querySelector('[data-site-header][data-header-overlay="true"]');
  if (!(header instanceof HTMLElement)) return;

  const transitionDistance = Math.max(150, Math.min(230, window.innerHeight * 0.24));
  const progress = Math.min(1, Math.max(0, window.scrollY / transitionDistance));

  header.style.setProperty('--header-bg-alpha', (0.96 * progress).toFixed(3));
  header.style.setProperty('--header-border-alpha', (0.82 * progress).toFixed(3));
  header.style.setProperty('--header-shadow-alpha', (0.06 * progress).toFixed(3));
  header.style.setProperty('--header-blur', `${(16 * progress).toFixed(1)}px`);
  header.classList.toggle('is-scrolled', progress > 0.02);
  header.classList.toggle('is-solid', progress >= 0.98);
}

window.addEventListener('scroll', syncStoreHeader, {passive: true});
window.addEventListener('resize', syncStoreHeader);
document.addEventListener('DOMContentLoaded', syncStoreHeader);
