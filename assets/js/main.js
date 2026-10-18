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
let qData=[],qIdx=0,qScore=0,qAnswered=false,qAnswers=[];
function initQuiz(data){qData=data;qIdx=0;qScore=0;qAnswered=false;qAnswers=Array(data.length).fill(null);renderQ()}
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
  qAnswers[qIdx]=idx;
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
      fetch('save_quiz.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({lesson_id:lid.value,answers:qAnswers,csrf_token:csrf?csrf.value:''})})
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

// ---- STUDY MODAL ----
let studyQuestions=[],studyAnswers=[],studyCardCount=0,studyQuestionIndex=0,studyCardIndex=0;
let studyLessonId=0,studyCsrfToken='',studyStatus=null,studyMode='',studyReturnFocus=null;
let studyCountdownTimer=null,studyCountdownSeconds=0,studyModalBodyOverflow='',studyRequestId=0;

function initStudyModal(questions,status,cardCount,lessonId,csrfToken){
  studyQuestions=questions;studyStatus=status;studyCardCount=cardCount;
  studyLessonId=lessonId;studyCsrfToken=csrfToken;
  const modal=document.getElementById('studyModal');
  if(!modal)return;
  document.getElementById('studyClose').addEventListener('click',closeStudyModal);
  document.getElementById('studyPrev').addEventListener('click',studyPrevious);
  document.getElementById('studyNext').addEventListener('click',studyNext);
  document.getElementById('studyRetake').addEventListener('click',startStudyQuiz);
  modal.addEventListener('click',event=>{if(event.target===modal)closeStudyModal()});
  modal.addEventListener('keydown',handleStudyKeys);
}

function openStudyMode(mode){
  const modal=document.getElementById('studyModal');
  if(!modal)return;
  studyReturnFocus=document.activeElement;
  const requestId=++studyRequestId;
  studyMode=mode;
  modal.classList.add('open');
  modal.setAttribute('aria-hidden','false');
  studyModalBodyOverflow=document.body.style.overflow;
  document.body.style.overflow='hidden';
  document.getElementById('studyQuizMode').hidden=mode!=='quiz';
  document.getElementById('studyFlashcardMode').hidden=mode!=='flashcard';
  document.getElementById('studyModalFooter').hidden=false;
  document.getElementById('studyModalTitle').textContent=mode==='quiz'?'Quiz':'Flashcards';
  if(mode==='flashcard'){
    studyCardIndex=0;
    document.querySelectorAll('.study-fc-scene').forEach((card,index)=>{
      card.hidden=index!==0;
      card.classList.remove('flipped');
      card.setAttribute('aria-pressed','false');
      card.tabIndex=index===0?0:-1;
      setStudyCardSide(card,false,false);
    });
    updateStudyNavigation();
  }else{
    document.getElementById('studyModalFooter').hidden=true;
    document.getElementById('studyQuizLoading').hidden=false;
    document.getElementById('studyQuizQuestion').hidden=true;
    document.getElementById('studyQuizResults').hidden=true;
    document.getElementById('studyProgressPill').textContent='Checking eligibility';
    document.getElementById('studyProgressBar').style.width='0%';
    fetchQuizStatus(requestId);
  }
  modal.focus();
}
window.openStudyMode=openStudyMode;

function closeStudyModal(){
  const modal=document.getElementById('studyModal');
  if(!modal)return;
  modal.classList.remove('open');
  modal.setAttribute('aria-hidden','true');
  studyRequestId++;
  document.body.style.overflow=studyModalBodyOverflow;
  if(studyCountdownTimer){clearInterval(studyCountdownTimer);studyCountdownTimer=null}
  if(studyReturnFocus&&typeof studyReturnFocus.focus==='function')studyReturnFocus.focus();
}

async function fetchQuizStatus(requestId=++studyRequestId){
  try{
    const response=await fetch(`quiz_status.php?lesson_id=${encodeURIComponent(studyLessonId)}`,{headers:{'Accept':'application/json'}});
    const data=await response.json();
    if(!response.ok||!data.success)throw new Error(data.error||'Could not check quiz eligibility.');
    if(requestId!==studyRequestId||studyMode!=='quiz'||!document.getElementById('studyModal').classList.contains('open'))return;
    studyStatus=data;
    showStudyEligibility();
  }catch(error){
    if(requestId!==studyRequestId||studyMode!=='quiz'||!document.getElementById('studyModal').classList.contains('open'))return;
    document.getElementById('studyQuizLoading').hidden=true;
    document.getElementById('studyQuizResults').hidden=false;
    document.getElementById('studyResultsHeading').textContent='Unable to check retake eligibility';
    document.getElementById('studySaveError').textContent=error.message||'Could not check quiz eligibility. Please try again.';
    document.getElementById('studySaveError').hidden=false;
    document.getElementById('studyRetake').hidden=true;
    document.getElementById('studyProgressPill').textContent='Eligibility unavailable';
    document.getElementById('studyModalFooter').hidden=true;
  }
}

function showStudyEligibility(){
  const latest=studyStatus.latest;
  document.getElementById('studyQuizLoading').hidden=true;
  document.getElementById('studyQuizQuestion').hidden=true;
  document.getElementById('studyQuizResults').hidden=false;
  document.getElementById('studyModalFooter').hidden=true;
  if(!latest){
    document.getElementById('studyResultsHeading').textContent='Ready to begin?';
    document.getElementById('studyScore').textContent='';
    document.getElementById('studyPercentage').textContent='';
    document.getElementById('studyResultMessage').textContent='Answer each question to see your score and retake schedule.';
    document.getElementById('studyCooldown').hidden=true;
    document.getElementById('studyLastAttempt').textContent='';
    document.getElementById('studyHistory').replaceChildren();
    const button=document.getElementById('studyRetake');
    button.textContent='Start Quiz';
    button.hidden=false;
    document.getElementById('studyProgressPill').textContent='Ready';
    document.getElementById('studyProgressBar').style.width='0%';
    return;
  }
  renderStudyResults(latest.score,latest.total,studyStatus);
}

function startStudyQuiz(){
  if(studyCountdownTimer){clearInterval(studyCountdownTimer);studyCountdownTimer=null}
  studyMode='quiz';
  studyQuestionIndex=0;
  studyAnswers=Array(studyQuestions.length).fill(null);
  document.getElementById('studyQuizResults').hidden=true;
  document.getElementById('studyQuizQuestion').hidden=false;
  document.getElementById('studyModalFooter').hidden=false;
  renderStudyQuestion();
  document.getElementById('studyOptions').querySelector('button')?.focus();
}

function renderStudyQuestion(){
  const question=studyQuestions[studyQuestionIndex];
  const total=studyQuestions.length;
  const percent=((studyQuestionIndex+1)/total)*100;
  document.getElementById('studyProgressPill').textContent=`Question ${studyQuestionIndex+1} of ${total}`;
  document.getElementById('studyProgressBar').style.width=`${percent}%`;
  document.getElementById('studyQuestion').textContent=question.question;
  const options=document.getElementById('studyOptions');
  options.replaceChildren();
  const keys=['A','B','C','D'];
  question.options.forEach((option,index)=>{
    const button=document.createElement('button');
    button.type='button';
    button.className='study-option';
    const selected=studyAnswers[studyQuestionIndex];
    if(selected!==null&&index===question.correct)button.classList.add('is-correct');
    if(selected===index&&selected!==question.correct)button.classList.add('is-wrong');
    button.disabled=studyAnswers[studyQuestionIndex]!==null;
    button.setAttribute('aria-pressed',selected===index?'true':'false');
    const key=document.createElement('span');
    key.className='study-option-key';
    key.textContent=keys[index];
    const text=document.createElement('span');
    text.className='study-option-text';
    text.textContent=option;
    button.append(key,text);
    button.addEventListener('click',()=>selectStudyAnswer(index));
    options.appendChild(button);
  });
  const selected=studyAnswers[studyQuestionIndex];
  const feedback=document.getElementById('studyFeedback');
  feedback.replaceChildren();
  feedback.className='study-feedback';
  if(selected!==null){
    const isCorrect=selected===question.correct;
    feedback.classList.add(isCorrect?'is-correct':'is-wrong');
    const message=document.createElement('strong');
    message.textContent=isCorrect?'Correct!':'Not quite.';
    const answer=document.createElement('span');
    answer.textContent=`Correct answer: ${keys[question.correct]}. ${question.options[question.correct]}`;
    feedback.append(message,answer);
    if(question.explanation){
      const explanation=document.createElement('span');
      explanation.textContent=question.explanation;
      feedback.appendChild(explanation);
    }
  }
  updateStudyNavigation();
}

function selectStudyAnswer(answerIndex){
  if(studyAnswers[studyQuestionIndex]!==null)return;
  studyAnswers[studyQuestionIndex]=answerIndex;
  renderStudyQuestion();
}

function updateStudyNavigation(){
  const previous=document.getElementById('studyPrev');
  const next=document.getElementById('studyNext');
  previous.disabled=studyMode==='flashcard'?studyCardIndex===0:studyQuestionIndex===0;
  if(studyMode==='flashcard'){
    next.textContent=studyCardIndex===studyCardCount-1?'Finish':'Next';
    next.insertAdjacentHTML('beforeend',studyCardIndex===studyCardCount-1?' <i class="fas fa-check" aria-hidden="true"></i>':' <i class="fas fa-arrow-right" aria-hidden="true"></i>');
    next.disabled=false;
  }else{
    const last=studyQuestionIndex===studyQuestions.length-1;
    next.textContent=last?'Finish':'Next';
    next.insertAdjacentHTML('beforeend',last?' <i class="fas fa-check" aria-hidden="true"></i>':' <i class="fas fa-arrow-right" aria-hidden="true"></i>');
    next.disabled=studyAnswers[studyQuestionIndex]===null;
  }
}

function studyPrevious(){
  if(studyMode==='flashcard'){
    if(studyCardIndex>0)showStudyCard(studyCardIndex-1);
    return;
  }
  if(studyQuestionIndex>0){
    studyQuestionIndex--;
    renderStudyQuestion();
  }
}

function studyNext(){
  if(studyMode==='flashcard'){
    if(studyCardIndex===studyCardCount-1){closeStudyModal();return}
    showStudyCard(studyCardIndex+1);
    return;
  }
  if(studyAnswers[studyQuestionIndex]===null)return;
  if(studyQuestionIndex===studyQuestions.length-1){submitStudyQuiz();return}
  studyQuestionIndex++;
  renderStudyQuestion();
}

function showStudyCard(index){
  const cards=document.querySelectorAll('.study-fc-scene');
  if(index<0||index>=cards.length)return;
  cards.forEach((card,cardIndex)=>{
    card.hidden=cardIndex!==index;
    card.setAttribute('aria-pressed','false');
    card.tabIndex=cardIndex===index?0:-1;
    setStudyCardSide(card,false,false);
  });
  studyCardIndex=index;
  document.getElementById('studyProgressPill').textContent=`Card ${index+1} of ${studyCardCount}`;
  document.getElementById('studyProgressBar').style.width=`${((index+1)/studyCardCount)*100}%`;
  updateStudyNavigation();
}

function flipStudyCard(card=document.getElementById(`studyFc${studyCardIndex}`)){
  if(!card)return;
  const flipped=!card.classList.contains('flipped');
  setStudyCardSide(card,flipped,true);
}

function setStudyCardSide(card,flipped,animate){
  const front=card.querySelector('.fc-front');
  const back=card.querySelector('.fc-back');
  if(!front||!back)return;
  front.hidden=flipped;
  back.hidden=!flipped;
  front.style.display=flipped?'none':'flex';
  back.style.display=flipped?'flex':'none';
  front.setAttribute('aria-hidden',flipped?'true':'false');
  back.setAttribute('aria-hidden',flipped?'false':'true');
  card.classList.toggle('flipped',flipped);
  card.classList.remove('is-flipping');
  card.style.setProperty('--study-flip-start',flipped?'90deg':'-90deg');
  if(animate){
    void card.offsetWidth;
    card.classList.add('is-flipping');
    card.addEventListener('animationend',()=>card.classList.remove('is-flipping'),{once:true});
  }
  card.setAttribute('aria-pressed',flipped?'true':'false');
  const question=card.querySelector('.fc-front .fc-text')?.textContent||'';
  const answer=card.querySelector('.fc-back .fc-text')?.textContent||'';
  card.setAttribute('aria-label',flipped?`Flashcard answer: ${answer}. Flip to show the question`:`Flashcard question: ${question}. Flip to reveal the answer`);
}
window.flipStudyCard=flipStudyCard;

async function submitStudyQuiz(){
  const requestId=studyRequestId;
  const score=studyAnswers.reduce((sum,answer,index)=>sum+(answer===studyQuestions[index].correct?1:0),0);
  const total=studyQuestions.length;
  const percent=Math.round((score/total)*100);
  document.getElementById('studyQuizQuestion').hidden=true;
  document.getElementById('studyQuizResults').hidden=false;
  document.getElementById('studyModalFooter').hidden=true;
  document.getElementById('studyProgressPill').textContent='Results';
  document.getElementById('studyProgressBar').style.width='100%';
  document.getElementById('studyResultsHeading').textContent='Quiz complete';
  document.getElementById('studyScore').textContent=`${score} / ${total}`;
  document.getElementById('studyPercentage').textContent=`${percent}%`;
  document.getElementById('studyResultMessage').textContent='Your score determines when your next retake becomes available.';
  document.getElementById('studySaveError').hidden=true;
  document.getElementById('studyCooldown').hidden=true;
  document.getElementById('studyRetake').hidden=true;
  document.getElementById('studyLastAttempt').textContent='Saving your attempt…';
  document.getElementById('studyHistory').replaceChildren();
  try{
    const response=await fetch('save_quiz.php',{
      method:'POST',
      headers:{'Content-Type':'application/json','Accept':'application/json'},
      body:JSON.stringify({lesson_id:studyLessonId,answers:studyAnswers,csrf_token:studyCsrfToken})
    });
    const data=await response.json();
    if(requestId!==studyRequestId||studyMode!=='quiz'||!document.getElementById('studyModal').classList.contains('open'))return;
    if(!response.ok||!data.success){
      if(response.status===429&&data.next_retake_at){
        await fetchQuizStatus();
        return;
      }
      throw new Error(data.error||'Could not save quiz result.');
    }
    studyStatus=data;
    renderStudyResults(score,total,data);
  }catch(error){
    if(requestId!==studyRequestId||studyMode!=='quiz'||!document.getElementById('studyModal').classList.contains('open'))return;
    document.getElementById('studyLastAttempt').textContent='';
    document.getElementById('studySaveError').textContent=error.message||'Could not save quiz result. Please try again.';
    document.getElementById('studySaveError').hidden=false;
  }
}

function renderStudyResults(score,total,status){
  const percent=Math.round((score/total)*100);
  const latest=status.latest;
  document.getElementById('studyQuizLoading').hidden=true;
  document.getElementById('studyQuizQuestion').hidden=true;
  document.getElementById('studyQuizResults').hidden=false;
  document.getElementById('studyModalFooter').hidden=true;
  document.getElementById('studyResultsHeading').textContent='Quiz results';
  document.getElementById('studyScore').textContent=`${score} / ${total}`;
  document.getElementById('studyPercentage').textContent=`${percent}%`;
  document.getElementById('studyResultMessage').textContent='Your score determines when your next retake becomes available.';
  document.getElementById('studySaveError').hidden=true;
  const cooldown=document.getElementById('studyCooldown');
  const retake=document.getElementById('studyRetake');
  retake.hidden=true;
  if(latest){
    document.getElementById('studyLastAttempt').textContent=`Last attempt: ${latest.completed_label}, Score: ${latest.score}/${latest.total}`;
    document.getElementById('studyCooldownRule').textContent=latest.rule_message;
    document.getElementById('studyCooldownDate').textContent=`Retake available on ${latest.next_retake_label}`;
    if(latest.remaining_seconds>0){
      cooldown.hidden=false;
      retake.textContent=`Retake available on ${latest.next_retake_label}`;
      retake.disabled=true;
      retake.hidden=false;
      beginStudyCountdown(latest.remaining_seconds);
    }else{
      if(studyCountdownTimer){clearInterval(studyCountdownTimer);studyCountdownTimer=null}
      cooldown.hidden=true;
      retake.textContent='Retake Quiz';
      retake.disabled=false;
      retake.hidden=false;
    }
  }else{
    document.getElementById('studyLastAttempt').textContent='';
    cooldown.hidden=true;
    retake.textContent='Start Quiz';
    retake.disabled=false;
    retake.hidden=false;
  }
  renderStudyHistory(status.attempts||[]);
  document.getElementById('studyProgressPill').textContent='Results';
  document.getElementById('studyProgressBar').style.width='100%';
}

function renderStudyHistory(attempts){
  const history=document.getElementById('studyHistory');
  history.replaceChildren();
  if(!attempts.length)return;
  const heading=document.createElement('h4');
  heading.textContent='Recent attempts';
  history.appendChild(heading);
  attempts.forEach(attempt=>{
    const row=document.createElement('div');
    row.className='study-history-row';
    const date=document.createElement('span');
    date.textContent=attempt.completed_label;
    const score=document.createElement('strong');
    score.textContent=`${attempt.score}/${attempt.total} (${Math.round(attempt.percentage)}%)`;
    const rule=document.createElement('small');
    rule.textContent=`${attempt.days}-day cooldown`;
    row.append(date,score,rule);
    history.appendChild(row);
  });
}

function beginStudyCountdown(seconds){
  if(studyCountdownTimer)clearInterval(studyCountdownTimer);
  studyCountdownSeconds=Math.max(0,seconds);
  const output=document.getElementById('studyCountdown');
  const tick=()=>{
    const days=Math.floor(studyCountdownSeconds/86400);
    const hours=Math.floor((studyCountdownSeconds%86400)/3600);
    const minutes=Math.floor((studyCountdownSeconds%3600)/60);
    output.textContent=studyCountdownSeconds>0?`Available in ${days}d ${hours}h ${minutes}m`:'Checking availability…';
    if(studyCountdownSeconds===0){
      clearInterval(studyCountdownTimer);
      studyCountdownTimer=null;
      fetchQuizStatus();
    }
  };
  tick();
  studyCountdownTimer=setInterval(()=>{
    studyCountdownSeconds=Math.max(0,studyCountdownSeconds-60);
    tick();
  },60000);
}

function handleStudyKeys(event){
  const modal=document.getElementById('studyModal');
  if(!modal.classList.contains('open'))return;
  if(event.key==='Escape'){event.preventDefault();closeStudyModal();return}
  if(event.key==='Tab'){
    const focusable=[...modal.querySelectorAll('button:not([disabled]):not([hidden]),[tabindex="0"]:not([hidden])')].filter(element=>element.offsetParent!==null);
    if(!focusable.length){event.preventDefault();modal.focus();return}
    const first=focusable[0],last=focusable[focusable.length-1];
    if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus()}
    else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus()}
    return;
  }
  if(studyMode==='flashcard'){
    if(event.key==='ArrowRight'){event.preventDefault();studyNext()}
    else if(event.key==='ArrowLeft'){event.preventDefault();studyPrevious()}
    else if((event.key===' '||event.key==='Enter')&&event.target.closest('.study-fc-scene')){event.preventDefault();flipStudyCard()}
    return;
  }
  if(event.target.matches('input,textarea,select'))return;
  if(document.getElementById('studyQuizQuestion').hidden)return;
  if(/^[1-4]$/.test(event.key)||/^[a-d]$/i.test(event.key)){
    event.preventDefault();
    const index=/^[1-4]$/.test(event.key)?Number(event.key)-1:event.key.toUpperCase().charCodeAt(0)-65;
    const option=document.getElementById('studyOptions').children[index];
    if(option&&!option.disabled)option.click();
  }else if(event.key==='Enter'&&!event.target.matches('button')){
    event.preventDefault();
    if(!document.getElementById('studyNext').disabled)studyNext();
  }
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
