<?php
// Public page (no login) reached from the link in reminder emails.
// GET shows a confirmation button; only POST changes anything, so email scanners that
// pre-open links cannot unsubscribe people by accident.
require 'config/db.php';

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$valid = (bool)preg_match('/^[a-f0-9]{64}$/', $token);
$done = false;

if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $upd = $pdo->prepare("UPDATE users SET notif_reviews = 0 WHERE unsubscribe_token = ?");
    $upd->execute([$token]);
    $done = true; // same message whether or not the token matched a user
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudiMate – Review emails</title>
<script src="assets/js/theme.js?v=<?=filemtime(__DIR__ . '/assets/js/theme.js')?>"></script>
<link rel="stylesheet" href="assets/css/style.css?v=<?=filemtime(__DIR__ . '/assets/css/style.css')?>">
</head>
<body>
<div class="auth-page">
  <button type="button" class="theme-toggle theme-toggle-floating" data-theme-toggle aria-label="Switch to dark mode" title="Switch to dark mode" aria-pressed="false">
    <span data-theme-icon aria-hidden="true">☾</span>
    <span data-theme-label>Dark mode</span>
  </button>
  <div class="auth-brand"><h1>StudiMate</h1></div>
  <div class="auth-card" style="max-width:420px;margin:auto;padding:24px">
    <?php if (!$valid): ?>
      <h2>Link not valid</h2>
      <p>This unsubscribe link is invalid or incomplete.</p>
    <?php elseif ($done): ?>
      <h2>You're unsubscribed</h2>
      <p>You will no longer get review reminder emails. You can turn them back on anytime in Profile &rarr; Notifications.</p>
    <?php else: ?>
      <h2>Turn off review emails?</h2>
      <p>You'll stop getting daily review reminders by email.</p>
      <form method="post">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
        <button type="submit" class="btn btn-primary btn-pill">Yes, turn them off</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
