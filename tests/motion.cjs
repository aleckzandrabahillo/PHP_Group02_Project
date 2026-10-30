'use strict';
// Process-independent interaction lifecycle tests; no browser, account or database access.
const assert = require('node:assert/strict');
const {createAnimator, timings} = require('../public/assets/js/motion.js');
const checks=[];
function check(name,fn){fn();checks.push({name,status:'PASS'});}
function element(){const animations=[];return {animations,animate(frames,options){let finish,reject;const animation={frames,options,cancelled:false,finished:new Promise((ok,no)=>{finish=ok;reject=no;}),finish(){finish();},cancel(){this.cancelled=true;reject(new Error('Cancelled'));}};animations.push(animation);return animation;}};}
(async()=>{
 check('Finite shared timing scale',()=>assert.deepEqual(timings,{fast:160,base:260,slow:360,dialog:320,page:440,exit:200}));
 const animation=createAnimator(kind=>timings[kind]??timings.base),first=element();
 const finished=animation.run(first,[{opacity:0},{opacity:1}],'dialog');
 check('Dialog uses shared duration',()=>assert.equal(first.animations[0].options.duration,320));
 check('Consistent easing',()=>assert.equal(first.animations[0].options.easing,'cubic-bezier(.2,.7,.2,1)'));
 first.animations[0].finish();check('Finishing resolves successfully',()=>{});assert.equal(await finished,true);
 check('Finished animation is cleaned up',()=>assert.equal(first.animations[0].cancelled,true));
 const quick=element(),old=animation.run(quick,[{height:'10px'},{height:'100px'}]);const current=animation.run(quick,[{height:'40px'},{height:'10px'}]);
 assert.equal(await old,false);check('Rapid reversal cancels prior job',()=>assert.equal(quick.animations[0].cancelled,true));
 check('Reversal preserves replacement job',()=>assert.equal(quick.animations[1].cancelled,false));
 quick.animations[0].finish();await Promise.resolve();check('Stale completion cannot finish new job',()=>assert.equal(quick.animations[1].cancelled,false));
 quick.animations[1].finish();assert.equal(await current,true);check('Replacement completes and cleans up',()=>assert.equal(quick.animations[1].cancelled,true));
 const a=element(),b=element(),pa=animation.run(a,[]),pb=animation.run(b,[]);animation.settleAll();assert.equal(await pa,true);assert.equal(await pb,true);
 check('All concurrent jobs can be released',()=>assert.ok(a.animations[0].cancelled&&b.animations[0].cancelled));
 check('Repeated cleanup is safe',()=>animation.settleAll());
 const noMotion=createAnimator(()=>0),zero=element();assert.equal(await noMotion.run(zero,[]),true);check('Zero duration is immediate without animation',()=>assert.equal(zero.animations.length,0));
 assert.equal(await animation.run({},[]),true);check('Unsupported browser gets usable immediate fallback',()=>{});
 assert.equal(await animation.run({animate(){throw new Error('Unavailable');}},[]),true);check('Animation errors cannot trap an interaction',()=>{});
 const bounded=createAnimator(()=>20),hung=element(),start=performance.now();assert.equal(await bounded.run(hung,[]),true);const elapsed=performance.now()-start;
 check('Missing completion has bounded cleanup',()=>assert.ok(elapsed>=100&&elapsed<700,'Elapsed '+elapsed));
 check('Fallback cancels suspended animation',()=>assert.equal(hung.animations[0].cancelled,true));
 // Reproduce defer-script registration order: motion registers before a later
 // AJAX submit handler. Its feedback must inspect the final prevented state.
 const vm=require('node:vm'),fs=require('node:fs'),path=require('node:path');
 const documentHandlers={},windowHandlers={},forms=[];
 class Form {constructor(){this.dataset={};this.method='post';this.isConnected=true;this.attrs={};forms.push(this);}matches(){return false;}setAttribute(k,v){this.attrs[k]=v;}removeAttribute(k){delete this.attrs[k];}querySelectorAll(){return this.button?[this.button]:[];}}
 const doc={readyState:'complete',documentElement:{dataset:{}},addEventListener(type,fn){(documentHandlers[type]??=[]).push(fn);},querySelector(){return null;},querySelectorAll(selector){return selector==='[data-motion-submitting]'?forms.filter(form=>form.dataset.motionSubmitting):[];}};
 vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../public/assets/js/motion.js'),'utf8'),{module:{exports:{}},window:{addEventListener(type,fn){windowHandlers[type]=fn;}},document:doc,HTMLFormElement:Form,MutationObserver:class{observe(){}},queueMicrotask,setTimeout,clearTimeout});
 function submit(form){const button={dataset:{},name:'action',value:'save',disabled:false,attrs:{},setAttribute(k,v){this.attrs[k]=v;},removeAttribute(k){delete this.attrs[k];}};form.button=button;const event={target:form,submitter:button,defaultPrevented:false,preventDefault(){this.defaultPrevented=true;}};documentHandlers.submit.forEach(fn=>fn(event));return event;}
 const ajax=new Form(),ajaxEvent=submit(ajax);await Promise.resolve();ajaxEvent.preventDefault();await new Promise(resolve=>setTimeout(resolve,0));
 check('Later AJAX preventDefault suppresses native loading state',()=>assert.equal(ajax.dataset.motionSubmitting,undefined));
 check('AJAX submit button never remains busy',()=>assert.equal(ajax.button.attrs['aria-busy'],undefined));
 const native=new Form();submit(native);await new Promise(resolve=>setTimeout(resolve,0));
 check('Real native POST gets pending feedback',()=>assert.equal(native.attrs['aria-busy'],'true'));
 check('Pending feedback preserves named submitter and validation data',()=>assert.ok(native.button.name==='action'&&native.button.value==='save'&&!native.button.disabled));
 const duplicate=submit(native);check('Repeated native POST is rejected while pending',()=>assert.equal(duplicate.defaultPrevented,true));
 windowHandlers.pageshow();check('History restoration clears pending feedback',()=>assert.equal(native.attrs['aria-busy'],undefined));
 check('Full motion is automatic without any settings UI',()=>assert.equal(doc.documentElement.dataset.avelaMotion,'full'));
 let preferenceChanged;const preference={matches:true,addEventListener(type,fn){if(type==='change')preferenceChanged=fn;}};
 const reducedDocument={...doc,documentElement:{dataset:{}}},reducedWindow={matchMedia(){return preference;},addEventListener(){}};
 vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../public/assets/js/motion.js'),'utf8'),{module:{exports:{}},window:reducedWindow,document:reducedDocument,HTMLFormElement:Form,MutationObserver:class{observe(){}},queueMicrotask,setTimeout,clearTimeout});
 check('Approved motion remains full on other layouts with a reduced-motion device preference',()=>assert.equal(reducedDocument.documentElement.dataset.avelaMotion,'full'));
 check('Required effects retain their finite duration on every layout',()=>assert.equal(reducedWindow.AvelaMotion.duration('dialog'),320));
 check('Shared effects do not silently switch to immediate mode',()=>assert.equal(reducedWindow.AvelaMotion.reduced(),false));
 preference.matches=false;preferenceChanged();
 check('Device preference changes keep the approved timing',()=>assert.equal(reducedWindow.AvelaMotion.duration('dialog'),320));
 check('Device preference changes keep the approved document policy',()=>assert.equal(reducedDocument.documentElement.dataset.avelaMotion,'full'));
 const customerDocument={...doc,body:{classList:{contains:name=>name==='avela-v3'},clientWidth:1425},documentElement:{dataset:{},style:{setProperty(){}}}},customerWindow={matchMedia(){return {matches:true,addEventListener(){}};},addEventListener(){}};
 vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../public/assets/js/motion.js'),'utf8'),{module:{exports:{}},window:customerWindow,document:customerDocument,HTMLFormElement:Form,MutationObserver:class{observe(){}},queueMicrotask,setTimeout,clearTimeout});
 check('Approved customer motion remains enabled with a reduced-motion device preference',()=>assert.equal(customerWindow.AvelaMotion.duration('dialog'),320));
 check('Customer motion policy is explicit in the rendered document',()=>assert.equal(customerDocument.documentElement.dataset.avelaMotion,'full'));
 check('Customer effects do not silently take the immediate branch',()=>assert.equal(customerWindow.AvelaMotion.reduced(),false));
 console.log(JSON.stringify({pass:checks.length,fail:0,fallback_ms:Math.round(elapsed),checks},null,2));
})().catch(error=>{console.error(error);process.exitCode=1;});
