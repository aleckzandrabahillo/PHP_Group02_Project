(() => {
 const dialog=document.querySelector('#address-dialog');if(!dialog)return;
 const form=dialog.querySelector('form'),submit=form.querySelector('[type=submit]'),error=dialog.querySelector('[data-address-error]');let busy=false,opener=null,oldOverflow='',saved=new FormData(form);
 const reset=()=>{for(const [name,value] of saved){const control=form.elements.namedItem(name);if(control&&'value' in control)control.value=value;}error.hidden=true;form.querySelectorAll('[data-address-field]').forEach(node=>node.textContent='');form.querySelectorAll('[aria-invalid]').forEach(node=>node.removeAttribute('aria-invalid'));};
 dialog.addEventListener('keydown',event=>{if(event.key!=='Tab')return;const controls=[...dialog.querySelectorAll('button:not([disabled]),input:not([type=hidden]):not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex="0"]')].filter(node=>node.getClientRects().length);const first=controls[0],last=controls.at(-1);if(event.shiftKey&&document.activeElement===first){event.preventDefault();last?.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first?.focus();}});
 document.querySelectorAll('[data-address-open]').forEach(button=>button.addEventListener('click',()=>{reset();opener=button;oldOverflow=document.body.style.overflow;document.body.style.overflow='hidden';(window.AvelaMotion?.openDialog(dialog)??dialog.showModal());}));
 dialog.querySelectorAll('[data-dialog-close]').forEach(button=>button.addEventListener('click',()=>{if(!busy)(window.AvelaMotion?.closeDialog(dialog)??dialog.close());}));
 dialog.addEventListener('cancel',event=>{event.preventDefault();if(!busy)(window.AvelaMotion?.closeDialog(dialog)??dialog.close());});
 dialog.addEventListener('close',()=>{document.body.style.overflow=oldOverflow;opener?.focus();});
 form.addEventListener('submit',async event=>{
  event.preventDefault();if(busy)return;const controller=new AbortController(),deadline=setTimeout(()=>controller.abort(),10000);busy=true;submit.disabled=true;submit.textContent='Saving…';form.setAttribute('aria-busy','true');error.hidden=true;
  form.querySelectorAll('[data-address-field]').forEach(node=>node.textContent='');form.querySelectorAll('[aria-invalid]').forEach(node=>node.removeAttribute('aria-invalid'));
  try{
   const response=await fetch(form.action,{method:'POST',credentials:'same-origin',body:new URLSearchParams(new FormData(form)),headers:{Accept:'application/json'},cache:'no-store',signal:controller.signal});
   if(response.redirected){location.assign('/login');return;}const data=await response.json();
   if(!response.ok||!data.ok){for(const [field,message] of Object.entries(data.errors||{})){const node=form.querySelector(`[data-address-field="${CSS.escape(field)}"]`);if(node){node.textContent=message;form.elements[field]?.setAttribute('aria-invalid','true');}}const problem=new Error(data.message||data.errors?.form||'Please check the highlighted address fields.');problem.userSafe=true;throw problem;}
   form.elements.revision.value=data.revision;saved=new FormData(form);const card=document.querySelector('[data-profile-address]');card.textContent=data.address;document.querySelectorAll('[data-address-open]').forEach(button=>button.textContent='Edit address');dialog.querySelector('h2').textContent='Edit delivery address';await (window.AvelaMotion?.closeDialog(dialog)??dialog.close());window.AvelaToast?.('Your delivery address has been saved.');
  }catch(failure){error.textContent=failure.userSafe?failure.message:'Your address could not be saved. Please try again.';error.hidden=false;error.focus();}
  finally{clearTimeout(deadline);busy=false;submit.disabled=false;submit.textContent='Save address';form.removeAttribute('aria-busy');}
 });
})();
