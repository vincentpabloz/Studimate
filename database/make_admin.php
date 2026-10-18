<?php
// Make an existing account an administrator:  php database/make_admin.php someone@example.com
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/db.php';
$email = trim($argv[1] ?? '');
if ($email === '') { fwrite(STDERR, "Usage: php database/make_admin.php email@example.com\n"); exit(1); }
$q = $pdo->prepare("UPDATE users SET role = 'admin', is_active = 1 WHERE email = ?");
$q->execute([$email]);
if ($q->rowCount() === 0) {
    $exists = $pdo->prepare('SELECT role FROM users WHERE email = ?');
    $exists->execute([$email]);
    echo $exists->fetch() ? "That account is already an administrator.\n" : "No account found with that email. Register it first.\n";
    exit(0);
}
echo "Done. $email is now an administrator. Log out and back in.\n";
