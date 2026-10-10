<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Authentication required.');
}
require '../config/db.php';

$query = $pdo->prepare('SELECT original_filename,storage_path,file_mime FROM documents WHERE id=? AND user_id=?');
$query->execute([(int)($_GET['id'] ?? 0), (int)$_SESSION['user_id']]);
$document = $query->fetch();
if (!$document || !is_file($document['storage_path'])) {
    http_response_code(404);
    exit('Document not found.');
}

$filename = basename((string)$document['original_filename']);
$fallback = preg_replace('/[^A-Za-z0-9._ -]/', '_', $filename);
header('Content-Type: ' . $document['file_mime']);
header('Content-Length: ' . filesize($document['storage_path']));
header('Content-Disposition: attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('X-Content-Type-Options: nosniff');
readfile($document['storage_path']);