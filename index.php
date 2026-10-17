<?php
session_start();
if(isset($_SESSION['user_id'])){header("Location: dashboard.php");exit;}
require 'config/db.php';
$csrfToken=$_SESSION['csrf_token']??bin2hex(random_bytes(32));
$_SESSION['csrf_token']=$csrfToken;
$error='';
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
    if($user&&password_verify($pass,$user['password'])){
      session_regenerate_id(true); // prevent session fixation
      unset($_SESSION['login_fail'],$_SESSION['login_lock_until']);
      $_SESSION['user_id']=$user['id'];
      $_SESSION['user_name']=$user['name'];
      $_SESSION['user_email']=$user['email'];
      $_SESSION['user_role']=$user['role'];
      header("Location: dashboard.php");exit;
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
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="auth-page">
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