<?php
session_start();
require '../config/db.php';
require_once '../config/env.php';
require '../includes/admin_helpers.php';
$adminId=requireAdmin($pdo);
$base='../';

$checks=adminHealthChecks($pdo);
$failedChecks=count(array_filter($checks,fn($c)=>!$c['ok']));
$passed=count($checks)-$failedChecks;
$groups=[];
foreach($checks as $c){$groups[$c['group']][]=$c;}
$checkedAt=date('M j, Y g:i A');

$pageTitle='System health – StudiMate Admin';
$topbarTitle='System health';
include '../includes/header.php';
include '../includes/admin_sidebar.php';
?>

<div class="page-body">
  <div class="card mb-4">
    <div class="card-header">
      <h3>System health checks <?=$failedChecks?'<span class="badge badge-danger">'.$failedChecks.' to fix</span>':'<span class="badge badge-success">All good</span>'?></h3>
    </div>
    <div class="card-body">
      <div class="admin-stats mb-4">
        <div class="analytics-stat"><div class="analytics-stat-val"><?=count($checks)?></div><div class="analytics-stat-label">Checks run</div></div>
        <div class="analytics-stat"><div class="analytics-stat-val" style="color:var(--success)"><?=$passed?></div><div class="analytics-stat-label">Passing</div></div>
        <div class="analytics-stat"><div class="analytics-stat-val" style="<?=$failedChecks?'color:var(--danger)':''?>"><?=$failedChecks?></div><div class="analytics-stat-label">Need attention</div></div>
      </div>
      <p class="text-sm text-muted" style="margin:0">
        Checked <?=htmlspecialchars($checkedAt)?>. <a href="admin_health.php">Run the checks again</a>.
        Secret values such as the Gemini key and the email password are never shown, only whether they are set.
      </p>
    </div>
  </div>

  <?php foreach($groups as $name=>$items):?>
  <div class="card mb-4">
    <div class="card-header"><h3><?=htmlspecialchars($name)?></h3></div>
    <div class="card-body">
      <?php foreach($items as $c):?>
      <div style="display:flex;gap:10px;align-items:flex-start;padding:7px 0;border-bottom:1px solid var(--border);font-size:0.85rem">
        <span style="width:18px;font-weight:700;color:<?=$c['ok']?'var(--success)':'var(--danger)'?>"><?=$c['ok']?'✓':'✗'?></span>
        <span style="flex:1"><?=htmlspecialchars($c['label'])?><?php if($c['detail']!==''):?><span class="text-muted"> – <?=htmlspecialchars($c['detail'])?></span><?php endif;?></span>
      </div>
      <?php endforeach;?>
    </div>
  </div>
  <?php endforeach;?>
</div>

<?php include '../includes/footer.php';?>
