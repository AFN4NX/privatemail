(()=>{
 const touchMedia=typeof matchMedia==='function'?matchMedia('(hover: none), (pointer: coarse)'):null;let keyboardIntent=false;
 document.addEventListener?.('keydown',()=>{keyboardIntent=true;},true);document.addEventListener?.('pointerdown',()=>{keyboardIntent=false;},true);
 const defaults={neutral:'Please review this action.',danger:'Please double-check before continuing.',happy:'Safe choice. Nothing will be removed.',positive:'Looks good. Ready when you are.',private:'I’ll look away while you type.'};
 const initialize=box=>{
  if(box.dataset.robotReady)return;box.dataset.robotReady='1';
  if(!box.hasAttribute('data-confirm-existing')){
   const banner=document.createElement('div');banner.className='confirmation-companion';banner.innerHTML='<svg class="confirmation-robot" viewBox="0 0 152 132" fill="none" aria-hidden="true"><path class="cr-antenna" d="M76 5V1"/><circle class="cr-beacon" cx="76" cy="3" r="3"/><rect class="cr-outline cr-ear" x="1" y="48" width="11" height="35" rx="5"/><rect class="cr-outline cr-ear" x="140" y="48" width="11" height="35" rx="5"/><rect class="cr-outline cr-head" x="8" y="8" width="136" height="120" rx="31"/><rect class="cr-screen" x="24" y="28" width="104" height="82" rx="20"/><g class="cr-front"><rect class="cr-eye cr-eye-left" x="48" y="49" width="15" height="23" rx="8"/><rect class="cr-eye cr-eye-right" x="89" y="49" width="15" height="23" rx="8"/><path class="cr-happy-eyes" d="M47 59q9-14 17 0m24 0q8-14 17 0"/><path class="cr-mouth cr-neutral-mouth" d="M70 87h12"/><path class="cr-mouth cr-happy-mouth" d="M66 81q10 16 20 0"/><path class="cr-mouth cr-sad-mouth" d="M66 89q10-14 20 0"/></g><g class="cr-private"><circle cx="66" cy="47" r="3"/><circle cx="76" cy="47" r="3"/><circle cx="86" cy="47" r="3"/><path d="M57 63h38M57 70h38"/><text x="76" y="91" text-anchor="middle">NOT LOOKING</text></g><circle class="cr-screw" cx="76" cy="119" r="2"/></svg><p data-robot-status aria-live="polite"></p>';box.prepend(banner);
  }
  const status=box.querySelector('[data-robot-status]');let touchChoice=null;const detailsParent=box.closest('details');
  const mobileDefault=()=>touchMedia?.matches&&box.querySelector('[data-confirm-danger]')?'danger':'neutral';
  const moodFor=node=>{
   if(!node||!box.contains(node)||typeof node.closest!=='function')return 'neutral';
   if(node.closest('input[type="password"]'))return 'private';
   if(node.closest('[data-confirm-danger]'))return 'danger';
   if(node.closest('[data-confirm-cancel]'))return 'happy';
   if(node.closest('[data-confirm-happy]'))return 'positive';return 'neutral';
  };
  const set=mood=>{if(box.dataset.robotMood===mood)return;box.dataset.robotMood=mood;if(status)status.textContent=mood==='neutral'?(box.dataset.confirmNeutral||defaults.neutral):defaults[mood];};
  const focusedMood=node=>{const mood=moodFor(node);if(touchMedia?.matches&&!keyboardIntent&&(mood==='happy'||mood==='positive')&&touchChoice!==mood)return mobileDefault();return mood==='neutral'?(touchChoice||mobileDefault()):mood;};
  const pointerMood=node=>{const mood=moodFor(node);return mood==='neutral'?focusedMood(document.activeElement):mood;};
  box.addEventListener('pointerover',e=>set(pointerMood(e.target)));box.addEventListener('pointerdown',e=>{const mood=moodFor(e.target);if(touchMedia?.matches&&mood!=='neutral')touchChoice=mood;set(pointerMood(e.target));});box.addEventListener('focusin',e=>set(focusedMood(e.target)));
  box.addEventListener('pointerout',e=>set(pointerMood(e.relatedTarget)));box.addEventListener('focusout',e=>set(focusedMood(e.relatedTarget)));
  box.addEventListener('click',e=>{const close=e.target.closest('[data-confirm-close]');if(close&&box.contains(close)){const details=box.closest('details');if(details)details.open=false;touchChoice='happy';set('happy');}});
  const reset=()=>{touchChoice=null;set(box.hidden||(detailsParent&&!detailsParent.open)?'neutral':focusedMood(document.activeElement));};reset();touchMedia?.addEventListener?.('change',reset);
  if(typeof MutationObserver!=='undefined'){new MutationObserver(reset).observe(box,{attributes:true,attributeFilter:['hidden']});const details=box.closest('details');if(details)new MutationObserver(reset).observe(details,{attributes:true,attributeFilter:['open']});}
 };
 document.querySelectorAll('[data-confirmation]').forEach(box=>{const details=box.closest('details');if(details&&!details.open){details.addEventListener('toggle',()=>{if(details.open)initialize(box);});}else initialize(box);});
})();
