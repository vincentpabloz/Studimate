<?php
session_start();
if(!isset($_SESSION['user_id'])){header("Location: index.php");exit;}
require 'config/db.php';
$uid=$_SESSION['user_id'];

// Stats
$avgQ=$pdo->prepare("SELECT COALESCE(ROUND(AVG((score/total)*100)),0) FROM quiz_results WHERE user_id=? AND total>0");
$avgQ->execute([$uid]);$avg=$avgQ->fetchColumn();

$dueCount=$pdo->prepare("SELECT COUNT(*) FROM review_schedule WHERE user_id=? AND next_review_date<=CURDATE()");
$dueCount->execute([$uid]);$due=$dueCount->fetchColumn();

// Streak (days with at least one quiz)
$streak=$pdo->prepare("SELECT COUNT(DISTINCT DATE(taken_at)) FROM quiz_results WHERE user_id=? AND taken_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)");
$streak->execute([$uid]);$streakDays=$streak->fetchColumn();

// Subjects with lesson count and avg score
$subjects=$pdo->prepare("
  SELECT s.*,COUNT(DISTINCT l.id) as lesson_count,
    COALESCE(ROUND(AVG((qr.score/qr.total)*100)),0) as avg_pct,
    MAX(qr.taken_at) as last_activity
  FROM subjects s
  LEFT JOIN lessons l ON l.subject_id=s.id
  LEFT JOIN quiz_results qr ON qr.lesson_id=l.id AND qr.user_id=?
  WHERE s.user_id=?
  GROUP BY s.id ORDER BY s.created_at DESC LIMIT 6
");
$subjects->execute([$uid,$uid]);$subjectList=$subjects->fetchAll();

// Upcoming reviews
$upcoming=$pdo->prepare("
  SELECT rs.*,l.title,s.subject_name,s.color
  FROM review_schedule rs
  JOIN lessons l ON rs.lesson_id=l.id
  JOIN subjects s ON l.subject_id=s.id
  WHERE rs.user_id=? ORDER BY rs.next_review_date ASC LIMIT 5
");
$upcoming->execute([$uid]);$reviews=$upcoming->fetchAll();

// Reminders (respect the user's notification settings)
$prefQ=$pdo->prepare("SELECT notif_reviews,notif_quizzes FROM users WHERE id=?");
$prefQ->execute([$uid]);
$prefs=$prefQ->fetch()?:['notif_reviews'=>1,'notif_quizzes'=>0];
$dueList=[];
if($due>0&&!empty($prefs['notif_reviews'])){
  $dl=$pdo->prepare("
    SELECT l.id AS lesson_id,l.title,s.subject_name,rs.next_review_date
    FROM review_schedule rs
    JOIN lessons l ON l.id=rs.lesson_id
    JOIN subjects s ON s.id=l.subject_id
    WHERE rs.user_id=? AND rs.next_review_date<=CURDATE()
    ORDER BY rs.next_review_date ASC LIMIT 3
  ");
  $dl->execute([$uid]);$dueList=$dl->fetchAll();
}
$noQuizToday=false;
if(!empty($prefs['notif_quizzes'])){
  $qt=$pdo->prepare("SELECT COUNT(*) FROM quiz_results WHERE user_id=? AND DATE(taken_at)=CURDATE()");
  $qt->execute([$uid]);
  $noQuizToday=((int)$qt->fetchColumn()===0);
}

$hour=(int)date('G');
$greet=$hour<12?'Good morning':($hour<18?'Good afternoon':'Good evening');
$firstName=explode(' ',$_SESSION['user_name'])[0];

$pageTitle='Dashboard – StudiMate';
$topbarTitle='Dashboard';
$base='';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="page-body">

  <!-- REMINDERS -->
  <?php if($dueList):?>
  <div class="alert alert-warning mb-4" role="alert">
    <i class="fas fa-bell"></i>
    <div style="flex:1">
      <strong>You have <?=(int)$due?> review<?=$due>1?'s':''?> due.</strong>
      <ul style="margin:6px 0 0 16px;padding:0;font-weight:500">
        <?php foreach($dueList as $d):
          $overdue=strtotime($d['next_review_date'])<strtotime('today');?>
        <li>
          <a href="modules/study.php?lesson_id=<?=(int)$d['lesson_id']?>" style="color:inherit;text-decoration:underline"><?=htmlspecialchars($d['title'])?></a>
          &middot; <?=htmlspecialchars($d['subject_name'])?> &middot; <?=$overdue?'Overdue':'Due today'?>
        </li>
        <?php endforeach;?>
      </ul>
      <?php if($due>count($dueList)):?>
      <div style="margin-top:6px;font-weight:500">+ <?=(int)($due-count($dueList))?> more &middot; <a href="modules/schedule.php" style="color:inherit;text-decoration:underline">View schedule</a></div>
      <?php endif;?>
    </div>
  </div>
  <?php endif;?>
  <?php if($noQuizToday):?>
  <div class="alert alert-info mb-4" role="status">
    <i class="fas fa-lightbulb"></i>
    <div>You haven't taken a quiz today. <a href="modules/ai_tools.php" style="color:inherit;font-weight:700;text-decoration:underline">Generate a quiz</a> to keep your streak going.</div>
  </div>
  <?php endif;?>

  <!-- HERO BANNER -->
  <div class="hero-banner mb-6">
    <div class="hero-date"><?=date('l, F j, Y')?></div>
    <div class="hero-greeting"><?=$greet?>, <?=htmlspecialchars($firstName)?> 👋</div>
    <div class="hero-sub">
      <?php if($due>0):?><?=$due?> review<?=$due>1?'s':''?> due today &middot; <?php endif;?>
      <?=count($reviews)?> upcoming scheduled
    </div>
    <a href="modules/schedule.php" class="hero-btn">View schedule <i class="fas fa-arrow-right"></i></a>
  </div>

  <!-- MINI STATS -->
  <div class="mini-stats mb-6">
    <div class="mini-stat">
      <div class="mini-stat-icon"><i data-lucide="trophy" aria-hidden="true"></i></div>
      <div class="mini-stat-val"><?=$avg?>%</div>
      <div class="mini-stat-label">Quiz Avg</div>
    </div>
    <div class="mini-stat">
      <div class="mini-stat-icon"><i data-lucide="flame" aria-hidden="true"></i></div>
      <div class="mini-stat-val"><?=$streakDays?>d</div>
      <div class="mini-stat-label">Streak</div>
    </div>
    <div class="mini-stat">
      <div class="mini-stat-icon"><i data-lucide="star" aria-hidden="true"></i></div>
      <div class="mini-stat-val"><?=$due?></div>
      <div class="mini-stat-label">Due</div>
    </div>
  </div>

  <!-- SUBJECTS -->
  <div class="section-header mb-4">
    <span class="section-title">My Subjects</span>
    <a href="modules/subjects.php" class="section-link">All <i class="fas fa-chevron-right"></i></a>
  </div>

  <?php if(empty($subjectList)):?>
  <div class="card mb-6">
    <div class="card-body">
      <div class="empty-state">
        <i class="fas fa-book-open"></i>
        <h4>No subjects yet</h4>
        <p>Add your first subject to start studying.</p>
        <a href="modules/subjects.php" class="btn btn-primary btn-pill mt-4" style="width:auto;padding:10px 24px">
          <i class="fas fa-plus"></i> Add Subject
        </a>
      </div>
    </div>
  </div>
  <?php else:?>
  <div class="subjects-grid mb-6">
    <?php
    $icons=['📘','📗','📕','📙','📓','📔'];
    foreach($subjectList as $i=>$s):
      $pct=(int)$s['avg_pct'];
      $timeAgo='';
      if($s['last_activity']){
        $diff=time()-strtotime($s['last_activity']);
        $timeAgo=$diff<3600?'Just now':($diff<86400?round($diff/3600).'h ago':($diff<172800?'Yesterday':date('M d',strtotime($s['last_activity']))));
      }
    ?>
    <a href="modules/lessons.php?subject_id=<?=$s['id']?>" class="subject-card">
      <div class="subject-card-top" style="background:<?=htmlspecialchars($s['color'])?>"></div>
      <div class="subject-card-body">
        <div class="subject-card-row">
          <div>
            <div class="subject-card-code"><?=htmlspecialchars($s['subject_code']??'')?></div>
            <div class="subject-card-name"><?=htmlspecialchars($s['subject_name'])?></div>
          </div>
          <div class="subject-card-icon"><?=$icons[$i%count($icons)]?></div>
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
    </a>
    <?php endforeach;?>
  </div>
  <?php endif;?>

  <!-- UPCOMING REVIEWS -->
  <div class="section-header mb-4">
    <span class="section-title">Upcoming Reviews</span>
    <a href="modules/schedule.php" class="section-link">View all <i class="fas fa-chevron-right"></i></a>
  </div>

  <?php if(empty($reviews)):?>
  <div class="card">
    <div class="card-body">
      <div class="empty-state">
        <i class="fas fa-calendar-check"></i>
        <h4>No reviews scheduled</h4>
        <p>Take a quiz to get your spaced repetition schedule.</p>
      </div>
    </div>
  </div>
  <?php else:?>
  <?php foreach($reviews as $r):
    $date=new DateTime($r['next_review_date']);
    $today=new DateTime('today');
    $diff=(int)$today->diff($date)->format('%r%a');
    if($diff<0){$cls='rb-overdue';$lbl='Overdue';}
    elseif($diff===0){$cls='rb-today';$lbl='Today';}
    elseif($diff===1){$cls='rb-tomorrow';$lbl='Tomorrow';}
    else{$cls='rb-upcoming';$lbl=$date->format('M j');}
  ?>
  <div class="review-item">
    <div class="review-item-icon"><i class="fas fa-calendar"></i></div>
    <div class="review-item-info">
      <div class="review-item-title"><?=htmlspecialchars($r['title'])?></div>
      <div class="review-item-sub"><?=htmlspecialchars($r['subject_name'])?></div>
    </div>
    <span class="review-badge <?=$cls?>"><?=$lbl?></span>
  </div>
  <?php endforeach;?>
  <?php endif;?>

</div>

<script src="https://unpkg.com/lucide@1.56.0"></script>
<?php
$extraJs='lucide.createIcons();';
if($dueList&&empty($_SESSION['due_toast_shown'])){
  $_SESSION['due_toast_shown']=true;
  $toastMsg='You have '.$due.' review'.($due>1?'s':'').' due.';
  $extraJs.="showToast(".json_encode($toastMsg,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT).",'warning');";
}
include 'includes/footer.php';?>
