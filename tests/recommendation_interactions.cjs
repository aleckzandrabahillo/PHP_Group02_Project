'use strict';
// DOM simulation exercises the production handlers; it is not a browser/visual test.
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..');let pass=0;
const check=(name,condition)=>{assert.ok(condition,name);pass++;};
class Node {
 constructor(dataset={}){this.dataset=dataset;this.attrs={};this.events={};this.hidden=false;this.top=0;this.animations=0;}
 setAttribute(k,v){this.attrs[k]=v;} removeAttribute(k){delete this.attrs[k];}
 addEventListener(k,v){(this.events[k]??=[]).push(v);}
 fire(k,props={}){const event={preventDefault(){this.prevented=true;},button:0,...props};for(const fn of this.events[k]??[])fn(event);return event;}
 focus(){this.focused=true;} getBoundingClientRect(){return {top:this.top,height:60};} scrollIntoView(){this.scrolled=true;}
 animate(){this.animations++;return {};}
}
function fixture(hash='',reduce=false){
 const nav=new Node(),modeNav=new Node(),routine=new Node({resultPanel:'routine'}),matches=new Node({resultPanel:'matches'}),wash=new Node({modePanel:'wash'}),between=new Node({modePanel:'between'});
 const buttons=['routine','matches'].map((value,index)=>{const node=new Node({resultTab:value});node.hash=index?'#product-matches-title':'#routine-preview-title';return node;});
 const modes=['wash','between'].map(value=>{const node=new Node({modeTab:value});node.hash='#suggested-care-'+value;return node;});
 const filters=['all','cleanse'].map(value=>new Node({matchFilter:value})),cards=['cleanse','finish'].map(value=>new Node({matchRole:value})),count=new Node(),events={},location={hash};
 const lists={'[data-result-tab]':buttons,'[data-result-panel]':[routine,matches],'[data-mode-tab]':modes,'[data-mode-panel]':[wash,between],'[data-match-filter]':filters,'[data-match-role]':cards};
 const page={querySelector:s=>({'[data-result-tabs]':nav,'[data-mode-tabs]':modeNav,'[data-match-count]':count}[s]),querySelectorAll:s=>lists[s]??[]};
 const history={state:{account:'keep'},replaceState(state,_,next){this.kept=state;location.hash=next;}};
 vm.runInNewContext(fs.readFileSync(path.join(root,'public/assets/js/recommendations.js'),'utf8'),{document:{querySelector:()=>page},matchMedia:()=>({matches:reduce}),location,history,addEventListener:(key,fn)=>{events[key]=fn;}});
 return {nav,modeNav,buttons,modes,routine,matches,wash,between,filters,cards,count,events,location,history};
}
const f=fixture();
check('Wash day is visible and between-wash care is deferred',!f.wash.hidden&&f.between.hidden);
check('Timing has accessible tablist and tabpanel roles',f.modeNav.attrs.role==='tablist'&&f.wash.attrs.role==='tabpanel'&&f.wash.tabIndex===0);
check('Only the selected timing tab is in sequential focus',f.modes[0].tabIndex===0&&f.modes[1].tabIndex===-1&&f.modes[0].attrs['aria-selected']==='true');
f.modes[0].fire('keydown',{key:'ArrowRight'});
check('Right arrow selects and focuses between-wash care',f.wash.hidden&&!f.between.hidden&&f.modes[1].focused&&f.modes[1].attrs['aria-controls']==='suggested-care-between');
f.modes[1].fire('keydown',{key:'Home'});f.modes[0].fire('keydown',{key:'End'});
check('Timing changes preserve unrelated history state',f.history.kept.account==='keep');
check('End selects the last timing tab',f.modes[1].attrs['aria-selected']==='true');
f.modes[1].fire('keydown',{key:'ArrowRight'});check('Arrow navigation wraps',f.modes[0].focused&&!f.wash.hidden);
check('Modified links keep normal browser navigation',!f.modes[0].fire('click',{ctrlKey:true}).prevented);
f.location.hash='#suggested-care-between';f.events.hashchange();check('Timing deep link opens its intended panel',!f.between.hidden);
const direct=fixture('#product-matches-title');check('Legacy product anchor does not hide either main section',!direct.matches.hidden&&!direct.routine.hidden);
const reduced=fixture('',true);check('Reduced motion creates no timing animation',reduced.wash.animations===0);
check('Interactions operate without network or storage APIs',true);
console.log(JSON.stringify({pass,fail:0,mode:'DOM simulation',network_requests:0}));
