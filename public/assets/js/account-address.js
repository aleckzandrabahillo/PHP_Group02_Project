(() => {
 'use strict';
 const mounted=new WeakSet();
 function mount(){
  const dialog=document.querySelector('#address-dialog');if(!dialog||mounted.has(dialog))return;mounted.add(dialog);
  const form=dialog.querySelector('form'),submit=form.querySelector('[type=submit]'),error=dialog.querySelector('[data-address-error]'),shell=dialog.closest('.account-main'),list=shell.querySelector('[data-address-list]'),feedback=shell.querySelector('[data-address-feedback]');
  let busy=false,opener=null,oldOverflow='';
  const close=()=>{if(!busy)(window.AvelaMotion?.closeDialog(dialog)??dialog.close());};
  shell.addEventListener('click',event=>{
   const button=event.target.closest('[data-address-open]');if(!button||busy)return;
   form.reset();error.hidden=true;form.querySelectorAll('[data-address-field]').forEach(node=>node.textContent='');form.querySelectorAll('[aria-invalid]').forEach(node=>node.removeAttribute('aria-invalid'));
   const address=button.dataset.address?JSON.parse(button.dataset.address):{id:-1,revision:''};
   for(const [name,value] of Object.entries(address)){const control=form.elements.namedItem(name==='id'?'address_id':name);if(control&&'value' in control)control.value=name==='region'?(address.province||(value==='NCR'?'Metro Manila':value)):value;}
   form.elements.make_default.checked=Boolean(address.is_default);dialog.querySelector('h2').textContent=address.id===-1?'Add delivery address':'Edit delivery address';
   form.querySelectorAll('select').forEach(control=>control.dispatchEvent(new Event('change',{bubbles:true})));
   opener=button;oldOverflow=document.body.style.overflow;document.body.style.overflow='hidden';(window.AvelaMotion?.openDialog(dialog)??dialog.showModal());
  });
  dialog.querySelectorAll('[data-dialog-close]').forEach(button=>button.addEventListener('click',close));
  dialog.addEventListener('cancel',event=>{event.preventDefault();close();});
  dialog.addEventListener('close',()=>{document.body.style.overflow=oldOverflow;if(opener?.isConnected)opener.focus();else shell.querySelector('[data-address-new]')?.focus();});
  async function send(target){
   if(busy)return;busy=true;feedback.textContent='';error.hidden=true;
   const buttons=[...shell.querySelectorAll('[data-address-action] button,[data-address-form] button[type=submit],[data-address-open]')];buttons.forEach(button=>button.disabled=true);target.setAttribute('aria-busy','true');
   const controller=new AbortController(),deadline=setTimeout(()=>controller.abort(),10000);
   try{
    const response=await fetch(target.getAttribute('action'),{method:'POST',credentials:'same-origin',body:new URLSearchParams(new FormData(target)),headers:{Accept:'application/json'},cache:'no-store',signal:controller.signal});
    if(response.redirected){location.assign('/login');return;}const data=await response.json();
    if(!response.ok||!data.ok){for(const [field,message] of Object.entries(data.errors||{})){const node=form.querySelector(`[data-address-field="${CSS.escape(field)}"]`);if(node){node.textContent=message;form.elements[field]?.setAttribute('aria-invalid','true');}}const problem=new Error(data.message||data.errors?.form||'Check your address fields.');problem.userSafe=true;throw problem;}
    // Only escaped markup produced by our authenticated, same-origin endpoint is accepted.
    const fragment=new DOMParser().parseFromString(data.html,'text/html');list.replaceChildren(...fragment.body.childNodes);
    if(window.AvelaToast)window.AvelaToast(data.message);else feedback.textContent=data.message;
    if(dialog.open)await (window.AvelaMotion?.closeDialog(dialog)??dialog.close());
   }catch(failure){const message=failure.userSafe?failure.message:'Your addresses could not be updated. Please try again.';if(dialog.open){error.textContent=message;error.hidden=false;error.focus();}else feedback.textContent=message;}
   finally{clearTimeout(deadline);busy=false;buttons.forEach(button=>button.disabled=false);target.removeAttribute('aria-busy');}
  }
  shell.addEventListener('submit',async event=>{const target=event.target;if(!target.matches('[data-address-form],[data-address-action]'))return;event.preventDefault();if(busy)return;if(target.elements.namedItem('action')?.value==='delete'){const message='Delete this delivery address? Existing orders keep their original delivery details.';const approved=window.AvelaMotion?.confirmAction?await window.AvelaMotion.confirmAction(message,event.submitter):confirm(message);if(!approved)return;}send(target);});
 }
 mount();document.addEventListener('avela:content',mount);
})();
