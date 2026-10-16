<?php
session_start();
if(!isset($_SESSION['user_id'])){header("Location: ../index.php");exit;}
require '../config/db.php';
require '../includes/content_helpers.php';
require '../includes/embedding_helpers.php';
$uid=$_SESSION['user_id'];$base='../';
$csrfToken=$_SESSION['csrf_token']??bin2hex(random_bytes(32));
$_SESSION['csrf_token']=$csrfToken;

$sid=(int)($_GET['subject_id']??0);
if(!$sid){header("Location: subjects.php");exit;}

$subStmt=$pdo->prepare("SELECT * FROM subjects WHERE id=? AND user_id=?");
$subStmt->execute([$sid,$uid]);
$sub=$subStmt->fetch();
if(!$sub){header("Location: subjects.php");exit;}

// Add lesson
$err=$_SESSION['upload_error']??'';$success=$_SESSION['upload_success']??'';$reusedLesson=null;$similarLesson=null;
unset($_SESSION['upload_error'],$_SESSION['upload_success']);
$editLesson=null;
if(!empty($_GET['edit'])){
  $editQuery=$pdo->prepare('SELECT l.* FROM lessons l JOIN subjects s ON s.id=l.subject_id WHERE l.id=? AND l.subject_id=? AND s.user_id=?');
  $editQuery->execute([(int)$_GET['edit'],$sid,$uid]);
  $editLesson=$editQuery->fetch()?:null;
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!hash_equals($csrfToken,(string)($_POST['csrf_token']??''))){$err='Your session expired. Reload and try again.';}
  elseif(($_POST['action']??'')==='delete_lesson'){
    $delete=$pdo->prepare('DELETE l FROM lessons l JOIN subjects s ON s.id=l.subject_id WHERE l.id=? AND l.subject_id=? AND s.user_id=?');
    $delete->execute([(int)($_POST['lesson_id']??0),$sid,$uid]);
    header('Location: lessons.php?subject_id='.$sid.'&deleted=1');
    exit;
  }
  elseif(($_POST['action']??'')==='update_lesson'){
    $lessonId=(int)($_POST['lesson_id']??0);
    $currentQuery=$pdo->prepare('SELECT l.* FROM lessons l JOIN subjects s ON s.id=l.subject_id WHERE l.id=? AND l.subject_id=? AND s.user_id=?');
    $currentQuery->execute([$lessonId,$sid,$uid]);
    $current=$currentQuery->fetch();
    $title=trim($_POST['title']??'');
    $text=trim($_POST['raw_text']??'');
    if(!$current){$err='Lesson not found.';}
    elseif($title===''||$text===''){$err='Lesson title and content are required.';$editLesson=$current;}
    else{
      $hashes=lessonContentHashes($text);
      $duplicate=$pdo->prepare('SELECT id,title FROM lessons WHERE normalized_content_hash=? AND id<>? AND subject_id IN (SELECT id FROM subjects WHERE user_id=?) LIMIT 1');
      $duplicate->execute([$hashes['normalized_content_hash'],$lessonId,$uid]);
      $duplicateLesson=$duplicate->fetch();
      $titleCheck=$pdo->prepare('SELECT id FROM lessons WHERE subject_id=? AND title=? AND id<>?');
      $titleCheck->execute([$sid,$title,$lessonId]);
      if($duplicateLesson){$err='This content already exists in another lesson: '.htmlspecialchars($duplicateLesson['title']).'. Reuse that lesson instead.';$editLesson=$current;}
      elseif($titleCheck->fetch()){$err='A lesson with that title already exists.';$editLesson=$current;}
      else{
        $contentChanged=!hash_equals((string)$current['content_hash'],$hashes['content_hash']);
        $titleChanged=$title!==(string)$current['title'];
        if($contentChanged||$titleChanged){
          $version=(int)$current['version']+1;
          $update=$pdo->prepare('UPDATE lessons SET title=?,raw_text=?,content_hash=?,normalized_content_hash=?,version=? WHERE id=?');
          $update->execute([$title,$text,$hashes['content_hash'],$hashes['normalized_content_hash'],$version,$lessonId]);
          if($contentChanged){
            saveLessonKnowledgeChunks($pdo,$uid,$lessonId,$version,$hashes['normalized_content']);
            $embedding=createLocalLessonEmbedding($hashes['normalized_content']);
            if($embedding)saveLessonEmbedding($pdo,$lessonId,$hashes['content_hash'],$embedding);
          }
          $_SESSION['upload_success']='Lesson updated to version '.$version.'. Earlier generated outputs are retained but will not be reused for this version.';
        }else{
          $_SESSION['upload_success']='No lesson changes to save.';
        }
        header('Location: lessons.php?subject_id='.$sid);
        exit;
      }
    }
  }
  elseif(isset($_POST['similar_action'])){
    $pending=$_SESSION['pending_lesson']??null;
  if(!$pending||(int)$pending['subject_id']!==$sid||!hash_equals((string)$pending['token'],(string)($_POST['pending_token']??''))){$err='The duplicate review expired. Submit the lesson again.';}
    elseif($_POST['similar_action']==='reuse'){
      $reusedLesson=$pending['similar'];
      $success='This content is similar to a lesson you already have. Reusing the existing lesson.';
      unset($_SESSION['pending_lesson']);
    }else{
      $titleCheck=$pdo->prepare('SELECT id FROM lessons WHERE subject_id=? AND title=?');
      $titleCheck->execute([$sid,$pending['title']]);
      if($titleCheck->fetch()){$err='A lesson with that title already exists.';}
      else{
        $hashes=lessonContentHashes($pending['text']);
        $insert=$pdo->prepare('INSERT INTO lessons (subject_id,title,raw_text,content_hash,normalized_content_hash,version) VALUES (?,?,?,?,?,1)');
        $insert->execute([$sid,$pending['title'],$pending['text'],$hashes['content_hash'],$hashes['normalized_content_hash']]);
        $newLessonId=(int)$pdo->lastInsertId();
        saveLessonKnowledgeChunks($pdo,$uid,$newLessonId,1,$hashes['normalized_content']);
        $embedding=createLocalLessonEmbedding($hashes['normalized_content']);
        if($embedding)saveLessonEmbedding($pdo,$newLessonId,$hashes['content_hash'],$embedding);
        $success='Lesson saved separately as requested. No AI was called.';
        unset($_SESSION['pending_lesson']);
      }
    }
  }
  else{
    $title=trim($_POST['title']??'');
    $text=trim($_POST['raw_text']??'');
    if(!$title){$err='Lesson title is required.';}
    elseif(!$text){$err='Lesson content is required.';}
    else{
      $hashes=lessonContentHashes($text);
      $duplicate=$pdo->prepare('SELECT l.id,l.title,s.subject_name FROM lessons l JOIN subjects s ON s.id=l.subject_id WHERE s.user_id=? AND l.normalized_content_hash=? ORDER BY l.id LIMIT 1');
      $duplicate->execute([$uid,$hashes['normalized_content_hash']]);
      $reusedLesson=$duplicate->fetch()?:null;
      if($reusedLesson){$success='This content is already stored. Reusing the existing lesson.';}
      else{
        $embedding=createLocalLessonEmbedding($hashes['normalized_content']);
        $similarLesson=$embedding?findSimilarLesson($pdo,$uid,$embedding,$hashes['normalized_content_hash']):null;
        if($similarLesson){
          $token=bin2hex(random_bytes(24));
          $_SESSION['pending_lesson']=['token'=>$token,'title'=>$title,'text'=>$text,'similar'=>$similarLesson,'subject_id'=>$sid];
        }else{
          $chk=$pdo->prepare('SELECT id FROM lessons WHERE subject_id=? AND title=?');
          $chk->execute([$sid,$title]);
          if($chk->fetch()){$err='A lesson with that title already exists.';}
          else{
            $ins=$pdo->prepare('INSERT INTO lessons (subject_id,title,raw_text,content_hash,normalized_content_hash,version) VALUES (?,?,?,?,?,1)');
            $ins->execute([$sid,$title,$text,$hashes['content_hash'],$hashes['normalized_content_hash']]);
            $newLessonId=(int)$pdo->lastInsertId();
            saveLessonKnowledgeChunks($pdo,$uid,$newLessonId,1,$hashes['normalized_content']);
            if($embedding)saveLessonEmbedding($pdo,$newLessonId,$hashes['content_hash'],$embedding);
            $success='Lesson saved. Choose a study output when you are ready; saving content does not call AI.';
          }
        }
      }
    }
  }
}
$pendingLesson=$_SESSION['pending_lesson']??null;
if($pendingLesson&&$pendingLesson['subject_id']===$sid)$similarLesson=$pendingLesson['similar'];

// Fetch lessons with tool count and quiz avg
$lessons=$pdo->prepare("
  SELECT l.*,
    (SELECT COUNT(*) FROM study_tools WHERE lesson_id=l.id) as tool_count,
    (SELECT ROUND(AVG((score/total)*100)) FROM quiz_results WHERE lesson_id=l.id AND user_id=? AND total>0) as avg_score,
    (SELECT COUNT(*) FROM quiz_results WHERE lesson_id=l.id AND user_id=?) as attempts
  FROM lessons l WHERE l.subject_id=? ORDER BY l.uploaded_at DESC
");
$lessons->execute([$uid,$uid,$sid]);$lessonList=$lessons->fetchAll();

// Compute subject progress
$doneCount=count(array_filter($lessonList,fn($l)=>$l['attempts']>0));
$pct=count($lessonList)>0?round(($doneCount/count($lessonList))*100):0;
$avgAll=count($lessonList)>0?round(array_sum(array_column($lessonList,'avg_score'))/max(1,count(array_filter($lessonList,fn($l)=>$l['avg_score']>0)))):0;

$pageTitle=htmlspecialchars($sub['subject_name']).' – StudiMate';
$topbarTitle=htmlspecialchars($sub['subject_name']);
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="page-body">

  <a href="subjects.php" style="display:inline-flex;align-items:center;gap:6px;font-size:0.78rem;font-weight:600;color:var(--text-mid);margin-bottom:14px;text-decoration:none">
    <i class="fas fa-chevron-left"></i> Back
  </a>

  <!-- SUBJECT HERO matching screenshot 3 -->
  <div class="subject-hero" style="background:linear-gradient(135deg,<?=htmlspecialchars($sub['color'])?>,<?=htmlspecialchars($sub['color'])?>cc)">
    <div class="subject-hero-code"><?=htmlspecialchars($sub['subject_code']??'')?></div>
    <div class="subject-hero-name"><?=htmlspecialchars($sub['subject_name'])?></div>
    <div class="subject-hero-meta"><?=count($lessonList)?> lesson<?=count($lessonList)!=1?'s':''?> &middot; <?=$pct?>% done<?=$avgAll?' &middot; Avg '.$avgAll.'%':''?></div>
    <div class="subject-hero-bar">
      <div class="subject-hero-fill" style="width:<?=$pct?>%"></div>
    </div>
    <div class="subject-hero-icon"><i data-lucide="book-open" aria-hidden="true"></i></div>
  </div>

  <!-- ADD LESSON BUTTON -->
  <button class="btn btn-primary w-full mb-6"
          style="border-radius:var(--radius-sm);padding:13px;font-size:0.88rem"
          onclick="openModal('addLessonModal')">
    <i class="fas fa-plus"></i> Add Lesson
  </button>

  <?php if($success):?>
  <div class="alert alert-success mb-4"><i class="fas fa-check-circle"></i><?=htmlspecialchars($success)?></div>
  <?php if($reusedLesson):?>
  <a class="btn btn-accent-outline btn-sm mb-4" href="ai_tools.php?lesson_id=<?=(int)$reusedLesson['id']?>">
    Open <?=htmlspecialchars($reusedLesson['title'])?><?=htmlspecialchars($reusedLesson['subject_name']!==$sub['subject_name']?' · '.$reusedLesson['subject_name']:'')?>
  </a>
  <?php endif;?>
  <?php endif;?>
  <?php if(isset($_GET['deleted'])):?>
  <div class="alert alert-danger mb-4"><i class="fas fa-trash"></i>Lesson deleted.</div>
  <?php endif;?>
  <?php if($similarLesson&&$pendingLesson):?>
  <div class="alert alert-warning"><i class="fas fa-clone"></i>Possible meaning-based duplicate: <strong><?=htmlspecialchars($similarLesson['title'])?></strong> (<?=number_format($similarLesson['similarity']*100,1)?>% similar). Similarity is only a suggestion; related ideas are not merged automatically.</div>
  <div class="flex gap-2 flex-wrap mb-4">
    <?php foreach(['reuse'=>'Reuse existing lesson','separate'=>'Save as separate lesson'] as $action=>$label):?>
        <form method="POST" action="lessons.php?subject_id=<?=$sid?>">
      <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrfToken)?>">
          <input type="hidden" name="subject_id" value="<?=$sid?>">
      <input type="hidden" name="pending_token" value="<?=htmlspecialchars($pendingLesson['token'])?>">
      <input type="hidden" name="similar_action" value="<?=$action?>">
      <button class="btn <?=$action==='reuse'?'btn-accent-outline':'btn-outline'?> btn-sm" type="submit"><?=$label?></button>
    </form>
    <?php endforeach;?>
  </div>
  <?php endif;?>

  <!-- TOPICS / LESSONS matching screenshot 3 -->
  <div class="section-header mb-4">
    <span class="section-title">Topics</span>
    <button class="btn btn-accent-outline btn-sm btn-pill" onclick="openModal('addLessonModal')">
      + Add
    </button>
  </div>

  <div class="card mb-6">
    <div class="card-body" style="padding:8px 16px">
      <?php if(empty($lessonList)):?>
      <div class="empty-state" style="padding:40px">
        <i class="fas fa-file-alt"></i>
        <h4>No lessons yet</h4>
        <p>Add your first lesson to start generating AI study tools.</p>
      </div>
      <?php else:?>
      <?php foreach($lessonList as $l):
        $hasTools=$l['tool_count']>0;
        $avgS=(int)$l['avg_score'];
        $scoreColor=$avgS>=80?'var(--success)':($avgS>=50?'var(--warning)':'var(--danger)');
      ?>
      <div style="display:flex;align-items:center;gap:12px;padding:13px 0;border-bottom:1px solid var(--border)">
        <div style="width:28px;height:28px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;
          background:<?=$hasTools?'var(--success)':'var(--border)'?>;color:<?=$hasTools?'#fff':'var(--text-light)'?>;font-size:0.7rem">
          <i class="fas fa-<?=$hasTools?'check':'file-alt'?>"></i>
        </div>
        <div style="flex:1">
          <div style="font-size:0.86rem;font-weight:600;color:var(--text-dark)"><?=htmlspecialchars($l['title'])?></div>
          <div style="font-size:0.72rem;color:var(--text-light)">
            <?=$l['attempts']?> quiz<?=$l['attempts']!=1?'zes':''?>
            <?php if($l['avg_score']):?>&bull; Avg <span style="color:<?=$scoreColor?>;font-weight:700"><?=$avgS?>%</span><?php endif;?>
          </div>
        </div>
        <div style="display:flex;gap:6px;align-items:center">
            <a href="lessons.php?subject_id=<?=$sid?>&edit=<?=(int)$l['id']?>#editLessonModal"
             class="btn btn-outline btn-sm btn-pill" style="font-size:0.75rem">Edit</a>
          <a href="study.php?lesson_id=<?=(int)$l['id']?>"
             class="btn btn-accent-outline btn-sm btn-pill" style="font-size:0.75rem">
            Study
          </a>
          <form method="POST" onsubmit="return confirm('Delete <?=htmlspecialchars(addslashes($l['title']))?>? This cannot be undone.')">
            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrfToken)?>">
            <input type="hidden" name="action" value="delete_lesson">
            <input type="hidden" name="lesson_id" value="<?=(int)$l['id']?>">
            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--text-light);padding:6px 8px"><i class="fas fa-trash"></i></button>
          </form>
        </div>
      </div>
      <?php endforeach;?>
      <?php endif;?>
    </div>
  </div>

</div>

<!-- ADD LESSON MODAL -->
<div class="modal-overlay" id="addLessonModal">
  <div class="modal-box" style="max-width:560px">
    <div class="modal-header">
      <h3><?=$editLesson?'Edit Lesson':'Add Lesson'?></h3>
      <button class="modal-close" onclick="closeModal('addLessonModal')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="lessons.php?subject_id=<?=$sid?>" enctype="multipart/form-data" id="lessonForm">
      <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrfToken)?>">
      <input type="hidden" name="subject_id" value="<?=$sid?>">
      <?php if($editLesson):?>
      <input type="hidden" name="action" value="update_lesson">
      <input type="hidden" name="lesson_id" value="<?=(int)$editLesson['id']?>">
      <?php endif;?>
      <div class="modal-body">
        <?php if($err):?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?=htmlspecialchars($err)?></div>
        <?php endif;?>
        <div class="form-group">
          <label>Lesson Title *</label>
          <input type="text" name="title" class="form-control form-control-flat"
               placeholder="e.g. Chapter 1: Introduction to Arrays" required maxlength="200" value="<?=htmlspecialchars($editLesson['title']??'')?>">
        </div>
        <?php if(!$editLesson):?>
        <div class="form-group">
          <label>Lesson Context *</label>
          <div role="group" aria-label="Choose lesson context type" style="display:flex;gap:8px;margin-bottom:12px">
            <button type="button" class="btn btn-primary btn-sm" id="lessonTextMode" aria-pressed="true" style="width:auto">Enter text</button>
            <button type="button" class="btn btn-outline btn-sm" id="lessonFileMode" aria-pressed="false" style="width:auto">Upload a file</button>
          </div>
          <div id="lessonTextPanel">
            <textarea name="raw_text" id="lessonText" class="form-control" rows="7"
                      placeholder="Paste lesson content here. You can request an explanation, quiz, or flashcards separately after saving." required></textarea>
          </div>
          <div id="lessonFilePanel" hidden>
            <label for="lessonMaterial">Study material <span style="color:var(--text-light);font-weight:400;font-size:0.72rem">(PDF, DOCX, PPT, PPTX; 20 MB max)</span></label>
            <div id="lessonDropZone" tabindex="0" role="button" aria-controls="lessonMaterial" aria-label="Choose a study material file or drag and drop it here"
                 style="border:2px dashed var(--border);border-radius:var(--radius-sm);padding:22px 14px;text-align:center;cursor:pointer;color:var(--text-mid);">
              <i class="fas fa-cloud-upload-alt" style="font-size:1.4rem;margin-bottom:8px;color:var(--primary)"></i>
              <div id="lessonFilePrompt">Drag and drop a file here, or <span style="color:var(--primary);font-weight:600;text-decoration:underline">browse files</span></div>
              <div style="font-size:0.72rem;color:var(--text-light);margin-top:5px">PDF, DOCX, PPT, or PPTX · up to 20 MB</div>
              <div id="lessonFileName" aria-live="polite" style="font-size:0.78rem;font-weight:600;margin-top:8px"></div>
            </div>
            <input id="lessonMaterial" type="file" name="material" accept=".pdf,.docx,.ppt,.pptx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation" style="display:none">
            <label for="lessonNotes" style="margin-top:12px">Additional notes <span style="color:var(--text-light);font-weight:400;font-size:0.72rem">(optional)</span></label>
            <textarea name="raw_text" id="lessonNotes" class="form-control" rows="3" disabled placeholder="Add notes to combine with the text extracted from your file."></textarea>
          </div>
          <div style="font-size:0.7rem;color:var(--text-light);margin-top:6px">
            <i class="fas fa-info-circle"></i> The text you enter or readable text extracted from the file will be saved as lesson content. Saving does not call AI.
          </div>
        </div>
        <?php else:?>
        <div class="form-group">
          <label>Lesson Content *</label>
          <textarea name="raw_text" class="form-control" rows="7"
                    placeholder="Edit lesson content here." required><?=htmlspecialchars($editLesson['raw_text']??'')?></textarea>
          <div style="font-size:0.7rem;color:var(--text-light);margin-top:4px">
            <i class="fas fa-info-circle"></i> Changes create a new source version; cached outputs for older versions remain available only as history.
          </div>
        </div>
        <?php endif;?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline btn-pill btn-sm" onclick="closeModal('addLessonModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-pill btn-sm" style="width:auto;padding:9px 20px">
          <i class="fas fa-save"></i> <?=$editLesson?'Save Changes':'Save Lesson'?>
        </button>
      </div>
    </form>
  </div>
</div>

<script src="https://unpkg.com/lucide@1.56.0"></script>
<?php
$extraJs="lucide.createIcons();".($editLesson?"document.addEventListener('DOMContentLoaded',()=>openModal('addLessonModal'));":"
document.addEventListener('DOMContentLoaded',()=>{
  const form=document.getElementById('lessonForm');
  const textMode=document.getElementById('lessonTextMode');
  const fileMode=document.getElementById('lessonFileMode');
  if(!form||!textMode||!fileMode)return;
  const textPanel=document.getElementById('lessonTextPanel');
  const filePanel=document.getElementById('lessonFilePanel');
  const textInput=document.getElementById('lessonText');
  const notesInput=document.getElementById('lessonNotes');
  const fileInput=document.getElementById('lessonMaterial');
  const dropZone=document.getElementById('lessonDropZone');
  const fileName=document.getElementById('lessonFileName');
  const textAction='lessons.php?subject_id='+encodeURIComponent(".$sid.");
  const fileAction='upload_document.php';
  function setMode(mode){
    const uploading=mode==='file';
    textPanel.hidden=uploading;
    filePanel.hidden=!uploading;
    textInput.disabled=uploading;
    textInput.required=!uploading;
    notesInput.disabled=!uploading;
    fileInput.required=uploading;
    form.action=uploading?fileAction:textAction;
    textMode.classList.toggle('btn-primary',!uploading);
    textMode.classList.toggle('btn-outline',uploading);
    textMode.setAttribute('aria-pressed',String(!uploading));
    fileMode.classList.toggle('btn-primary',uploading);
    fileMode.classList.toggle('btn-outline',!uploading);
    fileMode.setAttribute('aria-pressed',String(uploading));
  }
  function showFile(file){
    if(!file)return;
    const transfer=new DataTransfer();
    transfer.items.add(file);
    fileInput.files=transfer.files;
    fileName.textContent=file.name;
  }
  textMode.addEventListener('click',()=>setMode('text'));
  fileMode.addEventListener('click',()=>setMode('file'));
  fileInput.addEventListener('change',()=>{fileName.textContent=fileInput.files[0]?.name||'';});
  dropZone.addEventListener('click',()=>fileInput.click());
  dropZone.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();fileInput.click();}});
  ['dragenter','dragover'].forEach(type=>dropZone.addEventListener(type,event=>{event.preventDefault();dropZone.style.borderColor='var(--primary)';}));
  ['dragleave','drop'].forEach(type=>dropZone.addEventListener(type,event=>{event.preventDefault();dropZone.style.borderColor='var(--border)';}));
  dropZone.addEventListener('drop',event=>showFile(event.dataTransfer.files[0]));
});
");
include '../includes/footer.php';
?>
