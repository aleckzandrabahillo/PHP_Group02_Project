(() => {
  let stack=document.querySelector('.notification-stack');
  const ensure=()=>{if(!stack?.isConnected){stack=document.querySelector('.notification-stack');}if(!stack){stack=document.createElement('div');stack.className='notification-stack';document.body.append(stack);}const dialog=document.querySelector('dialog[open]');if(dialog)dialog.append(stack);else document.body.append(stack);return stack;};
  const records=new WeakMap();
  const manage=node=>{
    if(records.has(node)){records.get(node)();return;}
    let timer=null,removed=false;
    const dismiss=()=>{if(removed)return;removed=true;clearTimeout(timer);node.classList.add('notification-leaving');setTimeout(()=>node.remove(),window.AvelaMotion?.duration('exit')??180);};
    const schedule=()=>{clearTimeout(timer);if(node.hasAttribute('data-flash-transient'))timer=setTimeout(dismiss,node.classList.contains('alert-warning')?9000:5500);};
    node.querySelector('[data-flash-dismiss]')?.addEventListener('click',dismiss);
    node.addEventListener('mouseenter',()=>clearTimeout(timer));node.addEventListener('mouseleave',schedule);node.addEventListener('focusin',()=>clearTimeout(timer));node.addEventListener('focusout',schedule);records.set(node,schedule);schedule();
  };
  const seen=new Set();document.querySelectorAll('[data-flash]').forEach(node=>{const key=node.querySelector('span')?.textContent.trim();if(seen.has(key)){node.remove();return;}seen.add(key);manage(node);});
  document.addEventListener('avela:content',event=>(event.detail?.root||document).querySelectorAll('[data-flash]').forEach(manage));
  window.AvelaToast=(message,kind='success')=>{
    if(typeof message!=='string'||!message.trim())return;kind=['success','error','warning','info'].includes(kind)?kind:'info';const root=ensure();let node=[...root.children].find(item=>item.querySelector('span')?.textContent===message);
    if(node){manage(node);return;}node=document.createElement('div');node.className='alert alert-'+kind;node.setAttribute('role',['error','warning'].includes(kind)?'alert':'status');if(kind!=='error')node.setAttribute('data-flash-transient','');
    const text=document.createElement('span');text.textContent=message;const close=document.createElement('button');close.type='button';close.className='flash-dismiss';close.setAttribute('aria-label','Dismiss notification');close.setAttribute('data-flash-dismiss','');close.textContent='×';node.append(text,close);root.append(node);manage(node);
    while(root.children.length>3){const transient=[...root.children].find(item=>item.hasAttribute('data-flash-transient'));if(!transient)break;transient.remove();}
  };
  const quickForms=new WeakSet();
  function quickAdds(root=document){root.querySelectorAll('form[data-quick-add]').forEach(form=>{if(quickForms.has(form))return;quickForms.add(form);form.addEventListener('submit',async event=>{
    event.preventDefault();const button=form.querySelector('button');if(button.disabled)return;const keepFocus=form.contains(document.activeElement);form.classList.add('is-processing');button.disabled=true;button.setAttribute('aria-busy','true');
    try{const response=await fetch(form.action,{method:'POST',body:new URLSearchParams(new FormData(form)),credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});
      if(response.redirected){location.assign('/login');return;}const data=await response.json();if(!response.ok||!data.ok)throw new Error(data.message||'Your bag could not be updated.');
      document.querySelectorAll('[data-cart-count]').forEach(node=>{node.textContent=String(data.itemCount);node.hidden=data.itemCount<1;});window.AvelaToast(data.message);
    }catch(error){window.AvelaToast(error.message||'Your bag could not be updated. Please try again.','warning');}finally{button.disabled=false;button.removeAttribute('aria-busy');if(keepFocus&&(document.activeElement===document.body||document.activeElement===button))button.focus({preventScroll:true});form.classList.remove('is-processing');}
  });});}
  quickAdds();document.addEventListener('avela:content',event=>quickAdds(event.detail?.root||document));
  // Dialogs, confirmations and expandable menus share motion.js.
  const cardInfo=document.querySelector('[data-card-information]');
  if(cardInfo){const refresh=()=>{const expanded=document.querySelector('[name=payment_method]:checked')?.value==='maya_sandbox';cardInfo.dataset.expanded=String(expanded);cardInfo.setAttribute('aria-hidden',String(!expanded));};document.querySelectorAll('[name=payment_method]').forEach(input=>input.addEventListener('change',refresh));refresh();}
})();
