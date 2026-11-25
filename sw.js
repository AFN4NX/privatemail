'use strict';
const VERSION='privatemail-shell-v39';
const ASSETS=['/assets/compact-builder.css?v=6','/assets/confirmation-robot.css?v=1','/assets/confirmation-robot.js?v=2','/assets/login.css?v=3','/assets/login.js?v=2','/assets/migration.css?v=1','/assets/address-cards.css?v=3','/assets/settings.css?v=7','/assets/settings.js?v=3','/assets/tokens.css?v=10','/assets/app.css?v=14','/assets/mobile.css?v=11','/assets/magic-nav.css?v=15','/assets/features.css?v=15','/assets/app.js?v=17','/assets/features.js?v=22','/assets/navigation.js?v=13','/assets/pwa.js?v=12','/assets/icons/icon-192.png','/assets/icons/icon-512.png','/assets/icons/icon-maskable.png','/assets/icons/apple-touch-icon.png','/offline.html'];
const allowed=new Set(ASSETS);
self.addEventListener('install',event=>{event.waitUntil(caches.open(VERSION).then(cache=>cache.addAll(ASSETS)).then(()=>self.skipWaiting()));});
self.addEventListener('activate',event=>{event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k.startsWith('privatemail-shell-')&&k!==VERSION).map(k=>caches.delete(k)))).then(()=>self.clients.claim()));});
self.addEventListener('fetch',event=>{
 const request=event.request,url=new URL(request.url);
 if(url.origin!==self.location.origin)return;
 // Mail/status/auth mutation APIs never enter any service-worker cache.
 if(url.pathname.startsWith('/api/')){event.respondWith(fetch(request,{cache:'no-store'}));return;}
 if(request.method!=='GET')return;
 if(request.mode==='navigate'){
  event.respondWith(fetch(request).catch(()=>caches.match('/offline.html')));return;
 }
 const key=url.pathname+url.search,isFont=url.pathname.startsWith('/assets/fonts/')&&/\.(?:woff2?|ttf|otf)$/i.test(url.pathname);
 if(!allowed.has(key)&&!isFont)return;
 // Stale-While-Revalidate: cached bytes win immediately; a single background
 // fetch refreshes the static entry for the next request, even on cache hits.
 const update=(async()=>{
  try{
   const response=await fetch(request,{cache:'no-cache'});
   if(response.ok&&!response.redirected){
    try{const cache=await caches.open(VERSION);await cache.put(request,response.clone());}catch(_){}
   }
   return response;
  }catch(_){return null;}
 })();
 event.waitUntil(update.then(()=>undefined));
 event.respondWith(caches.match(request).catch(()=>undefined).then(cached=>cached||update.then(response=>response||Response.error())));
});
