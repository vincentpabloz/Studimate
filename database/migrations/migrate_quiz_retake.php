<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../config/db.php';

function quizResultColumnExists(PDO $pdo, string $column): bool
{
    $query = $pdo->prepare('SHOW COLUMNS FROM quiz_results LIKE ?');
    $query->execute([$column]);
    return (bool)$query->fetch();
}

$columns = [
    'percentage' => 'DECIMAL(5,2) NOT NULL DEFAULT 0',
    'completed_at' => 'DATETIME NULL',
    'next_retake_at' => 'DATETIME NULL',
];
foreach ($columns as $name => $definition) {
    if (!quizResultColumnExists($pdo, $name)) {
        $pdo->exec("ALTER TABLE quiz_results ADD COLUMN `$name` $definition");
    }
}
$pdo->exec("
    UPDATE quiz_results
    SET percentage=COALESCE(ROUND(score*100/NULLIF(total,0),2),0),
        completed_at=COALESCE(completed_at,taken_at),
        next_retake_at=COALESCE(next_retake_at,DATE_ADD(taken_at, INTERVAL
          (CASE WHEN score*100/NULLIF(total,0) >= 80 THEN 7
                WHEN score*100/NULLIF(total,0) >= 50 THEN 3 ELSE 1 END) DAY))
    WHERE completed_at IS NULL OR next_retake_at IS NULL OR percentage=0
");
$pdo->exec('ALTER TABLE quiz_results MODIFY completed_at DATETIME NOT NULL, MODIFY next_retake_at DATETIME NOT NULL');
$index = $pdo->prepare('SHOW INDEX FROM quiz_results WHERE Key_name=?');
$index->execute(['quiz_retake_lookup']);
if (!$index->fetch()) {
    $pdo->exec('ALTER TABLE quiz_results ADD INDEX quiz_retake_lookup (user_id,lesson_id,completed_at)');
}

echo "Quiz retake migration completed.\n";
