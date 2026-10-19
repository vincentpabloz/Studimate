<?php
require __DIR__ . '/../includes/quiz_retake.php';

$cases = [
    [0, 10, 1],
    [4, 10, 1],
    [49, 100, 1],
    [5, 10, 3],
    [7, 10, 3],
    [79, 100, 3],
    [8, 10, 7],
    [10, 10, 7],
];
foreach ($cases as [$score, $total, $expectedDays]) {
    if (quizRetakeDays($score, $total) !== $expectedDays) {
        throw new RuntimeException("Expected $expectedDays days for score $score/$total.");
    }
}

echo "Quiz retake rule tests passed.\n";
