// No browser accounts, payment requests or dependencies: exercise the real progressive enhancement.
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),path=require('node:path');
const code=fs.readFileSync(path.join(__dirname,'../public/assets/js/order-payment.js'),'utf8');
let pass=0;const check=(name,ok)=>{assert.ok(ok,name);pass++;};
async function fixture({hidden=false,error=false,auto=true}={}){
 const handlers={},button={disabled:false,textContent:'Check payment status'},feedback={textContent:''},label={textContent:'Awaiting payment confirmation'};
 const form={dataset:{autoCheck:auto?'true':'false'},action:'/payments/maya/refresh',isConnected:true,querySelector:()=>button,
  closest:()=>({querySelector:selector=>selector==='[data-payment-label]'?label:feedback}),addEventListener:(event,fn)=>handlers[event]=fn};
 let requests=[],timers=[],reloads=0;
 const doc={hidden,querySelectorAll:()=>[form],addEventListener:(event,fn)=>handlers[event]=fn};
 const ctx={document:doc,window:{location:{reload:()=>reloads++},addEventListener:()=>{}},FormData:class{constructor(f){this.form=f;}},
  fetch:async(url,options)=>{requests.push({url,options});if(error)throw Error('Synthetic connection failure');return {ok:true,headers:{get:()=> 'application/json'},json:async()=>({ok:true,label:'Awaiting payment confirmation',state:'pending',retry_after:30,message:'Payment status checked.'})};},
  setTimeout:(fn,delay)=>{timers.push({fn,delay});return timers.length;},clearTimeout:()=>{}};
 vm.runInNewContext(code,ctx);await new Promise(resolve=>setImmediate(resolve));
 return {handlers,button,feedback,label,requests,timers,ctx,form,get reloads(){return reloads;}};
}
(async()=>{
 const f=await fixture();check('Visible pending order checks automatically',f.requests.length===1);
 check('Verification uses same-origin POST, not GET',f.requests[0].options.method==='POST'&&f.requests[0].options.credentials==='same-origin');
 check('CSRF-bearing form fields are preserved',f.requests[0].options.body.form===f.form);
 check('Verification requests JSON',f.requests[0].options.headers.Accept==='application/json');
 check('Button restores after a check',!f.button.disabled&&f.button.textContent==='Check payment status');
 check('Next check waits at least 30 seconds',f.timers[0].delay>=30000);
 await f.timers[0].fn();await new Promise(resolve=>setImmediate(resolve));await f.timers[1].fn();await new Promise(resolve=>setImmediate(resolve));
 check('Automatic checks stop after three attempts',f.requests.length===3&&f.timers.length===2);
 f.handlers['avela:content']({detail:{root:f.ctx.document}});check('Remounting does not duplicate requests',f.requests.length===3);
 const h=await fixture({hidden:true});check('Hidden page never starts background requests',h.requests.length===0);
 const e=await fixture({error:true});check('Network failure is visible and retry remains possible',e.feedback.textContent.includes('could not check')&&!e.button.disabled);
 const n=await fixture({auto:false});check('Nonautomatic form waits for explicit submission',n.requests.length===0);
 console.log(JSON.stringify({pass,fail:0,network_requests:0}));
})().catch(e=>{console.error(e.message);process.exitCode=1;});
