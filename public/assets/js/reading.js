/* Native disclosures and reading navigation: no account data or browser storage. */
(() => {
 'use strict';
 const reduced=matchMedia('(prefers-reduced-motion: reduce)'),jobs=new Map();
 document.querySelectorAll('[data-animate-disclosures] details').forEach(detail=>{
  const summary=detail.querySelector('summary');if(!summary)return;
  summary.addEventListener('click',event=>{
   if(event.target.closest('a,button,input')||event.button>0)return;
   const pending=jobs.get(detail),expand=!(pending?pending.expand:detail.open),start=detail.getBoundingClientRect().height;
   pending?.animation.cancel();jobs.delete(detail);
   if(reduced.matches||!detail.animate){detail.style.overflow='';detail.open=!expand;return;}
   event.preventDefault();detail.open=true;detail.style.overflow='hidden';
   const finish=()=>{if(jobs.get(detail)?.animation!==animation)return;detail.open=expand;detail.style.overflow='';jobs.delete(detail);};
   const animation=detail.animate([{height:start+'px'},{height:(expand?detail.scrollHeight:summary.getBoundingClientRect().height)+'px'}],{duration:200,easing:'cubic-bezier(.22,.7,.25,1)'});
   jobs.set(detail,{animation,expand});animation.onfinish=finish;
  });
 });
 reduced.addEventListener('change',()=>{if(!reduced.matches)return;jobs.forEach(({animation,expand},detail)=>{animation.cancel();detail.open=expand;detail.style.overflow='';});jobs.clear();});
 const page=document.querySelector('[data-reading-page]');if(!page)return;
 const links=[...page.querySelectorAll('.help-navigation a[href^="#"]')];
 const sections=links.map(link=>page.querySelector(link.hash)).filter(Boolean);let queued=false;
 const update=()=>{
  queued=false;const top=(parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--account-sticky-top'))||110)+20;
  let current=sections[0];for(const section of sections)if(section.getBoundingClientRect().top<=top)current=section;
  if(scrollY+innerHeight>=document.documentElement.scrollHeight-3)current=sections.at(-1);
  links.forEach(link=>{if(link.hash==='#'+current?.id)link.setAttribute('aria-current','location');else link.removeAttribute('aria-current');});
 };
 const queue=()=>{if(!queued){queued=true;requestAnimationFrame(update);}};
 links.forEach(link=>link.addEventListener('click',event=>{
  if(event.defaultPrevented||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey||event.button>0)return;
  const section=page.querySelector(link.hash);if(!section)return;
  event.preventDefault();history.pushState(history.state,'',link.hash);section.scrollIntoView({block:'start',behavior:reduced.matches?'instant':'smooth'});
  section.tabIndex=-1;section.focus({preventScroll:true});
 }));
 addEventListener('scroll',queue,{passive:true});addEventListener('resize',queue);addEventListener('hashchange',queue);addEventListener('popstate',queue);
 new ResizeObserver(queue).observe(page);document.fonts?.ready.then(queue);update();
})();
