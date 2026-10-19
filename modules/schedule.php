<?php
session_start();
if(!isset($_SESSION['user_id'])){header("Location: ../index.php");exit;}
require '../config/db.php';
$uid=$_SESSION['user_id'];$base='../';

// Get all scheduled reviews
$stmt=$pdo->prepare("
  SELECT rs.*,l.title,l.id as lesson_id,s.subject_name,s.color
  FROM review_schedule rs
  JOIN lessons l ON rs.lesson_id=l.id
  JOIN subjects s ON l.subject_id=s.id
  WHERE rs.user_id=? ORDER BY rs.next_review_date ASC
");
$stmt->execute([$uid]);$schedules=$stmt->fetchAll();

// Build calendar data
$month=(int)($_GET['month']??date('n'));
$year=(int)($_GET['year']??date('Y'));
if($month<1){$month=12;$year--;}
if($month>12){$month=1;$year++;}
$prevM=$month-1;$prevY=$year;if($prevM<1){$prevM=12;$prevY--;}
$nextM=$month+1;$nextY=$year;if($nextM>12){$nextM=1;$nextY++;}
$daysInMonth=cal_days_in_month(CAL_GREGORIAN,$month,$year);
$firstDay=date('N',mktime(0,0,0,$month,1,$year)); // 1=Mon...7=Sun

// Map review dates
$reviewDates=[];
foreach($schedules as $s){
  $d=date('Y-m-d',strtotime($s['next_review_date']));
  $reviewDates[$d]=($reviewDates[$d]??0)+1;
}
$today=date('Y-m-d');

$pageTitle='Schedule – StudiMate';
$topbarTitle='Schedule';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="page-body">

  <div class="grid-2" style="align-items:start">

    <!-- CALENDAR -->
    <div class="calendar">
      <div class="cal-header">
        <a href="schedule.php?month=<?=$prevM?>&year=<?=$prevY?>" class="cal-nav"><i class="fas fa-chevron-left"></i></a>
        <span class="cal-title"><?=date('F Y',mktime(0,0,0,$month,1,$year))?></span>
        <a href="schedule.php?month=<?=$nextM?>&year=<?=$nextY?>" class="cal-nav"><i class="fas fa-chevron-right"></i></a>
      </div>

      <div class="cal-grid">
        <?php foreach(['S','M','T','W','T','F','S'] as $d):?>
        <div class="cal-day-label"><?=$d?></div>
        <?php endforeach;?>

        <?php
        // Empty cells before first day (adjust for Sun=1 start)
        $startOffset=($firstDay%7); // Sun=0,Mon=1,...
        for($i=0;$i<$startOffset;$i++):?>
        <div class="cal-day other-month"></div>
        <?php endfor;

        for($d=1;$d<=$daysInMonth;$d++):
          $dateStr=sprintf('%04d-%02d-%02d',$year,$month,$d);
          $isToday=$dateStr===$today;
          $hasReview=isset($reviewDates[$dateStr]);
          $isPast=$dateStr<$today&&$hasReview;
          $cls='cal-day';
          if($isToday)$cls.=' today';
          elseif($isPast)$cls.=' done';
          elseif($hasReview)$cls.=' has-review';
        ?>
        <div class="<?=$cls?>" title="<?=$hasReview?$reviewDates[$dateStr].' review(s)':''?>"><?=$d?></div>
        <?php endfor;?>
      </div>

      <div class="cal-legend">
        <div class="cal-leg-item"><div class="cal-leg-dot" style="background:var(--primary)"></div>Today</div>
        <div class="cal-leg-item"><div class="cal-leg-dot" style="background:var(--primary-light);border:1px solid var(--primary)"></div>Upcoming</div>
        <div class="cal-leg-item"><div class="cal-leg-dot" style="background:#DCFCE7"></div>Done</div>
      </div>
    </div>

    <!-- SCHEDULED REVIEWS LIST -->
    <div class="card">
      <div class="card-header">
        <h3>Scheduled Reviews</h3>
        <span class="badge badge-primary"><?=count($schedules)?></span>
      </div>
      <div class="card-body" style="padding:12px">
        <?php if(empty($schedules)):?>
        <div class="empty-state"><i class="fas fa-calendar-check"></i><h4>No reviews scheduled</h4><p>Take a quiz to generate your schedule.</p></div>
        <?php else:?>
        <?php foreach($schedules as $r):
          $date=new DateTime($r['next_review_date']);
          $diff=(int)(new DateTime('today'))->diff($date)->format('%r%a');
          if($diff<0){$cls='rb-overdue';$int='+'.(abs($diff)).'d overdue';}
          elseif($diff===0){$cls='rb-today';$int='Today';}
          else{$cls='';$int='+'.$r['interval_days'].'d';}
        ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 8px;border-bottom:1px solid var(--border)">
          <div style="text-align:center;min-width:38px">
            <div style="font-size:0.65rem;font-weight:700;color:var(--primary);text-transform:uppercase"><?=$date->format('M')?></div>
            <div style="font-size:1.1rem;font-weight:800;color:var(--text-dark);line-height:1"><?=$date->format('d')?></div>
          </div>
          <div style="flex:1">
            <div style="font-size:0.83rem;font-weight:600;color:var(--text-dark)"><?=htmlspecialchars($r['title'])?></div>
            <div style="font-size:0.72rem;color:var(--text-light)"><?=htmlspecialchars($r['subject_name'])?></div>
          </div>
          <div style="display:flex;align-items:center;gap:6px">
            <?php if($diff<0):?>
            <span class="badge badge-danger">Overdue</span>
            <?php elseif($diff===0):?>
            <span class="badge badge-warning">Today</span>
            <?php else:?>
            <span style="font-size:0.72rem;font-weight:700;color:var(--primary);background:var(--primary-light);padding:3px 8px;border-radius:var(--radius)">+<?=$r['interval_days']?>d</span>
            <?php endif;?>
            <a href="study.php?lesson_id=<?=$r['lesson_id']?>&amp;tab=quiz" class="btn btn-accent-outline btn-sm btn-pill">Review</a>
          </div>
        </div>
        <?php endforeach;?>
        <?php endif;?>
      </div>
    </div>

  </div>
</div>

<?php include '../includes/footer.php';?>
