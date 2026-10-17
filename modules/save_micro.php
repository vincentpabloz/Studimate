<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error' => 'Not logged in']); exit; }
require '../config/db.php';
require '../includes/content_helpers.php';
require '../includes/micro_helpers.php';
$uid = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) { http_response_code(400); echo json_encode(['error' => 'Invalid request.']); exit; }
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($input['csrf_token'] ?? ''))) {
    http_response_code(403); echo json_encode(['error' => 'Your session expired. Reload and try again.']); exit;
}
$lessonId = (int)($input['lesson_id'] ?? 0);
$unitIndex = (int)($input['unit_index'] ?? -1);
$choice = (int)($input['choice'] ?? -1);
if ($lessonId <= 0 || $unitIndex < 0 || $choice < 0) { http_response_code(400); echo json_encode(['error' => 'Invalid request.']); exit; }

// The lesson must belong to this student.
$st = $pdo->prepare('SELECT l.* FROM lessons l JOIN subjects s ON l.subject_id=s.id WHERE l.id=? AND s.user_id=?');
$st->execute([$lessonId, $uid]);
$lesson = $st->fetch();
if (!$lesson) { http_response_code(403); echo json_encode(['error' => 'Unauthorized']); exit; }

// The correct answer is checked here on the server and never sent to the page beforehand.
$units = microLoad($pdo, $lesson);
if (!$units || !isset($units[$unitIndex]) || $choice >= count($units[$unitIndex]['options'])) {
    http_response_code(400); echo json_encode(['error' => 'Unit not found.']); exit;
}
$answer = (int)$units[$unitIndex]['answer'];
$correct = $choice === $answer;
$pdo->prepare('INSERT INTO micro_progress (user_id,lesson_id,source_version,unit_index,is_correct) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE is_correct=GREATEST(is_correct,VALUES(is_correct)),attempts=attempts+1,completed_at=CURRENT_TIMESTAMP')
    ->execute([$uid, $lessonId, (int)$lesson['version'], $unitIndex, $correct ? 1 : 0]);
$progress = microProgress($pdo, $uid, $lesson);
$correctTotal = count(array_filter($progress, fn($p) => $p['correct']));
echo json_encode(['success' => true, 'correct' => $correct, 'answer' => $answer, 'checked' => count($progress), 'correct_total' => $correctTotal, 'total' => count($units)]);
