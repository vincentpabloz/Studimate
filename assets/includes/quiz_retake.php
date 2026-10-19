<?php

const QUIZ_RETAKE_RULES = [
    ['minPercent' => 0, 'days' => 1],
    ['minPercent' => 50, 'days' => 3],
    ['minPercent' => 80, 'days' => 7],
];

function quizRetakeDays(int $score, int $total): int
{
    $percentage = $total > 0 ? ($score / $total) * 100 : 0;
    $days = QUIZ_RETAKE_RULES[0]['days'];
    foreach (QUIZ_RETAKE_RULES as $rule) {
        if ($percentage >= $rule['minPercent']) {
            $days = $rule['days'];
        }
    }
    return $days;
}

function quizAttemptStatus(PDO $pdo, int $userId, int $lessonId, int $limit = 5): array
{
    $limit = max(1, min(10, $limit));
    $query = $pdo->prepare("
        SELECT score,total,percentage,completed_at,next_retake_at,
          GREATEST(TIMESTAMPDIFF(SECOND,NOW(),next_retake_at),0) AS remaining_seconds
        FROM quiz_results
        WHERE user_id=? AND lesson_id=?
        ORDER BY completed_at DESC,id DESC
        LIMIT $limit
    ");
    $query->execute([$userId, $lessonId]);
    $attempts = [];
    foreach ($query->fetchAll() as $attempt) {
        $score = (int)$attempt['score'];
        $total = (int)$attempt['total'];
        $days = quizRetakeDays($score, $total);
        $attempts[] = [
            'score' => $score,
            'total' => $total,
            'percentage' => (float)$attempt['percentage'],
            'completed_at' => $attempt['completed_at'],
            'completed_label' => date('M j, Y g:i A', strtotime($attempt['completed_at'])),
            'next_retake_at' => $attempt['next_retake_at'],
            'next_retake_label' => date('M j, Y g:i A', strtotime($attempt['next_retake_at'])),
            'days' => $days,
            'rule_message' => 'Score ' . (int)round((float)$attempt['percentage']) . '% -> next retake in ' . $days . ' ' . ($days === 1 ? 'day' : 'days'),
            'remaining_seconds' => (int)$attempt['remaining_seconds'],
        ];
    }

    return [
        'attempts' => $attempts,
        'latest' => $attempts[0] ?? null,
        'eligible' => !$attempts || $attempts[0]['remaining_seconds'] === 0,
    ];
}
