<?php
// Shared by modules/ai_tools.php and modules/study.php.
// AI models often add markdown or vary the labels, so normalise before parsing.

function normalizeAiText(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/[*_`#]+/', '', $text);
    // "Q1.", "Question 2:", "Q: " -> "Q: "
    $text = preg_replace('/^[ \t]*(?:Question|Q)\s*\d*\s*[.:)\-]\s*/mi', 'Q: ', $text);
    // "a)", "A.", "- B:" -> "A: "
    $text = preg_replace_callback('/^[ \t]*[-•]?\s*\(?([A-Da-d])\s*[.:)]\s+/m', fn($m) => strtoupper($m[1]) . ': ', $text);
    // "Answer: B", "Correct answer - B", "ANSWER: (B)"
    $text = preg_replace('/^[ \t]*(?:Correct\s+)?Answer\s*[:\-]\s*\(?([A-Da-d])\)?.*$/mi', 'ANSWER: $1', $text);
    // "Front:" / "Back:", "Card 1 - Front:"
    $text = preg_replace('/^[ \t]*(?:Card\s*\d+\s*[-:.]?\s*)?Front\s*[:\-]\s*/mi', 'FRONT: ', $text);
    $text = preg_replace('/^[ \t]*Back\s*[:\-]\s*/mi', 'BACK: ', $text);
    return trim($text);
}

function parseQuiz(string $text): array
{
    $text = normalizeAiText($text);
    $questions = [];
    $blocks = preg_split('/\n\s*Q:/i', "\n" . $text);
    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '' || !preg_match('/^(.+?)\n\s*A:/si', $block, $questionMatch)) {
            continue;
        }

        $options = [];
        foreach (['A', 'B', 'C', 'D'] as $letter) {
            if (preg_match('/^[ \t]*' . $letter . ':\s*(.+?)(?=\n\s*[A-D]:|\n\s*ANSWER:|$)/msi', $block, $optionMatch)) {
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
    $text = normalizeAiText($text);
    $cards = [];
    $blocks = preg_split('/\n\s*FRONT:/i', "\n" . $text);
    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block !== '' && preg_match('/^(.+?)\n\s*BACK:\s*(.+?)$/si', $block, $match)) {
            $cards[] = ['front' => trim($match[1]), 'back' => trim($match[2])];
        }
    }
    return $cards;
}

// Turns the AI's markdown-style summary into safe, readable HTML:
// headings, bullet lists and **bold** key terms. All text is escaped first.
function renderSummary(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $inline = static function (string $s): string {
        $s = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
        $s = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $s);
        $s = preg_replace('/(?<![\w*])\*(?!\s)([^*\n]+?)(?<!\s)\*(?![\w*])/', '<em>$1</em>', $s);
        $s = preg_replace('/`([^`\n]+)`/', '<code>$1</code>', $s);
        return str_replace('**', '', $s);
    };
    $html = '';
    $depth = 0; // 0 = no list, 1 = list, 2 = nested list
    $closeLists = static function (int &$depth, string &$html, int $to): void {
        while ($depth > $to) { $html .= '</ul>'; $depth--; }
    };
    foreach (explode("\n", $text) as $line) {
        if (trim($line) === '') { $closeLists($depth, $html, 0); continue; }
        if (preg_match('/^\s{0,3}#{1,6}\s+(.+)$/', $line, $m) || preg_match('/^\s*\*\*([^*]+)\*\*:?\s*$/', $line, $m)) {
            $closeLists($depth, $html, 0);
            $html .= '<div style="font-weight:700;font-size:0.98rem;margin:18px 0 6px;color:var(--text-dark)">' . $inline(rtrim(trim($m[1]), ':')) . '</div>';
            continue;
        }
        if (preg_match('/^(\s*)(?:[*\-\x{2022}]|\d+[.)])\s+(.+)$/u', $line, $m)) {
            $level = strlen(str_replace("\t", '    ', $m[1])) >= 2 ? 2 : 1;
            while ($depth < $level) { $html .= '<ul style="margin:4px 0 4px ' . ($depth === 0 ? '0' : '18px') . ';padding-left:22px">'; $depth++; }
            $closeLists($depth, $html, $level);
            $html .= '<li style="margin:3px 0">' . $inline($m[2]) . '</li>';
            continue;
        }
        $closeLists($depth, $html, 0);
        $html .= '<p style="margin:6px 0">' . $inline(trim($line)) . '</p>';
    }
    $closeLists($depth, $html, 0);
    return $html;
}
