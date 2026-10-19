<?php
require __DIR__ . '/../includes/micro_helpers.php';

$json = '[{"title":"Unit one","content":"Short text about one idea.","takeaway":"One idea.","question":"What is it?","options":["A","B","C","D"],"answer":2}]';
$units = microParseUnits($json);
if (count($units) !== 1 || $units[0]['answer'] !== 2 || $units[0]['options'][1] !== 'B') {
    throw new RuntimeException('A valid unit should be parsed with its options and correct answer index.');
}

$fenced = "```json\n" . $json . "\n```";
if (count(microParseUnits($fenced)) !== 1) {
    throw new RuntimeException('JSON wrapped in code fences should still be parsed.');
}

$letter = microParseUnits('[{"title":"T","content":"C","takeaway":"K","question":"Q?","options":["a","b","c","d"],"answer":"C"}]');
if (count($letter) !== 1 || $letter[0]['answer'] !== 2) {
    throw new RuntimeException('A letter answer should become a zero-based index.');
}

$wrapped = microParseUnits('{"units":' . $json . '}');
if (count($wrapped) !== 1) {
    throw new RuntimeException('A {"units": [...]} wrapper should be accepted.');
}

$bad = microParseUnits('[{"title":"T","content":"C","takeaway":"K","question":"Q?","options":["a","b"],"answer":5},{"title":"","content":"C","question":"Q?","options":["a","b"],"answer":0}]');
if ($bad !== []) {
    throw new RuntimeException('Units with an out-of-range answer or missing title should be dropped.');
}

if (microParseUnits('not json at all') !== []) {
    throw new RuntimeException('Invalid AI text should produce no units.');
}

if (microChunkCounts(1) !== [10] || microChunkCounts(3) !== [4, 3, 3] || array_sum(microChunkCounts(10)) !== 10 || count(microChunkCounts(12)) !== 12) {
    throw new RuntimeException('Unit counts per part should add up to 10 with at least one unit per part.');
}

if (strpos(microBuildPrompt('Title', 'Body text', 1, 1, 4), 'Split the material into 4 short units') === false) {
    throw new RuntimeException('The prompt should ask for the requested number of units.');
}

echo "Micro helper tests passed.\n";
