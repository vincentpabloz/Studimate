<?php
session_start();
if(!isset($_SESSION['user_id'])){header("Location: ../index.php");exit;}
require '../config/db.php';
$uid=$_SESSION['user_id'];$base='../';

$user=$pdo->prepare("SELECT * FROM users WHERE id=?");
$user->execute([$uid]);$u=$user->fetch();

$msg='';$msgType='success';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';
  if($action==='update_profile'){
    $name=trim($_POST['name']??'');
    $uni=trim($_POST['university']??'');
    $major=trim($_POST['major']??'');
    if(!$name){$msg='Name cannot be empty.';$msgType='danger';}
    else{
      $upd=$pdo->prepare("UPDATE users SET name=?,university=?,major=? WHERE id=?");
      $upd->execute([$name,$uni,$major,$uid]);
      $_SESSION['user_name']=$name;
      $msg='Profile updated successfully!';
      $u['name']=$name;$u['university']=$uni;$u['major']=$major;
    }
  }
  if($action==='update_notifs'){
    $nr=isset($_POST['notif_reviews'])?1:0;
    $nq=isset($_POST['notif_quizzes'])?1:0;
    $upd=$pdo->prepare("UPDATE users SET notif_reviews=?,notif_quizzes=? WHERE id=?");
    $upd->execute([$nr,$nq,$uid]);
    $msg='Notification settings saved.';
    $u['notif_reviews']=$nr;$u['notif_quizzes']=$nq;
  }
  if($action==='change_password'){
    $cur=$_POST['current_password']??'';
    $new=$_POST['new_password']??'';
    $conf=$_POST['confirm_new']??'';
    if(!password_verify($cur,$u['password'])){$msg='Current password is incorrect.';$msgType='danger';}
    elseif(strlen($new)<8){$msg='New password must be at least 8 characters.';$msgType='danger';}
    elseif($new!==$conf){$msg='Passwords do not match.';$msgType='danger';}
    else{
      $upd=$pdo->prepare("UPDATE users SET password=? WHERE id=?");
      $upd->execute([password_hash($new,PASSWORD_DEFAULT),$uid]);
      $msg='Password changed successfully!';
    }
  }
}

$initial=strtoupper(substr($u['name'],0,1));
$pageTitle='Profile – StudiMate';
$topbarTitle='Profile';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="page-body" style="max-width:680px;margin:0 auto">

  <?php if($msg):?>
  <div class="alert alert-<?=$msgType?> mb-4"><i class="fas fa-<?=$msgType==='success'?'check-circle':'exclamation-circle'?>"></i><?=htmlspecialchars($msg)?></div>
  <?php endif;?>

  <!-- PROFILE TOP CARD -->
  <div class="profile-card-top">
    <div class="profile-avatar-lg"><?=$initial?></div>
    <div>
      <div class="profile-name"><?=htmlspecialchars($u['name'])?></div>
      <div class="profile-sub"><?=htmlspecialchars($u['major']??'')?><?=($u['major']&&$u['university'])?' · ':''?><?=htmlspecialchars($u['university']??'')?></div>
      <div class="profile-active">Active</div>
    </div>
  </div>

  <!-- PERSONAL INFO -->
  <div class="card mb-4">
    <div class="card-header"><h3>Personal Info</h3></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="action" value="update_profile">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="name" class="form-control form-control-flat"
                 value="<?=htmlspecialchars($u['name'])?>" required>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" class="form-control form-control-flat"
                 value="<?=htmlspecialchars($u['email'])?>" disabled
                 style="opacity:0.6;cursor:not-allowed">
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label>University</label>
            <input type="text" name="university" class="form-control form-control-flat"
                   value="<?=htmlspecialchars($u['university']??'')?>" placeholder="e.g. PLMun">
          </div>
          <div class="form-group">
            <label>Major</label>
            <input type="text" name="major" class="form-control form-control-flat"
                   value="<?=htmlspecialchars($u['major']??'')?>" placeholder="e.g. Computer Science">
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-pill" style="width:auto;padding:10px 22px">Save Changes</button>
      </form>
    </div>
  </div>

  <!-- NOTIFICATIONS -->
  <div class="card mb-4">
    <div class="card-header"><h3>Notifications</h3></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="action" value="update_notifs">
        <div class="toggle-row">
          <div class="toggle-info">
            <h5>Review reminders</h5>
            <p>When a review session is due</p>
          </div>
          <label class="toggle">
            <input type="checkbox" name="notif_reviews" <?=$u['notif_reviews']?'checked':''?> onchange="this.form.submit()">
            <span class="toggle-slider"></span>
          </label>
        </div>
        <div class="toggle-row">
          <div class="toggle-info">
            <h5>Quiz suggestions</h5>
            <p>AI-suggested quizzes</p>
          </div>
          <label class="toggle">
            <input type="checkbox" name="notif_quizzes" <?=$u['notif_quizzes']?'checked':''?> onchange="this.form.submit()">
            <span class="toggle-slider"></span>
          </label>
        </div>
      </form>
    </div>
  </div>

  <!-- CHANGE PASSWORD -->
  <div class="card mb-4">
    <div class="card-header"><h3>Change Password</h3></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="action" value="change_password">
        <div class="form-group">
          <label>Current Password</label>
          <input type="password" name="current_password" class="form-control form-control-flat" placeholder="••••••••">
        </div>
        <div class="form-group">
          <label>New Password</label>
          <input type="password" name="new_password" class="form-control form-control-flat" placeholder="Min 8 characters">
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <input type="password" name="confirm_new" class="form-control form-control-flat" placeholder="Re-enter new password">
        </div>
        <button type="submit" class="btn btn-outline btn-pill btn-square" style="border-radius:var(--radius-sm)">Update Password</button>
      </form>
    </div>
  </div>

  <!-- ACTIONS -->
  <div class="grid-2">
    <button class="btn btn-outline btn-square" style="border-radius:var(--radius-sm);justify-content:center">
      <i class="fas fa-cog"></i> Settings
    </button>
    <a href="../logout.php" class="btn btn-square"
       style="background:#FEE2E2;color:#B91C1C;border:none;border-radius:var(--radius-sm);justify-content:center">
      <i class="fas fa-sign-out-alt"></i> Log Out
    </a>
  </div>

</div>

<?php include '../includes/footer.php';?>
