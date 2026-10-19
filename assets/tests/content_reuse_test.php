<?php
require __DIR__ . '/../includes/content_helpers.php';

function expectSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
    }
}

$known = 'Previously stored chapter content.';
$novel = 'New chapter content.';
$combinedSource = combineLessonSourceText("Typed lesson notes.\n", 'Extracted document text.');
expectSame("Typed lesson notes.\n\nExtracted document text.", $combinedSource, 'Typed and extracted lesson sources should be combined without losing either source.');
expectSame('Typed lesson notes.', combineLessonSourceText(' Typed lesson notes. ', ''), 'Text-only lesson content should remain intact.');
$knownHash = hash('sha256', normalizeLessonContent($known));
$partition = partitionKnownKnowledgeChunks([
    ['content' => $known, 'page' => 1],
    ['content' => $novel, 'page' => 2],
], [$knownHash => 42]);
expectSame($novel, $partition['novel_content'], 'Only new material should be passed as a new lesson source.');
expectSame(false, $partition['chunks'][0]['is_novel'], 'Known chunks should be marked reusable.');
expectSame(true, $partition['chunks'][1]['is_novel'], 'New chunks should be marked novel.');
expectSame($novel, combineLessonChunkContent([['content' => $novel], ['content' => $novel]]), 'Reconstructed sources should remove exact repeated chunks.');

$lesson = ['id' => 1, 'version' => 1, 'content_hash' => 'source-v1'];
$keyV1 = studyOutputCacheKey([$lesson], 'explanation');
$lesson['version'] = 2;
$lesson['content_hash'] = 'source-v2';
expectSame(false, $keyV1 === studyOutputCacheKey([$lesson], 'explanation'), 'Edited content must invalidate output cache identity.');
expectSame(false, $keyV1 === studyOutputCacheKey([['id' => 1, 'version' => 1, 'content_hash' => 'source-v1']], 'quiz'), 'Output types must have separate cache identities.');

$combined = mergeStoredStudyContent([
    ['title' => 'Chapter 1', 'tool_content' => "Q: Shared\nA: one\nB: two\nC: three\nD: four\nANSWER: A"],
    ['title' => 'Chapter 2', 'tool_content' => "Q: Shared\nA: one\nB: two\nC: three\nD: four\nANSWER: A\nQ: New\nA: one\nB: two\nC: three\nD: four\nANSWER: B"],
], 'quiz');
expectSame(1, substr_count($combined, 'Q: Shared'), 'Combined quizzes should remove exact duplicates.');
expectSame(1, substr_count($combined, 'Q: New'), 'Combined quizzes should retain unique questions.');

echo "Content reuse tests passed.\n";