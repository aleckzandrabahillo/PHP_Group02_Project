/* Presentation adapters only. Accounts, cart, prices and matching stay on the server. */
(() => {
 'use strict';
 const header=document.querySelector('[data-site-header]');
 const menu=document.querySelector('[data-mega-menu]');
 if(menu){new MutationObserver(()=>document.body.classList.toggle('v2-menu-open',menu.classList.contains('is-open'))).observe(menu,{attributes:true,attributeFilter:['class']});}
 const bag=document.querySelector('#avela-bag');
 if(bag){
  const content=bag.querySelector('[data-bag-content]');let opener=null,request=null,previousOverflow='',sequence=0;
  const render=(html,restoreFocus=false)=>{
   const doc=new DOMParser().parseFromString(html,'text/html'),main=doc.querySelector('main.customer-main');
   if(!main||doc.querySelector('.auth-panel'))throw new Error('Your session may have expired. Open your full bag to sign in again.');
   const restoreContentFocus=restoreFocus||content.contains(document.activeElement);
   main.querySelectorAll('script,style,iframe,object,embed').forEach(node=>node.remove());
   content.replaceChildren(...main.childNodes);
   const count=content.querySelector('[data-cart-summary-count]')?.textContent||'0';
   document.querySelectorAll('[data-cart-count]').forEach(node=>{node.textContent=count;node.hidden=Number(count)<1;});
   document.dispatchEvent(new CustomEvent('avela:content',{detail:{root:content}}));
   if(restoreContentFocus&&bag.open){const target=content.querySelector('h2,h3')||bag.querySelector('[data-bag-close]');target.setAttribute('tabindex','-1');target.focus({preventScroll:true});}
  };
  const load=async()=>{
   request?.abort();request=new AbortController();const current=++sequence;content.setAttribute('aria-busy','true');
   const loading=document.createElement('p');loading.textContent='Loading your bag…';content.replaceChildren(loading);
   const timer=setTimeout(()=>request?.abort(),10000);
   try{const response=await fetch(bag.dataset.bagUrl,{credentials:'same-origin',cache:'no-store',signal:request.signal});if(!response.ok)throw new Error('Your bag could not be loaded. Open your full bag or try again.');const html=await response.text();if(current===sequence)render(html);}
   catch(error){if(current===sequence){const message=document.createElement('p');message.textContent=error.name==='AbortError'?'Your bag is taking longer than expected. Open your full bag or try again.':error.message;content.replaceChildren(message);}}
   finally{clearTimeout(timer);if(current===sequence)content.removeAttribute('aria-busy');}
  };
  document.querySelectorAll('[data-bag-open]').forEach(link=>link.addEventListener('click',event=>{
   if(event.ctrlKey||event.metaKey||event.shiftKey||event.altKey||event.button!==0||location.pathname==='/cart'||!bag.showModal)return;
   event.preventDefault();opener=link;window.closeMegaMenu?.(false);window.closeGlobalSearch?.({restoreFocus:false,immediate:true});window.closeFilterDrawer?.({restoreFocus:false,immediate:true});
   previousOverflow=document.body.style.overflow;document.body.style.overflow='hidden';bag.showModal();link.setAttribute('aria-expanded','true');bag.querySelector('[data-bag-close]').focus();load();
  }));
  bag.querySelector('[data-bag-close]').addEventListener('click',()=>bag.close());
  bag.addEventListener('click',event=>{if(event.target===bag){const r=bag.getBoundingClientRect();if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)bag.close();}});
  bag.addEventListener('close',()=>{sequence++;request?.abort();document.body.style.overflow=previousOverflow;opener?.setAttribute('aria-expanded','false');opener?.focus({preventScroll:true});});
  // Existing delegated quantity handlers remain authoritative. Removal uses the same POST/CSRF contract.
  bag.addEventListener('submit',async event=>{
   const form=event.target;if(!(form instanceof HTMLFormElement)||new URL(form.action).pathname!=='/cart/remove')return;
   event.preventDefault();const button=form.querySelector('button');if(button.disabled)return;button.disabled=true;button.setAttribute('aria-busy','true');
   try{const response=await fetch(form.action,{method:'POST',body:new URLSearchParams(new FormData(form)),credentials:'same-origin',cache:'no-store'});if(!response.ok)throw new Error('Your bag could not be updated. Please try again.');render(await response.text(),true);}
   catch(error){window.AvelaToast?.(error.message,'error');button.disabled=false;button.removeAttribute('aria-busy');}
  });
 }
 const steps={cleanse:['01','Start at the scalp.','Massage shampoo gently into your scalp. Follow your product’s directions and rinse through the lengths.','wash-day'],condition:['02','A little softness. A little slip.','Condition after washing. Apply according to your hair’s needs and the directions on your product.','condition-with-care'],finish:['03','A gentler finish.','Handle wet hair gently, reduce rubbing, and follow the directions for your chosen finishing products.','a-gentler-finish']};
 const tabs=[...document.querySelectorAll('[data-v2-routine-tab]')];
 const activate=tab=>{const step=steps[tab.dataset.v2RoutineTab],panel=document.querySelector('#routine-panel');if(!step||!panel)return;tabs.forEach(node=>{node.setAttribute('aria-selected',String(node===tab));node.tabIndex=node===tab?0:-1;});panel.setAttribute('aria-labelledby',tab.id);panel.querySelector('.step-number').textContent=step[0];panel.querySelector('[data-v2-step-title]').textContent=step[1];panel.querySelector('[data-v2-step-copy]').textContent=step[2];panel.querySelector('a').href='/care-guide/'+step[3];};
 tabs.forEach((tab,index)=>{tab.addEventListener('click',()=>activate(tab));tab.addEventListener('keydown',event=>{let next;if(event.key==='ArrowRight')next=(index+1)%tabs.length;if(event.key==='ArrowLeft')next=(index+tabs.length-1)%tabs.length;if(event.key==='Home')next=0;if(event.key==='End')next=tabs.length-1;if(next!==undefined){event.preventDefault();activate(tabs[next]);tabs[next].focus();}});});
 const search=document.querySelector('[data-care-search]'),categories=[...document.querySelectorAll('[data-care-category]')];let category='All notes';
 const filter=()=>{const query=(search?.value||'').toLocaleLowerCase().trim();let count=0;document.querySelectorAll('[data-care-note]').forEach(note=>{note.hidden=(category!=='All notes'&&note.dataset.category!==category)||!note.textContent.toLocaleLowerCase().includes(query);if(!note.hidden)count++;});const status=document.querySelector('[data-care-count]');if(status)status.textContent=count+(count===1?' note':' notes');const empty=document.querySelector('[data-care-empty]');if(empty)empty.hidden=count>0;};
 search?.addEventListener('input',filter);categories.forEach(button=>button.addEventListener('click',()=>{category=button.dataset.careCategory;categories.forEach(node=>node.setAttribute('aria-pressed',String(node===button)));filter();}));
 // Never hide content until observation is available; reduced motion remains fully readable.
 if('IntersectionObserver' in window&&!matchMedia('(prefers-reduced-motion: reduce)').matches){const observer=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('is-visible');observer.unobserve(entry.target);}}),{threshold:.08});document.querySelectorAll('.v2-reference .reveal').forEach(node=>observer.observe(node));}
})();
