<?php
session_start();
require '../config/db.php';
require_once '../config/env.php';
require '../includes/admin_helpers.php';
$adminId=requireAdmin($pdo);
$base='../';

$totals=adminUsageTotals($pdo);
$ai=adminAiUsage($pdo,30);
$failures=adminRecentFailures($pdo);
$reminders=adminReminderStatus($pdo);
$weak=adminWeakSubjects($pdo);
$checks=adminHealthChecks($pdo);
$aiTotal=array_sum(array_column($ai,'ai'));
$cacheTotal=array_sum(array_column($ai,'cache'));
$errTotal=array_sum(array_column($ai,'errors'));
$saveRate=($aiTotal+$cacheTotal)>0?(int)round($cacheTotal/($aiTotal+$cacheTotal)*100):null;
$failedChecks=count(array_filter($checks,fn($c)=>!$c['ok']));
$kindLabels=['explanation'=>'Summary','quiz'=>'Quiz','flashcard'=>'Flashcards','microlearning'=>'Microlearning','reminder_email'=>'Reminder email'];
$th='text-align:left;padding:8px 10px;font-size:0.72rem;color:var(--text-light);text-transform:uppercase;letter-spacing:0.03em;border-bottom:1px solid var(--border)';
$td='padding:8px 10px;font-size:0.85rem;border-bottom:1px solid var(--border)';
$fmt=fn($t)=>$t?date('M j, Y g:i A',strtotime($t)):'Never';

$pageTitle='Overview – StudiMate Admin';
$topbarTitle='Overview';
include '../includes/header.php';
include '../includes/admin_sidebar.php';
?>

<div class="page-body">
  <?php if($failedChecks):?>
  <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?=$failedChecks?> system check<?=$failedChecks===1?'':'s'?> need attention. <a href="admin_health.php" style="color:inherit;font-weight:600">Open System health</a></div>
  <?php endif;?>

  <!-- USAGE (totals only) -->
  <div class="card mb-4">
    <div class="card-header"><h3>Usage across the platform</h3></div>
    <div class="card-body">
      <div class="grid-3 mb-4" style="grid-template-columns:repeat(4,1fr)">
        <div class="analytics-stat"><div class="analytics-stat-val"><?=$totals['students']?></div><div class="analytics-stat-label">Students</div></div>
        <div class="analytics-stat"><div class="analytics-stat-val"><?=$totals['active30']?></div><div class="analytics-stat-label">Active in 30 days</div></div>
        <div class="analytics-stat"><div class="analytics-stat-val"><?=$totals['quizzes']?></div><div class="analytics-stat-label">Quizzes taken</div></div>
        <div class="analytics-stat"><div class="analytics-stat-val"><?=$totals['avg_score']!==null?$totals['avg_score'].'%':'–'?></div><div class="analytics-stat-label">Average quiz score</div></div>
      </div>
      <p class="text-sm text-muted" style="margin:0">
        <?=$totals['new30']?> new students in the last 30 days · <?=$totals['lessons']?> lessons · <?=$totals['micro_checked']?> microlearning units checked.
        "Active" counts students who logged in during the last 30 days (recorded since the admin update).
        Only totals are shown. Individual scores, lessons and files are never displayed here.
      </p>
    </div>
  </div>

  <!-- GEMINI USAGE -->
  <div class="card mb-4">
    <div class="card-header"><h3>Gemini usage (last 30 days)</h3></div>
    <div class="card-body">
      <div class="grid-3 mb-4" style="grid-template-columns:repeat(3,1fr)">
        <div class="analytics-stat"><div class="analytics-stat-val"><?=$aiTotal?></div><div class="analytics-stat-label">New AI generations</div></div>
        <div class="analytics-stat"><div class="analytics-stat-val"><?=$cacheTotal?></div><div class="analytics-stat-label">Reused from saved copies<?=$saveRate!==null?' ('.$saveRate.'%)':''?></div></div>
        <div class="analytics-stat"><div class="analytics-stat-val" style="<?=$errTotal?'color:var(--danger)':''?>"><?=$errTotal?></div><div class="analytics-stat-label">Failed requests</div></div>
      </div>
      <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse">
        <thead><tr><th style="<?=$th?>">Output</th><th style="<?=$th?>">New AI</th><th style="<?=$th?>">Reused</th><th style="<?=$th?>">Failed</th></tr></thead>
        <tbody>
        <?php foreach($ai as $row):?>
          <tr><td style="<?=$td?>"><?=htmlspecialchars($row['label'])?></td><td style="<?=$td?>"><?=$row['ai']?></td><td style="<?=$td?>"><?=$row['cache']?></td><td style="<?=$td?>"><?=$row['errors']?></td></tr>
        <?php endforeach;?>
        </tbody>
      </table>
      </div>
      <h4 style="margin:18px 0 8px;font-size:0.88rem">Recent failures</h4>
      <?php if(!$failures):?>
        <p class="text-sm text-muted" style="margin:0">No failures recorded.</p>
      <?php else:?>
        <?php foreach($failures as $f):?>
        <div style="padding:8px 0;border-bottom:1px solid var(--border);font-size:0.82rem">
          <strong><?=htmlspecialchars($kindLabels[$f['kind']]??$f['kind'])?></strong>
          <span class="text-muted"> · <?=htmlspecialchars($fmt($f['created_at']))?></span><br>
          <?=htmlspecialchars((string)$f['message'])?>
        </div>
        <?php endforeach;?>
      <?php endif;?>
      <p class="text-sm text-muted" style="margin:12px 0 0">Counts start from the day the activity log was installed. Gemini availability and speed are outside the researchers' control, so failures here are expected from time to time.</p>
    </div>
  </div>

  <!-- REMINDER EMAILS -->
  <div class="card mb-4">
    <div class="card-header"><h3>Reminder emails</h3></div>
    <div class="card-body">
      <div class="grid-3 mb-4" style="grid-template-columns:repeat(4,1fr)">
        <div class="analytics-stat"><div class="analytics-stat-val"><?=$reminders['opted_in']?> / <?=$reminders['students']?></div><div class="analytics-stat-label">Students opted in</div></div>
        <div class="analytics-stat"><div class="analytics-stat-val"><?=$reminders['due_now']?></div><div class="analytics-stat-label">With reviews due now</div></div>
        <div class="analytics-stat"><div class="analytics-stat-val"><?=$reminders['sent_30']?></div><div class="analytics-stat-label">Sent (30 days)</div></div>
        <div class="analytics-stat"><div class="analytics-stat-val" style="<?=$reminders['failed_30']?'color:var(--danger)':''?>"><?=$reminders['failed_30']?></div><div class="analytics-stat-label">Failed (30 days)</div></div>
      </div>
      <p class="text-sm text-muted" style="margin:0">Last successful send: <strong><?=htmlspecialchars($fmt($reminders['last_ok']))?></strong>. The reminder script runs once a day from the scheduler.</p>
    </div>
  </div>

  <!-- WEAK SUBJECTS -->
  <div class="card mb-4">
    <div class="card-header"><h3>Subjects with the lowest quiz scores</h3></div>
    <div class="card-body">
      <?php if(!$weak):?>
        <p class="text-sm text-muted" style="margin:0">Not enough data yet. A subject appears once at least <?=ADMIN_MIN_GROUP?> different students have taken quizzes in it, so no individual can be identified.</p>
      <?php else:?>
        <?php foreach($weak as $w):?>
        <div class="weak-topic">
          <div class="weak-topic-header"><span><?=htmlspecialchars($w['name'])?></span><span class="weak-topic-pct"><?=(int)$w['avg_pct']?>%</span></div>
          <div class="text-sm text-muted"><?=(int)$w['attempts']?> quiz attempts · <?=(int)$w['students']?> students</div>
        </div>
        <?php endforeach;?>
      <?php endif;?>
    </div>
  </div>
</div>

<?php include '../includes/footer.php';?>
