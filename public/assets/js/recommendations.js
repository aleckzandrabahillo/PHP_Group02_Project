(() => {
 'use strict';
 const root=document.querySelector('[data-recommendations]');if(!root)return;
 const reduced=matchMedia('(prefers-reduced-motion: reduce)');
 const reveal=panel=>{if(!reduced.matches&&panel.animate)panel.animate([{opacity:.55},{opacity:1}],{duration:180,easing:'ease-out'});};
 function tabs(navSelector,tabSelector,panelSelector,key){
  const nav=root.querySelector(navSelector),buttons=[...root.querySelectorAll(tabSelector)],panels=[...root.querySelectorAll(panelSelector)];
  if(!nav||buttons.length<2||panels.length!==buttons.length)return null;
  nav.setAttribute('role','tablist');
  panels.forEach(panel=>{panel.setAttribute('role','tabpanel');panel.tabIndex=0;});
  const select=(value,updateHash=false)=>{
   const button=buttons.find(node=>node.dataset[key+'Tab']===value);if(!button)return;
   buttons.forEach(node=>{const active=node===button;node.setAttribute('role','tab');node.setAttribute('aria-selected',String(active));node.setAttribute('aria-controls',node.hash.slice(1));node.tabIndex=active?0:-1;});
   panels.forEach(panel=>{const active=panel.dataset[key+'Panel']===value;panel.hidden=!active;if(active)reveal(panel);});
   if(updateHash){history.replaceState(history.state,'',button.hash);if(nav.getBoundingClientRect().top<0)nav.scrollIntoView({block:'start',behavior:'auto'});}
  };
  buttons.forEach((button,index)=>{
   button.addEventListener('click',event=>{if(event.ctrlKey||event.metaKey||event.shiftKey||event.altKey||event.button>0)return;event.preventDefault();select(button.dataset[key+'Tab'],true);});
   button.addEventListener('keydown',event=>{const next={ArrowRight:(index+1)%buttons.length,ArrowLeft:(index+buttons.length-1)%buttons.length,Home:0,End:buttons.length-1,' ':index}[event.key];if(next===undefined)return;event.preventDefault();select(buttons[next].dataset[key+'Tab'],true);buttons[next].focus({preventScroll:true});});
  });
  return select;
 }
 const mode=tabs('[data-mode-tabs]','[data-mode-tab]','[data-mode-panel]','mode');
 mode?.('wash');
 const fromHash=()=>{if(location.hash==='#suggested-care-between'||location.hash==='#suggested-care-wash')mode?.(location.hash==='#suggested-care-between'?'between':'wash');};
 fromHash();addEventListener('hashchange',fromHash);
})();
