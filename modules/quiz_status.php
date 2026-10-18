<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}
require '../config/db.php';
require '../includes/quiz_retake.php';

$lessonId = filter_input(INPUT_GET, 'lesson_id', FILTER_VALIDATE_INT);
if (!$lessonId || $lessonId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid quiz.']);
    exit;
}
$userId = (int)$_SESSION['user_id'];
$owner = $pdo->prepare('SELECT l.id FROM lessons l JOIN subjects s ON s.id=l.subject_id WHERE l.id=? AND s.user_id=?');
$owner->execute([$lessonId, $userId]);
if (!$owner->fetchColumn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

echo json_encode(['success' => true] + quizAttemptStatus($pdo, $userId, $lessonId));
