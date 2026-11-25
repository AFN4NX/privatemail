(()=>{
const $=s=>document.querySelector(s), $$=s=>[...document.querySelectorAll(s)];
const list=$('#messageList'), search=$('#messageSearch'), emailSearch=$('#emailSearch'), refresh=$('#refreshButton');
let timer, searchController, searchVersion=0;
document.addEventListener('click',event=>document.querySelectorAll('.account-menu[open]').forEach(menu=>{if(!menu.contains(event.target))menu.open=false;}));
document.addEventListener('keydown',event=>{if(event.key==='Escape')document.querySelectorAll('.account-menu[open]').forEach(menu=>{menu.open=false;menu.querySelector('summary')?.focus();});});
const theme=document.body.dataset.theme;
if(theme==='system'){
 const mq=matchMedia('(prefers-color-scheme: light)');
 const apply=()=>document.body.dataset.theme=mq.matches?'light':'dark';apply();mq.addEventListener('change',apply);
}
function localFilter(q){const v=q.trim().toLowerCase();let n=0;list?.querySelectorAll('.message-card').forEach(c=>{const hit=!v||(c.dataset.search||'').includes(v);c.hidden=!hit;if(hit)n++;});const t=$('#messageTotal');if(t)t.textContent=n;}
async function remoteSearch(q){return window.PrivateMailInbox?.refresh({query:q});}
search?.addEventListener('input',e=>{localFilter(e.target.value);searchVersion++;searchController?.abort();clearTimeout(timer);timer=setTimeout(()=>remoteSearch(e.target.value),180);});
emailSearch?.addEventListener('input',e=>{const q=e.target.value.trim().toLowerCase();document.querySelectorAll('#sidebarAliases [data-email]').forEach(el=>el.hidden=!!q&&!(el.dataset.email||'').includes(q));});
refresh?.addEventListener('click',async()=>{refresh.disabled=true;try{await window.PrivateMailInbox?.refresh({force:true});}finally{refresh.disabled=false}});
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&modal&&!modal.hidden){if(domainPicker?.open)domainPicker.open=false;else closeModal()}});
document.addEventListener('click',e=>{const card=e.target.closest('.message-card');if(card&&e.button===0&&!e.ctrlKey&&!e.metaKey&&!e.shiftKey)card.classList.remove('unread');});
// Only the trusted, nonce-bearing mail document may resize its own iframe.
window.addEventListener?.('message',event=>{
 const data=event.data;
 if(!data||data.type!=='privatemail:resize'||!Number.isFinite(data.height)||data.height<1||data.height>250000)return;
 document.querySelectorAll('iframe.mime-frame[data-mail-channel]').forEach(frame=>{
  if(event.source!==frame.contentWindow||!frame.dataset.mailChannel||data.channel!==frame.dataset.mailChannel)return;
  frame.style.height=Math.ceil(data.height)+'px';
 });
});
document.querySelectorAll('iframe.mime-frame[data-mail-channel]').forEach(frame=>{
 const requestHeight=()=>frame.contentWindow?.postMessage?.({type:'privatemail:measure',channel:frame.dataset.mailChannel},'*');
 frame.addEventListener?.('load',requestHeight);requestHeight();
});
/* Address builder */
const modal=$('#aliasModal'),openers=$$('#createAliasButton, #createAliasButtonTop'),close=$('#aliasModalClose'),domain=$('#aliasDomain'),preview=$('#aliasPreview'),local=$('#aliasLocal'),form=$('#aliasForm'),error=$('#aliasFormError'),sort=$('#aliasSort'),aliasSearchEl=$('#aliasSearch'),aliasList=$('#aliasList'),aliasCount=$('#aliasCount');
const domainOptions=$('#domainOptions'),domainPicker=$('#domainPicker'),domainDisplay=$('#aliasDomainDisplay'),domainHint=$('#domainSelectionHint'),submitAlias=$('#aliasSubmit'),copyAlias=$('#aliasCopy');
const deleteModal=$('#aliasDeleteModal'),deleteCancel=$('#aliasDeleteCancel'),deleteConfirm=$('#aliasDeleteConfirm'),deleteError=$('#aliasDeleteError');
let modalPreviousFocus,deletePreviousFocus,pendingDelete=null,deleteBusy=false,metaPromise=null,toastTimer,aliasRequestVersion=0;
let mode='random',resolvedDomain='',domains=[...(domainOptions?.querySelectorAll('[data-domain]')||[])].map(x=>x.dataset.domain).filter(x=>x!=='__random__'),existingEmails=new Set();
const words=['frost','nova','orbit','pixel','ember','lunar','swift','cobalt','mint','echo','raven','cloud','maple','drift','vivid','spark','atlas','velvet','stone','wave','north','amber','silver','shadow','river','cosmic','moss','pearl','summit','terra','flare','cedar','violet','sonic','zenith','arctic','breeze','coral','dawn','meadow','opal','quartz','solar','storm','willow'];
function randomIndex(length){if(!length)return 0;const values=new Uint32Array(1);if(globalThis.crypto?.getRandomValues){crypto.getRandomValues(values);return values[0]%length;}return Math.floor(Math.random()*length);}
function randDomain(){const candidates=domains.length>1?domains.filter(d=>d!==resolvedDomain):domains;return candidates[randomIndex(candidates.length)]||'';}
function randLocal(d){for(let i=0;i<100;i++){const first=words[randomIndex(words.length)],second=words[randomIndex(words.length)];const v=first+'.'+second+String(10+randomIndex(90));if(!existingEmails.has(`${v}@${d}`))return v;}return 'mail'+Date.now().toString(36);}
function validAddressName(v){return /^[a-z0-9](?:[a-z0-9._-]{0,62}[a-z0-9])?$/i.test(v)&&!v.includes('..');}
function esc(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
function toast(message){const el=$('#appToast');if(!el)return;clearTimeout(toastTimer);el.textContent=message;el.hidden=false;toastTimer=setTimeout(()=>el.hidden=true,3000);}
function setPreview(regenerate=false){
 if(!domain||!local||!preview)return;
 if(domain.value==='__random__'){if(!resolvedDomain||regenerate||!domains.includes(resolvedDomain))resolvedDomain=randDomain();}else resolvedDomain=domain.value;
 if(mode==='random'&&(regenerate||!local.value))local.value=randLocal(resolvedDomain);
 const name=local.value.trim().toLowerCase();
 preview.textContent=resolvedDomain?`${name||'yourname'}@${resolvedDomain}`:'No domains configured';
 if(submitAlias)submitAlias.disabled=!resolvedDomain||!validAddressName(name);
 if(copyAlias)copyAlias.disabled=!resolvedDomain||!validAddressName(name);
 if(domainHint)domainHint.textContent=domain.value==='__random__'?(domains.length>1?'A different domain is picked when you shuffle.':domains.length===1?'One configured domain is available.':'Add a receiving domain before creating an address.'):'This domain stays selected when you shuffle.';
}
function updateDomainChoice(){
 const selected=domain?.value||'__random__';
 if(domainDisplay)domainDisplay.textContent=selected==='__random__'?'Random domain':selected;
 domainOptions?.querySelectorAll('[data-domain]').forEach(button=>{const picked=button.dataset.domain===selected;button.classList.toggle('is-picked',picked);button.setAttribute('aria-pressed',String(picked));});
}
async function loadAliasMeta(){
 if(!window.PRIVATEMAIL?.aliasesApi)return;
 if(metaPromise)return metaPromise;
 metaPromise=(async()=>{
  const response=await fetch(window.PRIVATEMAIL.aliasesApi,{credentials:'same-origin',headers:{Accept:'application/json'}}),data=await response.json();
  if(!data.ok)throw new Error(data.error||'Unable to load your domains.');
  domains=(data.domains||[]).map(String);existingEmails=new Set((data.aliases||[]).map(x=>String(x.email).toLowerCase()));
  if(domainOptions)domainOptions.innerHTML='<button type="button" class="domain-option" data-domain="__random__"><span>Random domain</span><span class="domain-check" aria-hidden="true">✓</span></button>'+domains.map(d=>`<button type="button" class="domain-option" data-domain="${esc(d)}"><span>${esc(d)}</span><span class="domain-check" aria-hidden="true">✓</span></button>`).join('');
  if(domain&&domain.value!=='__random__'&&!domains.includes(domain.value))domain.value='__random__';
  updateDomainChoice();setPreview();
 })().finally(()=>metaPromise=null);
 return metaPromise;
}
function openModal(){
 if(!modal)return;const pageMode=typeof matchMedia==='function'&&matchMedia('(max-width:900px)').matches;document.body.classList.toggle('alias-create-page',pageMode);if(pageMode){document.querySelector('.workspace')?.append(modal);modal.querySelector('[role=dialog]')?.setAttribute('aria-modal','false');modal.querySelector('[role=dialog]')?.setAttribute('role','region');}modalPreviousFocus=document.activeElement;modal.hidden=false;window.dispatchEvent(new CustomEvent('privatemail:alias-state',{detail:{open:true}}));if(pageMode){const title=document.getElementById('createAliasTitle');title?.setAttribute('tabindex','-1');title?.focus();}else close?.focus();document.body.style.overflow='hidden';
 if(error){error.hidden=true;error.textContent='';}setPreview();updateDomainChoice();
 loadAliasMeta().catch(err=>{if(error){error.textContent=err.message;error.hidden=false;}});
 if(typeof history!=='undefined'){const url=new URL(location.href);if(pageMode)url.searchParams.set('create','1');else url.searchParams.delete('create');history.replaceState(null,'',url.pathname+url.search);}
}
function closeModal(){if(!modal)return;modal.hidden=true;if(typeof history!=='undefined'){const url=new URL(location.href);url.searchParams.delete('create');history.replaceState(null,'',url.pathname+url.search);}document.body.classList.remove('alias-create-page');window.dispatchEvent(new CustomEvent('privatemail:alias-state',{detail:{open:false}}));if(domainPicker)domainPicker.open=false;document.body.style.overflow='';modalPreviousFocus?.focus();}
openers.forEach(b=>b.addEventListener('click',openModal));close?.addEventListener('click',closeModal);$('#aliasCancel')?.addEventListener('click',closeModal);modal?.addEventListener('click',e=>{if(e.target===modal&&!document.body.classList.contains('alias-create-page'))closeModal();});
domainOptions?.addEventListener('click',e=>{const button=e.target.closest('[data-domain]');if(!button||!domain)return;domain.value=button.dataset.domain;resolvedDomain='';if(domainPicker)domainPicker.open=false;updateDomainChoice();setPreview();domainPicker?.querySelector('summary')?.focus();});
document.addEventListener('click',e=>{if(domainPicker?.open&&!domainPicker.contains(e.target))domainPicker.open=false;});
$$('.generator-tab').forEach(button=>button.addEventListener('click',()=>{
 mode=button.dataset.mode||'random';$$('.generator-tab').forEach(b=>{const active=b===button;b.classList.toggle('active',active);b.setAttribute('aria-pressed',String(active));});
 if(local){local.readOnly=mode==='random';local.value='';if(mode==='custom')local.focus();}
 const nameField=$('#aliasNameField');if(nameField)nameField.hidden=false;
 const shuffle=$('#aliasRegenerate');if(shuffle)shuffle.hidden=false;setPreview(mode==='random');
}));
function resetPreviewCopy(){if(!copyAlias)return;clearTimeout(copyAlias.copyFeedbackTimer);if(copyAlias.originalIcon)copyAlias.innerHTML=copyAlias.originalIcon;copyAlias.classList.remove('is-copied');copyAlias.setAttribute('aria-label','Copy email address');}
local?.addEventListener('input',()=>{resetPreviewCopy();setPreview();});$('#aliasRegenerate')?.addEventListener('click',()=>{resetPreviewCopy();if(mode==='custom'&&local)local.value=randLocal(resolvedDomain);setPreview(true);const button=$('#aliasRegenerate');if(button){button.classList.remove('is-regenerating');void button.offsetWidth;button.classList.add('is-regenerating');clearTimeout(button.feedbackTimer);button.feedbackTimer=setTimeout(()=>button.classList.remove('is-regenerating'),600);}if(preview){preview.classList.remove('just-generated');void preview.offsetWidth;preview.classList.add('just-generated');setTimeout(()=>preview.classList.remove('just-generated'),500);}});
copyAlias?.addEventListener('click',async()=>{if(!resolvedDomain||!validAddressName(local?.value||''))return;const address=preview.textContent;try{await navigator.clipboard.writeText(address);if(address!==preview.textContent)return;if(!copyAlias.originalIcon)copyAlias.originalIcon=copyAlias.innerHTML;copyAlias.innerHTML=aliasActionIcon('check');copyAlias.classList.add('is-copied');copyAlias.setAttribute('aria-label','Email address copied');clearTimeout(copyAlias.copyFeedbackTimer);copyAlias.copyFeedbackTimer=setTimeout(resetPreviewCopy,1600);}catch(_){if(error){error.textContent='Copy is unavailable. Select the email address to copy it.';error.hidden=false;}}});
form?.addEventListener('submit',async e=>{
 e.preventDefault();if(error)error.hidden=true;const name=local?.value.trim().toLowerCase()||'';
 if(!resolvedDomain||!domains.includes(resolvedDomain)||!validAddressName(name)){if(error){error.textContent='Choose a domain and enter a valid address name.';error.hidden=false;}return;}
 if(submitAlias)submitAlias.disabled=true;
 const fd=new FormData(form);fd.set('action','create');fd.set('domain',resolvedDomain);fd.set('local_part',name);
 try{const response=await fetch(window.PRIVATEMAIL.aliasesApi,{method:'POST',body:fd,credentials:'same-origin'}),data=await response.json();if(!data.ok)throw new Error(data.error||'Unable to create the address.');location.href='/aliases.php?created=1';}
 catch(err){if(error){error.textContent=err.message;error.hidden=false;}if(submitAlias)submitAlias.disabled=false;}
});
/* Address list and in-app removal confirmation */
function aliasActionIcon(name){
 const paths={at:'<circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/>',copy:'<rect x="8" y="8" width="12" height="12" rx="3"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/>',inbox:'<path d="M4 4h16v12h-5l-2 3h-2l-2-3H4z"/><path d="M4 12h5l2 3h2l2-3h5"/>',trash:'<path d="M3 6h18M9 6V4h6v2M5 6l1 14h12l1-14M10 10v6M14 10v6"/>',check:'<path d="m5 12 4 4L19 6"/>'};
 return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[name]||paths.at}</svg>`;
}
async function reloadAliases(){
 if(!aliasList||!window.PRIVATEMAIL?.aliasesApi)return;const version=++aliasRequestVersion,qs=new URLSearchParams({sort:sort?.value||'new',search:aliasSearchEl?.value||''});
 try{const response=await fetch(`${window.PRIVATEMAIL.aliasesApi}?${qs}`,{credentials:'same-origin'}),data=await response.json();if(!data.ok)throw new Error(data.error||'Unable to load addresses.');if(version!==aliasRequestVersion)return;
 if(aliasCount)aliasCount.textContent=data.count;
 aliasList.innerHTML=data.aliases.length?data.aliases.map(x=>`<article class="alias-card"><div class="alias-main"><div class="alias-avatar" aria-hidden="true">${aliasActionIcon('at')}</div><div><strong>${esc(x.email)}</strong><span class="alias-domain-caption">${esc(x.domain||'Receiving address')}</span></div></div><div class="alias-actions" role="group" aria-label="Address actions"><button type="button" class="alias-action copy-alias" data-email="${esc(x.email)}" aria-label="Copy email address" title="Copy address" data-tooltip="Copy address">${aliasActionIcon('copy')}</button><a class="alias-action alias-open" href="/?inbox=${Number(x.id)}" aria-label="Open address inbox" title="Open inbox" data-tooltip="Open inbox">${aliasActionIcon('inbox')}</a><button type="button" class="alias-action danger delete-alias" data-id="${Number(x.id)}" data-email="${esc(x.email)}" aria-label="Delete address" title="Delete address" data-tooltip="Delete address">${aliasActionIcon('trash')}</button></div></article>`).join(''):'<div class="alias-empty"><div>@</div><strong>No addresses found</strong><span>Create an address or try another search.</span></div>';
 }catch(err){toast(err.message);}
}
sort?.addEventListener('change',reloadAliases);let aliasSearchTimer;aliasSearchEl?.addEventListener('input',()=>{aliasRequestVersion++;clearTimeout(aliasSearchTimer);aliasSearchTimer=setTimeout(reloadAliases,180);});
function closeDelete(){if(!deleteModal||deleteBusy)return;deleteModal.hidden=true;pendingDelete=null;document.body.style.overflow='';deletePreviousFocus?.focus();}
deleteCancel?.addEventListener('click',closeDelete);deleteModal?.addEventListener('click',e=>{if(e.target===deleteModal)closeDelete();});
aliasList?.addEventListener('click',async e=>{
 const copy=e.target.closest('.copy-alias'),remove=e.target.closest('.delete-alias');
 if(copy){try{await navigator.clipboard.writeText(copy.dataset.email);toast('Email address copied');copy.innerHTML=aliasActionIcon('check');copy.classList.toggle('is-copied',true);copy.setAttribute('aria-label','Email address copied');copy.dataset.tooltip='Copied';clearTimeout(copy.copyFeedbackTimer);copy.copyFeedbackTimer=setTimeout(()=>{copy.innerHTML=aliasActionIcon('copy');copy.classList.toggle('is-copied',false);copy.setAttribute('aria-label','Copy email address');copy.dataset.tooltip='Copy address';},1600);}catch(_){toast('Copy is unavailable in this browser.');}}
 if(remove&&deleteModal){pendingDelete={id:remove.dataset.id,email:remove.dataset.email};deletePreviousFocus=remove;$('#aliasDeleteEmail').textContent=pendingDelete.email;if(deleteError)deleteError.hidden=true;deleteModal.hidden=false;document.body.style.overflow='hidden';deleteCancel?.focus();}
});
deleteConfirm?.addEventListener('click',async()=>{
 if(!pendingDelete||deleteBusy)return;deleteBusy=true;deleteConfirm.disabled=true;if(deleteError)deleteError.hidden=true;
 const fd=new FormData();fd.set('_csrf',$('#aliasForm input[name="_csrf"]')?.value||'');fd.set('action','delete');fd.set('id',pendingDelete.id);
 try{const response=await fetch(window.PRIVATEMAIL.aliasesApi,{method:'POST',body:fd,credentials:'same-origin'}),data=await response.json();if(!data.ok)throw new Error(data.error||'Unable to remove this address.');
 const email=pendingDelete.email;document.querySelectorAll('#sidebarAliases [data-email]').forEach(el=>{if(el.dataset.email===email.toLowerCase())el.remove();});deleteBusy=false;closeDelete();await reloadAliases();toast('Address removed. Received mail is unchanged.');}
 catch(err){if(deleteError){deleteError.textContent=err.message;deleteError.hidden=false;}}
 finally{deleteBusy=false;deleteConfirm.disabled=false;}
});

// Shared one-click copy controls for sidebar addresses and reader sender.
document.addEventListener('click',async event=>{
 const button=event.target.closest('[data-copy-email]');if(!button)return;
 try{
  await navigator.clipboard.writeText(button.dataset.copyEmail);
  if(!button.dataset.copyIcon)button.dataset.copyIcon=button.innerHTML;
  if(button.__copyTimer)clearTimeout(button.__copyTimer);
  button.classList.add('is-copied');
  if(button.closest?.('.reader-toolbar'))toast('Copied address!');
  button.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg><span class="copy-tooltip">Copied</span>';
  button.__copyTimer=setTimeout(()=>{button.innerHTML=button.dataset.copyIcon;button.classList.remove('is-copied');},1600);
 }catch(_){toast('Copy is unavailable. Please copy the address manually.');}
});
const messageDeleteModal=$('#messageDeleteModal');
// Address drawer, with keyboard focus restored to its trigger.
const drawer=$('#mailSidebar'),drawerToggle=$('#toggleAddressDrawer'),drawerClose=$('#closeAddressDrawer'),scrim=$('#addressScrim');
let drawerPreviousFocus;
function setAddressDrawer(open){
 if(!drawer||!drawerToggle)return;
 if(open)drawerPreviousFocus=document.activeElement;
 document.body.classList.toggle('addresses-open',open);
 drawerToggle.setAttribute('aria-expanded',String(open));
 if(scrim)scrim.hidden=!open;
 if(open){drawer.setAttribute('role','dialog');drawer.setAttribute('aria-modal','true');drawerClose?.focus();}
 else{drawer.removeAttribute('role');drawer.removeAttribute('aria-modal');drawerPreviousFocus?.focus();}
}
drawerToggle?.addEventListener('click',()=>setAddressDrawer(!document.body.classList.contains('addresses-open')));
drawerClose?.addEventListener('click',()=>setAddressDrawer(false));scrim?.addEventListener('click',()=>setAddressDrawer(false));
function trapFocus(event,container){
 const focusable=[...container.querySelectorAll('a[href],button:not([disabled]),input:not([hidden]),select,[tabindex="0"]')].filter(el=>el.getClientRects().length);
 if(!focusable.length)return;
 const first=focusable[0],last=focusable[focusable.length-1];
 if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}
 else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}
}
document.addEventListener('keydown',event=>{
 if(document.body.classList.contains('addresses-open')){
  if(event.key==='Escape')setAddressDrawer(false);
  if(event.key==='Tab')trapFocus(event,drawer);
 }else if(messageDeleteModal&&!messageDeleteModal.hidden){if(event.key==='Tab')trapFocus(event,messageDeleteModal);}
 else if(deleteModal&&!deleteModal.hidden){if(event.key==='Escape')closeDelete();if(event.key==='Tab')trapFocus(event,deleteModal);}
 else if(modal&&!modal.hidden&&!document.body.classList.contains('alias-create-page')&&event.key==='Tab')trapFocus(event,modal);
});
if(drawerToggle&&typeof matchMedia==='function')matchMedia('(min-width:901px)').addEventListener('change',event=>{if(event.matches)setAddressDrawer(false);});
const routeParams=new URLSearchParams(location.search);
if(routeParams.get('create')==='1'&&modal)openModal();
if(routeParams.get('focus')==='search')search?.focus();
if(routeParams.get('created')==='1')toast('Your new address is ready.');

})();
