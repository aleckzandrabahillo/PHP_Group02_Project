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
  const showIcon=button.querySelector('.password-icon-show'),hideIcon=button.querySelector('.password-icon-hide');
  if(showIcon&&hideIcon){showIcon.hidden=isVisible;hideIcon.hidden=!isVisible;}
  else button.textContent = isVisible ? 'Hide' : 'Show';

  if (hasValue) {
    const label = isVisible ? 'Hide password' : 'Show password';
    button.setAttribute('aria-label', label);
    button.setAttribute('title', label);
    button.setAttribute('aria-pressed', isVisible ? 'true' : 'false');
  }
}

function syncAllPasswordToggles() {
  document.querySelectorAll('input[type="password"]:not([data-password-input])').forEach(input=>{
    if(!input.id){let index=1;while(document.getElementById('avela-password-'+index))index++;input.id='avela-password-'+index;}
    input.setAttribute('data-password-input','');
    const wrap=document.createElement('span'),button=document.createElement('button');
    wrap.className='password-wrap';button.type='button';button.className='password-toggle';button.hidden=true;
    button.dataset.passwordToggle=input.id;button.setAttribute('aria-controls',input.id);
    input.insertAdjacentElement('beforebegin',wrap);wrap.append(input,button);
  });
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
  if (!viewportLocks.has(key)) return;
  viewportLocks.delete(key);
  if (viewportLocks.size > 0) return;
  document.body.classList.remove('overlay-scroll-locked');
  document.body.style.removeProperty('--scroll-lock-offset');
  document.body.style.removeProperty('--scrollbar-compensation');
  window.scrollTo(0, lockedScrollY);
}

let filterReturnFocus = null, filterCloseTimer = null, filterDraft = null;
const filterMounts = new WeakSet();
function filterControls(){return [...document.querySelectorAll('[data-filter-drawer] input,[data-filter-drawer] select')];}
function openFilterDrawer(trigger) {
 const drawer=document.querySelector('[data-filter-drawer]');if(!drawer||drawer.open)return;
 clearTimeout(filterCloseTimer);closeGlobalSearch({restoreFocus:false,immediate:true});closeMegaMenu(false);
 filterReturnFocus=trigger;trigger?.setAttribute('aria-expanded','true');
 filterDraft=filterControls().map(input=>({input,value:input.value,checked:input.checked}));
 if(!filterMounts.has(drawer)){
  filterMounts.add(drawer);
  drawer.addEventListener('cancel',event=>{event.preventDefault();closeFilterDrawer();});
  drawer.addEventListener('click',event=>{const r=drawer.getBoundingClientRect();if(event.target===drawer&&(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom))closeFilterDrawer();});
 }
 drawer.classList.remove('is-closing');drawer.hidden=false;drawer.showModal();drawer.querySelector('.filter-drawer-body').scrollTop=0;document.body.classList.add('drawer-open');
 requestAnimationFrame(()=>{drawer.classList.add('is-open');drawer.querySelector('[data-filter-close]')?.focus({preventScroll:true});});
}
function closeFilterDrawer({restoreFocus=true,immediate=false,apply=false}={}) {
 const drawer=document.querySelector('[data-filter-drawer]');if(!drawer||!drawer.open)return;
 clearTimeout(filterCloseTimer);drawer.classList.remove('is-open');drawer.classList.add('is-closing');
 if(!apply&&filterDraft)filterDraft.forEach(({input,value,checked})=>{input.value=value;input.checked=checked;});
 const finish=()=>{drawer.close();drawer.hidden=true;drawer.classList.remove('is-closing');document.body.classList.remove('drawer-open');filterReturnFocus?.setAttribute('aria-expanded','false');if(restoreFocus)filterReturnFocus?.focus({preventScroll:true});};
 if(immediate||window.AvelaMotion?.reduced())finish();else filterCloseTimer=setTimeout(finish,260);
}
document.addEventListener('click',event=>{
 if(!event.target.closest?.('[data-filter-reset]'))return;
 filterControls().forEach(input=>{if(input.type==='checkbox')input.checked=false;else if(input.type==='radio')input.checked=input.value==='';else input.value='';});
});
document.addEventListener('submit',event=>{if(event.target.matches('[data-shop-form]')&&!event.defaultPrevented)closeFilterDrawer({restoreFocus:false,immediate:true,apply:true});});

const headerSurfaceReasons = new Set();

function closeHeaderDetails() {
  document.querySelectorAll('.account-menu[open], .mobile-nav-menu[open]').forEach((details) => {
    if(window.AvelaMotion)window.AvelaMotion.setDetails(details,false);else details.removeAttribute('open');
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

  if (immediate || (window.AvelaMotion?.reduced()??window.matchMedia('(prefers-reduced-motion: reduce)').matches)) finalize();
  else searchCloseTimer = window.setTimeout(finalize,(window.AvelaMotion?.duration('base')??220)+40);
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
    const searchUrl = new URL(endpoint, window.location.href);
    searchUrl.searchParams.set('q', query);
    if (drawer.dataset.catalogSort) searchUrl.searchParams.set('sort', drawer.dataset.catalogSort);
    if (drawer.dataset.catalogFilters) {
      const filters = JSON.parse(drawer.dataset.catalogFilters);
      Object.entries(filters).forEach(([key, value]) => {
        if (Array.isArray(value)) value.forEach((item) => searchUrl.searchParams.append(`${key}[]`, item));
        else searchUrl.searchParams.set(key, value);
      });
    }
    const response = await fetch(searchUrl, {
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

let favoriteFeedbackTimer;
function announceFavorite(message, persistent = false) {
  if (window.AvelaToast) { window.AvelaToast(message, persistent ? 'error' : 'success'); return; }
  const feedback = document.querySelector('[data-favorite-feedback]');
  if (!feedback) return;
  feedback.hidden = false;
  feedback.textContent = message;
  window.clearTimeout(favoriteFeedbackTimer);
  if (!persistent) favoriteFeedbackTimer = window.setTimeout(() => { feedback.hidden = true; }, 5500);
}

function syncFavoriteUi(productId, favorite, count) {
  document.querySelectorAll(`[data-favorite-form][data-product-id="${CSS.escape(String(productId))}"] .favorite-button`).forEach((button) => {
    button.classList.toggle('is-favorite', favorite);
    button.setAttribute('aria-pressed', String(favorite));
    const label = favorite ? 'Remove from favorites' : 'Add to favorites';
    button.setAttribute('aria-label', label);
    button.setAttribute('title', label);
  });

}

async function refreshFavoriteState() {
  const endpoint = document.querySelector('[data-favorite-state-url]')?.dataset.favoriteStateUrl;
  if (!endpoint) return;
  const controller = new AbortController();
  const deadline = window.setTimeout(() => controller.abort(), 5000);
  let response, data;
  try {
    response = await fetch(endpoint, {headers: {'Accept': 'application/json'}, credentials: 'same-origin', cache: 'no-store', signal: controller.signal});
    data = await response.json();
  } finally { window.clearTimeout(deadline); }
  if (!response.ok || !data.ok || !Array.isArray(data.ids) || !Number.isSafeInteger(data.count) || data.count < 0) throw new Error('Favorite state unavailable');
  const ids = new Set(data.ids.map(String));
  document.querySelectorAll('[data-favorite-form]').forEach((form) => syncFavoriteUi(form.dataset.productId, ids.has(form.dataset.productId), data.count));
  // Pages without product buttons still need their header count reconciled.
  syncFavoriteUi('', false, data.count);
}

let favoritePending = false;
async function submitFavorite(form) {
  if (favoritePending) return;
  const button = form.querySelector('.favorite-button');
  if (!(button instanceof HTMLButtonElement)) return;
  favoritePending = true;
  const buttons = [...document.querySelectorAll('[data-favorite-form] button')];
  const priorDisabled = buttons.map((item) => item.disabled);
  buttons.forEach((item) => { item.disabled = true; });
  form.setAttribute('aria-busy', 'true');
  const controller = new AbortController();
  const deadline = window.setTimeout(() => controller.abort(), 10000);
  try {
    const response = await fetch(form.action, {
      method: 'POST', body: new FormData(form), headers: {'Accept': 'application/json'},
      credentials: 'same-origin', signal: controller.signal,
    });
    const data = await response.json();
    if (!response.ok || !data.ok || typeof data.favorite !== 'boolean' || !Number.isSafeInteger(data.count) || data.count < 0) throw new Error('Favorite update failed');
    syncFavoriteUi(form.dataset.productId || '', data.favorite, data.count);
    announceFavorite(`${data.message} ${data.count} saved product${data.count === 1 ? '' : 's'}.`);
    if (!data.favorite && window.location.pathname.endsWith('/favorites')) {
      const card = form.closest('.product-card');
      const nextFocus = card?.nextElementSibling?.querySelector('a') || card?.previousElementSibling?.querySelector('a') || document.querySelector('[data-favorite-link]');
      card?.remove();
      const resultCount=document.querySelector('[data-favorite-result-count]');if(resultCount){const remaining=Math.max(0,Number(resultCount.dataset.matchingTotal)-1);resultCount.dataset.matchingTotal=String(remaining);resultCount.textContent=document.querySelectorAll('.favorite-grid .product-card').length+' shown · '+remaining+' matching favorites';}
      nextFocus?.focus();
      if (!document.querySelector('.favorite-grid .product-card')) window.location.reload();
    }
  } catch (_) {
    // A lost response may follow a committed toggle. Read state; never repeat the POST.
    try { await refreshFavoriteState(); } catch (_) {}
    announceFavorite('We could not confirm that update. Check the heart state or refresh before trying again.', true);
  } finally {
    window.clearTimeout(deadline);
    buttons.forEach((item, index) => { item.disabled = priorDisabled[index]; });
    form.removeAttribute('aria-busy');
    favoritePending = false;
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
  if (Array.isArray(data.availability)) {
    data.availability.forEach(item => {
      document.querySelectorAll('[data-cart-item]').forEach(row => {
        if (row.dataset.productId !== String(item.productId)) return;
        const warning = row.querySelector('[data-cart-stock-warning]');
        if (warning) { warning.textContent = item.message || ''; warning.hidden = !item.message; }
        const quantity = row.querySelector('input[name="quantity"]');
        if (quantity) { quantity.max = String(Math.max(1, Math.min(99, Number(item.stock) || 0))); quantity.disabled = !item.canAdjust; }
        row.querySelectorAll('[data-quantity-minus],[data-quantity-plus]').forEach(button => { button.disabled = !item.canAdjust; });
      });
    });
    document.querySelectorAll('[data-cart-availability]').forEach(notice => { notice.hidden = data.checkoutReady || Number(data.itemCount) < 1; });
  }
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
  let accepted = false;
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
    accepted = response.ok && Boolean(data.ok);

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
    if (accepted && !input.disabled && normalizeCartQuantity(input) !== confirmed) {
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
    panel.hidden = !active; if(active)window.AvelaMotion?.reveal(panel);
  });
}

let staffMotionTimer=null;
function setStaffNavigation(requestedOpen, restoreFocus = true) {
  const sidebar = document.querySelector('.staff-sidebar');
  const toggle = document.querySelector('.sidebar-toggle');
  const workspace = document.querySelector('.staff-workspace');
  const backdrop = document.querySelector('.staff-nav-backdrop');
  if (!sidebar || !toggle || !workspace || !backdrop) return;
  const mobile = window.matchMedia('(max-width:1000px)').matches;
  const wasOpen = sidebar.classList.contains('open');
  const open = mobile && requestedOpen;
  sidebar.classList.toggle('open', open);
  sidebar.inert = mobile && !open;
  workspace.inert = open;
  clearTimeout(staffMotionTimer);
  if(open){backdrop.hidden=false;requestAnimationFrame(()=>{if(sidebar.classList.contains('open'))backdrop.classList.add('is-open');});}
  else{backdrop.classList.remove('is-open');const finish=()=>{if(!sidebar.classList.contains('open'))backdrop.hidden=true;};if(!mobile||window.AvelaMotion?.reduced())finish();else staffMotionTimer=setTimeout(finish,(window.AvelaMotion?.duration('base')??220)+40);}
  toggle.setAttribute('aria-expanded', String(open));
  if (mobile && !open) sidebar.setAttribute('aria-hidden', 'true');
  else sidebar.removeAttribute('aria-hidden');
  if (open) {
    sidebar.setAttribute('role', 'dialog');
    sidebar.setAttribute('aria-modal', 'true');
    lockViewport('staff-navigation');
    window.requestAnimationFrame(() => {
      if (sidebar.classList.contains('open')) sidebar.querySelector('.staff-nav-close')?.focus();
    });
  } else {
    sidebar.removeAttribute('role');
    sidebar.removeAttribute('aria-modal');
    unlockViewport('staff-navigation');
    if (restoreFocus && wasOpen) {
      (mobile ? toggle : sidebar.querySelector('[aria-current="page"], .brand'))?.focus();
    }
  }
}

function setupStaffInterface() {
  if (document.querySelector('.staff-sidebar')&&!document.querySelector('.staff-sidebar').dataset.avelaInitialized) {
    document.querySelector('.staff-sidebar').dataset.avelaInitialized='true';
    document.body.classList.add('staff-enhanced');
    setStaffNavigation(false, false);
    const sidebar = document.querySelector('.staff-sidebar');
    sidebar.addEventListener('transitionend', () => {
      if (sidebar.classList.contains('open') && !sidebar.contains(document.activeElement)) {
        sidebar.querySelector('.staff-nav-close')?.focus();
      }
    });
    window.matchMedia('(max-width:1000px)').addEventListener('change', () => setStaffNavigation(false));
  }
  document.querySelectorAll('[data-staff-list]').forEach((root) => {
    if(root.dataset.avelaInitialized)return;root.dataset.avelaInitialized='true';
    const rows = [...root.querySelectorAll('[data-staff-row]')];
    if (!rows.length) return;
    const search = root.querySelector('[data-staff-search]');
    const status = root.querySelector('[data-staff-status]');
    const sort = root.querySelector('[data-staff-sort]');
    const previous = root.querySelector('[data-staff-previous]');
    const next = root.querySelector('[data-staff-next]');
    const initialOrder = new Map(rows.map((row, index) => [row, index]));
    const normalize = (value) => value.normalize('NFKC').toLocaleLowerCase();
    const pageSize = 10;
    let page = 1;
    // Previous/Next remain stable DOM controls, so repeated Enter/Space keeps working.
    const numbers = root.querySelector('[data-staff-numbers]');
    const renderNumbers = (pages) => {
      if (!numbers) return;
      const focused = numbers.contains(document.activeElement) ? Number(document.activeElement.dataset.staffGoto) : null;
      const fragment = document.createDocumentFragment();
      if (pages > 1) {
        const shown = [...new Set([1, pages, ...[-2,-1,0,1,2].map(offset => page + offset)])].filter(n => n >= 1 && n <= pages).sort((a,b) => a-b);
        let last = 0;
        shown.forEach(n => {
          if (last && n-last > 1) { const gap=document.createElement('span'); gap.className='page-gap'; gap.textContent='…'; gap.setAttribute('aria-hidden','true'); fragment.append(gap); }
          const button=document.createElement('button'); button.type='button'; button.className='btn btn-secondary btn-sm'; button.dataset.staffGoto=String(n); button.textContent=String(n); button.setAttribute('aria-label',`Page ${n}`);
          if (n === page) button.setAttribute('aria-current','page');
          fragment.append(button); last=n;
        });
      }
      numbers.replaceChildren(fragment);
      if (focused !== null) (numbers.querySelector(`[data-staff-goto="${page}"]`) || next || previous).focus({preventScroll:true});
    };
    numbers?.addEventListener('click', event => {
      const button=event.target.closest('[data-staff-goto]');
      if (!button || !numbers.contains(button)) return;
      const requested=Number(button.dataset.staffGoto); if (!Number.isInteger(requested) || requested < 1 || requested === page) return;
      page=requested; render();
    });
    const render = () => {
      const query = normalize(search.value.trim());
      const matches = rows.filter((row) => normalize(row.dataset.listSearch).includes(query)
        && (!status.value || row.dataset.listStatus === status.value));
      matches.sort((a, b) => {
        if (sort.value === 'name') return a.dataset.listName.localeCompare(b.dataset.listName, undefined, {sensitivity:'base', numeric:true}) || initialOrder.get(a) - initialOrder.get(b);
        if (sort.value === 'quantity') return Number(a.dataset.listQuantity) - Number(b.dataset.listQuantity) || initialOrder.get(a) - initialOrder.get(b);
        return initialOrder.get(a) - initialOrder.get(b);
      });
      const pages = Math.max(1, Math.ceil(matches.length / pageSize));
      page = Math.min(page, pages);
      const visible = new Set(matches.slice((page - 1) * pageSize, page * pageSize));
      rows.forEach((row) => { row.hidden = !visible.has(row); });
      matches.forEach((row) => row.parentElement.appendChild(row));
      const focusedEdge = document.activeElement === previous || document.activeElement === next ? document.activeElement : null;
      previous.disabled = page === 1;
      next.disabled = page === pages;
      renderNumbers(pages);
      if (focusedEdge?.disabled) (numbers?.querySelector('[aria-current="page"]') || search).focus({preventScroll:true});
      root.querySelector('[data-staff-page]').textContent = `Page ${page} of ${pages}`;
      root.querySelector('[data-staff-count]').textContent = matches.length
        ? `Showing ${(page - 1) * pageSize + 1}–${Math.min(page * pageSize, matches.length)} of ${matches.length} matching ${root.dataset.listLabel} (${rows.length} total).`
        : `0 matching ${root.dataset.listLabel} (${rows.length} total).`;
      root.querySelector('[data-staff-empty]').hidden = matches.length > 0;
    };
    root.querySelectorAll('[data-staff-tools]').forEach((tools) => { tools.hidden = false; });
    search.addEventListener('input', () => { page = 1; render(); });
    [status, sort].forEach((control) => control.addEventListener('change', () => { page = 1; render(); }));
    previous.addEventListener('click', () => { page--; render(); });
    next.addEventListener('click', () => { page++; render(); });
    root.querySelector('[data-staff-reset]').addEventListener('click', () => {
      search.value = ''; status.value = ''; sort.value = 'original'; page = 1; render(); status.dispatchEvent(new Event('change',{bubbles:true})); sort.dispatchEvent(new Event('change',{bubbles:true})); search.focus();
    });
    render();
  });
}

function trapFocus(event, container) {
  const focusable = [...container.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), a[href], summary')]
    .filter((element) => element instanceof HTMLElement && element.offsetParent !== null);
  if (focusable.length === 0) return;
  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  if (!container.contains(document.activeElement)) {
    event.preventDefault();
    first.focus();
    return;
  }
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}

document.addEventListener('DOMContentLoaded', () => {
  setupStaffInterface();
  syncAllPasswordToggles();
  setupMegaMenu();
  document.querySelectorAll('[data-product-tab][aria-selected="true"]').forEach(activateProductTab);
  window.setTimeout(syncAllPasswordToggles, 150);
  window.setTimeout(syncAllPasswordToggles, 800);

  const errorTarget = document.querySelector('[data-error-summary]') || document.querySelector('[aria-invalid="true"]');
  if (errorTarget instanceof HTMLElement) errorTarget.focus({preventScroll: false});
  document.querySelectorAll('[data-error-summary] a[href^="#"]').forEach((link) => {
    link.addEventListener('click', (event) => {
      const field = document.getElementById(link.getAttribute('href').slice(1));
      if (!(field instanceof HTMLElement)) return;
      event.preventDefault();
      const focusTarget = field.matches('input,select,textarea,button,[tabindex]')
        ? field : field.querySelector('input,select,textarea,button,[tabindex]');
      if (focusTarget instanceof HTMLElement) focusTarget.focus();
    });
  });
});

window.addEventListener('pageshow', () => {
  syncAllPasswordToggles();
  syncStoreHeader();
});

let passwordTogglePoll=null;
function watchPasswordValues(){
  if(passwordTogglePoll||!document.querySelector('[data-password-input],input[type="password"]'))return;
  passwordTogglePoll=setInterval(()=>{if(document.visibilityState==='visible')syncAllPasswordToggles();},400);
}
watchPasswordValues();document.addEventListener('avela:content',watchPasswordValues);

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
    if (document.querySelector('.staff-sidebar.open')) { setStaffNavigation(false); return; }
    if (searchDrawer instanceof HTMLElement && !searchDrawer.hidden) {
      closeGlobalSearch();
      return;
    }
    const filterOpen = filterDrawer instanceof HTMLElement && !filterDrawer.hidden;
    if (filterOpen) { closeFilterDrawer(); return; }
    closeMegaMenu(true);
    document.querySelectorAll('.mobile-nav-menu[open], .account-menu[open]').forEach((menu) => {
      window.AvelaMotion?window.AvelaMotion.setDetails(menu,false):menu.removeAttribute('open');
      menu.querySelector('summary')?.focus();
    });
    document.querySelectorAll('[data-sort-menu][open]').forEach((menu) => {
      window.AvelaMotion?window.AvelaMotion.setDetails(menu,false):menu.removeAttribute('open');
      menu.querySelector('summary')?.focus();
    });
    return;
  }

  if (event.key === 'Tab') {
    const staffNavigation = document.querySelector('.staff-sidebar.open');
    if (staffNavigation instanceof HTMLElement) {
      trapFocus(event, staffNavigation);
    } else if (searchDrawer instanceof HTMLElement && !searchDrawer.hidden) {
      trapFocus(event, searchDrawer);
    } else if (filterDrawer instanceof HTMLElement && !filterDrawer.hidden) {
      trapFocus(event, filterDrawer);
    }
  }
  const activeTab = event.target instanceof Element ? event.target.closest('[data-product-tab]') : null;
  if (activeTab && ['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
    const tabs = [...activeTab.closest('[role="tablist"]').querySelectorAll('[data-product-tab]')];
    const current = tabs.indexOf(activeTab);
    const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (current + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
    event.preventDefault();
    activateProductTab(tabs[next]);
    tabs[next].focus();
  }
});

document.addEventListener('click', async (event) => {
  const target = event.target instanceof Element ? event.target : null;
  if (!target) return;

  document.querySelectorAll('.account-menu[open]').forEach((menu) => {
    if (!menu.contains(target)) window.AvelaMotion?window.AvelaMotion.setDetails(menu,false):menu.removeAttribute('open');
  });

  document.querySelectorAll('[data-sort-menu][open]').forEach((menu) => {
    if (!menu.contains(target)) window.AvelaMotion?window.AvelaMotion.setDetails(menu,false):menu.removeAttribute('open');
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
      const start=input.selectionStart,end=input.selectionEnd,direction=input.selectionDirection;
      input.type = input.type === 'password' ? 'text' : 'password';
      syncPasswordToggle(input);
      input.focus({preventScroll: true});
      try { if(start!==null&&end!==null)input.setSelectionRange(start,end,direction); } catch (_) {}
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
  if (target.closest('[data-filter-close]')) {
    closeFilterDrawer();
    return;
  }

  const sidebarToggle = target.closest('.sidebar-toggle');
  if (sidebarToggle) setStaffNavigation(!document.querySelector('.staff-sidebar.open'));
  if (target.closest('[data-staff-nav-close]')) setStaffNavigation(false);
});

function syncStoreHeader() {
  const header = document.querySelector('[data-site-header][data-header-overlay="true"]');
  if (!(header instanceof HTMLElement)) return;

  const campaign = document.querySelector('.v2-home .campaign');
  const progress = campaign
    ? Math.min(1, Math.max(0, (window.scrollY - Math.max(0, campaign.offsetHeight - 110)) / 30))
    : Math.min(1, Math.max(0, (window.scrollY - 8) / 144));
  header.style.setProperty('--header-opacity', String(progress));
  const solid = progress >= 0.65;
  header.classList.toggle('is-scrolled', solid);
  header.classList.toggle('is-solid', solid);
}

let headerFrame = 0;
window.addEventListener('scroll', () => {
  if (headerFrame) return;
  headerFrame = window.requestAnimationFrame(() => { headerFrame = 0; syncStoreHeader(); });
}, {passive: true});
window.addEventListener('resize', syncStoreHeader);
document.addEventListener('DOMContentLoaded', syncStoreHeader);

document.querySelectorAll('.assessment-jump-links a').forEach((link) => {
  link.addEventListener('click', (event) => {
    const section = document.getElementById(link.hash.slice(1));
    if (!(section instanceof HTMLElement)) return;
    event.preventDefault();
    window.history.replaceState(null, '', link.hash);
    const menu = link.closest('.assessment-sections');
    if (menu instanceof HTMLDetailsElement) {if(window.AvelaMotion)window.AvelaMotion.setDetails(menu,false);else menu.open=false;}
    section.scrollIntoView({block:'start',behavior:window.AvelaMotion?.reduced()?'auto':'smooth'});
    section?.querySelector('h2')?.focus({preventScroll: true});
  });
});

document.querySelectorAll('[data-assessment-form]').forEach((form) => {
  const button = form.querySelector('button[type="submit"]');
  if (!(button instanceof HTMLButtonElement)) return;
  const label = button.innerHTML;
  form.addEventListener('submit', (event) => {
    if (event.defaultPrevented) return;
    button.disabled = true;
    button.textContent = 'Finding your matches…';
    form.setAttribute('aria-busy', 'true');
  });
  window.addEventListener('pageshow', () => {
    button.disabled = false;
    button.innerHTML = label;
    form.removeAttribute('aria-busy');
  });
});


function mountImageUploads(root=document){
root.querySelectorAll('[data-image-upload]').forEach((input) => {
  // Profile photos already have one shared toast handler for every role.
  if(input.matches('[data-photo-input]'))return;
  if(input.dataset.avelaUploadReady)return;input.dataset.avelaUploadReady='true';
  const feedback = document.createElement('small');
  feedback.className = 'field-error';
  feedback.setAttribute('role', 'alert');
  input.insertAdjacentElement('afterend', feedback);
  input.addEventListener('change', () => {
    const file = input.files?.[0];
    const message = file && (file.size === 0 || file.size > Number(input.dataset.maxBytes || 2621440))
      ? 'Choose a nonempty image of ' + (input.dataset.maxLabel || '2.5 MB') + ' or smaller.' : '';
    input.setCustomValidity(message);
    feedback.textContent = message;
    input.setAttribute('aria-invalid', message ? 'true' : 'false');
  });
});
}
mountImageUploads();
document.addEventListener('avela:content',event=>{setupStaffInterface();syncAllPasswordToggles();mountImageUploads(event.detail?.root||document);});
const addressChoices = document.querySelectorAll('[data-address-source]');
const deliveryField = document.querySelector('[data-delivery-address]');
if (addressChoices.length && deliveryField) {
  const syncAddress = () => {
    const saved = document.querySelector('[data-address-source]:checked')?.value === 'saved';
    deliveryField.hidden = saved;
    const input = deliveryField.querySelector('textarea');
    input.disabled = saved;
    input.required = !saved;
  };
  addressChoices.forEach((input) => input.addEventListener('change', syncAddress));
  syncAddress();
}

// Shared overlay notifications are managed by experience.js.
window.addEventListener('pageshow', (event) => {
  if (!event.persisted) return;
  document.querySelectorAll('[data-flash-transient]').forEach((notice) => notice.remove());
  refreshFavoriteState().catch(() => announceFavorite('Refresh this page to check your saved products.'));
});

const unsavedBaselines=new WeakMap(),submittedDrafts=new WeakSet();
function mountUnsavedForms(root=document){root.querySelectorAll('[data-unsaved-form]').forEach(form=>{if(!unsavedBaselines.has(form))unsavedBaselines.set(form,new URLSearchParams(new FormData(form)).toString());});}
mountUnsavedForms();document.addEventListener('avela:content',event=>mountUnsavedForms(event.detail?.root||document));
document.addEventListener('submit',event=>{if(event.target.matches('[data-unsaved-form]'))submittedDrafts.add(event.target);});
window.addEventListener('beforeunload',event=>{for(const form of document.querySelectorAll('[data-unsaved-form]')){if(submittedDrafts.has(form)||!form.getClientRects().length)continue;if(new URLSearchParams(new FormData(form)).toString()!==unsavedBaselines.get(form)){event.preventDefault();event.returnValue='';break;}}});
