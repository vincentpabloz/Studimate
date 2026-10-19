<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}
require '../config/db.php';
$uid = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid quiz result.']);
    exit;
}
$lid = (int)($input['lesson_id'] ?? 0);
$answers = $input['answers'] ?? null;
if ($lid < 1 || !is_array($answers) || !array_is_list($answers)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid quiz result.']);
    exit;
}
$sessionToken = (string)($_SESSION['csrf_token'] ?? '');
if ($sessionToken === '' || !hash_equals($sessionToken, (string)($input['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['error' => 'Your session expired. Reload and try again.']);
    exit;
}

require '../includes/study_helpers.php';
require '../includes/quiz_retake.php';

$pdo->beginTransaction();
$quizQuery = $pdo->prepare("
    SELECT l.id,st.content
    FROM lessons l
    JOIN subjects s ON s.id=l.subject_id AND s.user_id=?
    JOIN study_tools st ON st.lesson_id=l.id AND st.type='quiz'
      AND st.source_version=l.version AND st.source_hash=l.content_hash
    WHERE l.id=?
    FOR UPDATE
");
$quizQuery->execute([$uid, $lid]);
$savedQuiz = $quizQuery->fetch();
if (!$savedQuiz) {
    $pdo->rollBack();
    http_response_code(403);
    echo json_encode(['error' => 'This saved quiz is no longer available. Reload the Study page and try again.']);
    exit;
}
$questions = parseQuiz((string)$savedQuiz['content']);
if (!$questions || count($answers) !== count($questions)) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => 'Quiz answers do not match the saved quiz. Reload the Study page and try again.']);
    exit;
}
$score = 0;
foreach ($answers as $index => $answer) {
    if (!is_int($answer) || $answer < 0 || $answer > 3) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['error' => 'Invalid quiz answer.']);
        exit;
    }
    if ($answer === (int)$questions[$index]['correct']) {
        $score++;
    }
}
$total = count($questions);
$latestQuery = $pdo->prepare('
    SELECT next_retake_at,GREATEST(TIMESTAMPDIFF(SECOND,NOW(),next_retake_at),0) AS remaining_seconds
    FROM quiz_results
    WHERE user_id=? AND lesson_id=?
    ORDER BY completed_at DESC,id DESC
    LIMIT 1
    FOR UPDATE
');
$latestQuery->execute([$uid, $lid]);
$latest = $latestQuery->fetch();
if ($latest && (int)$latest['remaining_seconds'] > 0) {
    $pdo->rollBack();
    http_response_code(429);
    echo json_encode([
        'error' => 'You cannot retake this quiz yet.',
        'next_retake_at' => $latest['next_retake_at'],
        'remaining_seconds' => (int)$latest['remaining_seconds'],
    ]);
    exit;
}

$percentage = ($score / $total) * 100;
$days = quizRetakeDays($score, $total);
$save = $pdo->prepare('
    INSERT INTO quiz_results (user_id,lesson_id,score,total,percentage,completed_at,next_retake_at)
    VALUES (?,?,?,?,?,NOW(),DATE_ADD(NOW(), INTERVAL ? DAY))
');
$save->execute([$uid, $lid, $score, $total, $percentage, $days]);
$schedule = $pdo->prepare('
    INSERT INTO review_schedule (user_id,lesson_id,next_review_date,interval_days)
    VALUES (?,?,DATE_ADD(CURRENT_DATE, INTERVAL ? DAY),?)
    ON DUPLICATE KEY UPDATE next_review_date=VALUES(next_review_date),interval_days=VALUES(interval_days)
');
$schedule->execute([$uid, $lid, $days, $days]);

if ($percentage < 60) {
    $reason = $percentage < 40 ? 'Low quiz score – urgent review needed' : 'Below target – needs review';
    $recommendation = $pdo->prepare('
        INSERT INTO recommendations (user_id,lesson_id,reason,priority) VALUES (?,?,?,?)
        ON DUPLICATE KEY UPDATE reason=VALUES(reason),priority=VALUES(priority),created_at=NOW()
    ');
    $recommendation->execute([$uid, $lid, $reason, $percentage < 40 ? 3 : 2]);
} else {
    $pdo->prepare('DELETE FROM recommendations WHERE user_id=? AND lesson_id=?')->execute([$uid, $lid]);
}
$pdo->commit();

echo json_encode([
    'success' => true,
    'interval' => $days,
    'score' => $score,
    'total' => $total,
    'percentage' => (int)round($percentage),
] + quizAttemptStatus($pdo, $uid, $lid));
