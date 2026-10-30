(() => {
 'use strict';
 const mounted=new WeakSet();
 function mount(){document.querySelectorAll('[data-care-guide]').forEach(guide => {
  if(mounted.has(guide))return;mounted.add(guide);
  const buttons=[...guide.querySelectorAll('[data-care-tab]')],panels=[...guide.querySelectorAll('[data-care-phase]')];
  if(buttons.length!==2||panels.length!==2)return;
  const select=key=>{buttons.forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.careTab===key)));panels.forEach(panel=>{panel.hidden=panel.dataset.carePhase!==key;});};
  buttons.forEach(button=>button.addEventListener('click',()=>select(button.dataset.careTab)));
  guide.dataset.careEnhanced='true';select('wash');
  // Native details keep the full sequence usable without scripting.
  guide.querySelectorAll('.care-step details').forEach(detail=>detail.addEventListener('toggle',()=>{
   if(!detail.open)return;
   detail.closest('[data-care-phase]').querySelectorAll('.care-step details').forEach(other=>{if(other!==detail)other.open=false;});
  }));
 });}
 mount();document.addEventListener('avela:content',mount);
})();
