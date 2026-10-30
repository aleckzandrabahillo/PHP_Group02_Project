const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const css=fs.readFileSync(path.join(__dirname,'../public/assets/css/staff.css'),'utf8');
const block=css.match(/\.avela-staff\{([^}]+)\}/)[1];const tokens=Object.fromEntries([...block.matchAll(/(--staff-[\w-]+):([^;]+)/g)].map(m=>[m[1],m[2]]));
const color=value=>{const token=tokens[value]||value;return token.startsWith('var(')?token.match(/,(#[0-9a-f]{6})\)$/i)[1]:token;};
function luminance(hex){const values=hex.replace('#','').match(/../g).map(v=>parseInt(v,16)/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4);return values[0]*.2126+values[1]*.7152+values[2]*.0722;}
function ratio(a,b){a=luminance(color(a));b=luminance(color(b));return (Math.max(a,b)+.05)/(Math.min(a,b)+.05);}
const pairs=[['Body','--staff-ink','--staff-surface'],['Secondary text','--staff-muted','--staff-surface'],['Sidebar text','--staff-ink','--staff-ground'],['Workspace label','--staff-accent-hover','--staff-ground'],['Secondary sidebar text','--staff-muted','--staff-ground'],['Primary button','#ffffff','--staff-accent'],['Button hover','#ffffff','--staff-accent-hover'],['Table headings','--staff-muted','--staff-surface'],['Metric captions','--staff-muted','--staff-raised'],['Selected navigation','--staff-ink','--staff-active'],['Success status','#345e48','#edf3ed'],['Attention status','#78551c','#f6efdf'],['Error status','#943c42','#f8eaea'],['Neutral status','#655e55','#f0eee9']];
let pass=0;const results=[];for(const [name,a,b] of pairs){const measured=ratio(a,b);assert.ok(measured>=4.5,`${name}: ${measured.toFixed(2)}:1`);pass++;results.push({name,ratio:measured.toFixed(2)});}
for(const [name,a,b] of [['Control border','--staff-control','--staff-surface'],['Focus ring','--staff-accent','--staff-surface']]){const measured=ratio(a,b);assert.ok(measured>=3,`${name}: ${measured.toFixed(2)}:1`);pass++;results.push({name,ratio:measured.toFixed(2)});}
const storefront=fs.readFileSync(path.join(__dirname,'../public/assets/css/customer.css'),'utf8');
for(const [staff,name] of [['--staff-accent','espresso'],['--staff-ink','ink'],['--staff-muted','muted'],['--staff-surface','oat'],['--staff-ground','greige']]){const expected=storefront.match(new RegExp('--'+name+':(#[0-9a-f]{6})','i'))[1];assert.equal(color(staff),expected,staff+' matches storefront');pass++;}
assert.ok(!css.includes('#6b4933')&&!css.includes('#b78b67'),'Rejected cocoa and tan accents do not remain');pass++;
console.log(JSON.stringify({suite:'staff_contrast',pass,fail:0,results}));
