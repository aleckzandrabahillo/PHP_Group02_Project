/* Approved handoff interactions, backed by the existing PHP endpoints. */
(() => {
  'use strict';
  if (!document.body.classList.contains('avela-v3')) return;
  const motionPreference = matchMedia('(prefers-reduced-motion: reduce)');
  const reduced = () => window.AvelaMotion?.reduced() ?? motionPreference.matches;
  // One cancellable exit owner for the handoff's nonmodal panels and bag.
  const exitJobs = new Map();
  const cancelExit = node => {
    const job = exitJobs.get(node);
    if (job) { clearTimeout(job.timer); node.removeEventListener('animationend', job.end); node.removeEventListener('transitionend', job.end); exitJobs.delete(node); }
    node.classList.remove('is-closing');
  };
  const exit = (node, done, immediate = false) => {
    cancelExit(node);
    if (immediate || reduced() || node.hidden) { done(); return; }
    node.classList.add('is-closing');
    const job = {finish: () => { if (exitJobs.get(node) !== job) return; cancelExit(node); done(); }};
    job.end = event => { if (event.target === node) job.finish(); };
    node.addEventListener('animationend', job.end); node.addEventListener('transitionend', job.end);
    job.timer = setTimeout(job.finish, 300); exitJobs.set(node, job);
  };
  motionPreference.addEventListener('change', () => { if (reduced()) { [...exitJobs.values()].forEach(job => job.finish()); document.body.classList.remove('motion-ready'); } });
  const header = document.querySelector('[data-handoff-header], .site-header, .checkout-header');
  const panels = [...document.querySelectorAll('[data-nav-panel]')];
  const triggers = [...document.querySelectorAll('[data-nav-open]')];
  const scrim = document.querySelector('[data-nav-scrim]');
  const opened = new Map();
  let hoverTimer, shopPinned=false, searchScroll=null;
  let pageGutter=Math.max(0,innerWidth-document.body.clientWidth);
  const panelFor = name => panels.find(node=>node.dataset.navPanel===name);
  const topLayer = () => opened.has('shop')?'shop':opened.has('search')?'search':null;
  const layout = () => {
    const hero = document.querySelector('.campaign');
    if (hero && header) {
      const boundary = innerWidth <= 720 ? 85 : 101;
      const wasDocked = document.body.classList.contains('header-docked');
      document.body.classList.toggle('header-docked', hero.getBoundingClientRect().bottom <= boundary + (wasDocked ? 12 : 0));
    }
    const locked=document.documentElement.classList.contains('search-open')||Boolean(document.querySelector('dialog[open]'));
    if(!locked)pageGutter=Math.max(0,innerWidth-document.body.clientWidth);
    document.documentElement.style.setProperty('--layout-width', (locked?Math.max(0,innerWidth-pageGutter):document.body.clientWidth)+'px');
    document.documentElement.style.setProperty('--viewport-width', innerWidth+'px');
    document.documentElement.style.setProperty('--page-gutter', pageGutter+'px');
    if (!header) return;
    const rect=header.getBoundingClientRect(),style=getComputedStyle(header);
    const gap=document.body.classList.contains('home-page')?Math.min(10,Math.max(0,rect.top/(innerWidth<=720?17:26)*10)):0;
    document.documentElement.style.setProperty('--nav-panel-top',Math.ceil(rect.bottom+gap)+'px');
    document.documentElement.style.setProperty('--notice-top',Math.ceil(rect.bottom+12)+'px');
    document.documentElement.style.setProperty('--account-sticky-top',Math.ceil(rect.bottom+20)+'px');
    document.documentElement.style.setProperty('--checkout-sticky-top',Math.ceil(rect.bottom)+'px');
    const searchPanel=panelFor('search');
    if(searchPanel&&!searchPanel.hidden){
      const panelRect=searchPanel.getBoundingClientRect(),panelStyle=getComputedStyle(searchPanel),shop=innerWidth>720?triggers.find(node=>node.dataset.navOpen==='shop'&&node.getClientRects().length):null,bag=header.querySelector('[data-bag-open]');
      const start=shop?.getBoundingClientRect().left??header.querySelector('.wordmark')?.getBoundingClientRect().left??rect.left+parseFloat(style.paddingLeft);
      const end=innerWidth>720&&bag?bag.getBoundingClientRect().right:header.querySelector('.header-actions')?.getBoundingClientRect().right??rect.right-parseFloat(style.paddingRight);
      searchPanel.style.setProperty('--search-gutter-left',Math.max(0,start-panelRect.left-parseFloat(panelStyle.borderLeftWidth))+'px');
      searchPanel.style.setProperty('--search-gutter-right',Math.max(0,panelRect.right-end-parseFloat(panelStyle.borderRightWidth))+'px');
    }
  };
  const lockSearch=()=>{
    if(searchScroll===null)searchScroll={left:scrollX,top:scrollY};
    document.documentElement.classList.add('search-open');document.body.classList.add('search-open');
  };
  const unlockSearch=()=>{
    if(opened.has('search'))return;
    document.documentElement.classList.remove('search-open');document.body.classList.remove('search-open');
    const position=searchScroll;searchScroll=null;
    if(position)window.scrollTo({...position,behavior:'instant'});
  };
  const finishScrim=(immediate=false)=>{
    if(opened.size||panels.some(node=>!node.hidden))return;
    if(scrim&&!scrim.hidden){scrim.classList.remove('is-active');exit(scrim,()=>{if(!opened.size){scrim.hidden=true;unlockSearch();}},immediate);}else unlockSearch();
  };
  const closeLayer=(name,restore=false,immediate=false)=>{
    const panel=panelFor(name);if(!panel)return;
    const opener=opened.get(name);opened.delete(name);if(name==='shop')shopPinned=false;
    triggers.filter(node=>node.dataset.navOpen===name).forEach(node=>node.setAttribute('aria-expanded','false'));
    exit(panel,()=>{
      panel.hidden=true;panel.removeAttribute('open');finishScrim(immediate);
      if(restore){const target=opened.has('search')?panelFor('search')?.querySelector('input[type=search]'):opener;target?.focus({preventScroll:true});}
    },immediate);
  };
  const closeNav=(restore=false,immediate=false)=>{
    clearTimeout(hoverTimer);
    const opener=opened.get('search')||opened.get('shop');
    closeLayer('shop',false,immediate);closeLayer('search',false,immediate);
    if(restore)opener?.focus({preventScroll:true});
  };
  const openNav=(name,trigger,keyboard=false)=>{
    clearTimeout(hoverTimer);const panel=panelFor(name);if(!panel)return;
    if(name==='search')closeLayer('shop',false,true);
    if(opened.has(name))return;
    cancelExit(panel);opened.set(name,trigger);panel.hidden=false;panel.setAttribute('open','');
    if(name==='search')lockSearch();
    triggers.filter(node=>node.dataset.navOpen===name).forEach(node=>node.setAttribute('aria-expanded','true'));
    if(scrim){cancelExit(scrim);scrim.hidden=false;requestAnimationFrame(()=>{if(opened.size)scrim.classList.add('is-active');});}
    layout();
    if(name==='search')panel.querySelector('input[type=search]')?.focus({preventScroll:true});
    else if(keyboard)panel.querySelector('[data-menu-entry]')?.focus({preventScroll:true});
  };
  const hoverClose=()=>{if(!shopPinned&&opened.has('shop'))hoverTimer=setTimeout(()=>closeLayer('shop'),180);};
  triggers.forEach(trigger=>{
    trigger.addEventListener('click',event=>{
      if(event.ctrlKey||event.metaKey||event.shiftKey||event.altKey||event.button>0)return;
      event.preventDefault();const name=trigger.dataset.navOpen;
      if(name==='shop'){
        if(shopPinned&&opened.has('shop'))closeLayer('shop',true);
        else{openNav('shop',trigger,event.detail===0);shopPinned=true;}
      }else if(opened.has(name))closeNav(true);else openNav(name,trigger,event.detail===0);
    });
    if(trigger.dataset.navOpen==='shop'){
      trigger.addEventListener('pointerenter',event=>{if(event.pointerType==='mouse'&&matchMedia('(hover:hover)').matches)openNav('shop',trigger);});
      trigger.addEventListener('pointerleave',hoverClose);
    }
  });
  const shopPanel=panelFor('shop');
  shopPanel?.addEventListener('pointerenter',()=>clearTimeout(hoverTimer));
  shopPanel?.addEventListener('pointerleave',event=>{if(event.pointerType==='mouse')hoverClose();});
  scrim?.addEventListener('click',()=>{const name=topLayer();if(name)closeLayer(name,true);});
  document.querySelectorAll('[data-nav-close]').forEach(button=>button.addEventListener('click',()=>{const name=topLayer();if(name)closeLayer(name,true);}));
  document.addEventListener('keydown',event=>{const name=topLayer();if(event.key==='Escape'&&name){closeLayer(name,true);event.preventDefault();}});
  document.addEventListener('focusin',event=>{
    if(!opened.size||header?.contains(event.target))return;
    if(panelFor('search')?.contains(event.target)&&opened.has('shop')){closeLayer('shop');return;}
    if(!panels.some(panel=>panel.contains(event.target)))closeNav();
  });
  addEventListener('scroll',layout,{passive:true});addEventListener('resize',layout);
  if(header){new ResizeObserver(layout).observe(header);header.addEventListener('transitionend',layout);}
  panels.forEach(panel=>new ResizeObserver(layout).observe(panel));
  layout();

  const search = document.querySelector('[data-handoff-search]');
  const searchResults = document.querySelector('[data-handoff-search-results]');
  const clearSearch = document.querySelector('[data-search-clear]');
  clearSearch?.addEventListener('click', () => { search.value = ''; search.dispatchEvent(new Event('input')); search.focus(); });
  const suggestedSearch = () => {
    if (!searchResults) return;
    searchResults.replaceChildren();
    const group = document.createElement('section'), heading = document.createElement('h3'), terms = document.createElement('div');
    group.className = 'nav-search-group'; heading.textContent = 'Suggested search terms'; terms.className = 'nav-search-terms';
    for (const [label, value] of [['Shampoo','shampoo'],['Conditioner','conditioner'],['Hair assessment','hair assessment'],['Hair profile','hair profile'],['My Routine','routine'],['Care guide','care guide']]) {
      const button = document.createElement('button'); button.type = 'button'; button.textContent = label;
      button.addEventListener('click', () => { search.value = value; search.dispatchEvent(new Event('input')); search.focus(); }); terms.append(button);
    }
    group.append(heading, terms); searchResults.append(group);
  };
  const highlight = (node, text, query) => {
    const index = text.toLocaleLowerCase().indexOf(query.toLocaleLowerCase());
    if (index < 0) { node.textContent = text; return; }
    const mark = document.createElement('mark'); mark.textContent = text.slice(index, index + query.length);
    node.append(document.createTextNode(text.slice(0,index)), mark, document.createTextNode(text.slice(index + query.length)));
  };
  const sameOrigin = path => { if (typeof path !== 'string' || !path) return null; try { const target = new URL(path, location.href); return target.origin === location.origin ? target : null; } catch (_) { return null; } };
  let searchRequest, searchTimer, searchGeneration = 0;
  suggestedSearch();
  search?.addEventListener('input', () => {
    clearTimeout(searchTimer); searchRequest?.abort();
    if (clearSearch) clearSearch.hidden = !search.value;
    const current = ++searchGeneration;
    if (!search.value.trim()) { searchResults.removeAttribute('aria-busy'); suggestedSearch(); return; }
    searchResults.setAttribute('aria-busy','true'); searchResults.textContent = 'Searching…';
    searchTimer = setTimeout(async () => {
      const results = searchResults;
      const query = search.value.trim();
      const controller = new AbortController(); searchRequest = controller;
      const timeout = setTimeout(() => controller.abort(), 8000);
      try {
        const endpoint = sameOrigin(search.dataset.handoffSearch); if (!endpoint) throw Error('Search unavailable'); endpoint.searchParams.set('q', query);
        const response = await fetch(endpoint, {credentials: 'same-origin', headers: {Accept: 'application/json'}, signal: controller.signal});
        if (!response.ok) throw Error('Search unavailable');
        const data = await response.json();
        if (current !== searchGeneration) return;
        results.replaceChildren();
        let count = 0;
        const products = Array.isArray(data.products) ? data.products.slice(0,4) : [];
        const pages = [...(Array.isArray(data.pages) ? data.pages.slice(0,4) : []), ...(Array.isArray(data.discover) ? data.discover.slice(0,3) : [])];
        for (const [name, items, product] of [['Products',products,true],['Pages and care notes',pages,false]]) {
          if (!items.length) continue;
          const group = document.createElement('section'), h = document.createElement('h3'); group.className = 'nav-search-group'; h.textContent = name; group.append(h);
          for (const item of items) {
            const target = sameOrigin(item.url); if (!target || typeof item.title !== 'string') continue;
            const link = document.createElement('a'); link.href = target.href; link.className = product ? 'nav-search-product' : 'nav-search-page';
            if (product) {
              const image = document.createElement('img'), title = document.createElement('strong'), price = document.createElement('span');
              const source = sameOrigin(item.imageUrl); if (source) image.src = source.href; image.alt = ''; image.width = 32; image.height = 39;
              highlight(title, item.title, query); price.textContent = item.priceLabel || ''; link.append(image,title,price);
            } else highlight(link, item.title, query);
            group.append(link); count++;
          }
          if (group.children.length > 1) results.append(group);
        }
        if (!count) {
          const empty = document.createElement('div'), h = document.createElement('h3'), copy = document.createElement('p'), clear = document.createElement('button');
          empty.className = 'search-empty'; h.textContent = 'No matches, just yet.'; copy.textContent = 'Try a product, a care step, or a page like “hair assessment”.'; clear.className = 'text-link'; clear.type = 'button'; clear.textContent = 'Clear search';
          clear.addEventListener('click', () => { search.value = ''; search.dispatchEvent(new Event('input')); search.focus(); }); empty.append(h,copy,clear); results.append(empty);
        } else {
          const resultCount = document.createElement('p'); resultCount.className = 'search-result-count'; resultCount.textContent = count === 1 ? '1 result' : count + ' results shown'; results.prepend(resultCount);
          const all = document.createElement('a'), label = document.createElement('span'), hint = document.createElement('small');
          all.href = '/search?q=' + encodeURIComponent(query); all.className = 'view-all-search'; label.textContent = 'View all results'; hint.textContent = 'Press enter'; all.append(label,hint); results.append(all);
        }
      } catch (_) { if (current === searchGeneration) results.textContent = 'Search is temporarily unavailable. Press Enter to open the full search page.'; }
      finally { clearTimeout(timeout); if (current === searchGeneration) results.removeAttribute('aria-busy'); }
    }, 200);
  });

  const bag = document.querySelector('#avela-bag');
  if (bag) {
    const content = bag.querySelector('[data-bag-content]');
    // Count the list's scrollbar inside its right gutter, so rows, header and
    // checkout stay aligned for both short and scrolling bags.
    let bagScrollbar = -1;
    const alignBag = () => {
      const width = Math.max(0, content.offsetWidth - content.clientWidth);
      if (width !== bagScrollbar) {
        bagScrollbar = width;
        bag.style.setProperty('--bag-scrollbar', width + 'px');
      }
    };
    new ResizeObserver(alignBag).observe(content);
    let opener, request, generation = 0, changed = false;
    const checkout = bag.querySelector('[data-cart-checkout]');
    const checkoutState = ready => {
      checkout.classList.toggle('is-disabled', !ready);
      if (ready) { checkout.href = checkout.dataset.checkoutUrl; checkout.removeAttribute('aria-disabled'); checkout.removeAttribute('tabindex'); }
      else { checkout.removeAttribute('href'); checkout.setAttribute('aria-disabled','true'); checkout.setAttribute('tabindex','-1'); }
    };
    const loadBag = async () => {
      request?.abort(); request = new AbortController(); const current = ++generation;
      content.setAttribute('aria-busy', 'true');
      content.textContent='Loading your bag…';
      bag.querySelector('.drawer-footer').hidden=false;
      bag.querySelector('[data-bag-subtotal]').textContent='—';
      checkoutState(false);
      const timer = setTimeout(() => request.abort(), 10000);
      try {
        const response = await fetch(bag.dataset.bagUrl, {credentials: 'same-origin', cache: 'no-store', signal: request.signal});
        if (!response.ok || response.redirected) throw Error('Your bag is unavailable. Please sign in again or try reopening it.');
        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        const fragment = doc.querySelector('[data-bag-fragment]');
        if (!fragment) throw Error('Your bag could not be loaded. Please try reopening it.');
        if (current !== generation) return;
        fragment.querySelectorAll('script,style,iframe,object,embed').forEach(node => node.remove());
        content.replaceChildren(fragment);
        const count = Number(fragment.dataset.itemCount);
        bag.querySelector('[data-bag-count]').textContent = '(' + count + ')';
        bag.querySelector('[data-bag-subtotal]').textContent = '₱' + fragment.dataset.subtotal;
        bag.querySelector('.drawer-footer').hidden = count === 0;
        checkoutState(fragment.dataset.checkoutReady === 'true');
        document.querySelectorAll('[data-cart-count]').forEach(node => { node.textContent = count; node.hidden = count < 1; });
        document.dispatchEvent(new CustomEvent('avela:content', {detail: {root: content}}));
      } catch (error) { if (current === generation) { content.textContent = error.name === 'AbortError' ? 'Your bag is taking longer than expected. Please try reopening it.' : error.message; } }
      finally { clearTimeout(timer); if (current === generation) content.removeAttribute('aria-busy'); }
    };
    const openBag = link => {
      if (!bag.showModal || bag.open && !bag.classList.contains('is-closing')) return;
      cancelExit(bag);
      closeNav(); window.closeFilterDrawer?.({restoreFocus: false, immediate: true});
      opener = link; document.documentElement.classList.add('bag-open'); document.body.classList.add('bag-open');
      if (!bag.open) bag.showModal(); opener?.setAttribute('aria-expanded', 'true'); loadBag();
    };
    document.querySelectorAll('[data-bag-open]').forEach(link => link.addEventListener('click', event => {
      if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0 || !bag.showModal) return;
      event.preventDefault(); openBag(link);
    }));
    if (new URL(location.href).searchParams.get('bag') === 'open') {
      openBag(document.querySelector('[data-bag-open]'));
      const clean = new URL(location.href); clean.searchParams.delete('bag'); history.replaceState(null,'',clean);
    }
    const closeBag = () => { if (bag.open) exit(bag, () => bag.close()); };
    // Recheck server stock after returning to an open, idle bag. Never overwrite a quantity being edited.
    const recheckBag = () => {
      if (!bag.open || document.hidden || bag.classList.contains('is-closing') || content.hasAttribute('aria-busy')
          || content.querySelector('form[aria-busy="true"]') || content.contains(document.activeElement)) return;
      loadBag();
    };
    addEventListener('focus', recheckBag); document.addEventListener('visibilitychange', recheckBag);
    bag.querySelectorAll('[data-bag-close]').forEach(button => button.addEventListener('click', closeBag));
    bag.addEventListener('cancel', event => { event.preventDefault(); closeBag(); });
    bag.addEventListener('click', event => { if (event.target === bag) { const r = bag.getBoundingClientRect(); if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) closeBag(); } });
    bag.addEventListener('close', () => { generation++; request?.abort(); document.documentElement.classList.remove('bag-open'); document.body.classList.remove('bag-open'); opener?.setAttribute('aria-expanded', 'false'); opener?.focus({preventScroll: true}); if (changed && document.body.classList.contains('checkout-page')) location.reload(); });
    bag.addEventListener('input',event=>{if(event.target.matches('input[name=quantity]'))changed=true;});
    bag.addEventListener('click',event=>{if(event.target.closest('[data-quantity-minus],[data-quantity-plus]'))changed=true;});
    checkout?.addEventListener('click',event=>{if(changed&&document.body.classList.contains('checkout-page')){event.preventDefault();location.reload();}});
    bag.addEventListener('submit', async event => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement) || new URL(form.action).pathname !== '/cart/remove') return;
      event.preventDefault(); const button = form.querySelector('button'); if (button.disabled) return;
      button.disabled = true;
      try {
        const response = await fetch(form.action, {method: 'POST', body: new URLSearchParams(new FormData(form)), credentials: 'same-origin', cache: 'no-store'});
        if (!response.ok || !['/cart','/'].includes(new URL(response.url).pathname)) throw Error('Your bag could not be updated. Please try again.');
        changed = true;
        await loadBag(); bag.querySelector('[data-bag-close]').focus({preventScroll: true});
      } catch (error) { window.AvelaToast?.(error.message, 'error'); button.disabled = false; }
    });
    // The existing cart handler applies server-approved quantities and totals.
    new MutationObserver(() => {
      if (!bag.open) return;
      if (!content.querySelector('[data-bag-fragment]')) return;
      let subtotal = 0;
      content.querySelectorAll('[data-cart-line-total]').forEach(node => { subtotal += Number(node.textContent.replace(/[^0-9.]/g, '')) || 0; });
      bag.querySelector('[data-bag-subtotal]').textContent = '₱' + subtotal.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
      const count=[...content.querySelectorAll('input[data-confirmed-quantity]')].reduce((sum,node)=>sum+Number(node.dataset.confirmedQuantity||0),0);
      bag.querySelector('[data-bag-count]').textContent='('+count+')';
    }).observe(content, {subtree: true, characterData: true, childList: true});
  }

  // Preserve the already working editorial tabs/filtering, without the old header/bag code.
  const steps = {"cleanse": ["01", "Start at the roots.", "Focus shampoo on your scalp and let the rinse flow through your lengths. Your texture and how oily your scalp feels can help you find a wash rhythm that suits you.", "wash-day", "THE CLEANSING STEP", "Shampoo · Wash day"], "condition": ["02", "Care for your lengths.", "Follow washing with conditioner. For fine or straight hair, focus on the ends; dry or curly hair may need it along the lengths. Follow the directions on your product.", "condition-with-care", "THE CONDITIONING STEP", "Conditioner · After cleansing"], "finish": ["03", "Keep the finish gentle.", "Detangle with care and avoid rubbing with a towel. If you use heat, choose a low setting. Leave-in care is an optional extra, guided by your hair and the product label.", "a-gentler-finish", "THE FINISHING STEP", "Gentle styling · Optional extras"]};
  const tabs = [...document.querySelectorAll('[data-v2-routine-tab]')];
  const activate = tab => { const step = steps[tab.dataset.v2RoutineTab], panel = document.querySelector('#routine-panel'); if (!step || !panel) return; tabs.forEach(node => { node.setAttribute('aria-selected', String(node === tab)); node.tabIndex = node === tab ? 0 : -1; }); panel.setAttribute('aria-labelledby', tab.id); panel.querySelector('.step-number').textContent = 'STEP '+step[0]; panel.querySelector('[data-v2-step-title]').textContent = step[1]; panel.querySelector('[data-v2-step-copy]').textContent = step[2]; if(panel.querySelector('a'))panel.querySelector('a').href = '/care-guide/' + step[3]; if(panel.querySelector('[data-v2-step-category]'))panel.querySelector('[data-v2-step-category]').textContent=step[4]; if(panel.querySelector('[data-v2-step-detail]'))panel.querySelector('[data-v2-step-detail]').textContent=step[5]; panel.querySelectorAll('[data-step-art]').forEach(node => { node.toggleAttribute('hidden', node.dataset.stepArt !== tab.dataset.v2RoutineTab); }); };
  tabs.forEach((tab, index) => { tab.addEventListener('click', () => activate(tab)); tab.addEventListener('keydown', event => { const positions = {ArrowRight: (index + 1) % tabs.length, ArrowLeft: (index + tabs.length - 1) % tabs.length, Home: 0, End: tabs.length - 1}; if (positions[event.key] !== undefined) { event.preventDefault(); activate(tabs[positions[event.key]]); tabs[positions[event.key]].focus(); } }); });
  const careSearch = document.querySelector('[data-care-search]'), categories = [...document.querySelectorAll('[data-care-category]')]; let category = 'All notes';
  const filter = () => { const query = (careSearch?.value || '').toLocaleLowerCase().trim(); let count = 0; document.querySelectorAll('[data-care-note]').forEach(note => { note.hidden = (category !== 'All notes' && note.dataset.category !== category) || !note.textContent.toLocaleLowerCase().includes(query); if (!note.hidden) count++; }); const status = document.querySelector('[data-care-count]'); if (status) status.textContent = count + (count === 1 ? ' note' : ' notes'); const empty = document.querySelector('[data-care-empty]'); if (empty) empty.hidden = count > 0; };
  careSearch?.addEventListener('input', filter); categories.forEach(button => button.addEventListener('click', () => { category = button.dataset.careCategory; categories.forEach(node => node.setAttribute('aria-pressed', String(node === button))); filter(); }));
  document.querySelectorAll('[data-texture-dialog]').forEach(button => button.addEventListener('click', () => document.getElementById(button.dataset.textureDialog)?.showModal()));
  document.querySelectorAll('[data-texture-close]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
  if ('IntersectionObserver' in window && !reduced()) {
    try {
      const observer = new IntersectionObserver(entries => entries.forEach(entry => { if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); } }), {threshold: .08});
      document.querySelectorAll('.reveal').forEach(node => observer.observe(node)); document.body.classList.add('motion-ready');
    } catch (_) { document.body.classList.remove('motion-ready'); }
  }
})();
