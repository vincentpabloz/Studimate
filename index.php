<?php
session_start();
if(isset($_SESSION['user_id'])){header("Location: ".((($_SESSION['user_role']??'')==='admin')?"modules/admin.php":"dashboard.php"));exit;}
require 'config/db.php';
$csrfToken=$_SESSION['csrf_token']??bin2hex(random_bytes(32));
$_SESSION['csrf_token']=$csrfToken;
$error='';
if(isset($_GET['deactivated']))$error='This account has been deactivated. Please contact the administrator.';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $email=trim($_POST['email']??'');
  $pass=$_POST['password']??'';
  if(!hash_equals($csrfToken,(string)($_POST['csrf_token']??''))){$error='Your session expired. Reload the page and try again.';}
  elseif(($_SESSION['login_lock_until']??0)>time()){
    $wait=(int)ceil(($_SESSION['login_lock_until']-time())/60);
    $error='Too many failed attempts. Try again in '.$wait.' minute'.($wait>1?'s':'').'.';
  }
  elseif(!$email||!$pass){$error='Please fill in all fields.';}
  else{
    $s=$pdo->prepare("SELECT * FROM users WHERE email=?");
    $s->execute([$email]);
    $user=$s->fetch();
    $temporaryPasswordMatches=$user
      &&!empty($user['temporary_password_hash'])
      &&!empty($user['temporary_password_expires_at'])
      &&strtotime($user['temporary_password_expires_at'])>time()
      &&hash_equals($user['temporary_password_hash'],hash('sha256',$pass));
    $passwordMatches=$user&&(password_verify($pass,$user['password'])||$temporaryPasswordMatches);
    if($passwordMatches&&$temporaryPasswordMatches&&(!array_key_exists('is_active',$user)||(int)$user['is_active'])){
      $consume=$pdo->prepare('UPDATE users SET temporary_password_hash=NULL, temporary_password_expires_at=NULL, must_change_password=1 WHERE id=? AND temporary_password_hash=? AND temporary_password_expires_at>NOW() AND must_change_password=0');
      $consume->execute([(int)$user['id'],hash('sha256',$pass)]);
      $passwordMatches=$consume->rowCount()===1;
    }
    if($passwordMatches&&array_key_exists('is_active',$user)&&!(int)$user['is_active']){
      $error='This account has been deactivated. Please contact the administrator.';
    }elseif($passwordMatches){
      session_regenerate_id(true); // prevent session fixation
      unset($_SESSION['login_fail'],$_SESSION['login_lock_until']);
      $_SESSION['user_id']=$user['id'];
      $_SESSION['user_name']=$user['name'];
      $_SESSION['user_email']=$user['email'];
      $_SESSION['user_role']=$user['role'];
      $_SESSION['password_change_required']=$temporaryPasswordMatches||(int)($user['must_change_password']??0)===1;
      try{$pdo->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$user['id']]);}catch(PDOException $e){/* column not created yet */}
      header('Location: '.($_SESSION['password_change_required']?'change_temporary_password.php':($user['role']==='admin'?"modules/admin.php":"dashboard.php")));exit;
    }else{
      $_SESSION['login_fail']=($_SESSION['login_fail']??0)+1;
      if($_SESSION['login_fail']>=5){$_SESSION['login_lock_until']=time()+300;$_SESSION['login_fail']=0;}
      $error='Incorrect email or password.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudiMate – Log In</title>
<script src="assets/js/theme.js?v=<?=filemtime(__DIR__ . '/assets/js/theme.js')?>"></script>
<link rel="stylesheet" href="assets/css/style.css?v=<?=filemtime(__DIR__ . '/assets/css/style.css')?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="auth-page">
  <button type="button" class="theme-toggle theme-toggle-floating" data-theme-toggle aria-label="Switch to dark mode" title="Switch to dark mode" aria-pressed="false">
    <span data-theme-icon aria-hidden="true">☾</span>
    <span data-theme-label>Dark mode</span>
  </button>
  <!-- BRAND -->
  <div class="auth-brand">
    <div class="auth-brand-icon"><i class="fas fa-book-open" aria-hidden="true"></i></div>
    <h1>StudiMate</h1>
  </div>

  <!-- CARD -->
  <div class="auth-card">
    <div class="auth-card-title">Welcome back</div>
    <div class="auth-card-sub">Log in to your study dashboard</div>

    <div class="auth-tabs">
      <a href="index.php" class="active">Log In</a>
      <a href="register.php">Register</a>
    </div>

    <?php if($error):?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?=htmlspecialchars($error)?></div>
    <?php endif;?>

    <form method="POST" id="loginForm" novalidate>
      <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrfToken)?>">
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" class="form-control" placeholder="alex@university.edu"
               value="<?=htmlspecialchars($_POST['email']??'')?>" autocomplete="email">
        <div class="field-error"></div>
      </div>
      <div class="form-group">
        <label>Password</label>
        <div style="position:relative">
          <input type="password" name="password" id="pw" class="form-control" placeholder="••••••••" autocomplete="current-password">
          <button type="button" onclick="togglePw('pw',this)"
            style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-light);font-size:0.85rem">
            <i class="fas fa-eye"></i>
          </button>
        </div>
        <div class="field-error"></div>
      </div>
      <div class="forgot-link"><a href="forgot_password.php">Forgot password?</a></div>
      <button type="submit" class="btn btn-primary">Log In</button>
    </form>

    <p class="text-center text-sm mt-4" style="color:var(--text-mid)">
      New here? <a href="register.php" style="font-weight:700;color:var(--text-dark)">Register</a>
    </p>
  </div>

  <!-- FEATURE ICONS -->
  <div class="auth-features" style="max-width:420px;width:100%;margin-top:12px">
    <div class="auth-feature"><span><i data-lucide="brain" aria-hidden="true"></i></span>AI Quizzes</div>
    <div class="auth-feature"><span><i data-lucide="playing-card" aria-hidden="true"></i></span>Flashcards</div>
    <div class="auth-feature"><span><i data-lucide="chart-no-axes-column-increasing" aria-hidden="true"></i></span>Analytics</div>
  </div>
</div>
<script src="https://unpkg.com/lucide@1.56.0"></script>
<script>lucide.createIcons();</script>
<script src="assets/js/main.js"></script>
</body></html>