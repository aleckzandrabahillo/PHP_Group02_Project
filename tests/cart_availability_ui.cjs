'use strict';
// Simulated responses test the real quantity handler's stock-race behavior.
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),assert=require('node:assert/strict');let pass=0;
const check=(name,ok)=>{assert.ok(ok,name);pass++;};
class Element {
 constructor(){this.dataset={};this.hidden=false;this.textContent='';this.attrs={};this.classList={add(){},remove(){},toggle(){}};}
 setAttribute(k,v){this.attrs[k]=v;}removeAttribute(k){delete this.attrs[k];}
}
class Input extends Element{} class Form extends Element{} class Anchor extends Element{}
const source=fs.readFileSync(path.join(__dirname,'../public/assets/js/app.js'),'utf8');
const start=source.indexOf('const cartUpdateStates ='),end=source.indexOf('\nfunction activateProductTab',start);
async function fixture(stock,canAdjust,message){
 const input=new Input();input.value='3';input.min='1';input.max='20';input.dataset.confirmedQuantity='2';
 const feedback=new Element(),warning=new Element(),line=new Element(),notice=new Element(),badge=new Element(),count=new Element(),subtotal=new Element(),checkout=new Anchor(),row=new Element(),form=new Form(),buttons=[new Element(),new Element()];
 checkout.dataset.checkoutUrl='/checkout';row.dataset.productId='7';
 row.querySelector=s=>({'[data-cart-stock-warning]':warning,'input[name="quantity"]':input,'[data-cart-line-total]':line}[s]);row.querySelectorAll=()=>buttons;
 form.querySelector=s=>s.includes('quantity')?input:feedback;form.closest=()=>row;form.action='/cart/update';
 const document={querySelectorAll:s=>({'[data-cart-item]':[row],'[data-cart-availability]':[notice],'[data-cart-count]':[badge]}[s]??[]),querySelector:s=>({'[data-cart-summary-count]':count,'[data-cart-summary-subtotal]':subtotal,'[data-cart-checkout]':checkout}[s])??null};
 let requests=0,timers=0;
 const api=vm.runInNewContext(source.slice(start,end)+';({sendCartQuantityUpdate})',{
  document,HTMLInputElement:Input,HTMLFormElement:Form,HTMLAnchorElement:Anchor,HTMLElement:Element,FormData:class{},
  fetch:async()=>{requests++;return {ok:false,json:async()=>({ok:false,message:'Stock changed.',item:{quantity:2,lineTotal:100},itemCount:2,subtotal:100,checkoutReady:false,availability:[{productId:7,stock,canAdjust,message}]})};},
  window:{setTimeout(){timers++;},clearTimeout(){}},
 });
 await api.sendCartQuantityUpdate(form);
 return {input,feedback,warning,line,notice,checkout,buttons,requests,timers,form};
}
(async()=>{
 for(const scenario of [{stock:0,adjust:false,message:'Out of stock. Remove this item to continue to checkout.'},{stock:1,adjust:true,message:'Only 1 left in stock. Reduce the quantity or remove this item to continue to checkout.'}]){
  const f=await fixture(scenario.stock,scenario.adjust,scenario.message);
  check('Rejected update sends once without automatic retry: '+scenario.stock,f.requests===1&&f.timers===0);
  check('Confirmed quantity is preserved instead of silently clamped: '+scenario.stock,f.input.value==='2'&&f.input.dataset.confirmedQuantity==='2');
  check('Authoritative stock sets adjustment controls: '+scenario.stock,f.input.disabled===!scenario.adjust&&f.input.max==='1'&&f.buttons.every(b=>b.disabled===!scenario.adjust));
  check('Item warning and aggregate notification are visible: '+scenario.stock,!f.warning.hidden&&f.warning.textContent===scenario.message&&!f.notice.hidden);
  check('Checkout is disabled with a visible update error: '+scenario.stock,f.checkout.attrs['aria-disabled']==='true'&&f.feedback.textContent==='Stock changed.');
  check('Busy state clears after rejected update: '+scenario.stock,!('aria-busy' in f.form.attrs));
 }
 console.log(JSON.stringify({pass,fail:0,mode:'DOM simulation; mocked stock responses',external_requests:0}));
})().catch(e=>{console.error(e.message);process.exitCode=1;});
