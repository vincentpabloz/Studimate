<?php
// Run once from the project root:  php database/migrate_microlearning.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/db.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS micro_units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    source_hash CHAR(64) DEFAULT NULL,
    source_version INT NOT NULL,
    units LONGTEXT NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_micro_lesson_version (lesson_id, source_version),
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
)");
echo "micro_units ready\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS micro_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    lesson_id INT NOT NULL,
    source_version INT NOT NULL,
    unit_index INT NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    attempts INT NOT NULL DEFAULT 1,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_micro_progress (user_id, lesson_id, source_version, unit_index),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
)");
echo "micro_progress ready\n";
echo "Microlearning migration complete.\n";
