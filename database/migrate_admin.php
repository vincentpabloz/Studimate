<?php
// Run once from the project root:  php database/migrate_admin.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/db.php';

function colExists(PDO $pdo, string $table, string $column): bool
{
    $q = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $q->execute([$column]);
    return (bool)$q->fetch();
}

if (!colExists($pdo, 'users', 'is_active')) {
    $pdo->exec('ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1');
    echo "Added users.is_active\n";
}
if (!colExists($pdo, 'users', 'last_login_at')) {
    $pdo->exec('ALTER TABLE users ADD COLUMN last_login_at DATETIME DEFAULT NULL');
    echo "Added users.last_login_at\n";
}

$pdo->exec("CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    kind VARCHAR(30) NOT NULL,
    source VARCHAR(10) NOT NULL,
    status ENUM('ok','error') NOT NULL DEFAULT 'ok',
    message VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_activity_kind (kind, created_at)
)");
echo "activity_log ready\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    respondent_type ENUM('student','expert') NOT NULL,
    comments TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_evaluation_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");
echo "evaluations ready\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS evaluation_scores (
    evaluation_id INT NOT NULL,
    item_key VARCHAR(10) NOT NULL,
    characteristic VARCHAR(40) NOT NULL,
    score TINYINT NOT NULL,
    PRIMARY KEY (evaluation_id, item_key),
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE
)");
echo "evaluation_scores ready\n";
echo "Admin migration complete.\n";
