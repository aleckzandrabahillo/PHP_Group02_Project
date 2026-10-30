(() => {
 'use strict';if(!document.querySelector('.staff-shell'))return;
 let pending=null,serial=0;const allowed=path=>/^\/(?:admin|catalog)(?:\/|$)/.test(path)||['/account/password','/account/mfa','/account/profile/verify-otp'].includes(path);
 const dirty=()=>[...document.querySelectorAll('.staff-main form[method=post] input:not([type=hidden]),.staff-main form[method=post] textarea,.staff-main form[method=post] select')].filter(input=>input.getClientRects().length).some(input=>input.type==='file'?input.files.length:input.type==='checkbox'||input.type==='radio'?input.checked!==input.defaultChecked:input instanceof HTMLSelectElement?[...input.options].some(option=>option.selected!==option.defaultSelected)&&!![...input.options].find(option=>option.defaultSelected):input.value!==input.defaultValue);
 async function navigate(url,push=true){
  pending?.abort();const request=new AbortController();pending=request;const job=++serial,main=document.querySelector('.staff-main'),timer=setTimeout(()=>request.abort(),10000);main.setAttribute('aria-busy','true');
  try{
   const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json','X-Avela-Content':'staff'},signal:request.signal});
   if(response.redirected||!response.headers.get('Content-Type')?.includes('application/json')){location.assign(response.redirected?response.url:url);return;}
   const data=await response.json();if(job!==serial)return;if(!response.ok||typeof data.html!=='string'||data.html.length>3000000)throw new Error('Unavailable');
   document.dispatchEvent(new CustomEvent('avela:beforecontent'));const template=document.createElement('template');template.innerHTML=data.html;template.content.querySelectorAll('script:not([type="application/json"])').forEach(script=>script.remove());main.replaceChildren(template.content);document.title='Avela';
   const heading=document.querySelector('.staff-topbar strong');if(heading)heading.textContent=data.pageTitle;
   const links=[...document.querySelectorAll('.staff-sidebar nav a')],matches=links.filter(link=>{const path=new URL(link.href).pathname;return path===url.pathname||(!['/admin','/catalog'].includes(path)&&url.pathname.startsWith(path+'/'));}),current=matches.sort((a,b)=>new URL(b.href).pathname.length-new URL(a.href).pathname.length)[0];
   links.forEach(link=>{const active=link===current;link.classList.toggle('active',active);if(active)link.setAttribute('aria-current','page');else link.removeAttribute('aria-current');});
   document.querySelectorAll('.staff-sidebar details').forEach(group=>{group.open=!!group.querySelector('a[aria-current="page"]');});
   if(push)history.pushState({avelaStaff:true},'',url);window.scrollTo(0,0);if(typeof setStaffNavigation==='function')setStaffNavigation(false,false);
   for(const src of data.scripts||[]){const target=new URL(src,location.href);if(target.origin!==location.origin||!/^\/assets\/js\/[a-z-]+\.js$/.test(target.pathname))continue;if([...document.scripts].some(script=>script.src===target.href))continue;await new Promise((resolve,reject)=>{const script=document.createElement('script');script.src=target.href;script.onload=resolve;script.onerror=reject;document.body.append(script);});}
   document.dispatchEvent(new CustomEvent('avela:content',{detail:{root:main}}));const anchor=url.hash?document.getElementById(decodeURIComponent(url.hash.slice(1))):null;if(anchor){anchor.scrollIntoView({block:'start',behavior:'smooth'});(anchor.querySelector('input:not([type=hidden]),button')||main).focus({preventScroll:true});}else main.focus({preventScroll:true});window.AvelaMotion?.reveal(main,'fast');
  }catch(error){if(job!==serial)return;if(error.name==='AbortError'&&pending!==request)return;location.assign(url);}
  finally{clearTimeout(timer);if(job===serial)main.removeAttribute('aria-busy');}
 }
 document.addEventListener('click',event=>{
  const link=event.target.closest?.('.staff-sidebar a[href],.staff-profile-link,.staff-main a[href]');if(!link||event.defaultPrevented||event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey||link.target||link.hasAttribute('download'))return;
  const url=new URL(link.href,location.href);if(url.origin!==location.origin||!allowed(url.pathname)||(url.hash&&url.pathname===location.pathname&&url.search===location.search))return;
  if(dirty()&&!window.confirm('Leave this page without saving your changes?')){event.preventDefault();return;}event.preventDefault();navigate(url);
 });
 document.addEventListener('submit',event=>{const form=event.target;if(event.defaultPrevented||!(form instanceof HTMLFormElement)||form.method.toLowerCase()!=='get'||!form.closest('.staff-main'))return;const url=new URL(form.action,location.href);if(url.origin!==location.origin||!allowed(url.pathname))return;event.preventDefault();url.search=new URLSearchParams(new FormData(form)).toString();navigate(url);});
 window.addEventListener('popstate',()=>{const url=new URL(location.href);if(allowed(url.pathname))navigate(url,false);else location.reload();});
})();
