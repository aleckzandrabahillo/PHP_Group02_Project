(() => {
 'use strict';
 const close=(control,focus=false)=>{const menu=control?.querySelector('[data-photo-toggle]');control?.querySelector('.profile-photo-menu')?.setAttribute('hidden','');menu?.setAttribute('aria-expanded','false');if(focus)menu?.focus();};
 document.addEventListener('click',event=>{
  const target=event.target instanceof Element?event.target:null;if(!target)return;const control=target.closest('.profile-photo-control');
  document.querySelectorAll('.profile-photo-control').forEach(item=>{if(item!==control)close(item);});
  if(target.closest('[data-photo-toggle]')){const button=control.querySelector('[data-photo-toggle]'),menu=control.querySelector('.profile-photo-menu'),opening=menu.hidden;menu.hidden=!opening;button.setAttribute('aria-expanded',String(opening));if(opening){window.AvelaMotion?.reveal(menu,'fast');menu.querySelector('button')?.focus();}return;}
  if(target.closest('[data-photo-change]')){const picker=control.querySelector('[data-photo-input]');if(picker){picker.disabled=false;picker.click();}return;}
  if(target.closest('[data-photo-view]'))close(control);
 });
 document.addEventListener('keydown',event=>{if(event.key==='Escape'){const control=event.target.closest?.('.profile-photo-control');if(control&&!control.querySelector('.profile-photo-menu').hidden){event.stopPropagation();close(control,true);}}});
 window.addEventListener('pageshow',event=>{if(!event.persisted)return;document.querySelectorAll('.profile-photo-control').forEach(control=>{control.removeAttribute('aria-busy');control.querySelector('[data-photo-toggle]').disabled=false;control.querySelector('[data-photo-input]').value='';control.querySelector('.photo-upload-status')?.remove();});});
 document.addEventListener('change',event=>{
  const input=event.target;if(!(input instanceof HTMLInputElement)||!input.matches('[data-photo-input]'))return;const file=input.files?.[0];if(!file)return;
  const allowed=['image/jpeg','image/png','image/webp'];if(file.size<1||file.size>Number(input.dataset.maxBytes)||!allowed.includes(file.type)){input.value='';input.setCustomValidity('');window.AvelaToast?.('Choose a JPG, PNG or WebP of '+input.dataset.maxLabel+' or smaller.','warning');return;}
  input.setCustomValidity('');const control=input.closest('.profile-photo-control');close(control);control.setAttribute('aria-busy','true');const button=control.querySelector('[data-photo-toggle]');button.disabled=true;const status=document.createElement('p');status.className='photo-upload-status';status.setAttribute('role','status');status.textContent='Saving your photograph…';control.append(status);input.form.requestSubmit();
 });
})();
