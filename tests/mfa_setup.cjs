'use strict';
// Public RFC fixture only. No enrolled key, cookie, OTP or clipboard is read.
const assert = require('node:assert/strict'), vm = require('node:vm'), fs = require('node:fs'), path = require('node:path');
const qrcode = require('../public/assets/js/qr-encoder.js');
const source = fs.readFileSync(path.join(__dirname, '../public/assets/js/mfa-setup.js'), 'utf8');
const raw = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
const uri = 'otpauth://totp/Avela%3Atest-user?secret='+raw+'&issuer=Avela&algorithm=SHA1&digits=6&period=30';
function node(tag='div') {
  const events={};
  return {tag,attrs:{},children:[],dataset:{},textContent:'',value:'',disabled:false,hidden:false,open:false,events,
    setAttribute(k,v){this.attrs[k]=v;},getAttribute(k){return this.attrs[k];},removeAttribute(k){delete this.attrs[k];},
    append(child){this.children.push(child);},replaceChildren(...children){this.children=children;},
    addEventListener(k,fn){(events[k]??=new Set()).add(fn);},removeEventListener(k,fn){events[k]?.delete(fn);},
    fire(k,e={}){return Promise.all([...(events[k]||[])].map(fn=>fn(e)));},
    focus(){this.focused=true;},select(){this.selected=true;},getAnimations(){return [];},
    showModal(){this.open=true;},close(){this.open=false;this.fire('close');},
    querySelector(){return node('h2');}
  };
}
function mount({expired=false,encoder=qrcode,step=1,clipboard=true}={}) {
  const frame=node(),status=node(),key=node('textarea'),expiry=node(),restart=node('a'),field=node('input'),submit=node('button');
  const copy=node('button'),label=node(),open=node('button'),done=node('button'),dialog=node('dialog'),next=node('button'),back=node('button'),progress=node(),announcement=node(),ended=node();
  const first=node(),second=node('form');first.dataset.mfaStep='1';second.dataset.mfaStep='2';
  const active=[node(),node()], selectors={'[data-mfa-qr]':frame,'[data-mfa-qr-status]':status,'#authenticator-setup-key':key,'[data-mfa-expiry]':expiry,'[data-mfa-restart]':restart,'[data-mfa-copy]':copy,'[data-mfa-copy-label]':label,'[data-mfa-open-qr]':open,'[data-mfa-close-qr]':done,'#mfa-qr-dialog':dialog,'[data-mfa-next]':next,'[data-mfa-back]':back,'[data-mfa-progress]':progress,'[data-mfa-announcement]':announcement,'[data-mfa-expired]':ended,'[data-mfa-confirm]':second};
  key.value=raw.match(/.{1,4}/g).join(' ');frame.attrs['data-provisioning-uri']=uri;ended.hidden=true;
  let now=1000000,copied='',interval;
  const setup={dataset:{expiresAt:String(expired?1:1300),currentStep:String(step)},querySelector:s=>selectors[s],contains:()=>true,querySelectorAll:s=>{
    if(s==='[data-mfa-step]')return[first,second];if(s==='[data-mfa-active]')return active;
    if(s==='[data-mfa-copy], [data-mfa-open-qr], [data-mfa-next], [data-mfa-back]')return[copy,open,next,back];
    return s.includes('button')?[field,submit]:[field];
  }};
  const document=Object.assign(node(),{querySelector:()=>setup,createElementNS:(_,tag)=>node(tag),body:{style:{overflow:''}}});
  const window=node();window.qrcode=encoder;
  const context={document,window,navigator:{clipboard:clipboard?{writeText:async text=>{copied=text;}}:null},setInterval:fn=>{interval=fn;return 1;},clearInterval(){},setTimeout:()=>2,clearTimeout(){},Date:{now:()=>now},location:{reload(){}}};
  vm.runInNewContext(source,context);
  return{frame,status,key,expiry,restart,field,submit,setup,document,window,copy,label,open,done,dialog,next,back,first,second,active,announcement,ended,progress,setTime:value=>{now=value;interval?.();},copied:()=>copied};
}
let passed=0;
async function test(name,fn){await fn();passed++;console.log('PASS: '+name);}
(async()=>{
 const ready=mount();
 await test('Key first; QR is not generated until requested',()=>{assert.ok(!ready.first.hidden&&ready.second.hidden);assert.equal(ready.frame.children.length,0);assert.equal(ready.key.value.replace(/\s/g,''),raw);});
 await test('Provisioning URI removed from DOM attribute',()=>assert.equal(ready.frame.attrs['data-provisioning-uri'],undefined));
 await test('Copy removes display spaces and preserves complete key',async()=>{await ready.copy.fire('click');assert.equal(ready.copied(),raw);assert.equal(ready.label.textContent,'Copied');});
 await test('QR opens accessible native modal using exact local encoder',async()=>{await ready.open.fire('click');assert.ok(ready.dialog.open&&ready.done.focused);assert.equal(ready.frame.children[0].attrs.role,'img');assert.match(ready.frame.children[0].children[1].attrs.d,/M4,4/);});
 await test('Close removes QR and restores opener and scrolling',async()=>{await ready.done.fire('click');assert.equal(ready.dialog.open,false);assert.equal(ready.frame.children.length,0);assert.ok(ready.open.focused);assert.equal(ready.document.body.style.overflow,'');});
 await test('Continue and Back change steps without reloading or resetting expiry',async()=>{await ready.next.fire('click');assert.ok(ready.first.hidden&&!ready.second.hidden&&ready.next.hidden);assert.equal(ready.progress.textContent,'Step 2 of 2');await ready.back.fire('click');assert.ok(!ready.first.hidden&&ready.second.hidden&&!ready.next.hidden);assert.equal(ready.expiry.textContent,'Time left 05:00');});
 await test('Failed server confirmation can resume directly at second step',()=>{const view=mount({step:2});assert.ok(view.first.hidden&&!view.second.hidden);});
 await test('Repeated content event leaves key and navigation intact',async()=>{await ready.document.fire('avela:content');assert.equal(ready.key.value.replace(/\s/g,''),raw);assert.ok(!ready.first.hidden);});
 await test('Countdown warns once near expiry',()=>{ready.setTime(1240000);assert.equal(ready.expiry.textContent,'Time left 01:00');assert.match(ready.announcement.textContent,/One minute/);});
 await test('Expiry closes modal, clears secrets/codes and hides active setup',async()=>{await ready.open.fire('click');ready.field.value='123456';ready.setTime(1300000);assert.ok(!ready.dialog.open&&ready.ended.hidden===false);assert.equal(ready.key.value,'');assert.equal(ready.frame.children.length,0);assert.equal(ready.field.value,'');assert.ok(ready.field.disabled&&ready.submit.disabled&&ready.copy.disabled&&ready.next.hidden&&ready.active.every(n=>n.hidden));});
 await test('Expired actions cannot copy, render QR or submit',async()=>{let prevented=false;await ready.copy.fire('click');await ready.open.fire('click');await ready.second.fire('submit',{preventDefault(){prevented=true;}});assert.ok(prevented&&!ready.dialog.open);assert.equal(ready.frame.children.length,0);});
 await test('Expired initial load shows restart instead of an unusable form',()=>{const view=mount({expired:true});assert.ok(!view.ended.hidden&&view.active.every(n=>n.hidden)&&view.key.value==='');});
 await test('Background wake checks expiry immediately',async()=>{const view=mount();view.setTime(1300000);await view.document.fire('visibilitychange');assert.ok(!view.ended.hidden);});
 await test('Missing encoder offers readable manual fallback',async()=>{const view=mount({encoder:null});await view.open.fire('click');assert.match(view.status.textContent,/copy the key/);assert.equal(view.key.value.replace(/\s/g,''),raw);});
 await test('Clipboard unavailable selects key and gives clear instructions',async()=>{const view=mount({clipboard:false});await view.copy.fire('click');assert.ok(view.key.selected);assert.match(view.announcement.textContent,/copy option/);});
 await test('Page exit clears key and QR and removes event listeners',async()=>{const view=mount();await view.open.fire('click');await view.window.fire('pagehide');assert.equal(view.key.value,'');assert.equal(view.frame.children.length,0);assert.equal(view.dialog.open,false);assert.equal(view.copy.events.click.size,0);});
 await test('No network, persistent storage or diagnostic logging',()=>assert.doesNotMatch(source,/\b(fetch|XMLHttpRequest|localStorage|sessionStorage|sendBeacon|console)\s*[.(]/));
 if(process.env.AVELA_QR_TEST_DECODER){const decode=require(process.env.AVELA_QR_TEST_DECODER);await test('Independent decoder recovers complete exact provisioning URI',async()=>{const view=mount();await view.open.fire('click');const svg=view.frame.children[0],size=Number(svg.attrs.viewBox.split(' ')[2]),scale=5,width=size*scale,rgba=new Uint8ClampedArray(width*width*4).fill(255);for(const m of svg.children[1].attrs.d.matchAll(/M(\d+),(\d+)h1v1h-1z/g))for(let y=0;y<scale;y++)for(let x=0;x<scale;x++){const at=((Number(m[2])*scale+y)*width+Number(m[1])*scale+x)*4;rgba[at]=rgba[at+1]=rgba[at+2]=0;}assert.equal(decode(rgba,width,width)?.data,uri);});}
 console.log(JSON.stringify({passed,failed:0}));
})().catch(()=>{console.error('MFA interface regression failed; fixture values suppressed.');process.exitCode=1;});
