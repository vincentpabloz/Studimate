<?php
session_start();
if(!isset($_SESSION['user_id'])){header("Location: ../index.php");exit;}
require '../config/db.php';
require '../includes/admin_helpers.php';
$uid=(int)$_SESSION['user_id'];$base='../';

$csrfToken=$_SESSION['csrf_token']??bin2hex(random_bytes(32));
$_SESSION['csrf_token']=$csrfToken;
$catalog=adminEvalCatalog();
$validItems=[];
foreach($catalog as $charKey=>$c){foreach($c['items'] as $itemKey=>$statement){$validItems[$itemKey]=$charKey;}}

$error='';$notice='';
$type='';$comments='';$given=[];

// Load this person's earlier answers, if any.
try{
  $ex=$pdo->prepare('SELECT id,respondent_type,comments FROM evaluations WHERE user_id=?');
  $ex->execute([$uid]);
  $existing=$ex->fetch();
  if($existing){
    $type=$existing['respondent_type'];$comments=(string)$existing['comments'];
    $sq=$pdo->prepare('SELECT item_key,score FROM evaluation_scores WHERE evaluation_id=?');
    $sq->execute([(int)$existing['id']]);
    foreach($sq->fetchAll() as $r)$given[$r['item_key']]=(int)$r['score'];
  }
}catch(PDOException $e){
  $error='The evaluation tables are missing. Ask the administrator to run database/migrations/migrate_admin.php.';
}

if($_SERVER['REQUEST_METHOD']==='POST'&&!$error){
  $type=(string)($_POST['respondent_type']??'');
  $comments=mb_substr(trim((string)($_POST['comments']??'')),0,1000);
  $posted=is_array($_POST['score']??null)?$_POST['score']:[];
  $given=[];
  foreach($posted as $itemKey=>$value){
    if(isset($validItems[$itemKey])&&ctype_digit((string)$value)&&(int)$value>=1&&(int)$value<=5)$given[$itemKey]=(int)$value;
  }
  if(!hash_equals($csrfToken,(string)($_POST['csrf_token']??''))){
    $error='Your session expired. Reload the page and try again.';
  }elseif(!in_array($type,['student','expert'],true)){
    $error='Choose whether you are a student or an IT expert.';
  }elseif(!$given){
    $error='Rate at least one statement before submitting.';
  }else{
    try{
      $pdo->beginTransaction();
      $pdo->prepare('INSERT INTO evaluations (user_id,respondent_type,comments) VALUES (?,?,?) ON DUPLICATE KEY UPDATE respondent_type=VALUES(respondent_type),comments=VALUES(comments)')
          ->execute([$uid,$type,$comments!==''?$comments:null]);
      $idq=$pdo->prepare('SELECT id FROM evaluations WHERE user_id=?');$idq->execute([$uid]);
      $evalId=(int)$idq->fetchColumn();
      $pdo->prepare('DELETE FROM evaluation_scores WHERE evaluation_id=?')->execute([$evalId]);
      $ins=$pdo->prepare('INSERT INTO evaluation_scores (evaluation_id,item_key,characteristic,score) VALUES (?,?,?,?)');
      foreach($given as $itemKey=>$score)$ins->execute([$evalId,$itemKey,$validItems[$itemKey],$score]);
      $pdo->commit();
      $notice='Thank you! Your ratings were saved. You can come back and change them anytime.';
    }catch(Throwable $e){
      if($pdo->inTransaction())$pdo->rollBack();
      $error='Your ratings could not be saved. Please try again.';
    }
  }
}

$pageTitle='Rate StudiMate – StudiMate';
$topbarTitle='Rate StudiMate';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="page-body">
  <?php if($error):?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($error)?></div><?php endif;?>
  <?php if($notice):?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($notice)?></div><?php endif;?>

  <form method="POST" class="card">
    <div class="card-header"><h3>Rate StudiMate (ISO/IEC 25010)</h3></div>
    <div class="card-body">
      <p class="text-sm text-muted" style="margin:0 0 14px">Rate each statement from 1 (Poor) to 5 (Excellent): 5 Excellent · 4 Very Good · 3 Good · 2 Fair · 1 Poor. Skip any statement you cannot judge.</p>
      <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrfToken)?>">

      <div style="margin-bottom:18px;font-size:0.9rem">
        <strong>I am a:</strong>
        <label style="margin-left:12px"><input type="radio" name="respondent_type" value="student" <?=$type==='student'?'checked':''?>> Student user</label>
        <label style="margin-left:12px"><input type="radio" name="respondent_type" value="expert" <?=$type==='expert'?'checked':''?>> IT expert</label>
      </div>

      <?php foreach($catalog as $charKey=>$c):?>
      <div style="margin-bottom:20px">
        <div style="font-weight:700;font-size:0.92rem;margin-bottom:8px;color:var(--primary)"><?=htmlspecialchars($c['label'])?></div>
        <?php foreach($c['items'] as $itemKey=>$statement):?>
        <div style="padding:8px 0;border-bottom:1px solid var(--border)">
          <div style="font-size:0.86rem;margin-bottom:6px"><?=htmlspecialchars($statement)?></div>
          <?php for($v=5;$v>=1;$v--):?>
          <label style="display:inline-flex;align-items:center;gap:4px;margin-right:16px;font-size:0.84rem">
            <input type="radio" name="score[<?=htmlspecialchars($itemKey)?>]" value="<?=$v?>" <?=(($given[$itemKey]??0)===$v)?'checked':''?>> <?=$v?>
          </label>
          <?php endfor;?>
        </div>
        <?php endforeach;?>
      </div>
      <?php endforeach;?>

      <label for="comments" class="text-sm" style="display:block;margin-bottom:6px">Comments or suggestions (optional)</label>
      <textarea id="comments" name="comments" class="form-control" rows="3" maxlength="1000"><?=htmlspecialchars($comments)?></textarea>
      <div style="margin-top:16px"><button type="submit" class="btn btn-primary" style="width:auto"><i class="fas fa-paper-plane"></i> Submit ratings</button></div>
    </div>
  </form>
</div>

<?php include '../includes/footer.php';?>
