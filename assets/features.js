(()=>{
'use strict';
const $=s=>document.querySelector(s),cfg=window.PRIVATEMAIL||{},list=$('#messageList'),search=$('#messageSearch');
function staleView(response){const token=response.headers?.get?.('X-PrivateMail-View');if(cfg.viewToken&&token&&token!==cfg.viewToken){location.reload();return true;}return false;}
const validOtp=value=>/^\d{4,8}$/.test(String(value||''))&&!(String(value).length===4&&Number(value)>=1900&&Number(value)<=2099);
const escape=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const icons={sun:'<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.4 1.4M17.6 17.6 19 19M5 19l1.4-1.4M17.6 6.4 19 5"/>',moon:'<path d="M20.9 13a9 9 0 0 1-9.9-9.9A9 9 0 1 0 20.9 13Z"/>',trash:'<path d="M3 6h18M9 6V4h6v2M5 6l1 14h12l1-14M10 10v6M14 10v6"/>',copy:'<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/>',check:'<path d="m5 12 4 4L19 6"/>'};
const svg=name=>`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${icons[name]}</svg>`;
let toastTimer;
function notify(text,code=''){
 let toast=$('#appToast');if(!toast){toast=document.createElement('div');toast.id='appToast';toast.className='app-toast';toast.setAttribute('role','status');document.body.append(toast);}
 toast.textContent=text;
 if(code){const button=document.createElement('button');button.type='button';button.className='button otp-copy';button.dataset.copyCode=code;button.textContent='Copy '+code;toast.append(' ',button);}
 toast.hidden=false;clearTimeout(toastTimer);toastTimer=setTimeout(()=>toast.hidden=true,code?10000:3000);
}
async function copyCode(button){const code=button.dataset.copyCode;if(!validOtp(code))return;try{await navigator.clipboard.writeText(code);if(!button.dataset.copyLabel)button.dataset.copyLabel=button.innerHTML;button.innerHTML=svg('check')+'<span>Copied</span>';button.classList.add('is-copied');notify('Code copied!');setTimeout(()=>{button.innerHTML=button.dataset.copyLabel;button.classList.remove('is-copied');},1600);}catch(_){notify('Clipboard access is unavailable. Code: '+code);}}
document.addEventListener('click',event=>{
 const otp=event.target.closest('[data-copy-code]');if(otp){event.preventDefault();event.stopImmediatePropagation();copyCode(otp);return;}
 const option=event.target.closest('.setting-dropdown [data-option]');
 if(option){const dropdown=option.closest('.setting-dropdown');dropdown.querySelector('input').value=option.dataset.option;dropdown.querySelector('.dropdown-value').textContent=option.querySelector('span').textContent;dropdown.querySelectorAll('[data-option]').forEach(x=>x.setAttribute('aria-pressed',String(x===option)));dropdown.open=false;dropdown.querySelector('summary').focus();}
 const newAlias=event.target.closest('[data-nav-index="1"]');
 if(newAlias&&location.pathname.endsWith('/aliases.php')&&$('#createAliasButton')){event.preventDefault();event.stopImmediatePropagation();$('#createAliasButton').click();}
 document.querySelectorAll('.setting-dropdown[open]').forEach(dropdown=>{if(!dropdown.contains(event.target))dropdown.open=false;});
},true);
document.addEventListener('keydown',event=>{if(event.key==='Escape')document.querySelectorAll('.setting-dropdown[open]').forEach(x=>x.open=false);});
window.addEventListener('privatemail:alias-state',event=>$('#mobileNavigation')?.dispatchEvent(new CustomEvent('privatemail:nav-select',{detail:{index:event.detail.open?1:2}})));
let trashTarget=0,trashBusy=false;
function openTrash(id){trashTarget=Number(id);const modal=$('#messageDeleteModal');if(!modal)return;modal.hidden=false;const error=$('#messageDeleteError');if(error)error.hidden=true;$('#messageDeleteCancel')?.focus();}
function closeTrash(){if(trashBusy)return;const modal=$('#messageDeleteModal');if(modal)modal.hidden=true;$('#deleteMessageButton')?.focus();}
async function confirmTrash(){
 if(!trashTarget||trashBusy)return;trashBusy=true;const button=$('#messageDeleteConfirm');button.disabled=true;
 try{const fd=new FormData();fd.set('_csrf',cfg.csrf||'');fd.set('id',String(trashTarget));fd.set('action','trash');const response=await fetch(cfg.messageActionsApi,{method:'POST',credentials:'same-origin',body:fd}),data=await response.json();if(!data.ok)throw Error(data.error||'Unable to move message to Trash.');$('#messageDeleteModal').hidden=true;window.dispatchEvent(new CustomEvent('privatemail:deleted',{detail:{id:trashTarget}}));}
 catch(error){const el=$('#messageDeleteError');el.textContent=error.message;el.hidden=false;}
 finally{trashBusy=false;button.disabled=false;}
}
document.addEventListener('click',event=>{
 const open=event.target.closest('#deleteMessageButton'),confirm=event.target.closest('#messageDeleteConfirm'),cancel=event.target.closest('#messageDeleteCancel');
 if(open||confirm||cancel){event.preventDefault();event.stopImmediatePropagation();if(open)openTrash(open.dataset.messageId);else if(confirm)confirmTrash();else closeTrash();}
},true);
document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!$('#messageDeleteModal')?.hidden)closeTrash();});
let activeId=Number(cfg.selectedId||0),mailMode=cfg.emailTheme==='dark'?'dark':'light';
function recipient(message){const raw=String(message.original_to||message.alias_email||message.to||'');return raw.match(/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i)?.[0]||'';}
function applyMailMode(){
 const body=$('.email-body');if(body)body.dataset.emailTheme=mailMode;
 const frame=$('iframe.mime-frame');if(frame)frame.contentWindow?.postMessage({type:'privatemail:theme',channel:frame.dataset.mailChannel,mode:mailMode},'*');
}
window.addEventListener('message',event=>{const frame=$('iframe.mime-frame');if(frame&&event.source===frame.contentWindow&&event.data?.type==='privatemail:resize'&&event.data.channel===frame.dataset.mailChannel)applyMailModeOnce(frame);});
function applyMailModeOnce(frame){if(frame.dataset.appliedMode===mailMode)return;frame.dataset.appliedMode=mailMode;applyMailMode();}
$('iframe.mime-frame')?.addEventListener('load',applyMailMode);applyMailMode();
if(!list)return;
const route=new URLSearchParams(location.search);let messages=cfg.initialMessages||[],pending=null,sequence=0,pollTimer,loadingMessage=0,latestId=Math.max(Number(cfg.latestId)||0,...messages.map(m=>Number(m.id))),statusFlight=null,statusController=null,queuedRefresh=null,threadPending=0,signature='',followLatest=!route.has('message');
let renderedIds=new Set(messages.map(m=>Number(m.id)));
let olderHasMore=true;
const time=value=>{try{return new Intl.DateTimeFormat('en-US',{timeZone:'Asia/Dhaka',hour:'numeric',minute:'2-digit'}).format(new Date(value));}catch(_){return '';}};
function badge(code){return validOtp(code)?`<button type="button" class="otp-copy otp-pill" data-copy-code="${escape(code)}" aria-label="Copy code ${escape(code)}">Code: ${escape(code)}</button>`:'';}
function row(message,arriving=false){
 const params=new URLSearchParams({message:String(message.id)});if(route.has('inbox'))params.set('inbox',route.get('inbox'));else if(route.has('alias'))params.set('alias',route.get('alias'));
 const unread=Number(message.is_read)===0;
 return `<div class="message-row ${arriving?'is-arriving':''}" data-id="${Number(message.id)}" data-read="${unread?'0':'1'}"><div class="swipe-read" aria-hidden="true">${unread?'Mark read':'Mark unread'}</div><button class="swipe-trash" type="button" data-trash-id="${Number(message.id)}" aria-label="Delete message">Delete</button><a class="message-card ${unread?'unread':''} ${Number(message.id)===activeId?'selected':''}" href="/?${escape(params)}" data-search="${escape([message.sender_name,message.sender_email,message.subject,message.preview,message.original_to].join(' ').toLowerCase())}"><div class="message-top"><strong>${escape(message.sender_name||message.sender_email)}</strong><time>${escape(time(message.received_at))}</time></div><div class="message-subject">${escape(message.subject)}</div><div class="message-preview">${escape(message.preview)}</div><div class="message-meta"><span>${escape(message.original_to||message.sender_email)}</span>${!validOtp(message.code)&&message.subcategory&&message.subcategory!=='other'?`<em class="tag">${escape(message.subcategory.replaceAll('_',' '))}</em>`:''}</div></a>${badge(message.code)}</div>`;
}
function render(items){
 const next=JSON.stringify(items.map(m=>[m.id,m.is_read,m.code,m.subject,m.preview,m.original_to,activeId]));if(next===signature)return;signature=next;
 const top=list.scrollTop;list.innerHTML=items.length?items.map(m=>row(m,!renderedIds.has(Number(m.id)))).join(''):'<div class="message-empty"><strong>No messages found</strong><span>New emails will appear here.</span></div>';list.scrollTop=top;renderedIds=new Set(items.map(m=>Number(m.id)));
 const total=$('#messageTotal');if(total)total.textContent=String(items.length);syncOlderButton();
}
function syncOlderButton(){
 if(!Number.isFinite(cfg.messageLimit)||cfg.hasAccounts===false)return;
 let button=$('#loadOlderButton');const allowed=olderHasMore&&(cfg.messageLimit===0||messages.length<cfg.messageLimit);
 if(!allowed){button?.remove();return;}
 if(!button){button=document.createElement('button');button.id='loadOlderButton';button.type='button';button.className='button load-older';button.textContent='Load older messages';button.addEventListener('click',loadOlder);list.append(button);}
}
async function loadOlder(){
 if(isHidden()||pending||statusFlight||threadPending)return;
 const controller=new AbortController(),current=++sequence;pending=controller;const button=$('#loadOlderButton');if(button){button.disabled=true;button.textContent='Loading older mail…';}
 const data=new FormData();data.set('_csrf',cfg.csrf);data.set('offset',String(messages.length));data.set('seen_ids',JSON.stringify(messages.map(message=>Number(message.id))));data.set('q',search?.value||'');for(const key of ['inbox','alias'])if(route.has(key))data.set(key,route.get(key));
 try{
  const response=await fetch('/api/older.php',{method:'POST',body:data,credentials:'same-origin',cache:'no-store',signal:controller.signal}),reply=await response.json();
  if(staleView(response))return;if(current!==sequence||isHidden())return;if(!reply.ok)throw Error(reply.error||'Unable to load older mail.');
  const known=new Set(messages.map(m=>Number(m.id)));const extra=(reply.messages||[]).filter(m=>!known.has(Number(m.id)));
  latestId=Math.max(latestId,Number(reply.latest_id)||0);olderHasMore=reply.has_more!==false;messages=[...messages,...extra].sort((a,b)=>(Date.parse(b.received_at)||0)-(Date.parse(a.received_at)||0)||Number(b.id)-Number(a.id));render(messages);syncOlderButton();
  if(reply.warning)notify(reply.warning);else if(!extra.length&&olderHasMore)notify('Older mail is being indexed. Tap Load older again to continue.');
 }catch(error){if(error.name!=='AbortError')notify(error.message||'Unable to load older mail.');}
 finally{if(pending===controller)pending=null;const button=$('#loadOlderButton');if(button){button.disabled=false;button.textContent='Load older messages';}if(queuedRefresh&&!isHidden()){const next=queuedRefresh;queuedRefresh=null;refresh(next);}}
}
const isHidden=()=>document.hidden===true||document.visibilityState==='hidden';
async function refresh({query=search?.value||'',force=false,background=false}={}){
 if(isHidden())return;
 if(statusFlight){try{await statusFlight;}catch(_){}if(isHidden())return;}
 if(pending){if(!background){queuedRefresh={query,force,background:false};sequence++;}return;}
 const current=++sequence,controller=new AbortController();pending=controller;list.setAttribute('aria-busy','true');
 const params=new URLSearchParams();for(const key of ['inbox','alias'])if(route.has(key))params.set(key,route.get(key));if(query.trim())params.set('q',query.trim());if(force)params.set('refresh','1');
 try{
  const response=await fetch(cfg.api+'?'+params,{credentials:'same-origin',cache:'no-store',signal:controller.signal,headers:{Accept:'application/json'}}),data=await response.json();
  if(staleView(response))return;
  if(current!==sequence||isHidden())return;if(!data.ok)throw new Error(data.error||'Unable to refresh inbox.');
  cfg.csrf=data.csrf||cfg.csrf;let items=data.messages||[];const newArrival=items.some(m=>Number(m.id)>latestId);
  if(background&&messages.length>items.length){const ids=new Set(items.map(m=>Number(m.id)));items=[...items,...messages.filter(m=>!ids.has(Number(m.id)))];if(cfg.messageLimit>0)items=items.slice(0,cfg.messageLimit);}else olderHasMore=true;
  latestId=Math.max(latestId,Number(data.latest_id)||0,...items.map(m=>Number(m.id)));messages=items;render(items);
  document.querySelectorAll('[data-unread-count]').forEach(el=>{el.textContent=String(data.unread_count??items.filter(m=>!Number(m.is_read)).length);});
  const refreshButton=$('#refreshButton');if(refreshButton){refreshButton.dataset.syncState=data.warning?'offline':'online';refreshButton.title=data.warning?'Showing cached inbox':'Refresh inbox';}
  if(newArrival&&followLatest&&(!matchMedia('(max-width:900px)').matches||document.body.classList.contains('is-reading'))&&items[0])await loadMessage(items[0].id,false);
  
  if(data.warning&&force)notify(data.warning);if(current===sequence)list.setAttribute('aria-busy','false');
 }catch(error){
  if(error.name!=='AbortError'){
   if(force)notify(error.message||'Unable to refresh inbox.');
   else{const button=$('#refreshButton');if(button){button.dataset.syncState='offline';button.title='Mailbox temporarily unavailable. Refresh to retry.';}}
  }
 }finally{if(pending===controller){pending=null;list.setAttribute('aria-busy','false');}if(queuedRefresh&&!isHidden()){const next=queuedRefresh;queuedRefresh=null;Promise.resolve().then(()=>refresh(next));}}

}
function updateCounts(count){
 document.querySelectorAll('[data-unread-count]').forEach(el=>el.textContent=String(count));
 
}
async function checkStatus(){
 if(isHidden()||pending||statusFlight||threadPending)return;
 const controller=new AbortController();statusController=controller;
 const flight=(async()=>{
  const response=await fetch(cfg.statusApi||'/api/status.php',{credentials:'same-origin',cache:'no-store',signal:controller.signal,headers:{Accept:'application/json'}});
  if(staleView(response))throw Error('Mailbox selection changed.');
  const data=await response.json();
  if(response.ok===false||!Number.isFinite(data.latest_id)||!Number.isFinite(data.unread_count))throw Error('Invalid mailbox status.');
  return data;
 })();
 statusFlight=flight;let status;
 try{status=await flight;}catch(error){
  if(error.name!=='AbortError'){const button=$('#refreshButton');if(button)button.dataset.syncState='offline';}
 }finally{if(statusFlight===flight){statusFlight=null;statusController=null;}}
 if(!status||isHidden()||controller.signal.aborted)return;
 updateCounts(status.unread_count);
 // Do not download/re-render the list for unchanged IDs or unread-only changes.
 if(status.latest_id>latestId&&!pending)await refresh({background:true});
}
let markingAll=false;
$('#markAllReadButton')?.addEventListener('click',async()=>{
 if(markingAll)return;markingAll=true;const button=$('#markAllReadButton');button.disabled=true;button.setAttribute('aria-busy','true');threadPending++;sequence++;queuedRefresh=null;pending?.abort();statusController?.abort();
 try{const fd=new FormData();fd.set('_csrf',cfg.csrf);const response=await fetch('/api/mark_all_read.php',{method:'POST',credentials:'same-origin',body:fd});const data=await response.json();if(Number.isFinite(data.unread_count))updateCounts(data.unread_count);if(!data.ok)throw Error(data.error||'Unable to update mailboxes.');messages=messages.map(m=>({...m,is_read:1}));render(messages);button.classList.add('is-complete');button.title='All selected mailboxes marked as read';}
 catch(error){button.title=error.message||'Unable to mark all as read. Retry.';await refresh();}
 finally{threadPending--;markingAll=false;button.disabled=false;button.removeAttribute('aria-busy');}
});
window.PrivateMailInbox={refresh,checkStatus};
function ensureReader(){
 if($('.reader-content'))return;
 const reader=$('.reader');reader.innerHTML=`<div class="reader-header"><div class="reader-topline"><a class="reader-back" href="/">‹ Back to inbox</a><div class="reader-toolbar" role="toolbar" aria-label="Message actions"><button class="icon-button" data-copy-email="" aria-label="Copy recipient address">${svg('copy')}</button><button class="icon-button danger-action" id="deleteMessageButton" type="button" aria-label="Delete message">${svg('trash')}</button></div></div><div class="reader-subjectline"><h2></h2></div></div><div class="reader-scroll"><div class="reader-content"></div></div>`;
}
async function loadMessage(id,navigate=true){
 const current=++loadingMessage;threadPending++;
 try{
  if(statusFlight){try{await statusFlight;}catch(_){}}
  const response=await fetch(cfg.messageApi+'?id='+Number(id),{credentials:'same-origin',cache:'no-store'}),data=await response.json();if(staleView(response))return;if(current!==loadingMessage)return;if(!data.ok)throw Error(data.error||'Unable to read this message.');
  const m=data.message;if(Number.isFinite(data.unread_count))updateCounts(data.unread_count);activeId=Number(id);mailMode=cfg.emailTheme==='dark'?'dark':'light';if(navigate)followLatest=false;
  ensureReader();const heading=$('.reader-header h2');if(heading)heading.textContent=m.subject;
  const content=$('.reader-content'),code=m.code;
  content.innerHTML=`<div class="sender-line"><div class="avatar large">${escape(String(m.sender_name||m.sender_email).slice(0,2).toUpperCase())}</div><div><strong>${escape(m.sender_name||m.sender_email)}</strong><span>${escape(m.sender_email)}</span><span>To: ${escape(recipient(m)||m.to)}</span></div><time>${escape(time(m.date))}</time></div>${code?`<div class="code-card otp-banner"><span>Verification code</span><strong>${escape(code)}</strong><button type="button" class="button otp-copy" data-copy-code="${escape(code)}">Copy Code</button></div>`:''}<div class="email-body ${m.html?'mime-email':'plain-email'}"></div>`;
  const body=$('.email-body');if(m.html){const frame=document.createElement('iframe');frame.className='mime-frame';frame.title='Email content';frame.setAttribute('sandbox','allow-scripts allow-popups allow-popups-to-escape-sandbox');frame.setAttribute('scrolling','no');frame.referrerPolicy='no-referrer';frame.dataset.mailChannel=m.frame_token;frame.addEventListener('load',()=>{frame.contentWindow.postMessage({type:'privatemail:measure',channel:m.frame_token},'*');applyMailMode();});frame.srcdoc=m.html;body.append(frame);}else{const pre=document.createElement('pre');pre.textContent=m.text||'No readable content.';body.append(pre);}
  const copy=$('.reader-toolbar [data-copy-email]');if(copy)copy.dataset.copyEmail=recipient(m);
  const remove=$('#deleteMessageButton');if(remove)remove.dataset.messageId=String(id);
  document.body.classList.add('is-reading');$('.reader-scroll').scrollTop=0;
  messages=messages.map(message=>Number(message.id)===activeId?{...message,is_read:1,code:code||message.code}:message);signature='';render(messages);applyMailMode();
  const params=new URLSearchParams(location.search);params.set('message',String(id));history.replaceState(null,'','/?'+params);
 }catch(error){notify(error.message);}finally{threadPending--;}
}
list.addEventListener('click',event=>{
 const trash=event.target.closest('[data-trash-id]');if(trash){event.preventDefault();const button=$('#deleteMessageButton');openTrash(trash.dataset.trashId);return;}
 const card=event.target.closest('.message-card');if(!card||event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;event.preventDefault();loadMessage(Number(card.closest('.message-row').dataset.id));
});
window.addEventListener('privatemail:deleted',async event=>{if(Number(event.detail.id)===activeId){activeId=0;followLatest=true;}await refresh({force:true});if(!activeId&&messages.length)loadMessage(messages[0].id,false);else if(!messages.length){ensureReader();$('.reader-content').innerHTML='<div class="reader-empty">No messages</div>';document.body.classList.remove('is-reading');}});
async function toggleRead(row){const id=Number(row.dataset.id),wasRead=row.dataset.read==='1';try{const fd=new FormData();fd.set('_csrf',cfg.csrf);fd.set('id',String(id));fd.set('action',wasRead?'unread':'read');const data=await (await fetch(cfg.messageActionsApi,{method:'POST',credentials:'same-origin',body:fd})).json();if(!data.ok)throw Error(data.error||'Unable to update message.');messages=messages.map(m=>Number(m.id)===id?{...m,is_read:data.is_read}:m);signature='';render(messages);if(Number.isFinite(data.unread_count))updateCounts(data.unread_count);notify(wasRead?'Marked unread':'Marked read');}catch(error){notify(error.message);}}
let gesture=null,exposed=null;
list.addEventListener('pointerdown',event=>{if(!matchMedia('(max-width:900px)').matches||event.pointerType==='mouse'||event.target.closest('button'))return;const row=event.target.closest('.message-row');if(row)gesture={row,x:event.clientX,y:event.clientY,dx:0,locked:false};},{passive:true});
list.addEventListener('pointermove',event=>{if(!gesture)return;const dx=event.clientX-gesture.x,dy=event.clientY-gesture.y;if(!gesture.locked&&Math.abs(dy)>12){gesture=null;return;}if(Math.abs(dx)>14&&Math.abs(dx)>Math.abs(dy)*1.5)gesture.locked=true;if(!gesture.locked)return;gesture.dx=Math.max(-90,Math.min(90,dx));gesture.row.classList.add('is-swiping');gesture.row.querySelector('.message-card').style.transform=`translateX(${gesture.dx}px)`;},{passive:true});
list.addEventListener('pointerup',()=>{if(!gesture)return;const {row,dx,locked}=gesture;gesture=null;row.classList.remove('is-swiping');if(!locked)return;row.dataset.suppressClick='1';setTimeout(()=>delete row.dataset.suppressClick,350);if(dx<-48){if(exposed&&exposed!==row){exposed.querySelector('.message-card').style.transform='';exposed.classList.remove('is-revealed');}row.querySelector('.message-card').style.transform='translateX(-78px)';row.classList.add('is-revealed');exposed=row;}else{row.querySelector('.message-card').style.transform='';row.classList.remove('is-revealed');if(dx>60)toggleRead(row);}});
list.addEventListener('pointercancel',()=>{if(gesture){gesture.row.querySelector('.message-card').style.transform='';gesture.row.classList.remove('is-swiping');}gesture=null;});
list.addEventListener('click',event=>{if(event.target.closest('.message-row')?.dataset.suppressClick){event.preventDefault();event.stopImmediatePropagation();}},true);
const pull=document.createElement('div');pull.className='pull-refresh';pull.setAttribute('role','status');pull.innerHTML='<span class="pull-spinner"></span><span>Pull to refresh</span>';list.before(pull);let pullStart=null,pullDistance=0,pullBusy=false;
list.addEventListener('touchstart',event=>{if(!pullBusy&&list.scrollTop<=0&&event.touches.length===1&&matchMedia('(max-width:900px)').matches)pullStart={x:event.touches[0].clientX,y:event.touches[0].clientY};},{passive:true});
list.addEventListener('touchmove',event=>{if(!pullStart)return;const dy=event.touches[0].clientY-pullStart.y,dx=event.touches[0].clientX-pullStart.x;if(dy<0||Math.abs(dx)>dy){pullStart=null;return;}if(dy>12){event.preventDefault();pullDistance=Math.min(72,dy*.45);pull.style.height=pullDistance+'px';pull.classList.toggle('is-ready',pullDistance>=48);}},{passive:false});
async function finishPull(){pullStart=null;if(pullDistance>=48&&!pullBusy){pullBusy=true;pull.classList.add('is-loading');await refresh({force:true});pullBusy=false;pull.classList.remove('is-loading');}pullDistance=0;pull.style.height='0';pull.classList.remove('is-ready');}
list.addEventListener('touchend',finishPull);list.addEventListener('touchcancel',()=>{pullStart=null;pullDistance=0;pull.style.height='0';});
function resetRefreshClock(interval){const clock=$('#refreshClock');if(!clock)return;const enabled=interval>=10&&!isHidden();clock.classList.toggle('is-running',enabled);clock.style.setProperty('--refresh-seconds',interval+'s');clock.setAttribute('aria-label',enabled?'Auto refresh every '+interval+' seconds':'Auto refresh paused or off');const arc=clock.querySelector('.clock-progress');if(arc){arc.style.animation='none';void arc.getBoundingClientRect();arc.style.animation='';}}
function schedule(){clearTimeout(pollTimer);resetRefreshClock(Number(cfg.refreshInterval||0));const interval=Number(cfg.refreshInterval||0);if(interval>=10&&!isHidden())pollTimer=setTimeout(async()=>{await checkStatus();schedule();},interval*1000);}
document.addEventListener('visibilitychange',()=>{if(isHidden()){resetRefreshClock(0);clearTimeout(pollTimer);queuedRefresh=null;sequence++;statusController?.abort();pending?.abort();}else{checkStatus().finally(schedule);}});
render(messages);schedule();
})();
