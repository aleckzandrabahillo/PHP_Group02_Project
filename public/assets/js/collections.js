(() => {
 'use strict';
 const mounted=new WeakSet(),forms=new WeakSet();
 const validURL=(raw,path)=>{const url=new URL(raw,location.href);if(url.origin!==location.origin||url.pathname!==path)throw new Error('Invalid collection destination');return url;};
 const parse=async(response)=>{if(response.redirected){location.assign('/login');throw new Error('Please sign in again.');}if(!response.ok)throw new Error('This list could not be loaded. Please try again.');const text=await response.text();if(text.length>3000000)throw new Error('This list could not be loaded.');return new DOMParser().parseFromString(text,'text/html');};
 function init(root=document){
  root.querySelectorAll('[data-continuous-list]').forEach(list=>{
   if(mounted.has(list))return;mounted.add(list);const kind=list.dataset.continuousList,path=kind==='shop'?'/shop':'/'+kind;
   const scope=list.closest('[data-collection-view]')||list.parentElement,more=scope.querySelector('[data-collection-more]'),button=more?.querySelector('[data-load-more]'),status=more?.querySelector('[data-collection-status]');if(!more||!button)return;
   let busy=false,request=null;const observer=new IntersectionObserver(entries=>{if(entries.some(e=>e.isIntersecting))load();},{rootMargin:'450px'});
   const update=()=>{button.hidden=!list.dataset.nextUrl;button.href=list.dataset.nextUrl||'#';if(!list.dataset.nextUrl){observer.disconnect();if(list.children.length)status.textContent="You’ve reached the end.";}};
   async function load(){
    if(busy||!list.dataset.nextUrl||!list.isConnected)return;busy=true;request=new AbortController();const timeout=setTimeout(()=>request.abort(),10000);button.setAttribute('aria-disabled','true');list.setAttribute('aria-busy','true');status.textContent='Loading more…';
    try{
     const target=validURL(list.dataset.nextUrl,path),doc=await parse(await fetch(target,{credentials:'same-origin',cache:'no-store',signal:request.signal}));if(!list.isConnected)return;
     const next=doc.querySelector('[data-continuous-list="'+kind+'"]');if(!next){list.dataset.nextUrl='';status.textContent='You’ve reached the end.';return;}
     if(Number(next.dataset.streamPage)<=Number(list.dataset.streamPage)){list.dataset.nextUrl='';status.textContent='You’ve reached the end.';return;}
     const keys=new Set([...list.children].map(e=>e.dataset.streamKey));let added=0;for(const item of [...next.children]){if(item.dataset.streamKey&&keys.has(item.dataset.streamKey))continue;list.append(document.importNode(item,true));added++;}
     list.dataset.streamPage=next.dataset.streamPage;list.dataset.nextUrl=next.dataset.nextUrl||'';status.textContent=list.dataset.nextUrl?'':"You’ve reached the end.";
     const count=scope.querySelector('[data-favorite-result-count]');if(count)count.textContent=list.children.length+' shown · '+count.dataset.matchingTotal+' matching favorites';
     document.dispatchEvent(new CustomEvent('avela:content',{detail:{root:list}}));
    }catch(error){if(request.signal.aborted&&!list.isConnected)return;status.textContent=error.message==='Please sign in again.'?error.message:'More results could not be loaded. Use Load more to retry.';observer.unobserve(more);}
    finally{clearTimeout(timeout);busy=false;button.removeAttribute('aria-disabled');list.removeAttribute('aria-busy');update();if(list.dataset.nextUrl&&more.getBoundingClientRect().top<innerHeight+450&&!status.textContent.includes('retry'))queueMicrotask(load);}
   }
   button.addEventListener('click',event=>{event.preventDefault();load();});observer.observe(more);update();
   document.addEventListener('avela:beforecontent',()=>{if(!list.isConnected)return;request?.abort();observer.disconnect();},{once:true});
  });
  root.querySelectorAll('[data-auto-collection]').forEach(form=>{
   if(forms.has(form))return;forms.add(form);const kind=form.dataset.autoCollection;let timer,request,version=0;
   async function refresh(){
    request?.abort();request=new AbortController();const activeRequest=request,job=++version,timeout=setTimeout(()=>activeRequest.abort(),10000),view=document.querySelector('[data-collection-view="'+kind+'"]');if(!view)return;
    const url=validURL(form.action,'/'+kind);url.search=new URLSearchParams(new FormData(form)).toString();view.setAttribute('aria-busy','true');
    try{const doc=await parse(await fetch(url,{credentials:'same-origin',cache:'no-store',signal:request.signal}));if(job!==version||!view.isConnected)return;const next=doc.querySelector('[data-collection-view="'+kind+'"]');if(!next)throw new Error('Unavailable');document.dispatchEvent(new CustomEvent('avela:beforecontent'));view.replaceWith(document.importNode(next,true));history.replaceState(null,'',url);init(document);document.dispatchEvent(new CustomEvent('avela:content',{detail:{root:document.querySelector('[data-collection-view="'+kind+'"]')}}));}
    catch(error){if(error.name!=='AbortError'&&job===version)window.AvelaToast?.('Results could not be updated. Press Enter to try again.','warning');}
    finally{clearTimeout(timeout);if(job===version)view.removeAttribute('aria-busy');}
   }
   form.addEventListener('submit',event=>{event.preventDefault();clearTimeout(timer);refresh();});form.addEventListener('change',event=>{if(event.target.matches('select')){clearTimeout(timer);refresh();}});form.addEventListener('input',event=>{if(event.target.matches('input[type=search]')){request?.abort();version++;clearTimeout(timer);timer=setTimeout(refresh,350);}});
  });
 }
 init();document.addEventListener('avela:content',event=>init(event.detail?.root||document));
})();
