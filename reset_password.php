<?php
session_start();
require 'config/db.php';
require 'includes/password_reset_helpers.php';
date_default_timezone_set('Asia/Manila');
ensureTemporaryPasswordColumn($pdo);

$csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf;
$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$done = false;
ensurePasswordResetTable($pdo);
$reset = findValidReset($pdo, $token);

if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['password'] ?? '';
    $conf = $_POST['confirm_password'] ?? '';
    if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session expired. Reload the page and try again.';
    } elseif (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (strlen($pass) > 72) {
        $error = 'Password must be 72 characters or fewer.';
    } elseif ($pass !== $conf) {
        $error = 'Passwords do not match.';
    } else {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE users SET password=?, must_change_password=0, temporary_password_hash=NULL, temporary_password_expires_at=NULL WHERE id=?")
          ->execute([password_hash($pass, PASSWORD_DEFAULT), $reset['user_id']]);
        $pdo->prepare("UPDATE password_resets SET used_at=NOW() WHERE id=?")->execute([$reset['id']]);
        $pdo->commit();
        unset($_SESSION['login_fail'], $_SESSION['login_lock_until']);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudiMate – Reset Password</title>
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
  <div class="auth-brand">
    <div class="auth-brand-icon"><i class="fas fa-book-open" aria-hidden="true"></i></div>
    <h1>StudiMate</h1>
  </div>
  <div class="auth-card">
    <?php if ($done): ?>
      <div class="auth-card-title">Password updated</div>
      <div class="alert alert-success"><i class="fas fa-check-circle"></i>You can now log in with your new password.</div>
      <a href="index.php" class="btn btn-primary" style="display:block;text-align:center">Go to log in</a>
    <?php elseif (!$reset): ?>
      <div class="auth-card-title">Link not valid</div>
      <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i>This reset link is invalid, expired, or already used.</div>
      <a href="forgot_password.php" class="btn btn-primary" style="display:block;text-align:center">Request a new link</a>
    <?php else: ?>
      <div class="auth-card-title">Choose a new password</div>
      <div class="auth-card-sub">At least 8 characters</div>
      <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
        <div class="form-group"><label>New password</label>
          <input type="password" name="password" class="form-control" placeholder="••••••••" autocomplete="new-password" required></div>
        <div class="form-group"><label>Confirm password</label>
          <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" autocomplete="new-password" required></div>
        <button type="submit" class="btn btn-primary">Update password</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body></html>
