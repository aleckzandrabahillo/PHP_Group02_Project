(() => {
 'use strict';
 const openers=new WeakMap(),overflow=new WeakMap();
 const open=dialog=>{if(!dialog||dialog.open)return;openers.set(dialog,document.activeElement);overflow.set(dialog,document.body.style.overflow);document.body.style.overflow='hidden';window.AvelaMotion?.openDialog(dialog)??dialog.showModal();if(dialog.id==='profile-edit-dialog')dialog.querySelector('input:not([type=hidden])')?.focus();};
 const close=dialog=>window.AvelaMotion?.closeDialog(dialog)??dialog.close();
 // Keep native selects as the form/validation source; enhance their presentation only.
 const selectMounts=new WeakSet();let selectSequence=0,closeSelect=null;
 function mountSelects(root){
  if(!HTMLElement.prototype.showPopover)return;
  root.querySelectorAll('select:not([multiple]):not([size])').forEach(select=>{
   if(selectMounts.has(select))return;selectMounts.add(select);
   const labels=[...select.labels];const names=labels.map(label=>{const copy=label.cloneNode(true);copy.querySelectorAll('select,input,button,small,.field-error').forEach(node=>node.remove());return copy.textContent.trim();});
   let wrapper=select.parentElement;if(!wrapper.classList.contains('avela-select')){wrapper=document.createElement('span');wrapper.className='avela-select';select.before(wrapper);wrapper.append(select);}wrapper.querySelector('.select-chevron')?.remove();
   const button=document.createElement('button'),text=document.createElement('span'),arrow=document.createElement('span'),menu=document.createElement('div');
   button.type='button';button.className='avela-select-trigger';button.setAttribute('role','combobox');button.setAttribute('aria-haspopup','listbox');button.setAttribute('aria-expanded','false');button.setAttribute('aria-autocomplete','none');
   text.className='select-value';arrow.className='select-chevron';arrow.setAttribute('aria-hidden','true');button.append(text,arrow);
   menu.id='avela-options-'+(++selectSequence);menu.className='avela-select-options';menu.setAttribute('role','listbox');menu.setAttribute('popover','manual');button.setAttribute('aria-controls',menu.id);wrapper.append(button,menu);
   const name=select.getAttribute('aria-label')||names.join(' ')||select.name||'Choose an option';button.setAttribute('aria-label',name);menu.setAttribute('aria-label',name);
   select.classList.add('avela-native-select');select.tabIndex=-1;select.setAttribute('aria-hidden','true');
   let opened=false,active=-1,options=[],typed='',typeTimer;const lifecycle=new AbortController();
   const close=()=>{if(!opened)return;opened=false;typed='';clearTimeout(typeTimer);menu.hidePopover();button.setAttribute('aria-expanded','false');button.removeAttribute('aria-activedescendant');if(closeSelect===close)closeSelect=null;};
   const paint=()=>{text.textContent=select.selectedOptions[0]?.textContent||'Choose an option';button.disabled=select.disabled;button.setAttribute('aria-required',String(select.required));button.setAttribute('aria-invalid',select.getAttribute('aria-invalid')||'false');const described=select.getAttribute('aria-describedby');if(described)button.setAttribute('aria-describedby',described);else button.removeAttribute('aria-describedby');menu.querySelectorAll('[role=option]').forEach(node=>node.setAttribute('aria-selected',String(Number(node.dataset.index)===select.selectedIndex)));};
   const highlight=index=>{if(!options[index]||options[index].disabled||options[index].parentElement.disabled)return;active=index;menu.querySelectorAll('[role=option]').forEach(node=>node.classList.toggle('is-active',Number(node.dataset.index)===active));const node=menu.querySelector('[data-index="'+active+'"]');if(node){button.setAttribute('aria-activedescendant',node.id);node.scrollIntoView({block:'nearest'});}};
   const position=()=>{if(!opened)return;const r=button.getBoundingClientRect(),gap=6,below=innerHeight-r.bottom-gap,above=r.top-gap,up=below<200&&above>below;menu.style.width=Math.min(r.width,innerWidth-24)+'px';menu.style.maxHeight=Math.max(80,Math.min(300,(up?above:below)-12))+'px';menu.style.left=Math.max(12,Math.min(r.left,innerWidth-r.width-12))+'px';menu.style.top=(up?Math.max(12,r.top-menu.getBoundingClientRect().height-gap):r.bottom+gap)+'px';};
   const choose=index=>{if(select.disabled||!options[index]||options[index].disabled||options[index].parentElement.disabled)return;const changed=select.selectedIndex!==index;select.selectedIndex=index;paint();close();button.focus({preventScroll:true});if(changed){select.dispatchEvent(new Event('input',{bubbles:true}));select.dispatchEvent(new Event('change',{bubbles:true}));}};
   const render=()=>{options=[...select.options];active=-1;menu.replaceChildren();let group=null;options.forEach((option,index)=>{if(option.hidden)return;const parent=option.parentElement;if(parent.tagName==='OPTGROUP'&&group!==parent){group=parent;const heading=document.createElement('div');heading.className='select-group-label';heading.textContent=parent.label;menu.append(heading);}const node=document.createElement('div');node.id=menu.id+'-'+index;node.dataset.index=String(index);node.setAttribute('role','option');node.setAttribute('aria-selected',String(index===select.selectedIndex));node.textContent=option.textContent;const disabled=option.disabled||parent.disabled;node.setAttribute('aria-disabled',String(!!disabled));node.addEventListener('pointerdown',event=>event.preventDefault());node.addEventListener('click',()=>choose(index));node.addEventListener('pointermove',()=>highlight(index));menu.append(node);});paint();};
   const open=()=>{if(select.disabled)return;closeSelect?.();render();opened=true;closeSelect=close;menu.showPopover();button.setAttribute('aria-expanded','true');position();highlight(select.selectedIndex);if(active<0)highlight(options.findIndex(option=>!option.disabled&&!option.hidden&&!option.parentElement.disabled));if(!window.AvelaMotion?.reduced())menu.animate([{opacity:0,transform:'translateY(-4px)'},{opacity:1,transform:'translateY(0)'}],{duration:160,easing:'ease-out'});};
   button.addEventListener('click',()=>opened?close():open());button.addEventListener('keydown',event=>{
    if((event.key==='Escape'||event.altKey&&event.key==='ArrowUp')&&opened){event.preventDefault();event.stopPropagation();close();return;}if(event.key==='Tab'){close();return;}
    if(event.key==='Enter'||event.key===' '&&!typed){event.preventDefault();if(opened)choose(active);else open();return;}
    if(['ArrowDown','ArrowUp','Home','End'].includes(event.key)){event.preventDefault();if(!opened)open();const direction=event.key==='ArrowUp'||event.key==='End'?-1:1;let index=event.key==='Home'?0:event.key==='End'?options.length-1:active+direction;while(index>=0&&index<options.length){if(!options[index].disabled&&!options[index].hidden&&!options[index].parentElement.disabled){highlight(index);break;}index+=direction;}return;}
    if(event.key.length===1&&!event.ctrlKey&&!event.metaKey&&!event.altKey){event.preventDefault();if(!opened)open();clearTimeout(typeTimer);typed+=event.key.toLocaleLowerCase();const index=options.findIndex(option=>!option.disabled&&!option.hidden&&!option.parentElement.disabled&&option.textContent.trim().toLocaleLowerCase().startsWith(typed));if(index>=0)highlight(index);typeTimer=setTimeout(()=>typed='',600);}
   });
   select.addEventListener('change',paint);select.addEventListener('focus',()=>button.focus());select.addEventListener('invalid',event=>{event.preventDefault();button.setAttribute('aria-invalid','true');button.focus();});
   select.form?.addEventListener('reset',()=>setTimeout(paint));const observer=new MutationObserver(()=>{render();if(select.disabled)close();});observer.observe(select,{attributes:true,childList:true,subtree:true,attributeFilter:['disabled','selected','label','hidden','required','aria-invalid','aria-describedby']});
   document.addEventListener('pointerdown',event=>{if(opened&&!wrapper.contains(event.target))close();},{signal:lifecycle.signal});document.addEventListener('focusin',event=>{if(opened&&!wrapper.contains(event.target))close();},{signal:lifecycle.signal});
   window.addEventListener('resize',position,{signal:lifecycle.signal});document.addEventListener('scroll',event=>{if(opened&&!menu.contains(event.target))position();},{capture:true,signal:lifecycle.signal});document.addEventListener('avela:beforecontent',()=>{close();queueMicrotask(()=>{if(!select.isConnected){lifecycle.abort();observer.disconnect();clearTimeout(typeTimer);}});},{signal:lifecycle.signal});
   labels.forEach(label=>label.addEventListener('click',event=>{if(event.target===label||event.target.parentElement===label&&event.target.tagName==='SPAN'&&event.target!==wrapper){event.preventDefault();button.focus();}}));render();
  });
 }

 const bound=new WeakSet();
 function mount(root=document){
  root.querySelectorAll('[data-dialog-open]').forEach(button=>{if(bound.has(button))return;bound.add(button);button.addEventListener('click',()=>open(document.getElementById(button.dataset.dialogOpen)));});
  root.querySelectorAll('dialog:has([data-ux-close])').forEach(dialog=>{if(bound.has(dialog))return;bound.add(dialog);dialog.querySelectorAll('[data-ux-close]').forEach(button=>button.addEventListener('click',()=>close(dialog)));dialog.addEventListener('cancel',event=>{event.preventDefault();close(dialog);});dialog.addEventListener('close',()=>{document.body.style.overflow=overflow.get(dialog)||'';const opener=openers.get(dialog),focus=opener?.getClientRects().length?opener:opener?.closest('.profile-photo-control')?.querySelector('[data-photo-toggle]');focus?.focus({preventScroll:true});});if(dialog.dataset.dialogAuto==='true')open(dialog);});
  root.querySelectorAll('.avela-help').forEach(help=>{
   if(bound.has(help))return;bound.add(help);
   const tip=help.querySelector('[role=tooltip]'),trigger=help.querySelector('.info-trigger');if(!tip||!trigger||!tip.showPopover)return;
   tip.setAttribute('popover','manual');let pinned=false;const lifecycle=new AbortController();
   const position=()=>{if(!tip.matches(':popover-open'))return;const r=trigger.getBoundingClientRect(),dialog=trigger.closest('dialog'),d=dialog?.getBoundingClientRect();const left=Math.max(12,d?.left+12||12),right=Math.min(innerWidth-12,d?.right-12||innerWidth-12),top=Math.max(12,d?.top+12||12),bottom=Math.min(innerHeight-12,d?.bottom-12||innerHeight-12);tip.style.width=Math.min(260,right-left)+'px';tip.style.maxHeight=Math.max(60,bottom-top)+'px';const t=tip.getBoundingClientRect(),below=r.bottom+8+t.height<=bottom;tip.style.left=Math.max(left,Math.min(r.right-t.width,right-t.width))+'px';tip.style.top=Math.max(top,below?r.bottom+8:r.top-8-t.height)+'px';};
   const hide=()=>{pinned=false;if(tip.matches(':popover-open'))tip.hidePopover();trigger.setAttribute('aria-expanded','false');};
   const show=()=>{if(!tip.matches(':popover-open')){tip.showPopover();window.AvelaMotion?.reveal(tip,'fast');}trigger.setAttribute('aria-expanded','true');position();};
   help.addEventListener('pointerenter',e=>{if(e.pointerType==='mouse')show();});help.addEventListener('pointerleave',()=>{if(!pinned&&!help.contains(document.activeElement))hide();});
   help.addEventListener('focusin',show);help.addEventListener('focusout',e=>{if(!help.contains(e.relatedTarget))hide();});trigger.addEventListener('click',()=>{if(pinned)hide();else{pinned=true;show();}});
   help.addEventListener('keydown',e=>{if(e.key==='Escape'&&tip.matches(':popover-open')){hide();e.preventDefault();e.stopPropagation();}});
   document.addEventListener('pointerdown',e=>{if(!help.contains(e.target))hide();},{signal:lifecycle.signal});window.addEventListener('resize',position,{signal:lifecycle.signal});document.addEventListener('scroll',position,{capture:true,signal:lifecycle.signal});trigger.closest('dialog')?.addEventListener('close',hide);
   document.addEventListener('avela:beforecontent',()=>{hide();queueMicrotask(()=>{if(!help.isConnected)lifecycle.abort();});},{signal:lifecycle.signal});
  });
  mountSelects(root);
 }
 mount();document.addEventListener('avela:content',event=>mount(event.detail?.root||document));
 // Explicitly restore default delivery values; one-time edits never save a profile.
 const delivery=document.querySelector('[data-checkout-delivery]');
 if(delivery){
  const fill=values=>{const initial=new Map(Object.entries(values));for(const [key,value] of initial){if(!['country','first_name','last_name','address_line','barangay','postal_code','city','region','province','contact_no'].includes(key))continue;const control=delivery.elements.namedItem(key);if(control&&'value' in control)control.value=key==='region'?(initial.get('province')||(value==='NCR'?'Metro Manila':value)):value;}delivery.elements.region.dispatchEvent(new Event('change',{bubbles:true}));delivery.dispatchEvent(new Event('input',{bubbles:true}));window.AvelaToast?.('Your saved address is ready. You can still edit it for this order.');};
  const choices=delivery.querySelector('[data-saved-address]');choices?.addEventListener('change',()=>{const item=choices.selectedOptions[0];if(item?.dataset.delivery)fill(JSON.parse(item.dataset.delivery));});
 }
 // Bound the item region only; totals/actions remain outside its scrolling area.
 const list=document.querySelector('[data-summary-list]');
 if(list){
  const summary=list.closest('.checkout-summary'),content=summary.querySelector('.checkout-summary-inner');
  let scheduled=false;
  const fit=()=>{
   scheduled=false;
   const number=value=>parseFloat(value)||0,style=getComputedStyle(content),rows=[...list.children];
   const measured=rows.slice(0,5).reduce((sum,row)=>sum+row.getBoundingClientRect().height,0)+number(getComputedStyle(list).rowGap)*Math.max(0,Math.min(rows.length,5)-1)+number(getComputedStyle(list).paddingTop)+number(getComputedStyle(list).paddingBottom);
   const reserved=[...content.children].filter(node=>node!==list&&!node.hidden&&getComputedStyle(node).display!=='none').reduce((sum,node)=>{const css=getComputedStyle(node);return sum+node.getBoundingClientRect().height+number(css.marginTop)+number(css.marginBottom);},number(style.paddingTop)+number(style.paddingBottom)+number(style.borderTopWidth)+number(style.borderBottomWidth));
   const desktop=innerWidth>720,available=(window.visualViewport?.height||innerHeight)-number(style.top)-reserved,flow=desktop&&available<120;
   summary.classList.toggle('summary-flow',flow);
   const maxHeight=desktop&&!flow?Math.min(measured,Math.max(120,available)):rows.length>5?measured:null;
   list.style.maxHeight=maxHeight===null?'none':maxHeight+'px';
  };
  const schedule=()=>{if(!scheduled){scheduled=true;requestAnimationFrame(fit);}};
  new ResizeObserver(schedule).observe(content);
  window.addEventListener('resize',schedule);window.visualViewport?.addEventListener('resize',schedule);
  document.fonts.ready.then(schedule);fit();
 }

 // Policies remain normal, shareable reading pages; Help contains the full text.
})();
