(() => {
 'use strict';
 const profile=document.querySelector('[data-match-profile]');if(!profile)return;
 const small=matchMedia('(max-width:900px)');
 const arrange=()=>{profile.open=!small.matches;profile.querySelector('summary').setAttribute('aria-expanded',String(profile.open));};
 arrange();small.addEventListener('change',()=>{if(!profile.contains(document.activeElement))arrange();});
})();