(() => {
  const element = document.getElementById('session-watch-config');
  if (!element) return;
  const config = JSON.parse(element.textContent);
  let expires = 0, pending = false, lastTouch = Date.now(), dirty = false, stopped = false;
  const sync = async (touch = false) => {
    if (pending || stopped || !navigator.onLine) return;
    pending = true;
    try {
      const response = await fetch(touch ? config.activity : config.status, {
        method:touch?'POST':'GET',credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},
        ...(touch?{body:new URLSearchParams({_token:config.token})}:{})
      });
      if (response.status === 401) {stopped=true;window.location.replace(config.login);return;}
      if (!response.ok || response.redirected) return;
      const state = await response.json();
      if (!state.authenticated) {stopped=true;window.location.replace(config.login);return;}
      expires = Number(state.expires_at) * 1000;
      if (touch) {lastTouch=Date.now();dirty=false;}
    } catch (_) { /* A connection failure never claims a successful session extension. */ }
    finally {pending=false;}
  };
  ['pointerdown','keydown','input'].forEach((event) => document.addEventListener(event, () => {dirty=true;}, {passive:true}));
  const clock = window.setInterval(() => {
    if (stopped) {window.clearInterval(clock);return;}
    if (expires && Date.now() >= expires) {sync(false);return;}
    if (document.visibilityState === 'visible' && dirty && Date.now()-lastTouch >= 60000) sync(true);
  },1000);
  document.addEventListener('visibilitychange', () => {if(document.visibilityState==='visible')sync(false);});
  window.addEventListener('pageshow',()=>sync(false));
  sync(false);
})();
