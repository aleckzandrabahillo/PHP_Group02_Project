'use strict';
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),assert=require('node:assert/strict');
const root=process.argv[2]||path.resolve(__dirname,'..');let checks=0;
const check=(condition,message)=>{assert.ok(condition,message);checks++;};
class Element{
 constructor(){this.attrs={};this.dataset={};this.listeners={};this.children=[];this.value='';this.id='';this.hidden=false;this.inserted=[];this.textContent='';}
 setAttribute(k,v){this.attrs[k]=v;}getAttribute(k){return this.attrs[k]??null;}
 append(...items){this.children.push(...items);}closest(){return null;}insertAdjacentElement(position,node){this.inserted.push(node);}
 addEventListener(type,fn){(this.listeners[type]??=[]).push(fn);}fire(type){for(const fn of this.listeners[type]||[])fn();}
}
const source=fs.readFileSync(path.join(root,'public/assets/js/password-policy.js'),'utf8');
const start=source.indexOf('function setupPasswordConfirmation('),end=source.indexOf('\nfunction mountPasswordConfirmations',start);
check(start>=0&&end>start,'Confirmation feedback exists');
const setup=vm.runInNewContext('('+source.slice(start,end)+')',{document:{createElement:()=>new Element()}});
const password=new Element(),confirmation=new Element(),form=new Element();confirmation.id='confirm';confirmation.setAttribute('aria-describedby','existing-error');
form.querySelector=s=>s.includes('password_confirmation')?confirmation:password;
setup(form);const feedback=confirmation.inserted[0],message=feedback.children[1];
check(feedback.hidden,'Untouched confirmation is quiet');check(feedback.getAttribute('role')==='status'&&feedback.getAttribute('aria-live')==='polite','Accessible status');
check(confirmation.getAttribute('aria-describedby')==='existing-error confirm-match','Existing error description preserved');
confirmation.value='Fixture-A';confirmation.fire('input');check(!feedback.hidden&&feedback.dataset.matched==='false','Confirmation before password shows mismatch');
password.value='Fixture-A';password.fire('input');check(feedback.dataset.matched==='true'&&message.textContent==='Passwords match.','Primary typing immediately updates match');
password.value='Fixture-B';password.fire('input');check(feedback.dataset.matched==='false','Editing original password invalidates a prior match');
confirmation.value='Fixture-B';confirmation.fire('change');check(feedback.dataset.matched==='true','Change/paste-like update refreshes status');
confirmation.value='Fixture-B ';confirmation.fire('input');check(feedback.dataset.matched==='false','Whitespace remains significant');
confirmation.value='';confirmation.fire('input');check(feedback.hidden&&message.textContent==='','Empty confirmation hides message');
setup(form);check(confirmation.inserted.length===1,'Repeated initialization does not duplicate feedback');
confirmation.value='PrivateSyntheticValue';confirmation.fire('input');check(!message.textContent.includes(confirmation.value),'Password never appears in status text');
form.fire('reset');check(feedback.hidden&&message.textContent==='','Reset clears feedback');
const incomplete=new Element();incomplete.querySelector=()=>null;setup(incomplete);check(incomplete.inserted.length===0,'Forms without a confirmation are unaffected');
const app=fs.readFileSync(path.join(root,'public/assets/js/app.js'),'utf8');
const uploadSource=app.slice(app.indexOf('function mountImageUploads('),app.indexOf('\nmountImageUploads();',app.indexOf('function mountImageUploads(')));
const photo=new Element();photo.matches=s=>s==='[data-photo-input]';const product=new Element();product.matches=()=>false;product.dataset.maxBytes='2621440';product.dataset.maxLabel='2.5 MB';product.setCustomValidity=v=>product.validity=v;
const mount=vm.runInNewContext('('+uploadSource+')',{document:{createElement:()=>new Element()}});mount({querySelectorAll:()=>[photo,product]});
check(photo.inserted.length===0&&Object.keys(photo.listeners).length===0,'Profile picker has no second inline upload handler');
check(product.inserted.length===1,'Catalog product validation remains');product.files=[{size:2621441}];product.fire('change');check(product.validity.includes('2.5 MB'),'Catalog oversized image remains invalid');
product.files=[{size:2621440}];product.fire('change');check(product.validity==='','Exact upload limit accepted by client');
console.log(JSON.stringify({suite:'password_confirmation_and_upload_feedback',pass:checks,fail:0}));
