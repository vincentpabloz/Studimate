<?php
date_default_timezone_set('Asia/Manila');
$host = "studimate.infinityfreeapp.com";
$dbname = "if0_43217842_studimate_db";
$username = "if0_43217842";
$password = "passsahosting12";
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Runs on every web request for a signed-in user:
//  1. a deactivated account is signed out on its next request;
//  2. the role is re-read from the database, so promoting or demoting someone takes effect without a new login;
//  3. administrators only get the admin pages and are sent there if they open a student page.
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
    try {
        $activeCheck = $pdo->prepare('SELECT is_active, role, must_change_password FROM users WHERE id = ?');
        $activeCheck->execute([(int)$_SESSION['user_id']]);
        $activeRow = $activeCheck->fetch();
        $docRoot = rtrim(str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
        $appRoot = str_replace('\\', '/', dirname(__DIR__));
        $webRoot = ($docRoot !== '' && strpos($appRoot, $docRoot) === 0) ? substr($appRoot, strlen($docRoot)) : '';
        if ($activeRow !== false && !(int)$activeRow['is_active']) {
            $_SESSION = [];
            session_destroy();
            header('Location: ' . $webRoot . '/index.php?deactivated=1');
            exit;
        }
        if ($activeRow !== false) {
            $_SESSION['user_role'] = $activeRow['role'];
            $_SESSION['password_change_required'] = (int)$activeRow['must_change_password'] === 1;
            $adminOnlyPages = ['admin.php', 'admin_health.php', 'admin_users.php', 'admin_profile.php', 'admin_activity.php', 'admin_trends.php'];
            $publicPages = ['logout.php', 'index.php', 'register.php', 'forgot_password.php', 'reset_password.php', 'unsubscribe.php'];
            $thisPage = basename((string)($_SERVER['PHP_SELF'] ?? ''));
            if ($_SESSION['password_change_required'] && !in_array($thisPage, ['change_temporary_password.php', 'logout.php'], true)) {
                header('Location: ' . $webRoot . '/change_temporary_password.php');
                exit;
            }
            if ($activeRow['role'] === 'admin' && !in_array($thisPage, array_merge($adminOnlyPages, $publicPages), true)) {
                header('Location: ' . $webRoot . '/modules/admin.php');
                exit;
            }
        }
    } catch (PDOException $e) {
        // is_active column not created yet
    }
}
