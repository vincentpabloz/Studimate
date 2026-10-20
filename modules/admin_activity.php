<?php
session_start();
require '../config/db.php';
require_once '../config/env.php';
require '../includes/admin_helpers.php';
require '../includes/admin_report_helpers.php';
$adminId=requireAdmin($pdo);
$base='../';

$types=adminActionTypes();
$type=(string)($_GET['type']??'');
if(!isset($types[$type]))$type='';
$page=max(1,(int)($_GET['page']??1));

$counts=adminActionCounts($pdo);
$log=adminActivityPage($pdo,$type,$page,20);
$qs=$type!==''?'&type='.urlencode($type):'';
$fmt=fn($t)=>$t?date('M j, Y g:i A',strtotime($t)):'';
$th='text-align:left;padding:8px 10px;font-size:0.72rem;color:var(--text-light);text-transform:uppercase;letter-spacing:0.03em;border-bottom:1px solid var(--border)';
$td='padding:9px 10px;font-size:0.85rem;border-bottom:1px solid var(--border);vertical-align:top';

$pageTitle='Activity log – StudiMate Admin';
$topbarTitle='Activity log';
include '../includes/header.php';
include '../includes/admin_sidebar.php';
?>

<div class="page-body">
  <div class="card mb-4">
    <div class="card-header"><h3>Administrator activity log</h3></div>
    <div class="card-body">
      <div class="admin-stats mb-4">
        <div class="analytics-stat"><div class="analytics-stat-val"><?=$counts['total']?></div><div class="analytics-stat-label">Actions recorded</div></div>
        <div class="analytics-stat"><div class="analytics-stat-val"><?=$counts['last30']?></div><div class="analytics-stat-label">Last 30 days</div></div>
        <div class="analytics-stat"><div class="analytics-stat-val"><?=$counts['admins']?></div><div class="analytics-stat-label">Admins who acted</div></div>
        <div class="analytics-stat"><div class="analytics-stat-val"><?=$counts['by_type']['deactivate']?></div><div class="analytics-stat-label">Accounts deactivated</div></div>
      </div>

      <form method="GET" style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;align-items:center">
        <label for="type" class="text-sm">Action type</label>
        <select id="type" name="type" class="form-control" style="max-width:260px">
          <option value="">All actions (<?=$counts['total']?>)</option>
          <?php foreach($types as $key=>$t):?>
          <option value="<?=$key?>" <?=$type===$key?'selected':''?>><?=htmlspecialchars($t['label'])?> (<?=$counts['by_type'][$key]?>)</option>
          <?php endforeach;?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm" style="width:auto"><i class="fas fa-filter"></i> Filter</button>
        <?php if($type!==''):?><a href="admin_activity.php" class="btn btn-outline btn-sm" style="width:auto;text-decoration:none">Clear</a><?php endif;?>
      </form>

      <div style="overflow-x:auto">
      <table class="rtable" style="width:100%;border-collapse:collapse">
        <thead><tr>
          <th style="<?=$th?>">Date and time</th><th style="<?=$th?>">Administrator</th>
          <th style="<?=$th?>">Action</th><th style="<?=$th?>">Affected account</th>
        </tr></thead>
        <tbody>
        <?php if(!$log['rows']):?>
          <tr><td colspan="4" style="<?=$td?>" class="text-muted">No actions recorded<?=$type!==''?' for this action type':' yet'?>. Actions taken on the User accounts page appear here.</td></tr>
        <?php endif;?>
        <?php foreach($log['rows'] as $r):
          $info=$r['type']?($types[$r['type']]??null):null;?>
          <tr>
            <td data-label="When" style="<?=$td?>;white-space:nowrap"><?=htmlspecialchars($fmt($r['created_at']))?></td>
            <td class="r-block" data-label="By" style="<?=$td?>">
              <?php if($r['admin_name']):?><strong><?=htmlspecialchars($r['admin_name'])?></strong><?=$r['admin_email']?'<br><span class="text-muted">'.htmlspecialchars($r['admin_email']).'</span>':''?>
              <?php else:?><span class="text-muted">Removed admin</span><?php endif;?>
            </td>
            <td data-label="Action" style="<?=$td?>">
              <?php if($info):?><span class="badge <?=$info['badge']?>"><?=htmlspecialchars($info['label'])?></span>
              <?php else:?><?=htmlspecialchars((string)$r['message'])?><?php endif;?>
            </td>
            <td class="r-block" data-label="Affected account" style="<?=$td?>">
              <?php if($r['target']):?><strong><?=htmlspecialchars($r['target']['name'])?></strong><?=$r['target']['email']!==''?'<br><span class="text-muted">'.htmlspecialchars($r['target']['email']).'</span>':''?>
              <?php else:?><span class="text-muted">–</span><?php endif;?>
            </td>
          </tr>
        <?php endforeach;?>
        </tbody>
      </table>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;font-size:0.82rem">
        <span class="text-muted"><?=$log['total']?> action<?=$log['total']===1?'':'s'?> · page <?=$log['page']?> of <?=$log['pages']?></span>
        <span>
          <?php if($log['page']>1):?><a href="admin_activity.php?page=<?=$log['page']-1?><?=$qs?>" class="btn btn-outline btn-sm" style="width:auto;text-decoration:none">Previous</a><?php endif;?>
          <?php if($log['page']<$log['pages']):?><a href="admin_activity.php?page=<?=$log['page']+1?><?=$qs?>" class="btn btn-outline btn-sm" style="width:auto;text-decoration:none">Next</a><?php endif;?>
        </span>
      </div>
      <p class="text-sm text-muted" style="margin:12px 0 0">
        Every account action records which administrator did it, which account it affected, and when.
        The dashboard has no way to edit or delete these entries. Names show each account's current details.
      </p>
    </div>
  </div>
</div>

<?php include '../includes/footer.php';?>
