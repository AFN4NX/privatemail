(()=>{
 document.querySelectorAll('[data-custom-choice]').forEach(group=>{
  const input=group.querySelector('input[type="number"]'),field=group.querySelector('.custom-number-field');
  const apply=()=>{const custom=group.querySelector('input[type="radio"]:checked')?.value==='custom';field.hidden=!custom;input.disabled=!custom;input.required=custom;};
  group.querySelectorAll('input[type="radio"]').forEach(radio=>radio.addEventListener('change',apply));apply();
 });
 document.querySelectorAll('[data-account-select]').forEach(button=>button.addEventListener('click',()=>{const form=button.closest('form');form.querySelectorAll('input[name="accounts[]"]').forEach(input=>input.checked=button.dataset.accountSelect==='all');}));
 const form=document.querySelector('#appearanceForm');if(!form)return;
 const editor=document.querySelector('#customThemeEditor'),preview=document.querySelector('#themePreview'),warning=document.querySelector('#contrastWarning'),name=document.querySelector('#themeName');
 const colors=[...form.querySelectorAll('[data-theme-color]')];
 const defaults={name:'My theme',accent:'#A3E635',background:'#09090B',surface:'#121215',text:'#FAFAFA'};
 const presets={lime:{...defaults,name:'Lime'},violet:{...defaults,name:'Violet',accent:'#C4B5FD'},ocean:{...defaults,name:'Ocean Blue',accent:'#38BDF8'},rose:{...defaults,name:'Rose',accent:'#FDA4AF'}};
 function lum(hex){const c=hex.slice(1).match(/../g).map(v=>parseInt(v,16)/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4);return .2126*c[0]+.7152*c[1]+.0722*c[2];}
 function contrast(a,b){const x=lum(a),y=lum(b);return(Math.max(x,y)+.05)/(Math.min(x,y)+.05);}
 function update(){const t=Object.fromEntries(colors.map(input=>[input.dataset.themeColor,input.value]));preview.style.setProperty('--preview-bg',t.background);preview.style.setProperty('--preview-surface',t.surface);preview.style.setProperty('--preview-text',t.text);preview.style.setProperty('--preview-accent',t.accent);warning.hidden=Math.min(contrast(t.text,t.background),contrast(t.text,t.surface),contrast(t.accent,t.surface))>=4.5;editor.hidden=form.querySelector('input[name="theme"]:checked')?.value!=='custom';name.required=!editor.hidden;}
 function apply(t){colors.forEach(input=>input.value=t[input.dataset.themeColor]);name.value=t.name;update();}
 colors.forEach(input=>input.addEventListener('input',update));form.querySelectorAll('[name="theme"]').forEach(input=>input.addEventListener('change',update));
 form.querySelectorAll('[data-theme-preset]').forEach(button=>button.addEventListener('click',()=>apply(presets[button.dataset.themePreset])));
 document.querySelector('#resetCustomTheme')?.addEventListener('click',()=>apply(defaults));update();
 const imap=document.querySelector('#imapSettingsForm');let busy=false;
 imap?.addEventListener('submit',async event=>{
  if(busy){event.preventDefault();return;}
  if(event.submitter?.value!=='test')return;
  event.preventDefault();busy=true;const result=document.querySelector('#imapTestResult');const data=new FormData(imap);data.set('intent','test');
  result.hidden=false;result.textContent='Testing connection…';imap.setAttribute('aria-busy','true');
  const buttons=[...imap.querySelectorAll('button')];buttons.forEach(button=>button.disabled=true);
  try{const response=await fetch('/settings.php',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},body:data});const reply=await response.json();result.textContent=reply.message||'Unable to test connection.';result.style.color=reply.ok?'var(--soft-text)':'var(--danger)';}catch(_){result.textContent='Unable to test. Check your connection and try again.';result.style.color='var(--danger)';}
  finally{busy=false;imap.removeAttribute('aria-busy');buttons.forEach(button=>button.disabled=false);}
 });
})();
