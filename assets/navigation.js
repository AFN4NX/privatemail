(()=>{
'use strict';
const nav=document.getElementById('mobileNavigation');
if(!nav)return;
const tabs=[...nav.querySelectorAll('[data-nav-index]')],surface=nav.querySelector('.magic-surface'),path=nav.querySelector('.magic-surface-path'),items=nav.querySelector('.magic-nav-items'),indicator=nav.querySelector('.magic-indicator');
const mobile=matchMedia('(max-width:900px)'),reduced=matchMedia('(prefers-reduced-motion:reduce)');
const duration=450,key='privatemail:nav-transition';
let active=Number(nav.dataset.activeIndex)||0,currentX=0,animation=0,resizeFrame=0;
// Keep the indicator and curved cut-out on precisely the same easing curve.
function spring(t){
 const curve=(u,a,b)=>3*(1-u)*(1-u)*u*a+3*(1-u)*u*u*b+u*u*u;
 let lo=0,hi=1,u=t;
 for(let i=0;i<15;i++){u=(lo+hi)/2;if(curve(u,.34,.64)<t)lo=u;else hi=u;}
 return curve(u,1.56,1);
}
function center(index,width){return 3+(width-6)*(index+.5)/tabs.length;}
function shape(width,x){
 const w=Math.max(1,width),bottomR=20,s=Math.min(36,w/8-3),d=32;
 const topR=Math.max(1,Math.min(24,x-s-1,w-x-s-1));
 return `M${topR} 1 H${x-s} C${x-s*.7} 1 ${x-s*.74} ${d} ${x} ${d} C${x+s*.74} ${d} ${x+s*.7} 1 ${x+s} 1 H${w-topR} Q${w-1} 1 ${w-1} ${topR} V${56-bottomR} Q${w-1} 55 ${w-bottomR} 55 H${bottomR} Q1 55 1 ${56-bottomR} V${topR} Q1 1 ${topR} 1 Z`;
}
function draw(x){
 const width=nav.getBoundingClientRect().width;if(!width)return;
 const shoulder=Math.min(36,width/8-3);
 x=Math.max(shoulder+2,Math.min(width-shoulder-2,x));currentX=x;const d=shape(width,x);
 surface.setAttribute('viewBox',`0 0 ${width} 56`);path.setAttribute('d',d);
 indicator.style.setProperty('--indicator-x',(x-22)+'px');nav.classList.add('is-positioned');
 if(typeof CSS!=='undefined'&&CSS.supports('clip-path','path("M0 0 H1 V1 Z")')){items.style.clipPath=`path("${d}")`;nav.classList.add('has-clipped-hit-area');}
}
function stop(){if(animation)cancelAnimationFrame(animation);animation=0;nav.classList.remove('is-animating');}
function position(index,animate=false){
 if(!mobile.matches)return;
 const width=nav.getBoundingClientRect().width;if(!width)return;
 stop();const target=center(index,width),start=currentX||target;
 if(!animate||reduced.matches||Math.abs(target-start)<1){nav.classList.add('is-positioning');draw(target);requestAnimationFrame(()=>nav.classList.remove('is-positioning'));return;}
 nav.classList.add('is-animating');let began;
 function tick(time){if(began===undefined)began=time;const t=Math.min(1,(time-began)/duration);draw(start+(target-start)*spring(t));if(t<1)animation=requestAnimationFrame(tick);else{draw(target);animation=0;nav.classList.remove('is-animating');}}
 animation=requestAnimationFrame(tick);
}
function restore(){
 let previous=null;
 try{const value=sessionStorage.getItem(key);sessionStorage.removeItem(key);if(value){const data=JSON.parse(value);if(data.to===active&&Date.now()-data.at>=0&&Date.now()-data.at<10000&&Number.isInteger(data.from)&&data.from>=0&&data.from<tabs.length)previous=data.from;}}catch(_){}
 if(previous!==null&&previous!==active&&!reduced.matches&&mobile.matches){position(previous);requestAnimationFrame(()=>position(active,true));}else position(active);
}
indicator.addEventListener('click',()=>tabs[active].click());
tabs.forEach((tab,index)=>{
 tab.addEventListener('click',event=>{
  if(event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey||event.defaultPrevented)return;
  if(index===active){
   const target=new URL(tab.href,location.href),here=new URL(location.href);
   if(target.pathname===here.pathname&&target.search===here.search){event.preventDefault();position(active);}
   return;
  }
  // Native navigation stays immediate. The next page animates from this position.
  try{sessionStorage.setItem(key,JSON.stringify({from:active,to:index,at:Date.now()}));}catch(_){}
 });
});
function resize(){if(resizeFrame)cancelAnimationFrame(resizeFrame);resizeFrame=requestAnimationFrame(()=>{resizeFrame=0;position(active);});}
let measuredWidth=nav.getBoundingClientRect().width;
if(typeof ResizeObserver!=='undefined')new ResizeObserver(()=>{const width=nav.getBoundingClientRect().width;if(Math.abs(width-measuredWidth)<.5)return;measuredWidth=width;resize();}).observe(nav);
else addEventListener('resize',resize);
mobile.addEventListener('change',()=>{stop();resize();});reduced.addEventListener('change',()=>{stop();position(active);});
addEventListener('pageshow',event=>{if(event.persisted){stop();position(active);}});
addEventListener('pagehide',()=>{stop();if(resizeFrame)cancelAnimationFrame(resizeFrame);});
nav.addEventListener('privatemail:nav-select',event=>{const index=Number(event.detail?.index);if(!Number.isInteger(index)||index<0||index>=tabs.length)return;active=index;nav.dataset.activeIndex=String(index);tabs.forEach((tab,i)=>{tab.classList.toggle('is-active',i===index);if(i===index)tab.setAttribute('aria-current','page');else tab.removeAttribute('aria-current');});indicator.innerHTML=tabs[index].querySelector('svg').outerHTML;position(index,true);});
restore();
})();
