<?php
require __DIR__ . '/../includes/study_helpers.php';

$quiz = parseQuiz("Q: Which option is correct?\nA: First\nB: Second\nC: Third\nD: Fourth\nANSWER: C");
if (count($quiz) !== 1 || $quiz[0]['question'] !== 'Which option is correct?' || $quiz[0]['options'][2] !== 'Third' || $quiz[0]['correct'] !== 2) {
    throw new RuntimeException('Saved quiz content should parse into its question and correct answer.');
}

$flashcards = parseFC("FRONT: Term\nBACK: Definition\nFRONT: Second term\nBACK: Second definition");
if (count($flashcards) !== 2 || $flashcards[1] !== ['front' => 'Second term', 'back' => 'Second definition']) {
    throw new RuntimeException('Saved flashcards should parse into interactive front/back cards.');
}

if (parseQuiz('Invalid quiz') !== [] || parseFC('Invalid flashcards') !== []) {
    throw new RuntimeException('Invalid saved content should not produce study items.');
}

echo "Study helper tests passed.\n";
