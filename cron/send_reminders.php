<?php
// Daily review-reminder emailer.
//   php cron/send_reminders.php            send reminders
//   php cron/send_reminders.php --dry-run  show who would be emailed, send nothing
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/env.php';
require __DIR__ . '/../includes/mail_helpers.php';

date_default_timezone_set('Asia/Manila');
$dryRun = in_array('--dry-run', $argv ?? [], true);
$maxLessonsPerEmail = 10;

$appUrl = rtrim(mailEnv('APP_URL'), '/');
if ($appUrl === '') {
    fwrite(STDERR, "APP_URL is missing in .env (example: APP_URL=https://studimate.example.com)\n");
    exit(1);
}

// One row per due review that has not been reminded for its current due date.
// Retaking a quiz moves next_review_date forward, which makes the lesson eligible again.
$rows = $pdo->query("
    SELECT u.id AS user_id, u.name, u.email, u.unsubscribe_token,
           rs.id AS schedule_id, rs.next_review_date,
           l.id AS lesson_id, l.title, s.subject_name
    FROM review_schedule rs
    JOIN users u ON u.id = rs.user_id
    JOIN lessons l ON l.id = rs.lesson_id
    JOIN subjects s ON s.id = l.subject_id
    WHERE u.notif_reviews = 1
      AND rs.next_review_date <= CURDATE()
      AND (rs.last_reminded_on IS NULL OR rs.last_reminded_on < rs.next_review_date)
    ORDER BY u.id, rs.next_review_date ASC
")->fetchAll();

$byUser = [];
foreach ($rows as $row) {
    $byUser[(int)$row['user_id']][] = $row;
}

if (!$byUser) {
    echo "No reminders to send.\n";
    exit(0);
}

$mail = null;
if (!$dryRun) {
    try {
        $mail = createMailer();
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
}

$setToken = $pdo->prepare("UPDATE users SET unsubscribe_token = ? WHERE id = ? AND unsubscribe_token IS NULL");
$markSent = $pdo->prepare("UPDATE review_schedule SET last_reminded_on = CURDATE() WHERE id = ?");
$sent = 0;
$failed = 0;

foreach ($byUser as $userId => $items) {
    $first = $items[0];
    $token = $first['unsubscribe_token'];
    if (!$token) {
        $token = bin2hex(random_bytes(32));
        $setToken->execute([$token, $userId]);
    }
    $unsubscribeUrl = $appUrl . '/unsubscribe.php?token=' . urlencode($token);
    $firstName = explode(' ', trim((string)$first['name']))[0] ?: 'there';
    $count = count($items);
    $shown = array_slice($items, 0, $maxLessonsPerEmail);

    $htmlItems = '';
    $textItems = '';
    foreach ($shown as $item) {
        $link = $appUrl . '/modules/study.php?lesson_id=' . (int)$item['lesson_id'] . '&tab=quiz';
        $overdue = (strtotime($item['next_review_date']) < strtotime('today')) ? ' (overdue)' : '';
        $htmlItems .= '<li style="margin-bottom:8px"><a href="' . htmlspecialchars($link, ENT_QUOTES) . '">'
            . htmlspecialchars($item['title']) . '</a> &ndash; '
            . htmlspecialchars($item['subject_name']) . htmlspecialchars($overdue) . '</li>';
        $textItems .= '- ' . $item['title'] . ' (' . $item['subject_name'] . ')' . $overdue . "\n  " . $link . "\n";
    }
    $more = $count > $maxLessonsPerEmail ? ($count - $maxLessonsPerEmail) : 0;
    $moreHtml = $more ? '<p>+ ' . $more . ' more on your <a href="' . htmlspecialchars($appUrl . '/modules/schedule.php', ENT_QUOTES) . '">schedule</a>.</p>' : '';
    $moreText = $more ? "+ $more more: $appUrl/modules/schedule.php\n" : '';

    $subject = 'StudiMate: ' . $count . ' lesson' . ($count > 1 ? 's' : '') . ' ready for review';
    $html = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#222;max-width:560px">'
        . '<p>Hi ' . htmlspecialchars($firstName) . ',</p>'
        . '<p>These lessons are due for review today. A quick quiz now helps the material stick:</p>'
        . '<ul style="padding-left:18px">' . $htmlItems . '</ul>' . $moreHtml
        . '<hr style="border:none;border-top:1px solid #ddd;margin:24px 0">'
        . '<p style="font-size:12px;color:#777">You get this because review reminders are on in your StudiMate profile. '
        . '<a href="' . htmlspecialchars($unsubscribeUrl, ENT_QUOTES) . '">Turn off review emails</a>.</p></div>';
    $text = "Hi $firstName,\n\nThese lessons are due for review today:\n\n$textItems$moreText\n"
        . "Turn off review emails: $unsubscribeUrl\n";

    if ($dryRun) {
        echo "[dry-run] {$first['email']}: $count lesson(s)\n";
        continue;
    }

    try {
        $mail->clearAddresses();
        $mail->clearCustomHeaders();
        $mail->addAddress($first['email'], $first['name']);
        $mail->addCustomHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>');
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $text;
        $mail->send();
        foreach ($items as $item) {
            $markSent->execute([(int)$item['schedule_id']]);
        }
        $sent++;
        echo "Sent to {$first['email']} ($count lesson(s))\n";
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDERR, "Failed for {$first['email']}: " . $e->getMessage() . "\n");
    }
}

if ($mail) {
    $mail->smtpClose();
}
echo "Done. Sent: $sent, failed: $failed.\n";
exit($failed > 0 ? 2 : 0);
