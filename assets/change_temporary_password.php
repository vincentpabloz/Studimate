<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require 'config/db.php';

$csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf;
$error = '';
$userId = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT must_change_password, role FROM users WHERE id=?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user || !(int)$user['must_change_password']) {
    header('Location: ' . (($user['role'] ?? $_SESSION['user_role'] ?? '') === 'admin' ? 'modules/admin.php' : 'dashboard.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    $confirmation = (string)($_POST['confirm_password'] ?? '');
    if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session expired. Reload the page and try again.';
    } elseif (strlen($password) < 8 || strlen($password) > 72) {
        $error = 'Password must be between 8 and 72 characters.';
    } elseif ($password !== $confirmation) {
        $error = 'Passwords do not match.';
    } else {
        $update = $pdo->prepare('UPDATE users SET password=?, must_change_password=0, temporary_password_hash=NULL, temporary_password_expires_at=NULL WHERE id=?');
        $update->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
        unset($_SESSION['password_change_required']);
        header('Location: ' . ($user['role'] === 'admin' ? 'modules/admin.php' : 'dashboard.php'));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudiMate – Change Password</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-brand"><h1>StudiMate</h1></div>
  <div class="auth-card">
    <div class="auth-card-title">Choose a new password</div>
    <div class="auth-card-sub">You must change your temporary password to continue.</div>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <div class="form-group"><label>New password</label>
        <input type="password" name="password" class="form-control" autocomplete="new-password" required></div>
      <div class="form-group"><label>Confirm password</label>
        <input type="password" name="confirm_password" class="form-control" autocomplete="new-password" required></div>
      <button type="submit" class="btn btn-primary">Update password</button>
    </form>
  </div>
</div>
</body>
</html>