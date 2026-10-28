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
 
// Microlearning: tracked on its own so quick checks never change quiz scores or the review schedule.
$microLessons=[];$microChecked=0;$microCorrect=0;
try{
  $mu=$pdo->prepare("SELECT l.id,l.title,s.subject_name,mu.units FROM micro_units mu JOIN lessons l ON l.id=mu.lesson_id AND l.version=mu.source_version AND l.content_hash=mu.source_hash JOIN subjects s ON s.id=l.subject_id WHERE s.user_id=? ORDER BY mu.generated_at DESC");
  $mu->execute([$uid]);$microRows=$mu->fetchAll();
  $mp=$pdo->prepare("SELECT mp.lesson_id,COUNT(*) AS checked,COALESCE(SUM(mp.is_correct),0) AS correct FROM micro_progress mp JOIN lessons l ON l.id=mp.lesson_id AND l.version=mp.source_version JOIN subjects s ON s.id=l.subject_id AND s.user_id=? WHERE mp.user_id=? GROUP BY mp.lesson_id");
  $mp->execute([$uid,$uid]);$microProg=[];
  foreach($mp->fetchAll() as $r)$microProg[(int)$r['lesson_id']]=$r;
  foreach($microRows as $r){
    $microUnits=json_decode((string)$r['units'],true);
    $total=is_array($microUnits)?count($microUnits):0;
    if($total<1)continue;
    $prog=$microProg[(int)$r['id']]??['checked'=>0,'correct'=>0];
    $checked=min((int)$prog['checked'],$total);$correct=min((int)$prog['correct'],$checked);
    $microLessons[]=['id'=>(int)$r['id'],'title'=>$r['title'],'subject'=>$r['subject_name'],'total'=>$total,'checked'=>$checked,'correct'=>$correct];
    $microChecked+=$checked;$microCorrect+=$correct;
  }
}catch(PDOException $e){$microLessons=[];$microChecked=0;$microCorrect=0;}
$microAcc=$microChecked>0?(int)round($microCorrect/$microChecked*100):0;
$microStarted=count(array_filter($microLessons,fn($m)=>$m['checked']>0));
 
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
 
  <!-- MICROLEARNING -->
  <div class="card mb-6">
    <div class="card-header"><h3>⚡ Microlearning</h3></div>
    <div class="card-body">
      <?php if(empty($microLessons)):?>
      <div class="empty-state"><i class="fas fa-bolt"></i><h4>No microlearning yet</h4><p>Open Microlearning to study a lesson in short units with quick checks.</p></div>
      <?php else:?>
      <div class="grid-3 mb-4" style="grid-template-columns:repeat(3,1fr)">
        <div class="analytics-stat">
          <div class="analytics-stat-val"><?=$microChecked?></div>
          <div class="analytics-stat-label">Units Checked</div>
        </div>
        <div class="analytics-stat">
          <div class="analytics-stat-val"><?=$microAcc?>%</div>
          <div class="analytics-stat-label">Quick-check Accuracy</div>
        </div>
        <div class="analytics-stat">
          <div class="analytics-stat-val"><?=$microStarted?></div>
          <div class="analytics-stat-label">Lessons Started</div>
        </div>
      </div>
      <?php foreach(array_slice($microLessons,0,8) as $ml):
        $pct=(int)round($ml['checked']/$ml['total']*100);
        $acc=$ml['checked']>0?(int)round($ml['correct']/$ml['checked']*100):0;
      ?>
      <div style="margin-bottom:14px">
        <div style="display:flex;justify-content:space-between;gap:10px;font-size:0.82rem;margin-bottom:5px">
          <a href="micro.php?lesson_id=<?=$ml['id']?>" style="color:var(--text-dark);text-decoration:none;font-weight:600"><?=htmlspecialchars($ml['subject'])?> — <?=htmlspecialchars($ml['title'])?></a>
          <span style="color:var(--text-light);white-space:nowrap"><?=$ml['checked']?>/<?=$ml['total']?> units<?=$ml['checked']>0?' · '.$acc.'% correct':''?></span>
        </div>
        <div style="height:6px;background:var(--border);border-radius:3px;overflow:hidden"><div style="height:100%;width:<?=$pct?>%;background:var(--primary)"></div></div>
      </div>
      <?php endforeach;?>
      <p class="text-sm text-muted" style="margin:6px 0 0">Quick checks are tracked separately from quiz scores and do not change your review schedule.</p>
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
  data:{labels:<?=$tLabels?>,datasets:[{label:'Avg Score %',data:<?=$tVals?>,borderColor:'#60A5FA',backgroundColor:'rgba(96,165,250,0.12)',borderWidth:2.5,fill:true,tension:0.4,pointBackgroundColor:'#60A5FA',pointRadius:4}]},
  options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{min:0,max:100,grid:{color:'#F3F4F6'},ticks:{callback:v=>v+'%',font:{size:11}}},x:{grid:{display:false},ticks:{font:{size:11}}}}}
});
</script>
<?php endif;?>
<?php if(!empty($subStats)):?>
<script>
new Chart(document.getElementById('subChart'),{
  type:'bar',
  data:{labels:<?=$sLabels?>,datasets:[{label:'Avg %',data:<?=$sVals?>,backgroundColor:'#A78BFA',borderRadius:6,borderSkipped:false}]},
  options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{min:0,max:100,grid:{color:'#F3F4F6'},ticks:{callback:v=>v,font:{size:11}}},x:{grid:{display:false},ticks:{font:{size:11}}}}}
});
</script>
<?php endif;?>
</body></html>
