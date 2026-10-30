/* Progressive, in-memory assessment. No hair descriptions are written to browser storage. */
(() => {
 'use strict';
 const form=document.querySelector('[data-assessment-form]');if(!form)return;
 const steps=[...form.querySelectorAll('[data-quiz-step]')],controls=form.querySelector('[data-quiz-controls]'),progress=form.querySelector('[data-quiz-progress]');
 if(!steps.length||!controls)return;
 let current=0,dirty=false,submitted=false,returnToReview=false;
 const previous=controls.querySelector('[data-quiz-previous]'),next=controls.querySelector('[data-quiz-next]'),actions=form.querySelector('.assessment-actions');
 const bar=progress.querySelector('progress'),label=form.querySelector('[data-quiz-label]'),note=form.elements.concern_text;
 const selected=name=>[...form.querySelectorAll(`[name="${name}"],[name="${name}[]"]`)].filter(input=>input.checked).map(input=>input.closest('label').querySelector('.quiz-option-text strong').textContent.trim());
 function review(){
  const target=form.querySelector('[data-quiz-review]');target.replaceChildren();
  const concerns=selected('concerns'),treatments=selected('treatments');
  const rows=[['Texture',selected('hair_texture').join(', '),0],['Scalp',selected('scalp_type').join(', '),1],['Lengths & habits',[...selected('hair_condition'),concerns.join(', ')||'No particular concern',treatments.join(', ')||'No regular treatments'].join(' · '),2],['In your words',note.value.trim()||'No extra notes',3]];
  rows.forEach(([title,answer,index])=>{
   const row=document.createElement('div'),copy=document.createElement('div'),term=document.createElement('span'),value=document.createElement('strong'),edit=document.createElement('button');
   row.className='review-row';term.textContent=title;value.textContent=answer||'Not added';copy.append(term,value);
   edit.type='button';edit.textContent='Edit';edit.setAttribute('aria-label','Edit '+title.toLowerCase());
   edit.addEventListener('click',()=>{returnToReview=true;show(index);});row.append(copy,edit);target.append(row);
  });
 }
 function show(index,focus=true){
  current=index;
  form.querySelectorAll('[data-quiz-rail]').forEach((node,i)=>{
   node.classList.toggle('current',i===index);node.classList.toggle('done',i<index);
   node.querySelector('span').textContent=i<index?'✓':String(i+1).padStart(2,'0');
   if(i===index)node.setAttribute('aria-current','step');else node.removeAttribute('aria-current');
  });
  steps.forEach((step,i)=>{step.hidden=i!==index;});
  previous.hidden=index===0;next.hidden=index===steps.length-1;actions.hidden=index!==steps.length-1;
  next.textContent=returnToReview?'Back to review':'Continue';bar.value=index+1;bar.max=steps.length;label.textContent=`YOUR HAIR PROFILE · ${index+1} OF ${steps.length}`;
  if(index===steps.length-1)review();
  if(focus){const heading=steps[index].querySelector('h2');heading.tabIndex=-1;heading.focus({preventScroll:true});form.scrollIntoView({block:'start',behavior:(window.AvelaMotion?.reduced()??matchMedia('(prefers-reduced-motion: reduce)').matches)?'instant':'smooth'});window.AvelaMotion?.reveal(steps[index]);}
 }
 function valid(step){for(const input of step.querySelectorAll('input,textarea,select'))if(!input.checkValidity()){input.reportValidity();return false;}return true;}
 form.noValidate=true;controls.hidden=false;progress.hidden=false;form.dataset.quizEnhanced='true';
 form.querySelectorAll('[data-quiz-none-choice]').forEach(choice=>{choice.hidden=false;});
 form.addEventListener('change',event=>{
  const input=event.target;if(!(input instanceof HTMLInputElement))return;
  if(input.dataset.quizNone){if(input.checked)form.querySelectorAll(`[name="${input.dataset.quizNone}[]"]`).forEach(option=>{option.checked=false;});}
  else if(['concerns[]','treatments[]'].includes(input.name)){
   const name=input.name.slice(0,-2),none=form.querySelector(`[data-quiz-none="${name}"]`);
   none.checked=!form.querySelector(`[name="${input.name}"]:checked`);
  }
 });
 const updateCount=()=>{form.querySelector('[data-quiz-character-count]').textContent=Array.from(note.value).length;};note.addEventListener('input',updateCount);updateCount();
 next.addEventListener('click',()=>{if(valid(steps[current])){const destination=returnToReview?steps.length-1:current+1;returnToReview=false;show(destination);}});
 previous.addEventListener('click',()=>{returnToReview=false;show(current-1);});
 form.addEventListener('input',()=>{dirty=true;});
 form.addEventListener('submit',event=>{
  const bad=steps.findIndex(step=>[...step.querySelectorAll('input,textarea,select')].some(input=>!input.checkValidity()));
  if(bad!==-1){event.preventDefault();show(bad);valid(steps[bad]);return;}
  if(current!==steps.length-1){event.preventDefault();show(Math.min(current+1,steps.length-1));return;}
  submitted=true;
 });
 form.querySelectorAll('[data-error-summary] a').forEach(link=>link.addEventListener('click',()=>{const target=document.getElementById(link.hash.slice(1));const index=steps.findIndex(step=>step.contains(target));if(index>=0)show(index);}));
 window.addEventListener('beforeunload',event=>{if(dirty&&!submitted){event.preventDefault();event.returnValue='';}});
 const info=document.querySelector('#assessment-save-info'),infoLink=document.querySelector('[data-assessment-privacy]');
 if(info&&infoLink){
  const closeInfo=()=>{if(window.AvelaMotion)window.AvelaMotion.closeDialog(info);else info.close();};
  infoLink.addEventListener('click',event=>{
   if(event.ctrlKey||event.metaKey||event.shiftKey||event.altKey||event.button!==0||!info.showModal)return;
   event.preventDefault();if(info.open)return;
   if(window.AvelaMotion)window.AvelaMotion.openDialog(info);else info.showModal();
   info.querySelector('h2').focus({preventScroll:true});
  });
  info.querySelector('[data-assessment-privacy-close]').addEventListener('click',closeInfo);
  info.addEventListener('keydown',event=>{
   if(event.key!=='Tab')return;
   const targets=[...info.querySelectorAll('button,a[href]')].filter(node=>node.getClientRects().length&&!node.disabled),first=targets[0],last=targets[targets.length-1];
   if(event.shiftKey&&(document.activeElement===first||!targets.includes(document.activeElement))){event.preventDefault();last?.focus();}
   else if(!event.shiftKey&&(document.activeElement===last||!targets.includes(document.activeElement))){event.preventDefault();first?.focus();}
  });
  info.addEventListener('cancel',event=>{event.preventDefault();closeInfo();});
  info.addEventListener('close',()=>infoLink.focus({preventScroll:true}));
  info.addEventListener('click',event=>{if(event.target===info){const r=info.getBoundingClientRect();if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)closeInfo();}});
 }
 const error=steps.findIndex(step=>step.querySelector('[aria-invalid="true"]'));show(error<0?0:error,false);
})();
