// Adapted from the reviewed registration-checklist draft; server validation remains authoritative.
function passwordRequirementState(value, minimum, maximumBytes, counts = {}) {
  const characters = Array.from(value);
  const matches = (pattern) => characters.filter((c) => pattern.test(c)).length;
  return {
    length: characters.length >= minimum,
    uppercase: matches(/[A-Z]/) >= (counts.uppercase || 1),
    lowercase: matches(/[a-z]/) >= (counts.lowercase || 1),
    number: matches(/[0-9]/) >= (counts.numbers || 1),
    special: matches(/[^A-Za-z0-9\x09-\x0d\x20]/u) >= (counts.special || 1),
    bytes: new TextEncoder().encode(value).length <= maximumBytes && !value.includes('\0'),
  };
}
document.querySelectorAll('[data-password-requirements]').forEach(setupPasswordInput);
function setupPasswordInput(input){
  if(input.dataset.avelaPasswordReady)return;input.dataset.avelaPasswordReady='true';
  const checklist = document.getElementById(input.id + '-help');
  if (!checklist) return;
  checklist.hidden = true;
  const update = () => {
    checklist.hidden = false;
    const counts = Object.fromEntries(['uppercase','lowercase','numbers','special'].map((key) => [key, Number(input.dataset['password' + key[0].toUpperCase() + key.slice(1)])]));
    const state = passwordRequirementState(input.value, Number(input.dataset.passwordMinimum), Number(input.dataset.passwordMaximumBytes), counts);
    Object.entries(state).forEach(([rule, met]) => {
      const item = checklist.querySelector('[data-password-rule="' + rule + '"]');
      item?.classList.toggle('is-met', met);
      if (item) item.dataset.satisfied = String(met);
    });
    const sizeError = checklist.querySelector('[data-password-size-error]');
    if (sizeError) sizeError.hidden = state.bytes;
    const visibleRules = ['length', 'uppercase', 'lowercase', 'number', 'special'];
    checklist.querySelector('[data-password-progress]').textContent = visibleRules.filter((rule) => state[rule]).length + ' of 5 password requirements satisfied.' + (state.bytes ? '' : ' Use a shorter password.');
  };
  input.addEventListener('focus', update);
  input.addEventListener('input', update);
  if (input.value) update();
}
document.addEventListener('avela:content',()=>document.querySelectorAll('[data-password-requirements]').forEach(setupPasswordInput));
document.addEventListener('click',event=>{
  const button=event.target.closest?.('[data-reveal-setup-key]');if(!button)return;
  const input=document.querySelector('#authenticator-setup-key');if(!input)return;
  const reveal=input.type==='password';input.type=reveal?'text':'password';
  button.textContent=reveal?'Hide setup key':'Show setup key';button.setAttribute('aria-pressed',String(reveal));
});

function setupPasswordConfirmation(form) {
  const confirmation=form.querySelector('input[name="password_confirmation"]');
  const password=form.querySelector('input[name="password"]');
  if(!confirmation||!password||confirmation.dataset.avelaConfirmationReady)return;
  confirmation.dataset.avelaConfirmationReady='true';
  const label=confirmation.labels?.[0];
  if(label&&!confirmation.getAttribute('aria-labelledby')&&!confirmation.getAttribute('aria-label'))confirmation.setAttribute('aria-label',label.textContent.trim());
  const feedback=document.createElement('small'),icon=document.createElement('span'),message=document.createElement('span');
  feedback.className='password-match';feedback.id=(confirmation.id||'password-confirmation')+'-match';feedback.hidden=true;
  feedback.setAttribute('role','status');feedback.setAttribute('aria-live','polite');feedback.setAttribute('aria-atomic','true');icon.setAttribute('aria-hidden','true');feedback.append(icon,message);
  (confirmation.closest('.password-wrap')||confirmation).insertAdjacentElement('afterend',feedback);
  confirmation.setAttribute('aria-describedby',[confirmation.getAttribute('aria-describedby'),feedback.id].filter(Boolean).join(' '));
  const update=()=>{
    const entered=confirmation.value.length>0,matched=entered&&password.value.length>0&&password.value===confirmation.value;
    feedback.hidden=!entered;feedback.dataset.matched=String(matched);icon.textContent=matched?'✓':'×';
    message.textContent=entered?(matched?'Passwords match.':'Passwords don’t match yet.'):'';
  };
  password.addEventListener('input',update);confirmation.addEventListener('input',update);confirmation.addEventListener('change',update);
  form.addEventListener('reset',()=>{feedback.hidden=true;message.textContent='';});update();
}
function mountPasswordConfirmations(){document.querySelectorAll('form').forEach(setupPasswordConfirmation);}
mountPasswordConfirmations();
document.addEventListener('avela:content',mountPasswordConfirmations);