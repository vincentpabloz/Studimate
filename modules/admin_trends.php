<?php
session_start();
require '../config/db.php';
require_once '../config/env.php';
require '../includes/admin_helpers.php';
require '../includes/admin_report_helpers.php';
$adminId=requireAdmin($pdo);
$base='../';

$days=30;
$series=adminDailySeries($pdo,$days);
$sum=adminTrendSummary($series);
$chartJson=fn($v)=>json_encode($v,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
$firstDay=date('M j',strtotime($series['dates'][0]));
$lastDay=date('M j, Y',strtotime($series['dates'][count($series['dates'])-1]));

$pageTitle='Usage trends – StudiMate Admin';
$topbarTitle='Usage trends';
include '../includes/header.php';
include '../includes/admin_sidebar.php';
?>

<div class="page-body">
  <div class="card mb-4">
    <div class="card-header"><h3>Student activity (last <?=$days?> days)</h3></div>
    <div class="card-body">
      <div class="admin-stats mb-4">
        <div class="analytics-stat"><div class="analytics-stat-val"><?=$sum['quizzes']?></div><div class="analytics-stat-label">Quizzes taken</div></div>
        <div class="analytics-stat"><div class="analytics-stat-val"><?=$sum['peak_active']?></div><div class="analytics-stat-label">Most active students in one day</div></div>
      </div>
      <canvas id="activityChart" height="130"></canvas>
      <p class="text-sm text-muted" style="margin:12px 0 0">
        <?=htmlspecialchars($firstDay)?> to <?=htmlspecialchars($lastDay)?>. A student counts as active on a day when they took a quiz,
        checked a microlearning unit, or generated study tools. Totals only: no individual student is shown.
      </p>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><h3>Gemini requests and saved copies (last <?=$days?> days)</h3></div>
    <div class="card-body">
      <div class="admin-stats mb-4">
        <div class="analytics-stat"><div class="analytics-stat-val"><?=$sum['ai_new']?></div><div class="analytics-stat-label">New AI generations</div></div>
        <div class="analytics-stat"><div class="analytics-stat-val"><?=$sum['ai_reused']?></div><div class="analytics-stat-label">Reused from saved copies</div></div>
        <div class="analytics-stat"><div class="analytics-stat-val"><?=$sum['reuse_pct']!==null?$sum['reuse_pct'].'%':'–'?></div><div class="analytics-stat-label">Of requests avoided Gemini</div></div>
        <div class="analytics-stat"><div class="analytics-stat-val" style="<?=$sum['ai_failed']?'color:var(--danger)':''?>"><?=$sum['ai_failed']?></div><div class="analytics-stat-label">Failed requests</div></div>
      </div>
      <canvas id="aiChart" height="130"></canvas>
      <p class="text-sm text-muted" style="margin:12px 0 0">
        <?php if($sum['reuse_pct']!==null):?>
        Saved copies served <?=$sum['ai_reused']?> of <?=$sum['ai_new']+$sum['ai_reused']?> requests, so Gemini was called <?=$sum['reuse_pct']?>% less often than it would have been without them.
        <?php else:?>
        No AI requests recorded in this period yet.
        <?php endif;?>
        Counts start from the day the activity log was installed.
      </p>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
(function(){
  if(typeof Chart==='undefined')return;
  var labels=<?=$chartJson($series['labels'])?>;
  var opts={responsive:true,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'bottom',labels:{font:{size:11},boxWidth:12}}},
    scales:{y:{beginAtZero:true,grid:{color:'#F3F4F6'},ticks:{precision:0,font:{size:11}}},x:{grid:{display:false},ticks:{font:{size:11},maxRotation:0,autoSkip:true,maxTicksLimit:10}}}};
  function ds(label,data,color,dashed){return {label:label,data:data,borderColor:color,backgroundColor:color,borderWidth:2.5,tension:0.35,pointRadius:2,fill:false,borderDash:dashed?[6,4]:[]};}
  new Chart(document.getElementById('activityChart'),{type:'line',data:{labels:labels,datasets:[
    ds('Quizzes taken',<?=$chartJson($series['quizzes'])?>,'#5B5FDE'),
    ds('Active students',<?=$chartJson($series['active'])?>,'#10B981')
  ]},options:opts});
  new Chart(document.getElementById('aiChart'),{type:'line',data:{labels:labels,datasets:[
    ds('New AI generations',<?=$chartJson($series['ai_new'])?>,'#F59E0B'),
    ds('Reused from saved copies',<?=$chartJson($series['ai_reused'])?>,'#10B981'),
    ds('Failed',<?=$chartJson($series['ai_failed'])?>,'#EF4444',true)
  ]},options:opts});
})();
</script>

<?php include '../includes/footer.php';?>
