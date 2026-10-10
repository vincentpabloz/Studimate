<?php
session_start();
if(!isset($_SESSION['user_id'])){header("Location: ../index.php");exit;}
require '../config/db.php';
$uid=$_SESSION['user_id'];$base='../';

// Delete
if(isset($_GET['delete'])){
  $del=$pdo->prepare("DELETE FROM subjects WHERE id=? AND user_id=?");
  $del->execute([(int)$_GET['delete'],$uid]);
  header("Location: subjects.php?deleted=1");exit;
}

// Add
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $name=trim($_POST['subject_name']??'');
  $code=trim($_POST['subject_code']??'');
  $color=$_POST['color']??'#5B5FDE';
  $icon=$_POST['icon']??'📘';
  if(!$name){$err='Subject name is required.';}
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

$colors=['#5B5FDE','#22C55E','#EF4444','#F59E0B','#8B5CF6','#06B6D4','#EC4899','#F97316'];
$icons=['📘','📗','📕','📙','📓','📔','📐','🔬','🌍','💻','📊','🎨'];

$pageTitle='Subjects – StudiMate';
include '../includes/header.php';
include '../includes/sidebar.php';
$topbarTitle='Subjects';
?>

<div class="page-body">

  <?php if(isset($_GET['added'])):?>
  <div class="alert alert-success mb-4"><i class="fas fa-check-circle"></i>Subject added!</div>
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
            <button onclick="event.stopPropagation();confirmDelete('subjects.php?delete=<?=$s['id']?>','<?=htmlspecialchars($s['subject_name'])?>')"
              style="background:none;border:none;cursor:pointer;color:var(--text-light);font-size:0.75rem;padding:2px"
              title="Delete">
              <i class="fas fa-trash"></i>
            </button>
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

<script>
function selectIcon(el,icon){
  document.querySelectorAll('#iconPicker span').forEach(s=>{
    s.style.borderColor='transparent';s.style.background='transparent';
  });
  el.style.borderColor='var(--primary)';el.style.background='var(--primary-light)';
  document.getElementById('iconInput').value=icon;
}
</script>

<?php include '../includes/footer.php';?>
