<?php
use PHPMailer\PHPMailer\PHPMailer;

function mailEnv(string $name, string $default = ''): string
{
    $value = getenv($name);
    if ($value !== false && $value !== '') {
        return (string)$value;
    }
    return (string)($_ENV[$name] ?? $default);
}

/**
 * Build a PHPMailer instance configured from .env (SMTP_* keys).
 * Throws RuntimeException when the setup is incomplete.
 */
function createMailer(): PHPMailer
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('vendor/autoload.php not found. Run: composer require phpmailer/phpmailer');
    }
    require_once $autoload;

    $host = mailEnv('SMTP_HOST');
    $user = mailEnv('SMTP_USER');
    $pass = mailEnv('SMTP_PASS');
    $from = mailEnv('SMTP_FROM', $user);
    if ($host === '' || $user === '' || $pass === '' || $from === '') {
        throw new RuntimeException('SMTP is not configured. Set SMTP_HOST, SMTP_USER, SMTP_PASS in .env.');
    }

    $secure = strtolower(mailEnv('SMTP_SECURE', 'tls'));
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->SMTPAuth = true;
    $mail->Username = $user;
    $mail->Password = $pass;
    $mail->SMTPSecure = $secure === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int)mailEnv('SMTP_PORT', $secure === 'ssl' ? '465' : '587');
    $mail->CharSet = 'UTF-8';
    $mail->SMTPKeepAlive = true; // reuse one connection while sending many reminders
    $mail->setFrom($from, mailEnv('SMTP_FROM_NAME', 'StudiMate'));
    return $mail;
}
