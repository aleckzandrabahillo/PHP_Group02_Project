/* Shared interaction motion. Business actions remain owned by their existing forms. */
(() => {
  'use strict';
  const timings = {fast:160, base:260, slow:360, dialog:320, page:440, exit:200};
  function createAnimator(duration) {
    const jobs = new WeakMap(), active = new Set();
    const run = (element, frames, kind='base') => {
      jobs.get(element)?.settle(false);
      const ms = duration(kind);
      if (!ms || !element?.animate) return Promise.resolve(true);
      return new Promise(resolve => {
        let animation, timer, done=false;
        const job = {settle(completed) {
          if (done) return; done=true; clearTimeout(timer);
          animation?.cancel(); active.delete(job);
          if (jobs.get(element) === job) jobs.delete(element);
          resolve(completed);
        }};
        try {
          animation=element.animate(frames,{duration:ms,easing:'cubic-bezier(.2,.7,.2,1)'});
          jobs.set(element,job); active.add(job);
          animation.finished.then(()=>job.settle(true),()=>job.settle(false));
          // A suspended/unsupported animation must never trap a dialog or decision.
          timer=setTimeout(()=>job.settle(true),ms+100);
        } catch (_) { job.settle(true); }
      });
    };
    return {run, settleAll:()=>[...active].forEach(job=>job.settle(true))};
  }
  if (typeof module !== 'undefined' && module.exports) module.exports={createAnimator,timings};
  if (typeof window === 'undefined') return;

  // This approved version keeps brief, finite motion enabled on every layout.
  const customerLayout=Boolean(document.body?.classList.contains('avela-v3'));
  // Capture the page geometry before an auto-open dialog removes the root gutter.
  if(customerLayout)document.documentElement.style.setProperty('--layout-width',document.body.clientWidth+'px');
  const motionPreference=window.matchMedia?.('(prefers-reduced-motion: reduce)');
  const reduced=()=>false;
  const duration=kind=>reduced()?0:(timings[kind]??timings.base);
  const animator=createAnimator(duration), dialogJobs=new WeakMap(), detailJobs=new WeakMap();
  const syncPreference=()=>{document.documentElement.dataset.avelaMotion=reduced()?'reduced':'full';if(reduced())animator.settleAll();};
  syncPreference();
  motionPreference?.addEventListener?.('change',syncPreference);
  const reveal=(element,kind='base')=>element&&!element.hidden
    ? animator.run(element,element.matches('.notification-stack > .alert') ? [{opacity:0},{opacity:1}] : [{opacity:.25,transform:'translateY(7px)'},{opacity:1,transform:'translateY(0)'}],kind)
    : Promise.resolve(true);
  function pulse(element) {
    if (element && !element.closest('dialog')) animator.run(element,[{transform:'scale(1)'},{transform:'scale(1.12)',offset:.45},{transform:'scale(1)'}],'base');
  }
  async function openDialog(dialog) {
    const job={}; dialogJobs.set(dialog,job);
    if (!dialog.open) dialog.showModal();
    dialog.dataset.motionState='opening';
    await animator.run(dialog,[{opacity:0,transform:'translateY(12px) scale(.985)'},{opacity:1,transform:'translateY(0) scale(1)'}],'dialog');
    if (dialogJobs.get(dialog)===job) dialog.dataset.motionState='open';
  }
  function closeDialog(dialog) {
    if (!dialog.open) return Promise.resolve();
    const existing=dialogJobs.get(dialog); if(existing?.closing) return existing.promise;
    const job={closing:true}; dialogJobs.set(dialog,job);
    const from=getComputedStyle(dialog); const opacity=from.opacity, transform=from.transform;
    dialog.dataset.motionState='closing';
    job.promise=animator.run(dialog,[{opacity,transform},{opacity:0,transform:'translateY(8px) scale(.99)'}],'exit').then(()=>{
      if(dialogJobs.get(dialog)!==job)return;
      dialog.close(); delete dialog.dataset.motionState; dialogJobs.delete(dialog);
    });
    return job.promise;
  }
  function setDetails(details,open) {
    const summary=details.querySelector(':scope > summary'); if(!summary)return Promise.resolve();
    if(open&&details.matches('.staff-sidebar details')){for(const sibling of details.closest('nav').querySelectorAll(':scope > details'))if(sibling!==details&&(sibling.open||detailJobs.get(sibling)?.open))setDetails(sibling,false);}
    const overlay=details.matches('.checkout-account-menu,.header-popover,.mobile-nav-menu,.account-menu,.assessment-sections,[data-sort-menu]');
    const body=summary.nextElementSibling; if(!body){details.open=open;return Promise.resolve();}
    const current=detailJobs.get(details);
    if(!current&&details.open===open)return Promise.resolve();
    const start=details.getBoundingClientRect().height, style=getComputedStyle(body);
    const fromOpacity=style.opacity, fromTransform=style.transform;
    const job={open};detailJobs.set(details,job);
    if(!open&&body.contains(document.activeElement))summary.focus({preventScroll:true});
    details.open=true;body.inert=!open;summary.setAttribute('aria-expanded',String(open));
    details.dataset.motionState=open?'opening':'closing';
    let animation;
    if(overlay){
      animation=animator.run(body,open?[{opacity:0,transform:'translateY(-7px)'},{opacity:1,transform:'translateY(0)'}]:[{opacity:fromOpacity,transform:fromTransform},{opacity:0,transform:'translateY(-5px)'}],open?'base':'exit');
    }else{
      details.style.overflow='hidden';
      const end=open?details.scrollHeight:summary.getBoundingClientRect().height;
      animation=animator.run(details,[{height:start+'px'},{height:end+'px'}],'base');
    }
    return animation.then(()=>{
      if(detailJobs.get(details)!==job)return;
      details.open=open;details.style.overflow='';body.inert=false;
      details.dataset.motionState=open?'open':'closed';detailJobs.delete(details);
    });
  }
  window.AvelaMotion={duration,reduced,reveal,pulse,openDialog,closeDialog,setDetails,confirmAction};

  let confirmation=null;
  async function confirmAction(message,opener) {
    if(confirmation)return false;
    if(typeof HTMLDialogElement==='undefined')return window.confirm(message);
    const dialog=document.createElement('dialog');dialog.className='account-dialog motion-confirm-dialog';dialog.setAttribute('aria-labelledby','motion-confirm-title');dialog.setAttribute('aria-describedby','motion-confirm-copy');
    const title=document.createElement('h2');title.id='motion-confirm-title';title.textContent='Confirm this change';
    const copy=document.createElement('p');copy.id='motion-confirm-copy';copy.textContent=message;
    const actions=document.createElement('div');actions.className='account-form-actions';
    const cancel=document.createElement('button');cancel.type='button';cancel.className='btn btn-secondary';cancel.textContent='Keep unchanged';
    const proceed=document.createElement('button');proceed.type='button';proceed.className='btn btn-primary';const label=opener?.cloneNode(true);label?.querySelectorAll('.sr-only,[aria-hidden=true]').forEach(node=>node.remove());proceed.textContent=label?.textContent.trim().replace(/\s+/g,' ').slice(0,90)||'Continue';
    actions.append(cancel,proceed);dialog.append(title,copy,actions);document.body.append(dialog);
    confirmation=dialog;const oldOverflow=document.body.style.overflow;document.body.style.overflow='hidden';
    const result=await new Promise(resolve=>{
      let decided=false;
      const finish=async value=>{if(decided)return;decided=true;cancel.disabled=proceed.disabled=true;await closeDialog(dialog);resolve(value);};
      cancel.addEventListener('click',()=>finish(false));proceed.addEventListener('click',()=>finish(true));
      dialog.addEventListener('cancel',event=>{event.preventDefault();finish(false);});
      dialog.addEventListener('keydown',event=>{if(event.key!=='Tab')return;if(event.shiftKey&&document.activeElement===cancel){event.preventDefault();proceed.focus();}else if(!event.shiftKey&&document.activeElement===proceed){event.preventDefault();cancel.focus();}});
      openDialog(dialog);cancel.focus();
    });
    document.body.style.overflow=oldOverflow;dialog.remove();confirmation=null;
    if(opener?.isConnected)opener.focus({preventScroll:true});return result;
  }
  const mountedDetails=new WeakSet();
  function mountDetails(root=document){
    root.querySelectorAll('details').forEach(details=>{
      // Reading surfaces own their grouped height animation; do not bind a second toggle.
      if(details.closest('[data-animate-disclosures]')||mountedDetails.has(details))return;mountedDetails.add(details);
      const summary=details.querySelector(':scope > summary');if(!summary)return;
      summary.addEventListener('click',event=>{event.preventDefault();const job=detailJobs.get(details);setDetails(details,!(job?job.open:details.open));});
      if(details.matches('.checkout-account-menu,.header-popover,.mobile-nav-menu,.account-menu,.assessment-sections,[data-sort-menu]')){
        document.addEventListener('pointerdown',event=>{if(details.open&&!details.contains(event.target))setDetails(details,false);});
        details.addEventListener('keydown',event=>{if(event.key==='Escape'){event.stopPropagation();setDetails(details,false);summary.focus();}});
        details.querySelectorAll('a').forEach(link=>link.addEventListener('click',()=>setDetails(details,false)));
      }
    });
  }
  function setup(){
    mountDetails();document.addEventListener('avela:content',event=>{const root=event.detail?.root||document;mountDetails(root);reveal(root.querySelector('h1'),'fast');});
    const approved=new WeakSet();
    document.addEventListener('submit',async event=>{
      const form=event.target;if(!(form instanceof HTMLFormElement)||!form.matches('[data-confirm]'))return;
      if(approved.has(form)){approved.delete(form);return;}
      event.preventDefault();event.stopImmediatePropagation();const submitter=event.submitter;
      if(await confirmAction(form.dataset.confirm,submitter)){
        if(!form.isConnected)return;approved.add(form);try{form.requestSubmit(submitter?.isConnected?submitter:undefined);}finally{queueMicrotask(()=>approved.delete(form));}
      }
    },true);
    document.addEventListener('submit',event=>{
      const form=event.target;if(event.defaultPrevented||!(form instanceof HTMLFormElement)||(form.method||'').toLowerCase()!=='post')return;
      if(form.dataset.motionSubmitting==='true'){event.preventDefault();return;}
      // Later AJAX handlers may prevent this submission. Inspect the completed
      // dispatch before applying native-navigation feedback, regardless of load order.
      // A task is required: browsers run microtasks between DOM listeners.
      setTimeout(()=>{
        if(event.defaultPrevented||!form.isConnected)return;
        form.dataset.motionSubmitting='true';form.setAttribute('aria-busy','true');
        const button=event.submitter;if(button){button.dataset.motionSubmit='';button.setAttribute('aria-busy','true');}
      },0);
    });
    window.addEventListener('pageshow',()=>document.querySelectorAll('[data-motion-submitting]').forEach(form=>{delete form.dataset.motionSubmitting;form.removeAttribute('aria-busy');form.querySelectorAll('[data-motion-submit]').forEach(button=>{delete button.dataset.motionSubmit;button.removeAttribute('aria-busy');});}));
    document.addEventListener('invalid',event=>{const field=event.target;field.classList.add('motion-invalid');setTimeout(()=>field.classList.remove('motion-invalid'),600);},true);
    const enter=document.querySelector('.editorial-hero-copy')||document.querySelector('main .page-head')||document.querySelector('main .form-head')||document.querySelector('main h1');
    reveal(enter,'page');
    document.querySelectorAll('.orders-chart polyline').forEach(line=>{
      if(!reduced()&&line.getTotalLength){const length=line.getTotalLength();animator.run(line,[{strokeDasharray:length,strokeDashoffset:length},{strokeDasharray:length,strokeDashoffset:0}],'page');}
    });
    document.querySelectorAll('.analytics-bars meter').forEach(meter=>animator.run(meter,[{transform:'scaleX(.05)'},{transform:'scaleX(1)'}],'slow'));
    const badgeObserver=new MutationObserver(records=>{for(const record of records){const badge=record.target.nodeType===1?record.target:record.target.parentElement;if(badge?.matches('[data-cart-count]'))pulse(badge);}});
    document.querySelectorAll('[data-cart-count]').forEach(badge=>badgeObserver.observe(badge,{childList:true,characterData:true,subtree:true}));
    // Animate actual state changes, without adding a second favorite/cart request handler.
    const feedbackObserver=new MutationObserver(records=>records.forEach(record=>{
      if(record.attributeName==='aria-pressed'&&record.oldValue!==record.target.getAttribute('aria-pressed'))pulse(record.target);
      if(record.attributeName==='hidden'&&!record.target.hidden)reveal(record.target);
    }));
    document.querySelectorAll('.favorite-button,[data-discount-row],[data-applied-discount]').forEach(node=>feedbackObserver.observe(node,{attributes:true,attributeOldValue:true,attributeFilter:['aria-pressed','hidden']}));
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',setup,{once:true});else setup();
})();
