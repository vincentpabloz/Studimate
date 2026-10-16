// ============================================
// STUDIMATE — Main JavaScript
// ============================================

// ---- SIDEBAR TOGGLE ----
const sidebar  = document.getElementById('sidebar');
const overlay  = document.getElementById('sidebarOverlay');
const hamburger= document.getElementById('hamburger');
if(hamburger){hamburger.addEventListener('click',()=>{sidebar.classList.toggle('open');overlay.classList.toggle('open')})}
if(overlay){overlay.addEventListener('click',()=>{sidebar.classList.remove('open');overlay.classList.remove('open')})}

// ---- TOAST ----
function showToast(msg, type='success'){
  let c=document.getElementById('toastContainer');
  if(!c)return;
  const icons={success:'fa-check-circle',error:'fa-times-circle',warning:'fa-exclamation-triangle'};
  const colors={success:'#22C55E',error:'#EF4444',warning:'#F59E0B'};
  const t=document.createElement('div');
  t.className=`toast ${type==='error'?'error':type==='warning'?'warning':''}`;
  t.innerHTML=`<i class="fas ${icons[type]||icons.success}" style="color:${colors[type]||colors.success}"></i><span>${msg}</span>`;
  c.appendChild(t);
  setTimeout(()=>{t.style.animation='toastIn 0.3s ease reverse';setTimeout(()=>t.remove(),300)},3500);
}

// ---- MODALS ----
function openModal(id){const m=document.getElementById(id);if(m){m.classList.add('open');document.body.style.overflow='hidden'}}
function closeModal(id){const m=document.getElementById(id);if(m){m.classList.remove('open');document.body.style.overflow=''}}
document.querySelectorAll('.modal-overlay').forEach(m=>{m.addEventListener('click',e=>{if(e.target===m)closeModal(m.id)})});

// ---- FORM VALIDATION ----
function validateLogin(form){
  let ok=true; clearErrors(form);
  const e=form.querySelector('[name="email"]'), p=form.querySelector('[name="password"]');
  if(!e.value.trim()||!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e.value)){showErr(e,'Enter a valid email.');ok=false}
  if(!p.value||p.value.length<6){showErr(p,'Password must be at least 6 characters.');ok=false}
  return ok;
}
function validateRegister(form){
  let ok=true; clearErrors(form);
  const n=form.querySelector('[name="name"]'),e=form.querySelector('[name="email"]'),
        p=form.querySelector('[name="password"]'),c=form.querySelector('[name="confirm_password"]');
  if(!n||!n.value.trim()||n.value.trim().length<2){showErr(n,'Name must be at least 2 characters.');ok=false}
  if(!e||!e.value.trim()||!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e.value)){showErr(e,'Enter a valid email.');ok=false}
  if(!p||!p.value||p.value.length<8){showErr(p,'Password must be at least 8 characters.');ok=false}
  if(c&&p.value!==c.value){showErr(c,'Passwords do not match.');ok=false}
  return ok;
}
function showErr(input,msg){
  if(!input)return;
  input.classList.add('error');
  let err=input.parentElement.nextElementSibling;
  if(!err||!err.classList.contains('field-error')){err=document.createElement('div');err.className='field-error';input.parentElement.after(err)}
  err.textContent=msg;err.classList.add('show');
}
function clearErrors(form){
  form.querySelectorAll('.error,.valid').forEach(el=>el.classList.remove('error','valid'));
  form.querySelectorAll('.field-error').forEach(el=>{el.textContent='';el.classList.remove('show')});
}
function checkStrength(input){
  const v=input.value, bar=document.getElementById('pwBar'), lbl=document.getElementById('pwLbl');
  if(!bar)return;
  let s=0;if(v.length>=8)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;
  const lvls=[{w:'25%',c:'#EF4444',t:'Weak'},{w:'50%',c:'#F59E0B',t:'Fair'},{w:'75%',c:'#F59E0B',t:'Good'},{w:'100%',c:'#22C55E',t:'Strong'}];
  const l=lvls[Math.max(0,s-1)];
  bar.style.width=v.length?l.w:'0';bar.style.background=l.c;
  if(lbl)lbl.textContent=v.length?l.t:'';
}
function togglePw(id,btn){
  const i=document.getElementById(id),ic=btn.querySelector('i');
  if(i.type==='password'){i.type='text';ic.className='fas fa-eye-slash'}else{i.type='password';ic.className='fas fa-eye'}
}

// ---- COLOR CHIPS ----
function initColorPicker(){
  document.querySelectorAll('.color-chip').forEach(c=>{
    c.addEventListener('click',()=>{
      document.querySelectorAll('.color-chip').forEach(x=>x.classList.remove('selected'));
      c.classList.add('selected');
      const inp=document.getElementById('colorInput');if(inp)inp.value=c.dataset.color;
    });
  });
}

// ---- CONFIRM DELETE ----
function confirmDelete(url,name){if(confirm(`Delete "${name}"? This cannot be undone.`))window.location.href=url}

// ---- QUIZ ----
let qData=[],qIdx=0,qScore=0,qAnswered=false;
function initQuiz(data){qData=data;qIdx=0;qScore=0;qAnswered=false;renderQ()}
function renderQ(){
  if(qIdx>=qData.length){showResult();return}
  const q=qData[qIdx];qAnswered=false;
  const pct=(qIdx/qData.length)*100;
  const pf=document.getElementById('quizFill');if(pf)pf.style.width=pct+'%';
  const qc=document.getElementById('qCurrent');if(qc)qc.textContent=qIdx+1;
  const qt=document.getElementById('qTotal');if(qt)qt.textContent=qData.length;
  const qtext=document.getElementById('qText');if(qtext)qtext.textContent=q.question;
  const opts=document.getElementById('qOpts');if(!opts)return;
  opts.innerHTML='';
  const keys=['A','B','C','D'];
  q.options.forEach((opt,i)=>{
    const d=document.createElement('div');d.className='quiz-opt';
    d.innerHTML=`<span class="quiz-opt-key">${keys[i]}</span><span>${opt}</span>`;
    d.addEventListener('click',()=>selectAns(d,i,q.correct));
    opts.appendChild(d);
  });
  const nb=document.getElementById('qNext');if(nb)nb.style.display='none';
}
function selectAns(el,idx,correct){
  if(qAnswered)return; qAnswered=true;
  document.querySelectorAll('.quiz-opt').forEach((o,i)=>{
    if(i===correct)o.classList.add('correct');
    else if(i===idx&&idx!==correct)o.classList.add('wrong');
    o.style.pointerEvents='none';
  });
  if(idx===correct)qScore++;
  const nb=document.getElementById('qNext');if(nb)nb.style.display='flex';
}
function nextQ(){if(!qAnswered){showToast('Select an answer first.','warning');return}qIdx++;renderQ()}
function showResult(){
  const wrap=document.getElementById('quizWrap'),res=document.getElementById('quizResult');
  if(wrap)wrap.style.display='none';
  if(res){
    res.style.display='block';
    const pct=Math.round((qScore/qData.length)*100);
    const cc=pct>=80?'':pct>=50?'mid':'fail';
    res.querySelector('.score-circle').className=`score-circle ${cc}`;
    res.querySelector('.score-num').textContent=qScore;
    res.querySelector('.score-denom').textContent='/'+qData.length;
    res.querySelector('.score-pct').textContent=pct+'%';
    res.querySelector('.score-msg').textContent=pct>=80?'🎉 Excellent! Review in 7 days.':pct>=50?'👍 Good job! Review in 3 days.':'📖 Keep studying! Review in 1 day.';
    const lid=document.getElementById('lessonId');
    if(lid){
      const csrf=document.getElementById('quizCsrfToken');
      fetch('save_quiz.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({lesson_id:lid.value,score:qScore,total:qData.length,csrf_token:csrf?csrf.value:''})})
        .then(r=>r.json()).then(d=>{if(d.success)showToast('Score saved!');else showToast(d.error||'Could not save quiz result.','error')})
        .catch(()=>showToast('Could not save quiz result. Please try again.','error'));
    }
  }
}

// ---- FLASHCARD NAV ----
let fcIdx=0,fcTotal=0;
function fcNav(dir){
  const cur=document.getElementById('fc'+fcIdx);if(cur)cur.style.display='none';
  fcIdx=(fcIdx+dir+fcTotal)%fcTotal;
  const nxt=document.getElementById('fc'+fcIdx);if(nxt)nxt.style.display='block';
  const n=document.getElementById('fcNum');if(n)n.textContent=fcIdx+1;
  document.querySelectorAll('.fc-scene').forEach(s=>s.classList.remove('flipped'));
}

// ---- INIT ----
document.addEventListener('DOMContentLoaded',()=>{
  initColorPicker();
  const lf=document.getElementById('loginForm');
  if(lf)lf.addEventListener('submit',e=>{if(!validateLogin(lf))e.preventDefault()});
  const rf=document.getElementById('registerForm');
  if(rf){
    rf.addEventListener('submit',e=>{if(!validateRegister(rf))e.preventDefault()});
    const pw=rf.querySelector('[name="password"]');
    if(pw)pw.addEventListener('input',()=>checkStrength(pw));
  }
  // Flashcard click to flip
  document.querySelectorAll('.fc-scene').forEach(s=>{
    s.addEventListener('click',()=>s.classList.toggle('flipped'));
  });
});
