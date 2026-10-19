<?php
// Activity log and usage trend helpers for the administrator area.
// Everything here is aggregate or an audit entry: no lesson text, files or quiz answers are read.

const ADMIN_AI_KINDS = "'explanation','quiz','flashcard','microlearning'";

/** The admin actions that are recorded, with the sentence label and badge colour used on the log page. */
function adminActionTypes(): array
{
    return [
        'deactivate' => ['label' => 'Deactivated account', 'badge' => 'badge-danger',  'like' => 'deactivate user #%'],
        'activate'   => ['label' => 'Reactivated account', 'badge' => 'badge-success', 'like' => 'activate user #%'],
        'promote'    => ['label' => 'Made admin',          'badge' => 'badge-primary', 'like' => 'promote user #%'],
        'demote'     => ['label' => 'Removed admin role',  'badge' => 'badge-blue',    'like' => 'demote user #%'],
        'reset'      => ['label' => 'Sent reset link',     'badge' => 'badge-blue',    'like' => 'reset link user #%'],
    ];
}

/** Turns a stored message such as "deactivate user #12" into ['type' => 'deactivate', 'user_id' => 12], or null. */
function adminParseAction(string $message): ?array
{
    if (!preg_match('/^(deactivate|activate|promote|demote|reset link) user #(\d+)$/', $message, $m)) {
        return null;
    }
    return ['type' => $m[1] === 'reset link' ? 'reset' : $m[1], 'user_id' => (int)$m[2]];
}

/** One page of the admin action log, newest first, optionally filtered by action type. */
function adminActivityPage(PDO $pdo, string $type = '', int $page = 1, int $perPage = 20): array
{
    $out = ['rows' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
    $types = adminActionTypes();
    $where = "a.kind = 'admin_action'";
    $params = [];
    if ($type !== '' && isset($types[$type])) {
        $where .= ' AND a.message LIKE ?';
        $params[] = $types[$type]['like'];
    }
    try {
        $count = $pdo->prepare("SELECT COUNT(*) FROM activity_log a WHERE $where");
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        $query = $pdo->prepare("SELECT a.id, a.message, a.created_at, u.name AS admin_name, u.email AS admin_email
            FROM activity_log a LEFT JOIN users u ON u.id = a.user_id
            WHERE $where ORDER BY a.id DESC LIMIT " . (int)$perPage . ' OFFSET ' . (int)(($page - 1) * $perPage));
        $query->execute($params);
        $rows = $query->fetchAll();

        $ids = [];
        foreach ($rows as $row) {
            $parsed = adminParseAction((string)$row['message']);
            if ($parsed) { $ids[$parsed['user_id']] = true; }
        }
        $accounts = [];
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $find = $pdo->prepare("SELECT id, name, email FROM users WHERE id IN ($in)");
            $find->execute(array_keys($ids));
            foreach ($find->fetchAll() as $account) { $accounts[(int)$account['id']] = $account; }
        }
        foreach ($rows as &$row) {
            $parsed = adminParseAction((string)$row['message']);
            $row['type'] = $parsed['type'] ?? null;
            $row['target'] = $parsed ? ($accounts[$parsed['user_id']] ?? ['name' => 'Removed account #' . $parsed['user_id'], 'email' => '']) : null;
        }
        unset($row);
        $out = ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
    } catch (Throwable $e) {
    }
    return $out;
}

/** Number of recorded actions per type across all administrators, plus totals. */
function adminActionCounts(PDO $pdo): array
{
    $out = ['total' => 0, 'last30' => 0, 'admins' => 0, 'by_type' => array_fill_keys(array_keys(adminActionTypes()), 0)];
    try {
        $sums = [];
        foreach (adminActionTypes() as $key => $t) {
            $sums[] = "SUM(message LIKE '" . $t['like'] . "') AS $key";
        }
        $r = $pdo->query("SELECT COUNT(*) AS total, SUM(created_at >= (NOW() - INTERVAL 30 DAY)) AS last30, COUNT(DISTINCT user_id) AS admins, "
            . implode(', ', $sums) . " FROM activity_log WHERE kind = 'admin_action'")->fetch();
        $out['total'] = (int)($r['total'] ?? 0);
        $out['last30'] = (int)($r['last30'] ?? 0);
        $out['admins'] = (int)($r['admins'] ?? 0);
        foreach ($out['by_type'] as $key => $_) { $out['by_type'][$key] = (int)($r[$key] ?? 0); }
    } catch (Throwable $e) {
    }
    return $out;
}

/** The last N calendar dates as 'Y-m-d', oldest first, ending today (or at $end). */
function adminLastDays(int $days = 30, ?DateTimeInterface $end = null): array
{
    $end = $end ? DateTimeImmutable::createFromInterface($end) : new DateTimeImmutable('today');
    $dates = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $dates[] = $end->modify("-$i day")->format('Y-m-d');
    }
    return $dates;
}

/** Fills a date => count map into a list that has a value (0 when missing) for every date. */
function adminFillSeries(array $byDate, array $dates): array
{
    return array_map(fn($d) => (int)($byDate[$d] ?? 0), $dates);
}

/**
 * Daily figures for the usage trend charts. Active students are students who, on that day, took a quiz,
 * checked a microlearning unit, or generated study tools. Everything is aggregate.
 */
function adminDailySeries(PDO $pdo, int $days = 30): array
{
    $dates = adminLastDays($days);
    $since = 'CURDATE() - INTERVAL ' . ($days - 1) . ' DAY';

    $countMap = function (string $sql) use ($pdo): array {
        try {
            $map = [];
            foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_NUM) as $r) { $map[$r[0]] = (int)$r[1]; }
            return $map;
        } catch (Throwable $e) {
            return [];
        }
    };

    $quizzes = $countMap("SELECT DATE(taken_at), COUNT(*) FROM quiz_results WHERE taken_at >= $since GROUP BY DATE(taken_at)");
    $ai = ADMIN_AI_KINDS;
    $aiNew = $countMap("SELECT DATE(created_at), COUNT(*) FROM activity_log WHERE kind IN ($ai) AND status = 'ok' AND source = 'ai' AND created_at >= $since GROUP BY DATE(created_at)");
    $aiReused = $countMap("SELECT DATE(created_at), COUNT(*) FROM activity_log WHERE kind IN ($ai) AND status = 'ok' AND source = 'cache' AND created_at >= $since GROUP BY DATE(created_at)");
    $aiFailed = $countMap("SELECT DATE(created_at), COUNT(*) FROM activity_log WHERE kind IN ($ai) AND status = 'error' AND created_at >= $since GROUP BY DATE(created_at)");

    // Active students: the same student counts once per day, however many things they did.
    $seen = [];
    $pairs = [
        "SELECT DISTINCT DATE(t.taken_at), t.user_id FROM quiz_results t JOIN users u ON u.id = t.user_id AND u.role = 'student' WHERE t.taken_at >= $since",
        "SELECT DISTINCT DATE(t.completed_at), t.user_id FROM micro_progress t JOIN users u ON u.id = t.user_id AND u.role = 'student' WHERE t.completed_at >= $since",
        "SELECT DISTINCT DATE(t.created_at), t.user_id FROM activity_log t JOIN users u ON u.id = t.user_id AND u.role = 'student' WHERE t.kind IN ($ai) AND t.created_at >= $since",
    ];
    foreach ($pairs as $sql) {
        try {
            foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_NUM) as $r) { $seen[$r[0]][(int)$r[1]] = true; }
        } catch (Throwable $e) {
        }
    }
    $active = [];
    foreach ($seen as $d => $students) { $active[$d] = count($students); }

    return [
        'dates' => $dates,
        'labels' => array_map(fn($d) => date('M j', strtotime($d)), $dates),
        'quizzes' => adminFillSeries($quizzes, $dates),
        'active' => adminFillSeries($active, $dates),
        'ai_new' => adminFillSeries($aiNew, $dates),
        'ai_reused' => adminFillSeries($aiReused, $dates),
        'ai_failed' => adminFillSeries($aiFailed, $dates),
    ];
}

/** Headline figures from a daily series, including how many Gemini calls saved copies avoided. */
function adminTrendSummary(array $series): array
{
    $new = array_sum($series['ai_new']);
    $reused = array_sum($series['ai_reused']);
    $requests = $new + $reused;
    return [
        'quizzes' => array_sum($series['quizzes']),
        'peak_active' => $series['active'] ? max($series['active']) : 0,
        'ai_new' => $new,
        'ai_reused' => $reused,
        'ai_failed' => array_sum($series['ai_failed']),
        'reuse_pct' => $requests > 0 ? (int)round($reused / $requests * 100) : null,
    ];
}
