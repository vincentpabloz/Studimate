<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Authentication required.');
}
require '../config/db.php';

$query = $pdo->prepare('
    SELECT d.original_filename,d.storage_path,d.file_mime
    FROM documents d
    JOIN subjects s ON s.id=d.subject_id
    LEFT JOIN lessons l ON l.id=d.lesson_id
    WHERE d.id=? AND d.user_id=? AND s.user_id=d.user_id
      AND (d.lesson_id IS NULL OR l.subject_id=d.subject_id)
');
$query->execute([(int)($_GET['id'] ?? 0), (int)$_SESSION['user_id']]);
$document = $query->fetch();
$storageRoot = getenv('STUDIMATE_PRIVATE_STORAGE') ?: dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
$resolvedRoot = realpath($storageRoot);
$resolvedFile = $document ? realpath($document['storage_path']) : false;
$rootPrefix = $resolvedRoot === false ? '' : rtrim($resolvedRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (!$document || $resolvedFile === false || $rootPrefix === '' || strncasecmp($resolvedFile, $rootPrefix, strlen($rootPrefix)) !== 0 || !is_file($resolvedFile)) {
    http_response_code(404);
    exit('Document not found.');
}

$filename = basename((string)$document['original_filename']);
$fallback = preg_replace('/[^A-Za-z0-9._ -]/', '_', $filename);
header('Content-Type: ' . $document['file_mime']);
header('Content-Length: ' . filesize($resolvedFile));
header('Content-Disposition: attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('X-Content-Type-Options: nosniff');
readfile($resolvedFile);