<?php
function parseQuiz(string $text): array
{
    $questions = [];
    $blocks = preg_split('/\nQ:/i', "\n" . $text);
    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '' || !preg_match('/^(.+?)\nA:/si', $block, $questionMatch)) {
            continue;
        }

        $options = [];
        foreach (['A', 'B', 'C', 'D'] as $letter) {
            if (preg_match('/' . $letter . ':\s*(.+?)(?=\n[A-D]:|ANSWER:|$)/si', $block, $optionMatch)) {
                $options[] = trim($optionMatch[1]);
            }
        }

        if (count($options) !== 4 || !preg_match('/ANSWER:\s*([A-D])/i', $block, $answerMatch)) {
            continue;
        }

        $questions[] = [
            'question' => trim($questionMatch[1]),
            'options' => $options,
            'correct' => ord(strtoupper($answerMatch[1])) - ord('A'),
        ];
    }
    return $questions;
}

function parseFC(string $text): array
{
    $cards = [];
    $blocks = preg_split('/\nFRONT:/i', "\n" . $text);
    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block !== '' && preg_match('/^(.+?)\nBACK:\s*(.+?)$/si', $block, $match)) {
            $cards[] = ['front' => trim($match[1]), 'back' => trim($match[2])];
        }
    }
    return $cards;
}
