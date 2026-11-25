(()=>{
 const get=id=>document.getElementById(id),form=get('loginForm')||get('logoutForm'),input=get('loginPassword'),reveal=get('loginReveal'),button=get('loginSubmit')||get('logoutSubmit'),label=get('loginSubmitLabel')||get('logoutSubmitLabel'),caps=get('loginCaps'),robot=get('authRobot'),speech=get('robotSpeech');if(!form||!button)return;
 const loggingOut=!input,initialSpeech=speech?.textContent||'',defaultLabel=loggingOut?'Sign out':'Open PrivateMail';let focused=false;
 const setSpeech=text=>{if(speech&&speech.textContent!==text)speech.textContent=text;};
 const privacy=()=>{if(!robot||!input)return;const privateNow=focused||input.value.length>0||input.type==='text';robot.classList.toggle('is-private',privateNow);robot.style?.setProperty('--head-lean','0deg');setSpeech(privateNow?'A secret? Say no more. I’m turning around.':initialSpeech);};
 if(input&&reveal){
  const eye='<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',hiddenEye='<path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A12 12 0 0 1 12 5c7 0 10 7 10 7a17 17 0 0 1-3.1 4.1M6.5 6.5A18 18 0 0 0 2 12s3 7 10 7a13 13 0 0 0 5.5-1.3"/>';
  reveal.hidden=false;reveal.addEventListener('click',()=>{const show=input.type==='password';input.type=show?'text':'password';reveal.setAttribute('aria-pressed',String(show));reveal.setAttribute('aria-label',show?'Hide password':'Show password');reveal.querySelector('svg').innerHTML=show?hiddenEye:eye;input.focus();focused=true;privacy();});
  input.addEventListener('focus',()=>{focused=true;privacy();});input.addEventListener('input',privacy);input.addEventListener('change',privacy);
  const detect=event=>{if(caps)caps.hidden=!event.getModifierState?.('CapsLock');privacy();};input.addEventListener('keydown',detect);input.addEventListener('keyup',detect);input.addEventListener('blur',()=>{focused=false;if(caps)caps.hidden=true;privacy();});
 }
 if(robot){
  document.addEventListener('pointermove',event=>{if(event.pointerType==='touch'||robot.classList.contains('is-private')||window.matchMedia?.('(prefers-reduced-motion: reduce)').matches)return;const rect=robot.getBoundingClientRect();const x=Math.max(-1,Math.min(1,(event.clientX-rect.left-rect.width/2)/220)),y=Math.max(-1,Math.min(1,(event.clientY-rect.top-rect.height/2)/220));robot.style.setProperty('--gaze-x',(x*4).toFixed(2)+'px');robot.style.setProperty('--gaze-y',(y*3).toFixed(2)+'px');robot.style.setProperty('--head-lean',(x*3).toFixed(2)+'deg');});
  if(loggingOut){button.addEventListener('pointerenter',()=>{robot.classList.add('is-waving');setSpeech('See you soon. Your inbox will be right here.');});button.addEventListener('pointerleave',()=>{robot.classList.remove('is-waving');setSpeech(initialSpeech);});}
 }
 form.addEventListener('submit',event=>{if(form.dataset.submitting==='1'){event.preventDefault();return;}form.dataset.submitting='1';form.setAttribute('aria-busy','true');button.disabled=true;button.classList.add('is-loading');if(label)label.textContent=loggingOut?'Signing out…':'Opening your inbox…';setSpeech(loggingOut?'Until next time.':'One moment. Opening your private inbox.');});
 window.addEventListener('pageshow',()=>{delete form.dataset.submitting;form.removeAttribute('aria-busy');button.disabled=false;button.classList.remove('is-loading');if(label)label.textContent=defaultLabel;if(loggingOut)setSpeech(initialSpeech);privacy();});privacy();
})();
