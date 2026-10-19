<?php
require __DIR__ . '/../includes/admin_report_helpers.php';

$p = adminParseAction('deactivate user #12');
if ($p !== ['type' => 'deactivate', 'user_id' => 12]) { throw new RuntimeException('deactivate message should parse.'); }
$p = adminParseAction('activate user #3');
if ($p !== ['type' => 'activate', 'user_id' => 3]) { throw new RuntimeException('activate must not be confused with deactivate.'); }
$p = adminParseAction('reset link user #7');
if ($p !== ['type' => 'reset', 'user_id' => 7]) { throw new RuntimeException('reset link message should map to type reset.'); }
if (adminParseAction('something else') !== null || adminParseAction('promote user #x') !== null) {
    throw new RuntimeException('Unknown messages should return null.');
}

foreach (['deactivate', 'activate', 'promote', 'demote', 'reset'] as $type) {
    if (!isset(adminActionTypes()[$type])) { throw new RuntimeException("Missing action type $type."); }
}

$dates = adminLastDays(3, new DateTimeImmutable('2026-10-19'));
if ($dates !== ['2026-10-17', '2026-10-18', '2026-10-19']) { throw new RuntimeException('adminLastDays should end on the given day.'); }
if (count(adminLastDays(30)) !== 30) { throw new RuntimeException('adminLastDays(30) should return 30 dates.'); }
if (adminFillSeries(['2026-10-18' => 4], $dates) !== [0, 4, 0]) { throw new RuntimeException('Missing days should be filled with 0.'); }

$summary = adminTrendSummary(['quizzes' => [1, 2], 'active' => [3, 5], 'ai_new' => [2, 2], 'ai_reused' => [3, 3], 'ai_failed' => [0, 1]]);
if ($summary['quizzes'] !== 3 || $summary['peak_active'] !== 5 || $summary['ai_new'] !== 4 || $summary['ai_reused'] !== 6 || $summary['reuse_pct'] !== 60) {
    throw new RuntimeException('Trend summary figures are wrong (reuse should be 6 of 10 = 60%).');
}
if (adminTrendSummary(['quizzes' => [], 'active' => [], 'ai_new' => [], 'ai_reused' => [], 'ai_failed' => []])['reuse_pct'] !== null) {
    throw new RuntimeException('Reuse percentage should be null when there are no requests.');
}
echo "Admin report helper tests passed.\n";
