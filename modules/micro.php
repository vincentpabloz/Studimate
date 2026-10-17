<?php
session_start();
if(!isset($_SESSION['user_id'])){header("Location: ../index.php");exit;}
require '../config/db.php';
require '../includes/content_helpers.php';
require '../includes/micro_helpers.php';
require_once '../config/env.php';
$uid=(int)$_SESSION['user_id'];$base='../';

$csrfToken=$_SESSION['csrf_token']??bin2hex(random_bytes(32));
$_SESSION['csrf_token']=$csrfToken;
$AI_KEY=getenv('GEMINI_API_KEY')?:($_ENV['GEMINI_API_KEY']??'');

// All lessons of this student, grouped by subject in the dropdown.
$allLessons=$pdo->prepare("SELECT l.id,l.title,s.subject_name FROM lessons l JOIN subjects s ON l.subject_id=s.id WHERE s.user_id=? ORDER BY s.subject_name,l.title");
$allLessons->execute([$uid]);$lessonList=$allLessons->fetchAll();

$requestedId=$_SERVER['REQUEST_METHOD']==='POST'?(int)($_POST['lesson_id']??0):(int)($_GET['lesson_id']??0);
if(!$requestedId&&$lessonList)$requestedId=(int)$lessonList[0]['id'];
$lesson=null;
if($requestedId){
  $st=$pdo->prepare("SELECT l.*,s.subject_name FROM lessons l JOIN subjects s ON l.subject_id=s.id WHERE l.id=? AND s.user_id=?");
  $st->execute([$requestedId,$uid]);
  $lesson=$st->fetch()?:null;
}
$lesson_id=$lesson?(int)$lesson['id']:0;

$error='';$notice='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!hash_equals($csrfToken,(string)($_POST['csrf_token']??''))){
    $error='Your session expired. Reload the page and try again.';
  }elseif(!$lesson){
    $error='Choose a lesson that belongs to your account.';
  }else{
    $force=!empty($_POST['force_regenerate']);
    if(!$force&&microLoad($pdo,$lesson)!==null){
      $notice='Showing the saved microlearning units. No AI request was needed.';
    }elseif(!$AI_KEY){
      $error='AI is not configured. Add GEMINI_API_KEY to the .env file.';
    }else{
      $genError=null;
      $generated=microGenerate($pdo,$uid,$lesson,$AI_KEY,$genError);
      if($generated===null){
        $error=$genError??'AI could not generate the units. Try again.';
      }else{
        microSave($pdo,$lesson,$generated);
        if($force)microResetProgress($pdo,$uid,$lesson);
        header('Location: micro.php?lesson_id='.$lesson_id.'&generated=1');
        exit;
      }
    }
  }
}elseif(isset($_GET['generated'])){
  $notice='Microlearning units generated and saved to this lesson.';
}

$units=$lesson?microLoad($pdo,$lesson):null;
$progress=$units?microProgress($pdo,$uid,$lesson):[];
$start=0;
if($units){
  foreach(array_keys($units) as $i){if(!isset($progress[$i])){$start=$i;break;}}
}

$microData=null;
if($units){
  $clientUnits=[];
  foreach($units as $u){
    $clientUnits[]=['title'=>$u['title'],'content'=>$u['content'],'takeaway'=>$u['takeaway'],'question'=>$u['question'],'options'=>$u['options']];
  }
  $microData=json_encode([
    'lessonId'=>$lesson_id,
    'csrf'=>$csrfToken,
    'units'=>$clientUnits,
    'progress'=>(object)$progress,
    'start'=>$start,
  ],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
  $extraJs=<<<'JS'
(function(){
var node=document.getElementById('microData');if(!node)return;
var D=JSON.parse(node.textContent);
var units=D.units,progress=D.progress||{},idx=D.start||0,session={};
var elUnit=document.getElementById('mUnit'),elFill=document.getElementById('mFill'),elCount=document.getElementById('mCount'),elDone=document.getElementById('mDone'),btnPrev=document.getElementById('mPrev'),btnNext=document.getElementById('mNext');
function h(tag,cls,text){var e=document.createElement(tag);if(cls)e.className=cls;if(text!==undefined&&text!==null)e.textContent=text;return e;}
function doneCount(){return Object.keys(progress).length;}
function correctCount(){var c=0;for(var k in progress){if(progress[k].correct)c++;}return c;}
function updateBar(){
  var total=units.length;
  elFill.style.width=Math.round(doneCount()/total*100)+'%';
  elDone.textContent=doneCount()+' of '+total+' checked';
}
function paintResult(btns,choice,answer){
  for(var i=0;i<btns.length;i++){
    btns[i].disabled=true;btns[i].style.cursor='default';
    if(i===answer)btns[i].classList.add('correct');
    else if(i===choice)btns[i].classList.add('wrong');
  }
}
function render(){
  elUnit.innerHTML='';
  var total=units.length;
  updateBar();
  if(idx>=total){renderFinish();return;}
  elCount.textContent='Unit '+(idx+1)+' of '+total;
  btnPrev.style.visibility=idx>0?'visible':'hidden';
  btnNext.style.display='';
  btnNext.textContent=idx===total-1?'Finish':'Next';
  var u=units[idx];
  elUnit.appendChild(h('div','quiz-q',u.title));
  u.content.split(/\n{2,}/).forEach(function(p){
    if(!p.trim())return;
    var para=h('p',null,p.trim());
    para.style.cssText='font-size:0.9rem;line-height:1.7;margin:0 0 10px;color:var(--text-dark)';
    elUnit.appendChild(para);
  });
  if(u.takeaway){
    var tk=h('div',null,null);
    tk.style.cssText='background:var(--primary-light);color:var(--primary);border-radius:var(--radius-sm);padding:10px 14px;font-size:0.84rem;margin:12px 0 20px';
    tk.appendChild(h('strong',null,'Key takeaway: '));
    tk.appendChild(document.createTextNode(u.takeaway));
    elUnit.appendChild(tk);
  }
  var qh=h('div',null,'Quick check');
  qh.style.cssText='font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:var(--text-light);margin-bottom:6px';
  elUnit.appendChild(qh);
  elUnit.appendChild(h('div','quiz-q',u.question));
  if(progress[idx]){
    var badge=h('span',progress[idx].correct?'badge badge-success':'badge badge-primary',progress[idx].correct?'Answered correctly before':'Checked before - try again');
    badge.style.marginBottom='10px';badge.style.display='inline-flex';
    elUnit.appendChild(badge);
  }
  var wrap=h('div','quiz-options',null);
  var fb=h('div',null,null);fb.style.cssText='margin-top:12px;font-size:0.84rem;font-weight:600';
  var btns=[];
  u.options.forEach(function(opt,i){
    var b=h('button','quiz-opt',null);b.type='button';b.style.width='100%';b.style.textAlign='left';
    b.appendChild(h('span','quiz-opt-key',String.fromCharCode(65+i)));
    b.appendChild(h('span',null,opt));
    b.addEventListener('click',function(){choose(i,btns,fb);});
    btns.push(b);wrap.appendChild(b);
  });
  elUnit.appendChild(wrap);elUnit.appendChild(fb);
  var s=session[idx];
  if(s){paintResult(btns,s.choice,s.answer);fb.textContent=s.correct?'Correct!':'Not quite - the right answer is highlighted.';fb.style.color=s.correct?'var(--success)':'var(--danger)';}
}
function choose(choice,btns,fb){
  var at=idx;
  for(var i=0;i<btns.length;i++)btns[i].disabled=true;
  fb.textContent='Checking...';fb.style.color='var(--text-light)';
  fetch('save_micro.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf_token:D.csrf,lesson_id:D.lessonId,unit_index:at,choice:choice})})
  .then(function(r){return r.json();})
  .then(function(res){
    if(!res||res.error){fb.textContent=(res&&res.error)||'Could not save. Try again.';fb.style.color='var(--danger)';for(var i=0;i<btns.length;i++)btns[i].disabled=false;return;}
    var prev=progress[at]&&progress[at].correct;
    progress[at]={correct:!!(res.correct||prev)};
    session[at]={choice:choice,answer:res.answer,correct:!!res.correct};
    if(at===idx){paintResult(btns,choice,res.answer);fb.textContent=res.correct?'Correct!':'Not quite - the right answer is highlighted.';fb.style.color=res.correct?'var(--success)':'var(--danger)';}
    updateBar();
  })
  .catch(function(){fb.textContent='Network error. Try again.';fb.style.color='var(--danger)';for(var i=0;i<btns.length;i++)btns[i].disabled=false;});
}
function renderFinish(){
  var total=units.length;
  elCount.textContent='Lesson complete';
  btnNext.style.display='none';
  btnPrev.style.visibility='visible';
  var box=h('div',null,null);box.style.cssText='text-align:center;padding:18px 8px';
  var t=h('div','quiz-q','Nice work - you reached the end of this lesson');t.style.marginBottom='8px';
  box.appendChild(t);
  var p=h('p',null,'You checked '+doneCount()+' of '+total+' units and answered '+correctCount()+' correctly.'+(doneCount()<total?' Go back to finish the units you skipped.':''));
  p.style.cssText='font-size:0.9rem;color:var(--text-mid);margin:0 0 16px';
  box.appendChild(p);
  var a=h('a','btn btn-primary btn-pill','Take the full quiz');
  a.href='ai_tools.php?lesson_id='+encodeURIComponent(D.lessonId)+'&tab=quiz';a.style.cssText='width:auto;display:inline-flex';
  box.appendChild(a);
  elUnit.appendChild(box);
}
btnPrev.addEventListener('click',function(){if(idx>0){idx--;render();}});
btnNext.addEventListener('click',function(){if(idx<units.length){idx++;render();}});
render();
})();
JS;
}

$pageTitle='Microlearning – StudiMate';
$topbarTitle='Microlearning';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="page-body">

  <div class="card mb-4">
    <div class="card-header"><h3>Microlearning</h3></div>
    <div class="card-body">
      <p class="text-sm text-muted" style="margin:0 0 14px">Study a lesson in short units of about two minutes each. Every unit ends with a quick check question.</p>
      <?php if(!$lessonList):?>
      <p class="text-sm text-muted">Add a lesson first, then come back to study it in short units.</p>
      <?php else:?>
      <label for="lessonSelect" class="text-sm" style="display:block;margin-bottom:6px">Lesson</label>
      <select id="lessonSelect" class="form-control" onchange="window.location.href='micro.php?lesson_id='+encodeURIComponent(this.value)">
        <?php $currentSubject='';foreach($lessonList as $option):?>
        <?php if($currentSubject!==$option['subject_name']):?>
        <?php if($currentSubject!=='')echo '</optgroup>'; $currentSubject=$option['subject_name'];?>
        <optgroup label="<?=htmlspecialchars($currentSubject)?>">
        <?php endif;?>
          <option value="<?=(int)$option['id']?>" <?=(int)$option['id']===$lesson_id?'selected':''?>><?=htmlspecialchars($option['title'])?></option>
        <?php endforeach;if($currentSubject!=='')echo '</optgroup>';?>
      </select>
      <?php if($lesson):?>
      <form method="POST" style="margin-top:16px">
        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrfToken)?>">
        <input type="hidden" name="lesson_id" value="<?=$lesson_id?>">
        <button type="submit" class="btn btn-primary" style="width:auto"><i class="fas fa-bolt"></i> <?=$units?'Open saved units':'Generate microlearning units'?></button>
        <?php if($units):?>
        <label class="text-sm" style="display:inline-block;margin-left:10px"><input type="checkbox" name="force_regenerate" value="1"> Generate a fresh version (resets unit progress)</label>
        <?php endif;?>
      </form>
      <?php endif;?>
      <?php endif;?>
    </div>
  </div>

  <?php if($error):?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($error)?></div><?php endif;?>
  <?php if($notice):?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($notice)?></div><?php endif;?>

  <?php if($lesson&&!$units&&!$error):?>
  <div class="card"><div class="card-body"><div class="empty-state"><i class="fas fa-bolt"></i><h4>No units yet</h4><p>Generate short units for this lesson. They are saved, so the AI is only used the first time.</p></div></div></div>
  <?php endif;?>

  <?php if($units):?>
  <div class="quiz-wrap">
    <div style="margin-bottom:12px">
      <div style="font-size:1rem;font-weight:700;color:var(--text-dark)"><?=htmlspecialchars($lesson['title'])?></div>
      <div style="font-size:0.78rem;color:var(--text-light)"><?=htmlspecialchars($lesson['subject_name'])?> · <?=count($units)?> short units</div>
    </div>
    <div class="card"><div class="card-body" id="microApp">
      <div class="quiz-prog-bar"><div class="quiz-prog-fill" id="mFill" style="width:0%"></div></div>
      <div class="quiz-meta-row"><span id="mCount"></span><span id="mDone"></span></div>
      <div id="mUnit"></div>
      <div style="display:flex;justify-content:space-between;margin-top:20px">
        <button type="button" class="btn btn-outline btn-pill btn-sm" id="mPrev" style="width:auto"><i class="fas fa-arrow-left"></i> Previous</button>
        <button type="button" class="btn btn-primary btn-pill btn-sm" id="mNext" style="width:auto">Next</button>
      </div>
    </div></div>
  </div>
  <script type="application/json" id="microData"><?=$microData?></script>
  <?php endif;?>

</div>

<?php include '../includes/footer.php';?>
