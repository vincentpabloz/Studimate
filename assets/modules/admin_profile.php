<?php
session_start();
require '../config/db.php';
require_once '../config/env.php';
require '../includes/admin_helpers.php';
$adminId=requireAdmin($pdo);
$base='../';

$csrfToken=$_SESSION['csrf_token']??bin2hex(random_bytes(32));
$_SESSION['csrf_token']=$csrfToken;

$st=$pdo->prepare('SELECT id,name,email,password,created_at,last_login_at FROM users WHERE id=?');
$st->execute([$adminId]);
$u=$st->fetch();

$msg='';$msgType='success';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=(string)($_POST['action']??'');
  if(!hash_equals($csrfToken,(string)($_POST['csrf_token']??''))){
    $msg='Your session expired. Reload the page and try again.';$msgType='danger';
  }elseif($action==='update_profile'){
    $name=trim((string)($_POST['name']??''));
    if($name===''){$msg='Name cannot be empty.';$msgType='danger';}
    elseif(mb_strlen($name)>100){$msg='Name is too long.';$msgType='danger';}
    else{
      $pdo->prepare('UPDATE users SET name=? WHERE id=?')->execute([$name,$adminId]);
      $_SESSION['user_name']=$name;$u['name']=$name;
      $msg='Profile updated successfully!';
    }
  }elseif($action==='change_password'){
    $cur=(string)($_POST['current_password']??'');
    $new=(string)($_POST['new_password']??'');
    $conf=(string)($_POST['confirm_new']??'');
    if(!password_verify($cur,$u['password'])){$msg='Current password is incorrect.';$msgType='danger';}
    elseif(strlen($new)<8){$msg='New password must be at least 8 characters.';$msgType='danger';}
    elseif($new!==$conf){$msg='Passwords do not match.';$msgType='danger';}
    else{
      $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$adminId]);
      $msg='Password changed successfully!';
    }
  }
}

$fmt=fn($t)=>$t?date('M j, Y g:i A',strtotime($t)):'Never';
$initial=strtoupper(substr($u['name'],0,1));

$pageTitle='Profile – StudiMate Admin';
$topbarTitle='Profile';
include '../includes/header.php';
include '../includes/admin_sidebar.php';
?>

<div class="page-body" style="max-width:760px;margin:0 auto">
  <?php if($msg):?>
  <div class="alert alert-<?=$msgType?> mb-4"><i class="fas fa-<?=$msgType==='success'?'check-circle':'exclamation-circle'?>"></i> <?=htmlspecialchars($msg)?></div>
  <?php endif;?>

  <div class="profile-card-top">
    <div class="profile-avatar-lg"><?=htmlspecialchars($initial)?></div>
    <div>
      <div class="profile-name"><?=htmlspecialchars($u['name'])?></div>
      <div class="profile-sub"><?=htmlspecialchars($u['email'])?> · Administrator</div>
      <div class="profile-active">Active</div>
    </div>
  </div>

  <!-- ACCOUNT INFO -->
  <div class="card mb-4">
    <div class="card-header"><h3>Account info</h3></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrfToken)?>">
        <input type="hidden" name="action" value="update_profile">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="name" class="form-control form-control-flat" value="<?=htmlspecialchars($u['name'])?>" maxlength="100" required>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" class="form-control form-control-flat" value="<?=htmlspecialchars($u['email'])?>" disabled style="opacity:0.6;cursor:not-allowed">
        </div>
        <p class="text-sm text-muted" style="margin:0 0 14px">Member since <?=htmlspecialchars(date('M j, Y',strtotime($u['created_at'])))?> · Last login <?=htmlspecialchars($fmt($u['last_login_at']))?></p>
        <button type="submit" class="btn btn-primary btn-pill" style="width:auto;padding:10px 22px">Save Changes</button>
      </form>
    </div>
  </div>

  <!-- PASSWORD -->
  <div class="card mb-4">
    <div class="card-header"><h3>Change password</h3></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrfToken)?>">
        <input type="hidden" name="action" value="change_password">
        <div class="form-group"><label>Current password</label><input type="password" name="current_password" class="form-control form-control-flat" autocomplete="current-password" required></div>
        <div class="grid-2">
          <div class="form-group"><label>New password</label><input type="password" name="new_password" class="form-control form-control-flat" minlength="8" autocomplete="new-password" required></div>
          <div class="form-group"><label>Confirm new password</label><input type="password" name="confirm_new" class="form-control form-control-flat" minlength="8" autocomplete="new-password" required></div>
        </div>
        <button type="submit" class="btn btn-primary btn-pill" style="width:auto;padding:10px 22px">Update password</button>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php';?>
