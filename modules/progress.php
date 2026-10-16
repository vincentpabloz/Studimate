<?php
session_start();
if(!isset($_SESSION['user_id'])){header("Location: ../index.php");exit;}
require '../config/db.php';
$uid=$_SESSION['user_id'];$base='../';

// Stats
$s=$pdo->prepare("SELECT COUNT(*) as total,COALESCE(ROUND(AVG((score/total)*100)),0) as avg FROM quiz_results WHERE user_id=? AND total>0");
$s->execute([$uid]);$stats=$s->fetch();

$fcCount=$pdo->prepare("SELECT COUNT(*) FROM study_tools WHERE type='flashcard' AND lesson_id IN (SELECT id FROM lessons WHERE subject_id IN (SELECT id FROM subjects WHERE user_id=?))");
$fcCount->execute([$uid]);$cards=$fcCount->fetchColumn();

// Daily quiz trend last 7 days
$trend=$pdo->prepare("SELECT DATE(taken_at) as dt,ROUND(AVG((score/total)*100),1) as avg FROM quiz_results WHERE user_id=? AND total>0 AND taken_at>=DATE_SUB(CURDATE(),INTERVAL 7 DAY) GROUP BY DATE(taken_at) ORDER BY dt");
$trend->execute([$uid]);$trendData=$trend->fetchAll();

// Per subject
$bySub=$pdo->prepare("SELECT s.subject_name,s.color,COALESCE(ROUND(AVG((qr.score/qr.total)*100)),0) as avg_pct FROM subjects s LEFT JOIN lessons l ON l.subject_id=s.id LEFT JOIN quiz_results qr ON qr.lesson_id=l.id AND qr.user_id=? WHERE s.user_id=? GROUP BY s.id ORDER BY avg_pct DESC");
$bySub->execute([$uid,$uid]);$subStats=$bySub->fetchAll();

// Weak topics
$weak=$pdo->prepare("SELECT l.title,s.subject_name,ROUND(AVG((qr.score/qr.total)*100)) as avg_pct FROM quiz_results qr JOIN lessons l ON qr.lesson_id=l.id JOIN subjects s ON l.subject_id=s.id WHERE qr.user_id=? AND qr.total>0 GROUP BY l.id HAVING avg_pct<65 ORDER BY avg_pct ASC LIMIT 3");
$weak->execute([$uid]);$weakTopics=$weak->fetchAll();

$tLabels=json_encode(array_column($trendData,'dt'));
$tVals=json_encode(array_map(fn($r)=>$r['avg'],[]));
$tVals=json_encode(array_column($trendData,'avg'));
$sLabels=json_encode(array_map(fn($r)=>substr($r['subject_name'],0,6),$subStats));
$sVals=json_encode(array_column($subStats,'avg_pct'));

$pageTitle='Analytics – StudiMate';
$topbarTitle='Analytics';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="page-body">

  <!-- DATE RANGE LABEL -->
  <div style="font-size:0.78rem;color:var(--text-light);font-weight:600;margin-bottom:14px">
    Analytics &bull; <?=date('M j')?>–<?=date('M j, Y')?>
  </div>

  <!-- MINI STATS ROW -->
  <div class="grid-3 mb-6" style="grid-template-columns:repeat(4,1fr)">
    <div class="analytics-stat">
      <div class="analytics-stat-val"><?=number_format($stats['avg']*0.8,1)?>h</div>
      <div class="analytics-stat-label">Study Time</div>
    </div>
    <div class="analytics-stat">
      <div class="analytics-stat-val"><?=$stats['total']?></div>
      <div class="analytics-stat-label">Quizzes</div>
    </div>
    <div class="analytics-stat">
      <div class="analytics-stat-val"><?=$cards?></div>
      <div class="analytics-stat-label">Cards</div>
    </div>
    <div class="analytics-stat">
      <div class="analytics-stat-val"><?=$stats['avg']?>%</div>
      <div class="analytics-stat-label">Avg Score</div>
    </div>
  </div>

  <!-- DAILY CHART -->
  <div class="card mb-6">
    <div class="card-header"><h3>Daily Study Hours</h3></div>
    <div class="card-body">
      <?php if(empty($trendData)):?>
      <div class="empty-state"><i class="fas fa-chart-line"></i><h4>No data yet</h4><p>Take quizzes to see your trends.</p></div>
      <?php else:?>
      <canvas id="trendChart" height="180"></canvas>
      <?php endif;?>
    </div>
  </div>

  <!-- QUIZ SCORES BY SUBJECT -->
  <div class="card mb-6">
    <div class="card-header"><h3>Quiz Scores by Subject</h3></div>
    <div class="card-body">
      <?php if(empty($subStats)):?>
      <div class="empty-state"><i class="fas fa-chart-bar"></i><h4>No quiz data yet</h4></div>
      <?php else:?>
      <canvas id="subChart" height="180"></canvas>
      <?php endif;?>
    </div>
  </div>

  <!-- WEAK TOPICS -->
  <?php if(!empty($weakTopics)):?>
  <div class="card">
    <div class="card-header">
      <h3>🔥 Weak Topics</h3>
    </div>
    <div class="card-body">
      <?php foreach($weakTopics as $wt):
        $p=(int)$wt['avg_pct'];
      ?>
      <div class="weak-topic">
        <div class="weak-topic-header">
          <span><?=htmlspecialchars($wt['subject_name'])?> — <?=htmlspecialchars($wt['title'])?></span>
          <span class="weak-topic-pct"><?=$p?>%</span>
        </div>
        <div class="weak-bar"><div class="weak-fill" style="width:<?=$p?>%"></div></div>
      </div>
      <?php endforeach;?>
      <button class="btn btn-accent-outline w-full mt-4" style="border-radius:var(--radius-sm)"
              onclick="window.location='../modules/recommendations.php'">
        Generate Focused Review
      </button>
    </div>
  </div>
  <?php endif;?>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script src="../assets/js/main.js"></script>
<?php if(!empty($trendData)):?>
<script>
new Chart(document.getElementById('trendChart'),{
  type:'line',
  data:{labels:<?=$tLabels?>,datasets:[{label:'Avg Score %',data:<?=$tVals?>,borderColor:'#5B5FDE',backgroundColor:'rgba(91,95,222,0.08)',borderWidth:2.5,fill:true,tension:0.4,pointBackgroundColor:'#5B5FDE',pointRadius:4}]},
  options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{min:0,max:100,grid:{color:'#F3F4F6'},ticks:{callback:v=>v+'%',font:{size:11}}},x:{grid:{display:false},ticks:{font:{size:11}}}}}
});
</script>
<?php endif;?>
<?php if(!empty($subStats)):?>
<script>
new Chart(document.getElementById('subChart'),{
  type:'bar',
  data:{labels:<?=$sLabels?>,datasets:[{label:'Avg %',data:<?=$sVals?>,backgroundColor:'#5B5FDE',borderRadius:6,borderSkipped:false}]},
  options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{min:0,max:100,grid:{color:'#F3F4F6'},ticks:{callback:v=>v,font:{size:11}}},x:{grid:{display:false},ticks:{font:{size:11}}}}}
});
</script>
<?php endif;?>
</body></html>
