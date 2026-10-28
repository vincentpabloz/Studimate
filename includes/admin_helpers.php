<?php
// Administrator helpers: access control, activity log, monitoring queries and evaluation scoring.
// Admin pages only ever read totals and averages. They never read lesson text, uploaded files or quiz answers.

const ADMIN_MIN_GROUP = 3; // an aggregate row is hidden unless at least this many different students are behind it

function adminEnv(string $name): string
{
    $value = getenv($name);
    if ($value !== false && $value !== '') {
        return (string)$value;
    }
    return (string)($_ENV[$name] ?? '');
}

/** Stops the request unless the signed-in user is an active administrator. The role is re-read from the database. */
function requireAdmin(PDO $pdo): int
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ../index.php');
        exit;
    }
    try {
        $query = $pdo->prepare('SELECT role, is_active FROM users WHERE id = ?');
        $query->execute([(int)$_SESSION['user_id']]);
    } catch (PDOException $e) { // is_active not created yet
        $query = $pdo->prepare('SELECT role, 1 AS is_active FROM users WHERE id = ?');
        $query->execute([(int)$_SESSION['user_id']]);
    }
    $user = $query->fetch();
    if (!$user || $user['role'] !== 'admin' || !(int)$user['is_active']) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Access denied</title><link rel="stylesheet" href="../assets/css/style.css?v=' . filemtime(__DIR__ . '/../assets/css/style.css') . '"></head>'
            . '<body><div class="auth-page"><div class="auth-card" style="max-width:420px;text-align:center"><h2>Administrator access only</h2>'
            . '<p>Your account does not have permission to open this page.</p><p><a href="../dashboard.php">Back to dashboard</a></p></div></div></body></html>';
        exit;
    }
    return (int)$_SESSION['user_id'];
}

/** Writes one row to the activity log. Logging never breaks the page that calls it. */
function logActivity(PDO $pdo, ?int $userId, string $kind, string $source, string $status = 'ok', ?string $message = null): void
{
    try {
        $pdo->prepare('INSERT INTO activity_log (user_id, kind, source, status, message) VALUES (?,?,?,?,?)')
            ->execute([$userId, $kind, $source, $status === 'error' ? 'error' : 'ok', $message !== null ? mb_substr($message, 0, 250) : null]);
    } catch (Throwable $e) {
        // table not created yet, or database busy: ignore
    }
}

/** Setup checks shown on the overview page. Never shows secret values. */
function adminHealthChecks(PDO $pdo): array
{
    $checks = [];
    $group = 'Server';
    $add = function (string $label, bool $ok, string $detail = '') use (&$checks, &$group) {
        $checks[] = ['group' => $group, 'label' => $label, 'ok' => $ok, 'detail' => $detail];
    };

    $add('PHP 8.1 or newer', version_compare(PHP_VERSION, '8.1.0', '>='), 'Running PHP ' . PHP_VERSION);
    foreach (['curl' => 'cURL (Gemini API)', 'zip' => 'ZIP (DOCX and PPTX upload)', 'mbstring' => 'mbstring', 'pdo_mysql' => 'PDO MySQL', 'openssl' => 'OpenSSL (SMTP)'] as $ext => $label) {
        $add($label . ' extension', extension_loaded($ext));
    }
    $group = 'Database';
    try {
        $add('Database connection', true, 'MySQL ' . $pdo->query('SELECT VERSION()')->fetchColumn());
    } catch (Throwable $e) {
        $add('Database connection', false);
    }
    $group = 'Integrations';
    $model = adminEnv('GEMINI_MODEL');
    $add('Gemini API key set', adminEnv('GEMINI_API_KEY') !== '', 'Model: ' . ($model !== '' ? $model : 'default'));

    $missing = [];
    foreach (['SMTP_HOST', 'SMTP_USER', 'SMTP_PASS', 'APP_URL'] as $key) {
        if (adminEnv($key) === '') { $missing[] = $key; }
    }
    $add('Email (SMTP) settings', !$missing, $missing ? 'Missing: ' . implode(', ', $missing) : 'Host, user, password and APP_URL are set');
    $add('PHPMailer installed', is_file(__DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php'));

    $group = 'Database';
    $absent = [];
    foreach (['activity_log', 'evaluations', 'evaluation_scores', 'micro_units', 'micro_progress'] as $table) {
        try {
            $q = $pdo->prepare('SHOW TABLES LIKE ?');
            $q->execute([$table]);
            if (!$q->fetch()) { $absent[] = $table; }
        } catch (Throwable $e) {
            $absent[] = $table;
        }
    }
    $add('Database tables up to date', !$absent, $absent ? 'Missing: ' . implode(', ', $absent) . ' (run the migrations)' : 'All tables found');
    return $checks;
}

/** AI generations in the last N days, split into new AI calls, outputs reused from saved copies, and failures. */
function adminAiUsage(PDO $pdo, int $days = 30): array
{
    $rows = [];
    foreach (['explanation' => 'Summary', 'quiz' => 'Quiz', 'flashcard' => 'Flashcards', 'microlearning' => 'Microlearning'] as $kind => $label) {
        $rows[$kind] = ['label' => $label, 'ai' => 0, 'cache' => 0, 'errors' => 0];
    }
    try {
        $q = $pdo->query("SELECT kind, source, status, COUNT(*) AS c FROM activity_log
            WHERE kind IN ('explanation','quiz','flashcard','microlearning') AND created_at >= (NOW() - INTERVAL " . (int)$days . " DAY)
            GROUP BY kind, source, status");
        foreach ($q->fetchAll() as $r) {
            if (!isset($rows[$r['kind']])) { continue; }
            if ($r['status'] === 'error') { $rows[$r['kind']]['errors'] += (int)$r['c']; }
            elseif ($r['source'] === 'cache') { $rows[$r['kind']]['cache'] += (int)$r['c']; }
            else { $rows[$r['kind']]['ai'] += (int)$r['c']; }
        }
    } catch (Throwable $e) {
    }
    return $rows;
}

function adminRecentFailures(PDO $pdo, int $limit = 8): array
{
    try {
        return $pdo->query("SELECT kind, message, created_at FROM activity_log WHERE status = 'error' AND kind <> 'admin_action' ORDER BY id DESC LIMIT " . (int)$limit)->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function adminReminderStatus(PDO $pdo): array
{
    $out = ['opted_in' => 0, 'students' => 0, 'due_now' => 0, 'last_ok' => null, 'sent_30' => 0, 'failed_30' => 0];
    try {
        $out['students'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_active = 1")->fetchColumn();
        $out['opted_in'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_active = 1 AND notif_reviews = 1")->fetchColumn();
        $out['due_now'] = (int)$pdo->query("SELECT COUNT(DISTINCT rs.user_id) FROM review_schedule rs JOIN users u ON u.id = rs.user_id
            WHERE u.is_active = 1 AND u.notif_reviews = 1 AND rs.next_review_date <= CURDATE()")->fetchColumn();
    } catch (Throwable $e) {
    }
    try {
        $out['last_ok'] = $pdo->query("SELECT MAX(created_at) FROM activity_log WHERE kind = 'reminder_email' AND status = 'ok'")->fetchColumn() ?: null;
        $out['sent_30'] = (int)$pdo->query("SELECT COUNT(*) FROM activity_log WHERE kind = 'reminder_email' AND status = 'ok' AND created_at >= (NOW() - INTERVAL 30 DAY)")->fetchColumn();
        $out['failed_30'] = (int)$pdo->query("SELECT COUNT(*) FROM activity_log WHERE kind = 'reminder_email' AND status = 'error' AND created_at >= (NOW() - INTERVAL 30 DAY)")->fetchColumn();
    } catch (Throwable $e) {
    }
    return $out;
}

/** Institution-level totals. No individual student is identified. */
function adminUsageTotals(PDO $pdo): array
{
    $t = ['students' => 0, 'active30' => 0, 'new30' => 0, 'lessons' => 0, 'quizzes' => 0, 'avg_score' => null, 'micro_checked' => 0];
    $one = function (string $sql) use ($pdo) {
        try { return $pdo->query($sql)->fetchColumn(); } catch (Throwable $e) { return 0; }
    };
    $t['students'] = (int)$one("SELECT COUNT(*) FROM users WHERE role = 'student'");
    $t['active30'] = (int)$one("SELECT COUNT(*) FROM users WHERE role = 'student' AND last_login_at >= (NOW() - INTERVAL 30 DAY)");
    $t['new30'] = (int)$one("SELECT COUNT(*) FROM users WHERE role = 'student' AND created_at >= (NOW() - INTERVAL 30 DAY)");
    $t['lessons'] = (int)$one('SELECT COUNT(*) FROM lessons');
    $t['quizzes'] = (int)$one('SELECT COUNT(*) FROM quiz_results');
    $avg = $one('SELECT AVG(score / NULLIF(total, 0)) * 100 FROM quiz_results');
    $t['avg_score'] = $avg !== null && $avg !== false && $t['quizzes'] > 0 ? (int)round((float)$avg) : null;
    $t['micro_checked'] = (int)$one('SELECT COUNT(*) FROM micro_progress');
    return $t;
}

/** Subjects with the lowest average quiz score. Hidden unless ADMIN_MIN_GROUP different students are behind the row. */
function adminWeakSubjects(PDO $pdo, int $limit = 8): array
{
    try {
        $q = $pdo->prepare('SELECT MIN(s.subject_name) AS name, COUNT(*) AS attempts, COUNT(DISTINCT qr.user_id) AS students,
                ROUND(AVG(qr.score / NULLIF(qr.total, 0)) * 100) AS avg_pct
            FROM quiz_results qr
            JOIN lessons l ON l.id = qr.lesson_id
            JOIN subjects s ON s.id = l.subject_id
            GROUP BY LOWER(TRIM(s.subject_name))
            HAVING COUNT(DISTINCT qr.user_id) >= ?
            ORDER BY avg_pct ASC, attempts DESC
            LIMIT ' . (int)$limit);
        $q->execute([ADMIN_MIN_GROUP]);
        return $q->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/** ISO/IEC 25010 characteristics, each with the statements people rate from 1 to 5. Edit the statements to match your questionnaire. */
function adminEvalCatalog(): array
{
    return [
        'functional_suitability' => ['label' => 'Functional Suitability', 'items' => [
            'FS1' => 'The system provides the functions I need to study (upload lessons, summaries, quizzes, flashcards, review schedule).',
            'FS2' => 'The generated summaries, quizzes and flashcards are accurate and relevant to the lesson.',
        ]],
        'performance_efficiency' => ['label' => 'Performance Efficiency', 'items' => [
            'PE1' => 'Pages load and respond quickly.',
            'PE2' => 'The AI tools return results within an acceptable waiting time.',
        ]],
        'compatibility' => ['label' => 'Compatibility', 'items' => [
            'CO1' => 'The system works well in the browser and device I use.',
            'CO2' => 'The pages display properly on different screen sizes.',
        ]],
        'usability' => ['label' => 'Usability', 'items' => [
            'US1' => 'The system is easy to learn and use.',
            'US2' => 'The layout, menus and labels are clear and easy to understand.',
        ]],
        'reliability' => ['label' => 'Reliability', 'items' => [
            'RE1' => 'The system works without errors or unexpected crashes.',
            'RE2' => 'My saved lessons, outputs and progress are kept correctly.',
        ]],
        'security' => ['label' => 'Security', 'items' => [
            'SE1' => 'My account and uploaded files are protected from other users.',
            'SE2' => 'The login and password reset features make me feel my data is safe.',
        ]],
        'maintainability' => ['label' => 'Maintainability', 'items' => [
            'MA1' => 'The system is organized into clear modules that are easy to understand and modify.',
            'MA2' => 'Problems can be identified and corrected easily.',
        ]],
        'portability' => ['label' => 'Portability', 'items' => [
            'PO1' => 'The system can be installed and run in different environments.',
            'PO2' => 'The system can be used on different devices and browsers without changes.',
        ]],
    ];
}

/** Table 4 of the manuscript (Likert scale). */
function adminEvalInterpretation(float $mean): string
{
    $m = round($mean, 2);
    if ($m >= 4.51) { return 'Excellent'; }
    if ($m >= 3.51) { return 'Very Good'; }
    if ($m >= 2.51) { return 'Good'; }
    if ($m >= 1.51) { return 'Fair'; }
    return 'Poor';
}

function adminMean(int $sum, int $count): ?float
{
    return $count > 0 ? $sum / $count : null;
}

/** Sums and counts per characteristic and respondent type. The mean is a weighted mean over every rating. */
function adminEvalResults(PDO $pdo): array
{
    $rows = [];
    foreach (adminEvalCatalog() as $key => $c) {
        $rows[$key] = ['label' => $c['label'], 'sum' => ['student' => 0, 'expert' => 0], 'n' => ['student' => 0, 'expert' => 0]];
    }
    $respondents = ['student' => 0, 'expert' => 0];
    try {
        foreach ($pdo->query('SELECT respondent_type, COUNT(*) AS c FROM evaluations GROUP BY respondent_type')->fetchAll() as $r) {
            if (isset($respondents[$r['respondent_type']])) { $respondents[$r['respondent_type']] = (int)$r['c']; }
        }
        $q = $pdo->query('SELECT es.characteristic, e.respondent_type, SUM(es.score) AS total, COUNT(*) AS n
            FROM evaluation_scores es JOIN evaluations e ON e.id = es.evaluation_id GROUP BY es.characteristic, e.respondent_type');
        foreach ($q->fetchAll() as $r) {
            if (isset($rows[$r['characteristic']], $respondents[$r['respondent_type']])) {
                $rows[$r['characteristic']]['sum'][$r['respondent_type']] = (int)$r['total'];
                $rows[$r['characteristic']]['n'][$r['respondent_type']] = (int)$r['n'];
            }
        }
    } catch (Throwable $e) {
    }
    return ['respondents' => $respondents, 'rows' => $rows];
}

/** Account counts for the User accounts page. Counts only. */
function adminUserCounts(PDO $pdo): array
{
    $out = ['total' => 0, 'active' => 0, 'deactivated' => 0, 'admins' => 0, 'students' => 0];
    try {
        $r = $pdo->query("SELECT COUNT(*) AS total, SUM(is_active = 1) AS active, SUM(is_active = 0) AS deactivated,
            SUM(role = 'admin') AS admins, SUM(role = 'student') AS students FROM users")->fetch();
        foreach ($out as $k => $_) { $out[$k] = (int)($r[$k] ?? 0); }
    } catch (Throwable $e) {
    }
    return $out;
}

/** The signed-in administrator's own admin actions (counts and the latest few). Only action names and account ids, nothing private. */
function adminOwnActivity(PDO $pdo, int $adminId): array
{
    $out = ['total' => 0, 'last30' => 0, 'by_type' => ['deactivate' => 0, 'activate' => 0, 'promote' => 0, 'demote' => 0, 'reset' => 0], 'recent' => []];
    try {
        $q = $pdo->prepare("SELECT COUNT(*) AS total, SUM(created_at >= (NOW() - INTERVAL 30 DAY)) AS last30,
                SUM(message LIKE 'deactivate%') AS deactivate, SUM(message LIKE 'activate%') AS activate,
                SUM(message LIKE 'promote%') AS promote, SUM(message LIKE 'demote%') AS demote, SUM(message LIKE 'reset%') AS reset
            FROM activity_log WHERE kind = 'admin_action' AND user_id = ?");
        $q->execute([$adminId]);
        $r = $q->fetch();
        $out['total'] = (int)($r['total'] ?? 0);
        $out['last30'] = (int)($r['last30'] ?? 0);
        foreach ($out['by_type'] as $k => $_) { $out['by_type'][$k] = (int)($r[$k] ?? 0); }
        $q = $pdo->prepare("SELECT message, created_at FROM activity_log WHERE kind = 'admin_action' AND user_id = ? ORDER BY id DESC LIMIT 8");
        $q->execute([$adminId]);
        $out['recent'] = $q->fetchAll();
    } catch (Throwable $e) {
    }
    return $out;
}

/** Turns a logged admin action such as "deactivate user #3" into a sentence with the person's name. */
function adminDescribeAction(PDO $pdo, string $message): string
{
    static $cache = [];
    if (!preg_match('/^(deactivate|activate|promote|demote|reset link) user #(\d+)$/', $message, $m)) {
        return $message;
    }
    $id = (int)$m[2];
    if (!array_key_exists($id, $cache)) {
        try {
            $q = $pdo->prepare('SELECT name, email FROM users WHERE id = ?');
            $q->execute([$id]);
            $row = $q->fetch();
            $cache[$id] = $row ? $row['name'] . ' (' . $row['email'] . ')' : null;
        } catch (Throwable $e) {
            $cache[$id] = null;
        }
    }
    $who = $cache[$id] ?? 'an account that no longer exists (#' . $id . ')';
    return [
        'deactivate' => 'Deactivated ' . $who,
        'activate' => 'Reactivated ' . $who,
        'promote' => 'Made ' . $who . ' an administrator',
        'demote' => 'Removed administrator access from ' . $who,
        'reset link' => 'Sent a password reset link to ' . $who,
    ][$m[1]];
}
