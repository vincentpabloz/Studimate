<?php
session_start();
if(!isset($_SESSION['user_id'])){header("Location: ../index.php");exit;}
require '../config/db.php';
$uid=$_SESSION['user_id'];$base='../';

$csrfToken=$_SESSION['csrf_token']??bin2hex(random_bytes(32));
$_SESSION['csrf_token']=$csrfToken;

$colors=['#5B5FDE','#22C55E','#EF4444','#F59E0B','#8B5CF6','#06B6D4','#EC4899','#F97316'];
$icons=['📘','📗','📕','📙','📓','📔','📐','🔬','🌍','💻','📊','🎨'];

$err='';$pageErr='';$openModal='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'add';
  if(!hash_equals($csrfToken,(string)($_POST['csrf_token']??''))){
    $pageErr='Your session expired. Reload the page and try again.';
  }elseif($action==='delete'){
    $del=$pdo->prepare("DELETE FROM subjects WHERE id=? AND user_id=?");
    $del->execute([(int)($_POST['subject_id']??0),$uid]);
    header("Location: subjects.php?deleted=1");exit;
  }elseif($action==='update'){
    $id=(int)($_POST['subject_id']??0);
    $cur=$pdo->prepare("SELECT * FROM subjects WHERE id=? AND user_id=?");
    $cur->execute([$id,$uid]);$current=$cur->fetch();
    $name=trim($_POST['subject_name']??'');
    $code=trim($_POST['subject_code']??'');
    if(!$current){$pageErr='Subject not found.';}
    elseif($name===''){$pageErr='Subject name is required.';}
    elseif(mb_strlen($name)>150||mb_strlen($code)>50){$pageErr='Subject name or code is too long.';}
    else{
      $color=in_array($_POST['color']??'',$colors,true)?$_POST['color']:$current['color'];
      $icon=in_array($_POST['icon']??'',$icons,true)?$_POST['icon']:$current['icon'];
      $dup=$pdo->prepare("SELECT id FROM subjects WHERE user_id=? AND subject_name=? AND id<>?");
      $dup->execute([$uid,$name,$id]);
      if($dup->fetch()){$pageErr='You already have a subject with that name.';}
      else{
        $upd=$pdo->prepare("UPDATE subjects SET subject_name=?,subject_code=?,color=?,icon=? WHERE id=? AND user_id=?");
        $upd->execute([$name,$code,$color,$icon,$id,$uid]);
        header("Location: subjects.php?updated=1");exit;
      }
    }
  }else{
    $name=trim($_POST['subject_name']??'');
    $code=trim($_POST['subject_code']??'');
    $color=in_array($_POST['color']??'',$colors,true)?$_POST['color']:$colors[0];
    $icon=in_array($_POST['icon']??'',$icons,true)?$_POST['icon']:$icons[0];
    if(!$name){$err='Subject name is required.';}
    elseif(mb_strlen($name)>150||mb_strlen($code)>50){$err='Subject name or code is too long.';}
    else{
      $chk=$pdo->prepare("SELECT id FROM subjects WHERE user_id=? AND subject_name=?");
      $chk->execute([$uid,$name]);
      if($chk->rowCount()>0){$err='You already have a subject with that name.';}
      else{
        $ins=$pdo->prepare("INSERT INTO subjects (user_id,subject_name,subject_code,color,icon) VALUES (?,?,?,?,?)");
        $ins->execute([$uid,$name,$code,$color,$icon]);
        header("Location: subjects.php?added=1");exit;
      }
    }
    if($err)$openModal='addSubjectModal';
  }
}

$stmt=$pdo->prepare("
  SELECT s.*,COUNT(DISTINCT l.id) as lesson_count,
    COALESCE(ROUND(AVG((qr.score/qr.total)*100)),0) as avg_pct,
    MAX(qr.taken_at) as last_activity
  FROM subjects s
  LEFT JOIN lessons l ON l.subject_id=s.id
  LEFT JOIN quiz_results qr ON qr.lesson_id=l.id AND qr.user_id=s.user_id
  WHERE s.user_id=?
  GROUP BY s.id ORDER BY s.created_at DESC
");
$stmt->execute([$uid]);$subjects=$stmt->fetchAll();

$pageTitle='Subjects – StudiMate';
$topbarTitle='Subjects';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="page-body">

  <?php if(isset($_GET['added'])):?>
  <div class="alert alert-success mb-4"><i class="fas fa-check-circle"></i>Subject added!</div>
  <?php endif;?>
  <?php if(isset($_GET['updated'])):?>
  <div class="alert alert-success mb-4"><i class="fas fa-check-circle"></i>Subject updated!</div>
  <?php endif;?>
  <?php if($pageErr):?>
  <div class="alert alert-danger mb-4"><i class="fas fa-exclamation-circle"></i><?=htmlspecialchars($pageErr)?></div>
  <?php endif;?>
  <?php if(isset($_GET['deleted'])):?>
  <div class="alert alert-danger mb-4"><i class="fas fa-trash"></i>Subject deleted.</div>
  <?php endif;?>

  <!-- HEADER ROW -->
  <div class="section-header mb-6">
    <span class="section-title">All Subjects (<?=count($subjects)?>)</span>
    <button class="btn btn-primary btn-pill btn-sm" onclick="openModal('addSubjectModal')">
      <i class="fas fa-plus"></i> New Subject
    </button>
  </div>

  <?php if(empty($subjects)):?>
  <div class="card">
    <div class="card-body">
      <div class="empty-state" style="padding:60px 20px">
        <i class="fas fa-book-open"></i>
        <h4>No subjects yet</h4>
        <p>Create your first subject folder to organize your study materials.</p>
        <button class="btn btn-primary btn-pill mt-4" style="width:auto;padding:10px 24px"
                onclick="openModal('addSubjectModal')">
          <i class="fas fa-plus"></i> Add Subject
        </button>
      </div>
    </div>
  </div>
  <?php else:?>
  <div class="subjects-grid">
    <?php
    $iconList=['📘','📗','📕','📙','📓','📔','📐','🔬','🌍','💻','📊','🎨'];
    foreach($subjects as $i=>$s):
      $pct=(int)$s['avg_pct'];
      $icon=$s['icon']??$iconList[$i%count($iconList)];
      $timeAgo='';
      if($s['last_activity']){
        $diff=time()-strtotime($s['last_activity']);
        $timeAgo=$diff<3600?'Just now':($diff<86400?round($diff/3600).'h ago':($diff<172800?'Yesterday':date('M d',strtotime($s['last_activity']))));
      }
    ?>
    <div class="subject-card" onclick="window.location='lessons.php?subject_id=<?=$s['id']?>'">
      <div class="subject-card-top" style="background:<?=htmlspecialchars($s['color'])?>"></div>
      <div class="subject-card-body">
        <div class="subject-card-row">
          <div>
            <div class="subject-card-code"><?=htmlspecialchars($s['subject_code']??'')?></div>
            <div class="subject-card-name"><?=htmlspecialchars($s['subject_name'])?></div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px">
            <div class="subject-card-icon"><?=$icon?></div>
            <div style="display:flex;align-items:center;gap:6px">
              <button type="button" onclick="event.stopPropagation();openEditSubject(this)"
                data-id="<?=(int)$s['id']?>" data-name="<?=htmlspecialchars($s['subject_name'])?>"
                data-code="<?=htmlspecialchars($s['subject_code']??'')?>" data-icon="<?=htmlspecialchars($icon)?>"
                data-color="<?=htmlspecialchars($s['color'])?>"
                style="background:none;border:none;cursor:pointer;color:var(--text-light);font-size:0.75rem;padding:2px"
                title="Rename / edit">
                <i class="fas fa-pen"></i>
              </button>
              <form method="POST" style="margin:0" onclick="event.stopPropagation()"
                    onsubmit="return confirm(<?=htmlspecialchars(json_encode('Delete "'.$s['subject_name'].'"? This cannot be undone.'),ENT_QUOTES)?>)">
                <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrfToken)?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="subject_id" value="<?=(int)$s['id']?>">
                <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--text-light);font-size:0.75rem;padding:2px" title="Delete">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </div>
          </div>
        </div>
        <div class="subject-card-topics"><?=$s['lesson_count']?> lesson<?=$s['lesson_count']!=1?'s':''?></div>
        <div class="subject-card-progress">
          <div class="subject-card-fill" style="width:<?=$pct?>%;background:<?=htmlspecialchars($s['color'])?>"></div>
        </div>
        <div class="subject-card-footer">
          <span class="subject-card-pct"><?=$pct?>%</span>
          <?php if($timeAgo):?>
          <span class="subject-card-time"><i class="fas fa-clock"></i><?=$timeAgo?></span>
          <?php endif;?>
        </div>
      </div>
    </div>
    <?php endforeach;?>

    <!-- ADD NEW card -->
    <div class="subject-card" style="border:2px dashed var(--border);box-shadow:none;display:flex;align-items:center;justify-content:center;min-height:160px"
         onclick="openModal('addSubjectModal')">
      <div class="text-center text-muted" style="padding:20px">
        <i class="fas fa-plus-circle" style="font-size:1.8rem;opacity:0.25;display:block;margin-bottom:8px"></i>
        <p class="text-sm font-bold">Add Subject</p>
      </div>
    </div>
  </div>
  <?php endif;?>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="addSubjectModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3>New Subject</h3>
      <button class="modal-close" onclick="closeModal('addSubjectModal')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrfToken)?>">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <?php if($err):?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?=htmlspecialchars($err)?></div>
        <?php endif;?>
        <div class="form-group">
          <label>Subject Name *</label>
          <input type="text" name="subject_name" class="form-control form-control-flat"
                 placeholder="e.g. Mathematics" required maxlength="150">
        </div>
        <div class="form-group">
          <label>Subject Code <span style="color:var(--text-light);font-weight:400">(optional)</span></label>
          <input type="text" name="subject_code" class="form-control form-control-flat"
                 placeholder="e.g. MATH 101" maxlength="50">
        </div>
        <div class="form-group">
          <label>Icon</label>
          <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px" id="iconPicker">
            <?php foreach($icons as $ii=>$ic):?>
            <span onclick="selectIcon(this,'<?=$ic?>')"
              style="font-size:1.4rem;cursor:pointer;padding:6px;border-radius:8px;border:2px solid transparent;transition:all 0.15s;<?=$ii===0?'border-color:var(--primary);background:var(--primary-light)':''?>"
              data-icon="<?=$ic?>"><?=$ic?></span>
            <?php endforeach;?>
          </div>
          <input type="hidden" name="icon" id="iconInput" value="<?=$icons[0]?>">
        </div>
        <div class="form-group">
          <label>Color</label>
          <div style="display:flex;gap:8px;margin-top:6px;flex-wrap:wrap">
            <?php foreach($colors as $ci=>$c):?>
            <span class="color-chip <?=$ci===0?'selected':''?>" data-color="<?=$c?>"
                  style="background:<?=$c?>;width:22px;height:22px"></span>
            <?php endforeach;?>
          </div>
          <input type="hidden" name="color" id="colorInput" value="<?=$colors[0]?>">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline btn-pill btn-sm" onclick="closeModal('addSubjectModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-pill btn-sm" style="width:auto;padding:9px 20px">
          <i class="fas fa-plus"></i> Add Subject
        </button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<style>
.edit-color-chip{width:22px;height:22px;border-radius:50%;display:inline-block;border:2px solid transparent;cursor:pointer;transition:var(--transition)}
.edit-color-chip.selected{border-color:var(--text-dark);transform:scale(1.2)}
</style>
<div class="modal-overlay" id="editSubjectModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3>Edit Subject</h3>
      <button class="modal-close" onclick="closeModal('editSubjectModal')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrfToken)?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="subject_id" id="editSubjectId" value="">
      <div class="modal-body">
        <div class="form-group">
          <label>Subject Name *</label>
          <input type="text" name="subject_name" id="editName" class="form-control form-control-flat" required maxlength="150">
        </div>
        <div class="form-group">
          <label>Subject Code <span style="color:var(--text-light);font-weight:400">(optional)</span></label>
          <input type="text" name="subject_code" id="editCode" class="form-control form-control-flat" maxlength="50">
        </div>
        <div class="form-group">
          <label>Icon</label>
          <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px" id="editIconPicker">
            <?php foreach($icons as $ic):?>
            <span onclick="selectEditIcon('<?=$ic?>')" data-icon="<?=$ic?>"
              style="font-size:1.4rem;cursor:pointer;padding:6px;border-radius:8px;border:2px solid transparent;transition:all 0.15s"><?=$ic?></span>
            <?php endforeach;?>
          </div>
          <input type="hidden" name="icon" id="editIconInput" value="">
        </div>
        <div class="form-group">
          <label>Color</label>
          <div style="display:flex;gap:8px;margin-top:6px;flex-wrap:wrap" id="editColorPicker">
            <?php foreach($colors as $c):?>
            <span class="edit-color-chip" data-color="<?=$c?>" style="background:<?=$c?>" onclick="selectEditColor('<?=$c?>')"></span>
            <?php endforeach;?>
          </div>
          <input type="hidden" name="color" id="editColorInput" value="">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline btn-pill btn-sm" onclick="closeModal('editSubjectModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-pill btn-sm" style="width:auto;padding:9px 20px">
          <i class="fas fa-save"></i> Save Changes
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function selectIcon(el,icon){
  document.querySelectorAll('#iconPicker span').forEach(s=>{
    s.style.borderColor='transparent';s.style.background='transparent';
  });
  el.style.borderColor='var(--primary)';el.style.background='var(--primary-light)';
  document.getElementById('iconInput').value=icon;
}
function selectEditIcon(icon){
  document.querySelectorAll('#editIconPicker span').forEach(s=>{
    const on=s.dataset.icon===icon;
    s.style.borderColor=on?'var(--primary)':'transparent';
    s.style.background=on?'var(--primary-light)':'transparent';
  });
  document.getElementById('editIconInput').value=icon;
}
function selectEditColor(color){
  document.querySelectorAll('#editColorPicker .edit-color-chip').forEach(c=>c.classList.toggle('selected',c.dataset.color===color));
  document.getElementById('editColorInput').value=color;
}
function openEditSubject(btn){
  document.getElementById('editSubjectId').value=btn.dataset.id;
  document.getElementById('editName').value=btn.dataset.name;
  document.getElementById('editCode').value=btn.dataset.code;
  selectEditIcon(btn.dataset.icon);
  selectEditColor(btn.dataset.color);
  openModal('editSubjectModal');
}
</script>

<?php $extraJs=$openModal?"openModal('".$openModal."');":'';include '../includes/footer.php';?>