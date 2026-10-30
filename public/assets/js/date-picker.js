(() => {
  'use strict';
  if (!HTMLElement.prototype.showPopover) return;
  let sequence = 0, closeActive;
  const mounted = new WeakSet();
  const pad = n => String(n).padStart(2, '0');
  const dateKey = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
  const parse = value => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(value); return m ? new Date(+m[1], +m[2]-1, +m[3], 12) : null; };
  function mount(root = document) {
  root.querySelectorAll('input[type=date],input[type=datetime-local]').forEach(input => {
    if (mounted.has(input)) return;
    mounted.add(input);
    const lifecycle = new AbortController();
    const isTime = input.type === 'datetime-local', wrapper = document.createElement('span');
    wrapper.className = 'date-control'; input.before(wrapper); wrapper.append(input);
    const trigger = document.createElement('button'), label = document.createElement('span');
    trigger.type = 'button'; trigger.className = 'date-trigger'; trigger.append(label);
    trigger.insertAdjacentHTML('beforeend','<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="6" width="16" height="15" rx="2"/><path d="M8 3v6m8-6v6M4 12h16"/></svg>');
    const name = input.getAttribute('aria-label') || [...input.labels].map(l => l.textContent.trim()).join(' ') || 'Date';
    trigger.setAttribute('aria-label',name); trigger.setAttribute('aria-haspopup','dialog'); trigger.setAttribute('aria-expanded','false');
    const popover = document.createElement('div'); popover.className = 'date-calendar'; popover.id = 'date-calendar-'+(++sequence); popover.setAttribute('popover','manual'); popover.setAttribute('role','dialog'); popover.setAttribute('aria-label',name); trigger.setAttribute('aria-controls',popover.id);
    wrapper.append(trigger,popover); input.classList.add('date-native'); input.tabIndex = -1; input.setAttribute('aria-hidden','true');
    let month, selected, opened = false, timeValue;
    const sync = () => { const d = parse(input.value); label.textContent = d ? d.toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'})+(isTime?' · '+input.value.slice(11):'') : (isTime?'Choose date and time':'Choose date'); trigger.disabled = input.disabled || input.readOnly; trigger.setAttribute('aria-required',String(input.required)); trigger.setAttribute('aria-invalid',input.getAttribute('aria-invalid')||'false'); };
    const position = () => { if(!opened)return; const r=trigger.getBoundingClientRect(),h=popover.getBoundingClientRect().height; popover.style.left=Math.max(12,Math.min(r.left,innerWidth-popover.offsetWidth-12))+'px';popover.style.top=Math.max(12, r.bottom+h+8<innerHeight?r.bottom+6:r.top-h-6)+'px'; };
    const close = (focus=true) => { if(!opened)return;opened=false;popover.hidePopover();trigger.setAttribute('aria-expanded','false');if(focus)trigger.focus({preventScroll:true});if(closeActive===close)closeActive=null; };
    const commit = value => { const previous=input.value; input.value=value; if(!input.validity.valid && value){ input.value=previous; const error=popover.querySelector('.date-calendar-error');error.textContent='Choose a date and time within the allowed range.';return; } sync();if(previous!==input.value){input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));}close(); };
    const selectable = key => (!input.min || key>=input.min.slice(0,10)) && (!input.max || key<=input.max.slice(0,10));
    const render = focusKey => {
      popover.replaceChildren(); const head=document.createElement('div');head.className='date-calendar-head';
      const title=document.createElement('strong');title.id=popover.id+'-month';title.textContent=month.toLocaleDateString('en-PH',{month:'long',year:'numeric'});title.setAttribute('aria-live','polite');
      for(const [text,delta] of [['‹',-1],['›',1]]){const b=document.createElement('button');b.type='button';b.textContent=text;b.setAttribute('aria-label',delta<0?'Previous month':'Next month');b.addEventListener('click',()=>{month=new Date(month.getFullYear(),month.getMonth()+delta,1,12);render();});if(delta<0)head.append(b,title);else head.append(b);}popover.append(head);
      const grid=document.createElement('div');grid.className='date-calendar-grid';grid.setAttribute('role','grid');grid.setAttribute('aria-labelledby',title.id);
      ['Su','Mo','Tu','We','Th','Fr','Sa'].forEach(day=>{const n=document.createElement('span');n.textContent=day;n.setAttribute('role','columnheader');grid.append(n);});
      for(let i=0;i<month.getDay();i++){const n=document.createElement('span');n.setAttribute('aria-hidden','true');grid.append(n);}
      const today=dateKey(new Date()), days=new Date(month.getFullYear(),month.getMonth()+1,0).getDate();
      for(let day=1;day<=days;day++){
        const d=new Date(month.getFullYear(),month.getMonth(),day,12), key=dateKey(d), b=document.createElement('button');b.type='button';b.textContent=day;b.dataset.date=key;b.setAttribute('role','gridcell');b.setAttribute('aria-label',d.toLocaleDateString('en-PH',{weekday:'long',year:'numeric',month:'long',day:'numeric'}));b.setAttribute('aria-selected',String(selected===key));b.disabled=!selectable(key);b.tabIndex=-1;if(key===today)b.setAttribute('aria-current','date');
        b.addEventListener('click',()=>{selected=key;if(isTime)render(key);else commit(key);});
        b.addEventListener('keydown',event=>{let target=new Date(d);const move={ArrowLeft:-1,ArrowRight:1,ArrowUp:-7,ArrowDown:7,Home:-d.getDay(),End:6-d.getDay()};if(event.key in move)target.setDate(target.getDate()+move[event.key]);else if(event.key==='PageUp'||event.key==='PageDown'){target.setDate(1);target.setMonth(target.getMonth()+(event.key==='PageUp'?-1:1)*(event.shiftKey?12:1));}else return;event.preventDefault();month=new Date(target.getFullYear(),target.getMonth(),1,12);render(dateKey(target));});grid.append(b);
      }
      popover.append(grid);
      if(isTime){const row=document.createElement('label');row.className='date-calendar-time';row.textContent='Time';const time=document.createElement('input');time.type='time';time.value=timeValue;time.step=input.step||'60';time.setAttribute('aria-label','Time');time.addEventListener('input',()=>timeValue=time.value);row.append(time);popover.append(row);}
      const error=document.createElement('p');error.className='date-calendar-error';error.setAttribute('role','alert');popover.append(error);
      const footer=document.createElement('div');footer.className='date-calendar-footer';
      const clear=document.createElement('button');clear.type='button';clear.textContent=input.required?'Cancel':'Clear';clear.addEventListener('click',()=>input.required?close():commit(''));footer.append(clear);
      const done=document.createElement('button');done.type='button';done.textContent=isTime?'Apply':'Today';done.addEventListener('click',()=>{const key=isTime?(selected||dateKey(new Date())):dateKey(new Date());if(!selectable(key)){error.textContent='Choose a date within the allowed range.';return;}commit(key+(isTime?'T'+(timeValue||'00:00'):''));});footer.append(done);popover.append(footer);
      const chosen=grid.querySelector('[data-date="'+(focusKey||selected||today)+'"]:not(:disabled)')||grid.querySelector('button:not(:disabled)');if(chosen){chosen.tabIndex=0;if(focusKey)chosen.focus({preventScroll:true});}position();
    };
    trigger.addEventListener('click',()=>{if(opened){close();return;}closeActive?.(false);selected=input.value.slice(0,10);timeValue=input.value.slice(11)||'09:00';const d=parse(input.value)||parse(input.min)||new Date();month=new Date(d.getFullYear(),d.getMonth(),1,12);opened=true;closeActive=close;render();popover.showPopover();trigger.setAttribute('aria-expanded','true');position();popover.querySelector('[tabindex="0"]')?.focus({preventScroll:true});});
    popover.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();event.stopPropagation();close();}});
    document.addEventListener('pointerdown',event=>{if(opened&&!wrapper.contains(event.target))close(false);},{signal:lifecycle.signal});document.addEventListener('focusin',event=>{if(opened&&!wrapper.contains(event.target))close(false);},{signal:lifecycle.signal});addEventListener('resize',position,{signal:lifecycle.signal});document.addEventListener('scroll',event=>{if(opened&&!popover.contains(event.target))position();},{capture:true,signal:lifecycle.signal});
    input.addEventListener('change',sync);input.addEventListener('focus',()=>trigger.focus());input.addEventListener('invalid',event=>{event.preventDefault();trigger.setAttribute('aria-invalid','true');trigger.focus();});input.form?.addEventListener('reset',()=>setTimeout(sync));const observer=new MutationObserver(sync);observer.observe(input,{attributes:true,attributeFilter:['disabled','readonly','required','aria-invalid','min','max','step']});
    document.addEventListener('avela:beforecontent',()=>{close(false);queueMicrotask(()=>{if(!input.isConnected){lifecycle.abort();observer.disconnect();}});},{signal:lifecycle.signal});sync();
  });
  }
  mount();
  document.addEventListener('avela:content',event=>mount(event.detail?.root||document));
})();
