(()=>{
'use strict';

const $=(s,r=document)=>r.querySelector(s);
const $$=(s,r=document)=>Array.from(r.querySelectorAll(s));
const storage={get(k){try{return localStorage.getItem(k)}catch{return null}},set(k,v){try{localStorage.setItem(k,v)}catch{}}};
const reduced=()=>window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const raf=fn=>{let busy=false;return(...args)=>{if(busy)return;busy=true;requestAnimationFrame(()=>{busy=false;fn(...args)})}};
const debounce=(fn,wait=160)=>{let t;return(...args)=>{clearTimeout(t);t=setTimeout(()=>fn(...args),wait)}};

const Theme={
 init(){
  const media=matchMedia('(prefers-color-scheme: dark)'),saved=storage.get('soon_theme');
  this.apply(saved||(media.matches?'dark':'light'),false);
  $$('[data-theme-toggle]').forEach(b=>b.addEventListener('click',()=>this.toggle()));
  media.addEventListener?.('change',e=>{if(!storage.get('soon_theme'))this.apply(e.matches?'dark':'light',false)});
 },
 apply(theme,persist=true){
  theme=theme==='dark'?'dark':'light';
  document.documentElement.dataset.theme=theme;
  document.documentElement.style.colorScheme=theme;
  if(persist)storage.set('soon_theme',theme);
  $$('[data-theme-toggle]').forEach(b=>{
   b.setAttribute('aria-pressed',String(theme==='dark'));
   b.setAttribute('aria-label',theme==='dark'?'Yorug‘ rejimga o‘tish':'Tungi rejimga o‘tish');
   b.setAttribute('title',theme==='dark'?'Yorug‘ rejim':'Tungi rejim');
  });
 },
 toggle(){this.apply(document.documentElement.dataset.theme==='dark'?'light':'dark')}
};

const Reveal={
 init(){
  const items=$$('.scroll-reveal');if(!items.length)return;
  if(reduced()||!('IntersectionObserver'in window)){items.forEach(x=>x.classList.add('revealed'));return}
  const io=new IntersectionObserver(es=>es.forEach(e=>{
   if(e.isIntersecting){e.target.classList.add('revealed');io.unobserve(e.target)}
  }),{threshold:.06,rootMargin:'0px 0px -50px'});
  items.forEach((x,i)=>{x.style.setProperty('--reveal-delay',`${Math.min(i%8,7)*55}ms`);io.observe(x)});
 }
};

const Nav={
 init(){
  const toggle=$('[data-mobile-nav-toggle]'),menu=$('[data-mobile-nav-menu]'),overlay=$('[data-mobile-nav-overlay]');if(!toggle||!menu)return;
  let previous=null;
  const close=()=>{
   menu.classList.remove('active');overlay?.classList.remove('active');toggle.setAttribute('aria-expanded','false');document.body.classList.remove('nav-open');
   previous?.focus?.({preventScroll:true});previous=null;
  };
  const open=()=>{
   previous=document.activeElement;menu.classList.add('active');overlay?.classList.add('active');toggle.setAttribute('aria-expanded','true');document.body.classList.add('nav-open');
   $('a,button,input,select,textarea',menu)?.focus({preventScroll:true});
  };
  toggle.setAttribute('aria-expanded','false');
  toggle.addEventListener('click',()=>menu.classList.contains('active')?close():open());
  overlay?.addEventListener('click',close);
  $$('[data-mobile-nav-close],a',menu).forEach(x=>x.addEventListener('click',close));
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&menu.classList.contains('active'))close()});
  addEventListener('resize',()=>{if(innerWidth>768)close()});
 }
};

const Scroll={
 init(){
  document.addEventListener('click',e=>{
   const a=e.target.closest('a[href^="#"]');if(!a)return;
   const id=a.getAttribute('href');if(!id||id==='#')return;
   const target=document.getElementById(id.slice(1));if(!target)return;
   e.preventDefault();target.scrollIntoView({behavior:reduced()?'auto':'smooth',block:'start'});history.replaceState(null,'',id);
  });
 }
};

const Forms={
 init(){
  $$('form[data-validate]').forEach(f=>f.addEventListener('submit',e=>{
   if(!this.validate(f)){e.preventDefault();$('.error',f)?.focus()}
   else{f.classList.add('is-submitting');$$('button[type="submit"],input[type="submit"]',f).forEach(b=>{b.disabled=true;b.setAttribute('aria-busy','true');b.dataset.originalText=b.textContent;b.textContent=b.dataset.loadingText||'Yuklanmoqda…'})}
  }));
  $$('input,textarea,select').forEach(i=>{
   i.addEventListener('input',()=>{if(i.classList.contains('error'))this.check(i)});
   i.addEventListener('blur',()=>{if(i.required||i.value)this.check(i)});
  });
 },
 check(i){
  const v=String(i.value||'').trim();let ok=true;
  if(i.required&&!v)ok=false;
  if(i.type==='email'&&v&&!/^\S+@\S+\.\S+$/.test(v))ok=false;
  if(i.type==='tel'&&v&&!/^\+998\d{9}$/.test(v.replace(/[^\d+]/g,'')))ok=false;
  if(i.type==='url'&&v){try{const u=new URL(v);ok=['http:','https:'].includes(u.protocol)}catch{ok=false}}
  i.classList.toggle('error',!ok);i.setAttribute('aria-invalid',String(!ok));return ok;
 },
 validate(f){return $$('input,textarea,select',f).every(i=>this.check(i))}
};

const Motion={
 init(){
  if(reduced())return;
  document.addEventListener('pointermove',e=>{
   if(innerWidth<820)return;const el=e.target.closest('[data-tilt]');if(!el)return;
   const r=el.getBoundingClientRect();if(!r.width||!r.height)return;
   const x=(e.clientX-r.left)/r.width-.5,y=(e.clientY-r.top)/r.height-.5;
   el.style.setProperty('--mx',`${(x*100).toFixed(1)}%`);el.style.setProperty('--my',`${(y*100).toFixed(1)}%`);
   el.style.transform=`perspective(1000px) rotateX(${(-y*3).toFixed(2)}deg) rotateY(${(x*3).toFixed(2)}deg) translateY(-5px)`;
  },{passive:true});
  document.addEventListener('pointerout',e=>{const el=e.target.closest('[data-tilt]');if(el&&!el.contains(e.relatedTarget))el.style.transform=''});
 }
};

const Lazy={
 init(){
  const items=$$('[data-src],[data-srcset]');if(!items.length)return;
  const load=el=>{if(el.dataset.src){el.src=el.dataset.src;el.removeAttribute('data-src')}if(el.dataset.srcset){el.srcset=el.dataset.srcset;el.removeAttribute('data-srcset')}el.classList.add('lazy-loaded')};
  if(!('IntersectionObserver'in window)){items.forEach(load);return}
  const io=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting){load(e.target);io.unobserve(e.target)}}),{rootMargin:'300px'});items.forEach(x=>io.observe(x));
 }
};

const Header={
 init(){
  const h=$('[data-header]');if(!h)return;let last=scrollY;
  const update=raf(()=>{const y=scrollY;h.classList.toggle('scrolled',y>18);h.classList.toggle('scrolling-down',y>last&&y>100);h.classList.toggle('scrolling-up',y<last||y<=100);last=y});
  addEventListener('scroll',update,{passive:true});update();
 }
};

const Parallax={
 init(){
  if(reduced())return;const items=$$('[data-parallax]');if(!items.length)return;
  const update=raf(()=>{if(innerWidth<768)return;items.forEach(el=>{const r=el.getBoundingClientRect();if(r.bottom<0||r.top>innerHeight)return;const speed=parseFloat(el.dataset.parallax)||.06;el.style.setProperty('--parallax-y',`${((innerHeight/2-(r.top+r.height/2))*speed).toFixed(2)}px`)})});
  addEventListener('scroll',update,{passive:true});addEventListener('resize',update);update();
 }
};

const Ripple={
 init(){
  if(reduced())return;
  document.addEventListener('pointerdown',e=>{const b=e.target.closest('.btn,[data-ripple]');if(!b||b.disabled)return;const r=b.getBoundingClientRect(),size=Math.max(r.width,r.height)*1.6,s=document.createElement('span');s.className='ripple';s.style.width=s.style.height=`${size}px`;s.style.left=`${e.clientX-r.left-size/2}px`;s.style.top=`${e.clientY-r.top-size/2}px`;b.appendChild(s);setTimeout(()=>s.remove(),650)});
 }
};

const Page={
 init(){
  document.body.classList.add('page-loaded');if(reduced())return;
  document.addEventListener('click',e=>{const a=e.target.closest('a[href]');if(!a||a.target==='_blank'||a.hasAttribute('download')||e.metaKey||e.ctrlKey||e.shiftKey||e.altKey)return;const href=a.getAttribute('href')||'';if(!href||href[0]==='#'||href.startsWith('mailto:')||href.startsWith('tel:')||href.startsWith('javascript:')||/^[a-z][a-z0-9+.-]*:\/\//i.test(href))return;document.body.classList.add('page-leaving');setTimeout(()=>document.body.classList.remove('page-leaving'),420)});
 }
};

const ActiveNav={
 init(){
  const links=$$('a[href^="#"]');if(!links.length)return;
  const map=new Map();links.forEach(a=>{const id=a.getAttribute('href')?.slice(1),target=id&&document.getElementById(id);if(target)map.set(target,a)});if(!map.size)return;
  const set=(target)=>{links.forEach(a=>a.classList.remove('active'));const a=map.get(target);if(a){a.classList.add('active');a.setAttribute('aria-current','location')}links.filter(a=>a!==map.get(target)).forEach(a=>a.removeAttribute('aria-current'))};
  if(!('IntersectionObserver'in window)){return}
  const io=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting)set(e.target)}),{rootMargin:'-35% 0px -55% 0px',threshold:0});map.forEach((_,section)=>io.observe(section));
 }
};

const Progress={
 init(){
  let bar=$('[data-scroll-progress]');
  if(!bar){bar=document.createElement('div');bar.setAttribute('data-scroll-progress','');bar.setAttribute('aria-hidden','true');document.body.appendChild(bar)}
  const update=raf(()=>{const max=document.documentElement.scrollHeight-innerHeight,p=max>0?Math.min(100,Math.max(0,scrollY/max*100)):0;bar.style.setProperty('--progress',`${p}%`)});
  addEventListener('scroll',update,{passive:true});addEventListener('resize',update);update();
 }
};

const BackTop={
 init(){
  const buttons=$$('[data-back-to-top]');if(!buttons.length)return;
  const update=raf(()=>buttons.forEach(b=>b.classList.toggle('visible',scrollY>500)));
  addEventListener('scroll',update,{passive:true});update();
  buttons.forEach(b=>b.addEventListener('click',()=>scrollTo({top:0,behavior:reduced()?'auto':'smooth'})));
 }
};

const Copy={
 init(){
  document.addEventListener('click',async e=>{
   const b=e.target.closest('[data-copy]');if(!b)return;const selector=b.dataset.copy,target=selector?$(selector):null,text=b.dataset.copyText||target?.textContent||'';if(!text)return;
   try{await navigator.clipboard.writeText(text.trim());b.classList.add('copied');const old=b.dataset.originalLabel||b.textContent;b.dataset.originalLabel=old;b.textContent=b.dataset.copiedLabel||'Nusxalandi';setTimeout(()=>{b.classList.remove('copied');b.textContent=old},1400)}catch{Toast.show('Nusxalash amalga oshmadi','error')}
  });
 }
};

const Password={
 init(){
  document.addEventListener('click',e=>{const b=e.target.closest('[data-password-toggle]');if(!b)return;const target=$(b.dataset.passwordToggle||'input[type="password"]',b.parentElement);if(!target)return;const show=target.type==='password';target.type=show?'text':'password';b.setAttribute('aria-pressed',String(show));b.setAttribute('aria-label',show?'Parolni yashirish':'Parolni ko‘rsatish');b.classList.toggle('active',show)});
 }
};

const Counter={
 init(){
  const items=$$('[data-counter]');if(!items.length)return;
  const run=el=>{if(el.dataset.counterDone)return;el.dataset.counterDone='1';const target=parseFloat(el.dataset.counter)||0,duration=parseInt(el.dataset.counterDuration||'1200',10);if(reduced()){el.textContent=el.dataset.counterSuffix?`${target}${el.dataset.counterSuffix}`:String(target);return}const start=performance.now(),from=parseFloat(el.dataset.counterFrom||'0');const tick=now=>{const p=Math.min(1,(now-start)/duration),ease=1-Math.pow(1-p,3),value=from+(target-from)*ease;el.textContent=`${Number.isInteger(target)?Math.round(value):value.toFixed(1)}${el.dataset.counterSuffix||''}`;if(p<1)requestAnimationFrame(tick)};requestAnimationFrame(tick)};
  if(!('IntersectionObserver'in window)){items.forEach(run);return}const io=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting){run(e.target);io.unobserve(e.target)}}),{threshold:.5});items.forEach(x=>io.observe(x));
 }
};

const Accordion={
 init(){
  $$('[data-accordion]').forEach(group=>{
   const items=$$('[data-accordion-item]',group);
   items.forEach(item=>{const trigger=$('[data-accordion-trigger]',item),panel=$('[data-accordion-panel]',item);if(!trigger||!panel)return;trigger.setAttribute('aria-expanded',String(item.classList.contains('open')));trigger.addEventListener('click',()=>{const open=item.classList.contains('open');if(group.dataset.accordionSingle==='true')items.forEach(other=>{other.classList.remove('open');$('[data-accordion-trigger]',other)?.setAttribute('aria-expanded','false')});item.classList.toggle('open',!open);trigger.setAttribute('aria-expanded',String(!open))})});
  });
 }
};

const Tabs={
 init(){
  $$('[data-tabs]').forEach(root=>{
   const tabs=$$('[data-tab]',root),panels=$$('[data-tab-panel]',root);if(!tabs.length)return;
   const activate=id=>{tabs.forEach(t=>{const active=t.dataset.tab===id;t.classList.toggle('active',active);t.setAttribute('aria-selected',String(active));t.setAttribute('tabindex',active?'0':'-1')});panels.forEach(p=>{const active=p.dataset.tabPanel===id;p.hidden=!active;p.classList.toggle('active',active)})};
   const first=tabs.find(t=>t.classList.contains('active'))||tabs[0];activate(first.dataset.tab);
   tabs.forEach((t,i)=>{t.addEventListener('click',()=>activate(t.dataset.tab));t.addEventListener('keydown',e=>{if(!['ArrowRight','ArrowLeft','Home','End'].includes(e.key))return;e.preventDefault();let n=i;if(e.key==='ArrowRight')n=(i+1)%tabs.length;if(e.key==='ArrowLeft')n=(i-1+tabs.length)%tabs.length;if(e.key==='Home')n=0;if(e.key==='End')n=tabs.length-1;tabs[n].focus();activate(tabs[n].dataset.tab)})});
  });
 }
};

const Modal={
 init(){
  const close=m=>{m.classList.remove('active');m.setAttribute('aria-hidden','true');document.body.classList.remove('modal-open');m.querySelector('[data-modal-close]')?.blur()};
  const open=m=>{m.classList.add('active');m.setAttribute('aria-hidden','false');document.body.classList.add('modal-open');setTimeout(()=>m.querySelector('button,input,select,textarea,a,[tabindex]:not([tabindex="-1"])')?.focus(),30)};
  document.addEventListener('click',e=>{const trigger=e.target.closest('[data-modal-open]');if(trigger){const m=$(trigger.dataset.modalOpen);if(m)open(m);return}const closeBtn=e.target.closest('[data-modal-close]');if(closeBtn){const m=closeBtn.closest('[data-modal]');if(m)close(m);return}const backdrop=e.target.closest('[data-modal]');if(backdrop&&e.target===backdrop)close(backdrop)});
  document.addEventListener('keydown',e=>{if(e.key!=='Escape')return;const m=$('[data-modal].active');if(m)close(m)});
 }
};

const Toast={
 root:null,
 init(){this.root=$('[data-toast-root]')||Object.assign(document.createElement('div'),{className:'toast-root'});if(!this.root.parentNode){this.root.setAttribute('data-toast-root','');document.body.appendChild(this.root)}},
 show(message,type='info',duration=3200){if(!this.root)this.init();const item=document.createElement('div');item.className=`toast toast-${type}`;item.setAttribute('role','status');item.innerHTML=`<span class="toast-icon" aria-hidden="true"></span><span class="toast-message"></span><button type="button" class="toast-close" aria-label="Yopish">×</button>`;$('.toast-message',item).textContent=message;$('.toast-close',item).addEventListener('click',()=>this.remove(item));this.root.appendChild(item);requestAnimationFrame(()=>item.classList.add('show'));setTimeout(()=>this.remove(item),duration)},remove(item){item.classList.remove('show');setTimeout(()=>item.remove(),280)}};

const Lightbox={
 init(){
  const make=()=>{let box=$('[data-lightbox-root]');if(box)return box;box=document.createElement('div');box.className='lightbox';box.dataset.lightboxRoot='';box.setAttribute('aria-hidden','true');box.innerHTML='<button type="button" class="lightbox-close" data-lightbox-close aria-label="Yopish">×</button><div class="lightbox-frame"><img alt=""></div>';document.body.appendChild(box);return box};
  document.addEventListener('click',e=>{const trigger=e.target.closest('[data-lightbox]');if(!trigger)return;const box=make(),img=$('img',box),src=trigger.dataset.lightbox||trigger.currentSrc||trigger.src;if(!src)return;img.src=src;img.alt=trigger.alt||'';box.classList.add('active');box.setAttribute('aria-hidden','false');document.body.classList.add('lightbox-open')});
  document.addEventListener('click',e=>{const box=e.target.closest('[data-lightbox-root]');if(!box)return;if(e.target===box||e.target.closest('[data-lightbox-close]')){box.classList.remove('active');box.setAttribute('aria-hidden','true');document.body.classList.remove('lightbox-open')}});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'){const box=$('[data-lightbox-root].active');if(box){box.classList.remove('active');box.setAttribute('aria-hidden','true');document.body.classList.remove('lightbox-open')}}});
 }
};

const Images={
 init(){
  document.addEventListener('error',e=>{const img=e.target;if(!(img instanceof HTMLImageElement))return;if(img.dataset.fallbackApplied)return;img.dataset.fallbackApplied='1';img.classList.add('image-error');if(img.dataset.fallback)img.src=img.dataset.fallback},true);
 }
};

const Viewport={
 init(){
  const set=()=>{document.documentElement.style.setProperty('--vh',`${innerHeight*.01}px`);document.documentElement.style.setProperty('--vw',`${innerWidth*.01}px`)};addEventListener('resize',debounce(set,80),{passive:true});addEventListener('orientationchange',set,{passive:true});set();
 }
};

const Online={
 init(){
  const update=()=>document.documentElement.classList.toggle('offline',!navigator.onLine);addEventListener('online',update);addEventListener('offline',update);update();
 }
};

const Boot=()=>{
 Theme.init();Reveal.init();Nav.init();Scroll.init();Forms.init();Motion.init();Lazy.init();Header.init();Parallax.init();Ripple.init();Page.init();ActiveNav.init();Progress.init();BackTop.init();Copy.init();Password.init();Counter.init();Accordion.init();Tabs.init();Modal.init();Toast.init();Lightbox.init();Images.init();Viewport.init();Online.init();
 window.Soon={Theme,Reveal,Nav,Forms,Lazy,Header,Parallax,Ripple,ActiveNav,Progress,BackTop,Copy,Password,Counter,Accordion,Tabs,Modal,Toast,Lightbox,Images,Viewport,Online};
};

document.readyState==='loading'?document.addEventListener('DOMContentLoaded',Boot,{once:true}):Boot();
})();