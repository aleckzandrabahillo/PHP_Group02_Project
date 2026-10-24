(() => {
  const form=document.querySelector('[data-checkout-delivery]');if(!(form instanceof HTMLFormElement))return;
  const entry=document.querySelector('[data-discount-input]'),apply=document.querySelector('[data-discount-apply]'),remove=document.querySelector('[data-discount-remove]');
  const feedback=document.querySelector('[data-discount-feedback]'),shippingFeedback=form.querySelector('[data-shipping-feedback]'),button=document.querySelector('[data-place-order]');
  const totals=document.querySelector('[data-order-totals]'),money=new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP'});
  let controller,timer,generation=0,busy=false,hasShipping=false;
  const field=(name)=>form.elements.namedItem(name);
  const state=()=>{if(apply)apply.disabled=busy||!entry.value.trim();if(button)button.disabled=busy||!hasShipping||form.dataset.checkoutReady!=='true';};
  const discount=(value)=>{
    field('discount_code').value=value?.code||'';field('discount_revision').value=value?.revision||'';
    document.querySelector('[data-applied-discount]').hidden=!value;document.querySelector('[data-discount-row]').hidden=!value;
    document.querySelector('[data-applied-code]').textContent=value?.code||'';document.querySelector('[data-discount-name]').textContent=value?.code||'';
    document.querySelector('[data-discount-details]').textContent=value?.benefit||'';
    document.querySelector('[data-discount-total]').textContent='−'+money.format((value?.amount_cents||0)/100);
  };
  const render=(data)=>{
    if(!Number.isSafeInteger(data.total_cents)||data.total_cents<0)throw new Error('Your total could not be updated.');
    const waiver=document.querySelector('[data-shipping-waiver]');waiver.hidden=!data.shipping?.free_delivery;document.querySelector('[data-shipping-waiver-total]').textContent='−'+money.format((data.shipping?.calculated_cents||0)/100);
    hasShipping=!!data.shipping;document.querySelector('[data-shipping-total]').textContent=data.shipping?money.format((data.shipping.calculated_cents??data.shipping.cents)/100):'Enter delivery address';
    document.querySelectorAll('[data-grand-total],[data-inline-grand]').forEach(node=>node.textContent=money.format(data.total_cents/100));
    document.querySelectorAll('[data-shipping-price]').forEach(node=>{const value=data.options?.[node.dataset.shippingPrice];node.textContent=value?money.format((value.calculated_cents??value.cents)/100)+(value.free_delivery?' · Free':''):'—';});
    const benefit=document.querySelector('[data-free-delivery]');if(benefit)benefit.hidden=!data.shipping?.free_delivery;
    discount(data.discount);
  };
  const update=async(candidate=field('discount_code').value,applying=false)=>{
    clearTimeout(timer);controller?.abort();const version=++generation;controller=new AbortController();busy=true;state();totals?.setAttribute('aria-busy','true');if(applying)apply.textContent='Processing…';
    shippingFeedback.textContent='';feedback.textContent='';
    try{
      const values={_token:field('_token').value,discount_code:candidate};for(const name of ['postal_code','city','region','shipping_method'])values[name]=field(name)?.value||'';
      const response=await fetch(form.dataset.pricingUrl,{method:'POST',body:new URLSearchParams(values),credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal});
      if(response.redirected)throw new Error('Please sign in again before continuing.');
      const data=await response.json();if(version!==generation)return;
      if(typeof data.total_cents==='number')render(data);
      if(!response.ok||!data.ok){if(data.field==='discount'){discount(null);feedback.textContent=data.error||"This discount code isn't valid.";}else{shippingFeedback.textContent=data.error||'Check your delivery address.';hasShipping=false;}return;}
      if(applying&&data.discount){entry.value=data.discount.code;feedback.textContent='Discount applied.';}
    }catch(error){if(version!==generation||error.name==='AbortError')return;hasShipping=false;document.querySelectorAll('[data-grand-total],[data-inline-grand]').forEach(node=>node.textContent='Update required');if(applying){discount(null);feedback.textContent='The discount could not be applied. Please try again.';}else shippingFeedback.textContent=error.message;}
    finally{if(version===generation){busy=false;apply.textContent='Apply';totals?.removeAttribute('aria-busy');state();}}
  };
  entry?.addEventListener('input',state);entry?.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();if(entry.value.trim())update(entry.value.trim(),true);}});
  apply?.addEventListener('click',()=>{if(!busy)update(entry.value.trim(),true);});remove?.addEventListener('click',()=>{entry.value='';discount(null);update('');});
  for(const name of ['postal_code','city','region','shipping_method'])for(const node of form.querySelectorAll('[name="'+name+'"]'))node.addEventListener(name==='region'||name==='shipping_method'?'change':'input',()=>{clearTimeout(timer);controller?.abort();generation++;hasShipping=false;busy=true;state();timer=setTimeout(()=>update(),250);});
  form.addEventListener('submit',event=>{if(busy||!hasShipping){event.preventDefault();shippingFeedback.textContent='Wait for your delivery total before placing the order.';return;}if(button){button.disabled=true;button.textContent='Placing your order…';}});
  window.addEventListener('pageshow',()=>{if(button)button.textContent='Place order';update();});state();
})();
