<?php
// Run once from the project root:  php database/migrations/migrate_email_reminders.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../config/db.php';

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $query = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $query->execute([$column]);
    return (bool)$query->fetch();
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $query = $pdo->prepare("SHOW INDEX FROM `$table` WHERE Key_name = ?");
    $query->execute([$index]);
    return (bool)$query->fetch();
}

// 1. Per-user token used by the one-click unsubscribe link in reminder emails.
if (!columnExists($pdo, 'users', 'unsubscribe_token')) {
    $pdo->exec("ALTER TABLE users ADD COLUMN unsubscribe_token CHAR(64) DEFAULT NULL");
    echo "Added users.unsubscribe_token\n";
}
if (!indexExists($pdo, 'users', 'unique_unsubscribe_token')) {
    $pdo->exec("ALTER TABLE users ADD UNIQUE KEY unique_unsubscribe_token (unsubscribe_token)");
    echo "Added unique index on users.unsubscribe_token\n";
}

// Backfill tokens for existing users (new users get one on their first reminder).
$missing = $pdo->query("SELECT id FROM users WHERE unsubscribe_token IS NULL")->fetchAll();
$set = $pdo->prepare("UPDATE users SET unsubscribe_token = ? WHERE id = ? AND unsubscribe_token IS NULL");
foreach ($missing as $row) {
    $set->execute([bin2hex(random_bytes(32)), (int)$row['id']]);
}
echo "Backfilled " . count($missing) . " user token(s)\n";

// 2. Remember the last day a reminder was sent so nobody is emailed twice for the same due review.
if (!columnExists($pdo, 'review_schedule', 'last_reminded_on')) {
    $pdo->exec("ALTER TABLE review_schedule ADD COLUMN last_reminded_on DATE DEFAULT NULL");
    echo "Added review_schedule.last_reminded_on\n";
}

echo "Email reminder migration complete.\n";
