<?php
session_start();
if (isset($_SESSION['user_id'])) { header("Location: dashboard.php"); exit; }
require 'config/db.php';
require 'config/env.php';
require 'includes/mail_helpers.php';
require 'includes/password_reset_helpers.php';
date_default_timezone_set('Asia/Manila');

$csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf;
$error = '';
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session expired. Reload the page and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (($_SESSION['reset_last'] ?? 0) > time() - 30) {
        $error = 'Please wait a few seconds before trying again.';
    } else {
        $_SESSION['reset_last'] = time();
        try {
            ensurePasswordResetTable($pdo);
            ensureTemporaryPasswordColumn($pdo);
            $u = $pdo->prepare("SELECT id, name, email FROM users WHERE email=?");
            $u->execute([$email]);
            $user = $u->fetch();
            if ($user) {
                // at most one email per account per 2 minutes
                $recent = $pdo->prepare("SELECT COUNT(*) FROM password_resets WHERE user_id=? AND created_at > (NOW() - INTERVAL 2 MINUTE)");
                $recent->execute([$user['id']]);
                if ((int)$recent->fetchColumn() === 0) {
                    $token = bin2hex(random_bytes(32));
                    $temporaryPassword = bin2hex(random_bytes(16));
                    $pdo->prepare("DELETE FROM password_resets WHERE user_id=?")->execute([$user['id']]);
                    $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?, NOW() + INTERVAL 30 MINUTE)")
                        ->execute([$user['id'], hash('sha256', $token)]);
                    $appUrl = rtrim(mailEnv('APP_URL'), '/');
                    if ($appUrl === '') {
                        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $appUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                    }
                    $link = $appUrl . '/reset_password.php?token=' . $token;
                    $mail = createMailer();
                    $mail->addAddress($user['email'], $user['name']);
                    $mail->isHTML(true);
                    $mail->Subject = 'Your temporary StudiMate password';
                    $name = htmlspecialchars($user['name'], ENT_QUOTES);
                    $mail->Body = "<p>Hi $name,</p><p>Your temporary StudiMate password is <strong>" . htmlspecialchars($temporaryPassword, ENT_QUOTES) . "</strong>. It expires in 30 minutes. Log in with it and choose a new password before using your account.</p>"
                      . "<p>If you prefer, use this one-time reset link. It works for 30 minutes:</p>"
                        . "<p><a href=\"" . htmlspecialchars($link, ENT_QUOTES) . "\">Reset my password</a></p>"
                        . "<p>If the button does not work, copy this address into your browser:<br>" . htmlspecialchars($link, ENT_QUOTES) . "</p>"
                        . "<p>If you did not ask for this, you can ignore this email. Your password will not change.</p>";
                    $mail->AltBody = "Hi {$user['name']},\n\nYour temporary StudiMate password is {$temporaryPassword}. It expires in 30 minutes. Log in with it and choose a new password before using your account.\n\nOr reset your password with this one-time link (valid 30 minutes):\n$link\n\nIf you did not ask for this, ignore this email.";
                    $pdo->beginTransaction();
                    $pdo->prepare('UPDATE users SET temporary_password_hash=?, temporary_password_expires_at=NOW() + INTERVAL 30 MINUTE WHERE id=?')
                      ->execute([hash('sha256', $temporaryPassword), $user['id']]);
                    $mail->send();
                    $pdo->commit();
                }
            }
            $sent = true; // same message whether or not the email exists
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('StudiMate password reset mail failed: ' . $e->getMessage());
            $error = 'We could not send the email right now. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudiMate – Forgot Password</title>
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
    <div class="auth-card-title">Forgot password?</div>
    <div class="auth-card-sub">Enter your email and we'll send you a temporary password</div>
    <?php if ($error): ?>
      <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($sent): ?>
      <div class="alert alert-success"><i class="fas fa-check-circle"></i>If that email is registered, a temporary password is on its way. Check your inbox and spam folder.</div>
    <?php else: ?>
    <form method="POST" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" class="form-control" placeholder="alex@university.edu" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email" required>
      </div>
      <button type="submit" class="btn btn-primary">Send temporary password</button>
    </form>
    <?php endif; ?>
    <p class="text-center text-sm mt-4" style="color:var(--text-mid)"><a href="index.php" style="font-weight:700;color:var(--text-dark)">Back to log in</a></p>
  </div>
</div>
</body></html>
