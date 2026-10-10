<?php
session_start();
if(isset($_SESSION['user_id'])){header("Location: dashboard.php");exit;}
require 'config/db.php';
$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $name=trim($_POST['name']??'');
  $email=trim($_POST['email']??'');
  $pass=$_POST['password']??'';
  $conf=$_POST['confirm_password']??'';
  if(!$name||!$email||!$pass){$error='Please fill in all fields.';}
  elseif(strlen($name)<2){$error='Name must be at least 2 characters.';}
  elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)){$error='Enter a valid email address.';}
  elseif(strlen($pass)<8){$error='Password must be at least 8 characters.';}
  elseif($pass!==$conf){$error='Passwords do not match.';}
  else{
    $chk=$pdo->prepare("SELECT id FROM users WHERE email=?");$chk->execute([$email]);
    if($chk->rowCount()>0){$error='This email is already registered.';}
    else{
      $ins=$pdo->prepare("INSERT INTO users (name,email,password) VALUES (?,?,?)");
      $ins->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT)]);
      $success='Account created! You can now log in.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudiMate – Create Account</title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-brand">
    <div class="auth-brand-icon">✦</div>
    <h1>StudiMate</h1>
  </div>

  <div class="auth-card">
    <div class="auth-card-title">Create account</div>
    <div class="auth-card-sub" style="color:var(--text-mid)">Start your AI study journey</div>

    <div class="auth-tabs" style="margin-top:14px">
      <a href="index.php">Log In</a>
      <a href="register.php" class="active">Register</a>
    </div>

    <?php if($error):?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?=htmlspecialchars($error)?></div>
    <?php endif;?>
    <?php if($success):?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i><?=htmlspecialchars($success)?></div>
    <?php endif;?>

    <form method="POST" id="registerForm" novalidate>
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="name" class="form-control" placeholder="Alex Johnson"
               value="<?=htmlspecialchars($_POST['name']??'')?>" autocomplete="name">
        <div class="field-error"></div>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" class="form-control" placeholder="alex@university.edu"
               value="<?=htmlspecialchars($_POST['email']??'')?>" autocomplete="email">
        <div class="field-error"></div>
      </div>
      <div class="form-group">
        <label>Password</label>
        <div style="position:relative">
          <input type="password" name="password" id="pw" class="form-control" placeholder="••••••••"
                 autocomplete="new-password" oninput="checkStrength(this)">
          <button type="button" onclick="togglePw('pw',this)"
            style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-light);font-size:0.85rem">
            <i class="fas fa-eye"></i>
          </button>
        </div>
        <div style="margin-top:6px;height:4px;background:var(--border);border-radius:2px;overflow:hidden">
          <div id="pwBar" style="height:100%;width:0;border-radius:2px;transition:all 0.3s"></div>
        </div>
        <div id="pwLbl" style="font-size:0.68rem;color:var(--text-light);margin-top:2px;font-weight:600;padding-left:2px"></div>
        <div class="field-error"></div>
      </div>
      <div class="form-group">
        <label>Confirm Password</label>
        <div style="position:relative">
          <input type="password" name="confirm_password" id="pw2" class="form-control" placeholder="••••••••" autocomplete="new-password">
          <button type="button" onclick="togglePw('pw2',this)"
            style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-light);font-size:0.85rem">
            <i class="fas fa-eye"></i>
          </button>
        </div>
        <div class="field-error"></div>
      </div>
      <button type="submit" class="btn btn-primary">Create Account</button>
    </form>

    <p class="text-center text-sm mt-4" style="color:var(--text-mid)">
      Have an account? <a href="index.php" style="font-weight:700;color:var(--text-dark)">Log in</a>
    </p>
  </div>

  <div class="auth-features" style="max-width:420px;width:100%;margin-top:12px">
    <div class="auth-feature"><span>🧠</span>AI Quizzes</div>
    <div class="auth-feature"><span>🃏</span>Flashcards</div>
    <div class="auth-feature"><span>📊</span>Analytics</div>
  </div>
</div>
<script src="assets/js/main.js"></script>
</body></html>
