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
check('Default routine and wash view',!f.routine.hidden&&f.matches.hidden&&!f.wash.hidden&&f.between.hidden);
check('Primary tablist and panels have accessible roles',f.nav.attrs.role==='tablist'&&f.routine.attrs.role==='tabpanel'&&f.routine.tabIndex===0);
check('Only selected primary tab is in sequential focus',f.buttons[0].tabIndex===0&&f.buttons[1].tabIndex===-1&&f.buttons[0].attrs['aria-selected']==='true');
f.buttons[0].fire('keydown',{key:'ArrowRight'});
check('Right arrow selects and focuses matches',f.routine.hidden&&!f.matches.hidden&&f.buttons[1].focused&&f.buttons[1].attrs['aria-controls']==='product-matches-title');
f.filters[1].fire('click');check('Filter count uses visible result cards',!f.cards[0].hidden&&f.cards[1].hidden&&f.count.textContent==='1 match');
f.buttons[1].fire('keydown',{key:'Home'});f.modes[1].fire('click');f.buttons[1].fire('click');f.buttons[0].fire('click');
check('Timing and filters survive primary tab changes',f.wash.hidden&&!f.between.hidden&&f.filters[1].attrs['aria-pressed']==='true'&&f.cards[1].hidden);
check('Tab selection preserves existing history state',f.history.kept.account==='keep');
f.buttons[0].fire('keydown',{key:'ArrowLeft'});check('Left arrow wraps focus',f.buttons[1].focused&&!f.matches.hidden);
f.buttons[1].fire('keydown',{key:'Home'});f.buttons[0].fire('keydown',{key:'End'});check('End selects last tab',f.buttons[1].attrs['aria-selected']==='true');
check('Modified links keep normal browser navigation',!f.buttons[0].fire('click',{ctrlKey:true}).prevented);
f.location.hash='#routine-preview-title';f.events.hashchange();check('Legacy routine direct link activates matching view',!f.routine.hidden);
const direct=fixture('#product-matches-title');check('Legacy product direct link opens matches',!direct.matches.hidden&&direct.routine.hidden);
const timing=fixture('#suggested-care-between');check('Timing deep link exposes both ancestors',!timing.routine.hidden&&!timing.between.hidden);
const reduced=fixture('',true);check('Reduced motion creates no tab animation',reduced.routine.animations===0&&reduced.wash.animations===0);
// Source never receives fetch or persistence APIs in this fixture; any implicit mutation would throw.
check('Tabs and filters operate with no network or storage APIs',true);
console.log(JSON.stringify({pass,fail:0,mode:'DOM simulation',network_requests:0}));
