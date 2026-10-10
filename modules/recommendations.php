<?php
session_start();
if(!isset($_SESSION['user_id'])){header("Location: ../index.php");exit;}
require '../config/db.php';
$uid=$_SESSION['user_id'];$base='../';

// Auto recommendations from low quiz scores
$weak=$pdo->prepare("
  SELECT l.id as lesson_id,l.title,s.subject_name,s.color,
    ROUND(AVG((qr.score/qr.total)*100)) as avg_pct,
    COUNT(qr.id) as quiz_count
  FROM quiz_results qr
  JOIN lessons l ON qr.lesson_id=l.id
  JOIN subjects s ON l.subject_id=s.id
  WHERE qr.user_id=? AND qr.total>0
  GROUP BY l.id HAVING avg_pct<75
  ORDER BY avg_pct ASC LIMIT 8
");
$weak->execute([$uid]);$weakList=$weak->fetchAll();

// Overdue reviews
$overdue=$pdo->prepare("
  SELECT l.id as lesson_id,l.title,s.subject_name,s.color,
    DATEDIFF(CURDATE(),rs.next_review_date) as days_overdue
  FROM review_schedule rs
  JOIN lessons l ON rs.lesson_id=l.id
  JOIN subjects s ON l.subject_id=s.id
  WHERE rs.user_id=? AND rs.next_review_date<CURDATE()
  ORDER BY rs.next_review_date ASC LIMIT 5
");
$overdue->execute([$uid]);$overdueList=$overdue->fetchAll();

$pageTitle='Recommendations – StudiMate';
include '../includes/header.php';
include '../includes/sidebar.php';
$topbarTitle='Recommendations';
?>

<div class="page-body">

  <?php if(!empty($overdueList)):?>
  <div class="alert alert-danger mb-4">
    <i class="fas fa-fire"></i>
    <strong><?=count($overdueList)?> overdue review<?=count($overdueList)>1?'s':''?></strong> — study these first!
  </div>
  <div class="card mb-6">
    <div class="card-header">
      <h3>🔴 Overdue — Do These First</h3>
      <span class="badge badge-danger"><?=count($overdueList)?></span>
    </div>
    <div class="card-body" style="padding:8px 18px">
      <?php foreach($overdueList as $r):?>
      <div class="lesson-item">
        <div class="lesson-icon" style="background:#FEE2E2;color:var(--danger)">
          <i class="fas fa-exclamation"></i>
        </div>
        <div class="lesson-info" style="flex:1">
          <div class="lesson-title"><?=htmlspecialchars($r['title'])?></div>
          <div class="lesson-meta"><?=htmlspecialchars($r['subject_name'])?> &bull; <?=$r['days_overdue']?> day<?=$r['days_overdue']>1?'s':''?> overdue</div>
        </div>
        <a href="ai_tools.php?lesson_id=<?=$r['lesson_id']?>&tab=quiz" class="btn btn-danger btn-pill btn-sm" style="width:auto">
          Review Now
        </a>
      </div>
      <?php endforeach;?>
    </div>
  </div>
  <?php endif;?>

  <!-- NEEDS IMPROVEMENT -->
  <div class="card">
    <div class="card-header">
      <h3>💡 Needs Improvement</h3>
      <?php if(!empty($weakList)):?><span class="badge badge-primary"><?=count($weakList)?></span><?php endif;?>
    </div>
    <div class="card-body" style="padding:8px 18px">
      <?php if(empty($weakList)&&empty($overdueList)):?>
      <div class="empty-state" style="padding:48px">
        <i class="fas fa-trophy"></i>
        <h4>Great job! No low-scoring lessons.</h4>
        <p>Keep taking quizzes and we'll flag anything that needs attention.</p>
      </div>
      <?php elseif(empty($weakList)):?>
      <div class="empty-state"><i class="fas fa-check-circle"></i><h4>All lessons above target!</h4></div>
      <?php else:?>
      <?php foreach($weakList as $r):
        $pct=(int)$r['avg_pct'];
        $c=$pct<40?'var(--danger)':($pct<60?'var(--warning)':'var(--primary)');
        $pri=$pct<40?'Urgent':($pct<60?'High':'Medium');
        $bc=$pct<40?'badge-danger':($pct<60?'badge-warning':'badge-primary');
      ?>
      <div class="lesson-item">
        <div class="lesson-icon" style="background:<?=$c?>22;color:<?=$c?>">
          <i class="fas fa-chart-line"></i>
        </div>
        <div class="lesson-info" style="flex:1">
          <div class="lesson-title"><?=htmlspecialchars($r['title'])?></div>
          <div class="lesson-meta">
            <?=htmlspecialchars($r['subject_name'])?> &bull;
            Avg: <strong style="color:<?=$c?>"><?=$pct?>%</strong> &bull;
            <?=$r['quiz_count']?> quiz<?=$r['quiz_count']>1?'zes':''?>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:7px">
          <span class="badge <?=$bc?>"><?=$pri?></span>
          <a href="ai_tools.php?lesson_id=<?=$r['lesson_id']?>&tab=explanation" class="btn btn-ghost btn-sm">Study</a>
          <a href="ai_tools.php?lesson_id=<?=$r['lesson_id']?>&tab=quiz" class="btn btn-primary btn-pill btn-sm" style="width:auto">Quiz</a>
        </div>
      </div>
      <?php endforeach;?>
      <?php endif;?>
    </div>
  </div>

</div>

<?php include '../includes/footer.php';?>
