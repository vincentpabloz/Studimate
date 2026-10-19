<?php
function ensurePasswordResetTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_reset_token (token_hash),
        KEY idx_reset_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ensureTemporaryPasswordColumn(PDO $pdo): void
{
    $columns = [
        'must_change_password' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER password',
        'temporary_password_hash' => 'CHAR(64) DEFAULT NULL AFTER must_change_password',
        'temporary_password_expires_at' => 'DATETIME DEFAULT NULL AFTER temporary_password_hash',
    ];
    foreach ($columns as $name => $definition) {
        $column = $pdo->query("SHOW COLUMNS FROM users LIKE '$name'")->fetch();
        if (!$column) {
            $pdo->exec("ALTER TABLE users ADD COLUMN $name $definition");
        }
    }
}

function findValidReset(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $q = $pdo->prepare("SELECT * FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at > NOW()");
    $q->execute([hash('sha256', $token)]);
    return $q->fetch() ?: null;
}
