'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');let pass=0;
const check=(name,ok)=>{assert.ok(ok,name);pass++;};
class Element {
 constructor(){this.attrs={};this.events={};this.style={};this.top=0;this.id='';this.hash='';}
 getAttribute(k){return this.attrs[k]??null;}setAttribute(k,v){this.attrs[k]=v;}removeAttribute(k){delete this.attrs[k];}addEventListener(k,v){this.events[k]=v;}
 getBoundingClientRect(){return {height:50,top:this.top};}closest(){return null;}
 fire(props={}){const e={button:0,target:this,preventDefault(){this.prevented=true;},...props};this.events.click?.(e);return e;}
 focus(){this.focused=true;}scrollIntoView(options){this.scrollOptions=options;}
}
const summaries=[new Element(),new Element(),new Element(),new Element()],details=summaries.map(summary=>{
 const d=new Element();d.open=false;d.scrollHeight=200;d.querySelector=()=>summary;d.animate=()=>{const a={cancel(){this.cancelled=true;}};d.animation=a;return a;};return d;
});
details[2].attrs.name='product-care';details[3].attrs.name='product-care';
const links=[new Element(),new Element(),new Element()],sections=links.map((link,i)=>{const s=new Element();s.id=['shopping','terms','privacy-policy'][i];s.top=i*700;link.hash='#'+s.id;return s;});
const page={querySelectorAll:()=>links,querySelector:hash=>sections.find(s=>'#'+s.id===hash)};
const preference={matches:false,addEventListener(_,fn){this.change=fn;}},events={},context={
 document:{querySelectorAll:()=>details,querySelector:()=>page,documentElement:{scrollHeight:3000},fonts:{ready:Promise.resolve()}},
 matchMedia:()=>preference,history:{state:{keep:true},pushState(state,_,hash){this.kept=state;this.hash=hash;}},
 getComputedStyle:()=>({getPropertyValue:()=>110}),scrollY:0,innerHeight:700,
 requestAnimationFrame:fn=>fn(),addEventListener:(key,fn)=>{events[key]=fn;},ResizeObserver:class{observe(){}},
};
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname,'../public/assets/js/reading.js'),'utf8'),context);
check('Initial reading topic is marked',links[0].attrs['aria-current']==='location');
sections[0].top=-700;sections[1].top=120;events.scroll();check('Reading underline follows actual viewport section',links[1].attrs['aria-current']==='location'&&!links[0].attrs['aria-current']);
links[2].fire();check('Topic link scrolls smoothly and preserves history state',sections[2].scrollOptions.behavior==='smooth'&&sections[2].focused&&context.history.kept.keep&&context.history.hash==='#privacy-policy');
summaries[0].fire();details[0].animation.onfinish();summaries[1].fire();details[1].animation.onfinish();
check('Multiple independent disclosures stay open',details[0].open&&details[1].open);
summaries[0].fire();const closing=details[0].animation;summaries[0].fire();details[0].animation.onfinish();
check('Rapid close/reopen cancels the old animation',closing.cancelled&&details[0].open&&details[0].style.overflow==='');
summaries[1].fire();preference.matches=true;preference.change();check('Reduced-motion change settles a pending close',!details[1].open&&details[1].animation.cancelled);
links[1].fire();check('Reduced motion uses immediate topic navigation',sections[1].scrollOptions.behavior==='instant');
check('Modified topic links retain normal navigation',!links[0].fire({ctrlKey:true}).prevented);
preference.matches=false;summaries[2].fire();details[2].animation.onfinish();summaries[3].fire();details[2].animation.onfinish();details[3].animation.onfinish();check('Product accordion closes its grouped sibling',!details[2].open&&details[3].open);
console.log(JSON.stringify({pass,fail:0,mode:'DOM simulation',network_requests:0}));
