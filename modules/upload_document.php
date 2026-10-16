<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}
require '../config/db.php';
require '../includes/content_helpers.php';
require '../includes/document_extractor.php';

$uid = (int)$_SESSION['user_id'];
$subjectId = (int)($_POST['subject_id'] ?? 0);
$storageRoot = getenv('STUDIMATE_PRIVATE_STORAGE') ?: dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'studimate-private-storage';
$storedPath = null;

function uploadError(string $message, int $subjectId): never
{
    $_SESSION['upload_error'] = $message;
    header('Location: lessons.php?subject_id=' . $subjectId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf_token'] ?? '', (string)($_POST['csrf_token'] ?? ''))) {
    uploadError('Your session expired. Reload and try again.', $subjectId);
}

$subjectQuery = $pdo->prepare('SELECT id FROM subjects WHERE id=? AND user_id=?');
$subjectQuery->execute([$subjectId, $uid]);
if (!$subjectQuery->fetch()) {
    http_response_code(404);
    exit('Subject not found.');
}

if (!isset($_FILES['material']) || $_FILES['material']['error'] !== UPLOAD_ERR_OK) {
    uploadError('Choose a supported document to upload.', $subjectId);
}

$file = $_FILES['material'];
$maxUploadBytes = 20 * 1024 * 1024;
if ($file['size'] < 1 || $file['size'] > $maxUploadBytes || !is_uploaded_file($file['tmp_name'])) {
    uploadError('Files must be smaller than 20 MB.', $subjectId);
}

$originalName = mb_substr(basename(str_replace('\\', '/', (string)$file['name'])), 0, 255);
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowedExtensions = ['pdf', 'docx', 'ppt', 'pptx'];
if (!in_array($extension, $allowedExtensions, true)) {
    uploadError('Supported formats are PDF, DOCX, PPT, and PPTX.', $subjectId);
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: 'application/octet-stream';
$prefix = file_get_contents($file['tmp_name'], false, null, 0, 8);
$allowedMimeTypes = [
    'pdf' => ['application/pdf'],
    'docx' => ['application/zip', 'application/x-zip', 'application/x-zip-compressed', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'pptx' => ['application/zip', 'application/x-zip', 'application/x-zip-compressed', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
    'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/octet-stream'],
];
$mimeMatches = in_array(strtolower($mime), $allowedMimeTypes[$extension], true);
$validSignature = match ($extension) {
    'pdf' => str_starts_with((string)$prefix, '%PDF-'),
    'ppt' => str_starts_with((string)$prefix, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"),
    'docx', 'pptx' => str_starts_with((string)$prefix, "PK\x03\x04") || str_starts_with((string)$prefix, "PK\x05\x06") || str_starts_with((string)$prefix, "PK\x07\x08"),
    default => false,
};
if (!$validSignature || !$mimeMatches) {
    uploadError('The selected file does not match its extension or expected file signature.', $subjectId);
}

if (in_array($extension, ['docx', 'pptx'], true)) {
    if (!class_exists(ZipArchive::class)) {
        uploadError('Office extraction requires the PHP ZIP extension. Enable extension=zip and restart Apache.', $subjectId);
    }
    $archive = new ZipArchive();
    if ($archive->open($file['tmp_name']) !== true) {
        uploadError('The Office document is corrupt or invalid.', $subjectId);
    }
    $expandedBytes = 0;
    if ($archive->numFiles > 5000) {
        $archive->close();
        uploadError('The Office document contains too many files.', $subjectId);
    }
    for ($index = 0; $index < $archive->numFiles; $index++) {
        $stat = $archive->statIndex($index);
        $expandedBytes += (int)($stat['size'] ?? 0);
        if ($expandedBytes > 40 * 1024 * 1024) {
            $archive->close();
            uploadError('The expanded Office document exceeds the 40 MB processing limit.', $subjectId);
        }
    }
    $requiredEntry = $extension === 'docx' ? 'word/document.xml' : 'ppt/presentation.xml';
    $validOfficeArchive = $archive->locateName('[Content_Types].xml') !== false && $archive->locateName($requiredEntry) !== false;
    $archive->close();
    if (!$validOfficeArchive) {
        uploadError('The ZIP file is not a valid DOCX or PPTX document.', $subjectId);
    }
}

if (!is_dir($storageRoot) && !mkdir($storageRoot, 0700, true) && !is_dir($storageRoot)) {
    uploadError('Private document storage is unavailable.', $subjectId);
}
$randomName = bin2hex(random_bytes(24)) . '.' . $extension;
$storedPath = $storageRoot . DIRECTORY_SEPARATOR . $randomName;
if (!move_uploaded_file($file['tmp_name'], $storedPath)) {
    uploadError('The uploaded document could not be stored.', $subjectId);
}

try {
    $extracted = extractDocumentText($storedPath, $extension);
    if (strlen($extracted) > 2 * 1024 * 1024) {
        throw new RuntimeException('Extracted text exceeds the 2 MB processing limit.');
    }
    $typedText = trim((string)($_POST['raw_text'] ?? ''));
    $lessonSource = combineLessonSourceText($typedText, $extracted);
    $lessonSourceHashes = lessonContentHashes($lessonSource);
    $documentHashes = lessonContentHashes($extracted);
    if ($documentHashes['normalized_content'] === '') {
        throw new RuntimeException('No readable text was found in the document.');
    }
    $normalizedText = $documentHashes['normalized_content'];
    $documentContentHash = $documentHashes['normalized_content_hash'];

    $sections = preg_split('/(?=^(?:Page|Slide)\s+\d+:)/m', $normalizedText, -1, PREG_SPLIT_NO_EMPTY) ?: [$normalizedText];
    $chunkRecords = [];
    foreach ($sections as $section) {
        $pageNumber = null;
        $slideNumber = null;
        if (preg_match('/^(Page|Slide)\s+(\d+):/i', ltrim($section), $sourceMatch)) {
            if (strtolower($sourceMatch[1]) === 'page') $pageNumber = (int)$sourceMatch[2];
            else $slideNumber = (int)$sourceMatch[2];
        }
        foreach (splitKnowledgeContent($section) as $content) {
            $chunkRecords[] = ['content' => $content, 'page' => $pageNumber, 'slide' => $slideNumber];
        }
    }

    $duplicate = $pdo->prepare('SELECT l.id,l.title FROM lessons l JOIN subjects s ON s.id=l.subject_id WHERE l.subject_id=? AND s.user_id=? AND l.normalized_content_hash=? ORDER BY l.id LIMIT 1');
    $duplicateContentHash = $typedText !== '' ? $lessonSourceHashes['normalized_content_hash'] : $documentContentHash;
    $duplicate->execute([$subjectId, $uid, $duplicateContentHash]);
    $existingLesson = $duplicate->fetch() ?: null;
    $lessonId = $existingLesson ? (int)$existingLesson['id'] : null;

    $pdo->beginTransaction();
    $existingChunkQuery = $pdo->prepare('SELECT id FROM knowledge_chunks WHERE user_id=? AND content_hash=?');
    $knownHashes = [];
    foreach ($chunkRecords as $chunk) {
        $chunkHash = hash('sha256', normalizeLessonContent($chunk['content']));
        $existingChunkQuery->execute([$uid, $chunkHash]);
        $knowledgeChunkId = $existingChunkQuery->fetchColumn();
        if ($knowledgeChunkId !== false) $knownHashes[$chunkHash] = (int)$knowledgeChunkId;
    }
    $partition = partitionKnownKnowledgeChunks($chunkRecords, $knownHashes);
    $newKnowledge = $partition['chunks'];
    $novelText = $partition['novel_content'];
    if ($lessonId === null && $novelText === '' && !empty($newKnowledge)) {
        $knownLesson = $pdo->prepare('(SELECT l.id,l.title FROM lesson_chunks lc JOIN lessons l ON l.id=lc.lesson_id JOIN subjects s ON s.id=l.subject_id JOIN knowledge_chunks kc ON kc.id=lc.knowledge_chunk_id WHERE l.subject_id=? AND s.user_id=? AND kc.content_hash=? ORDER BY lc.id LIMIT 1) UNION ALL (SELECT l.id,l.title FROM document_chunks dc JOIN documents d ON d.id=dc.document_id JOIN lessons l ON l.id=d.lesson_id JOIN knowledge_chunks kc ON kc.id=dc.knowledge_chunk_id WHERE d.subject_id=? AND d.user_id=? AND kc.content_hash=? ORDER BY dc.id LIMIT 1) LIMIT 1');
        $knownLesson->execute([$subjectId, $uid, $newKnowledge[0]['hash'], $subjectId, $uid, $newKnowledge[0]['hash']]);
        $existingLesson = $knownLesson->fetch() ?: null;
        if ($existingLesson) {
            $lessonId = (int)$existingLesson['id'];
        }
    }
    $lessonText = $typedText !== '' ? $lessonSourceHashes['normalized_content'] : ($novelText !== '' ? $novelText : $normalizedText);
    $lessonHashes = lessonContentHashes($lessonText);
    $newLessonCreated = false;
    if ($lessonId === null) {
        $requestedTitle = trim((string)($_POST['title'] ?? ''));
        $title = mb_substr($requestedTitle !== '' ? $requestedTitle : pathinfo($originalName, PATHINFO_FILENAME), 0, 200);
        if ($title === '') {
            $title = 'Uploaded material';
        }
        $baseTitle = $title;
        $suffix = 2;
        $titleCheck = $pdo->prepare('SELECT id FROM lessons WHERE subject_id=? AND title=?');
        while (true) {
            $titleCheck->execute([$subjectId, $title]);
            if (!$titleCheck->fetch()) {
                break;
            }
            $tail = ' (' . $suffix++ . ')';
            $title = mb_substr($baseTitle, 0, 200 - mb_strlen($tail)) . $tail;
        }
        $insertLesson = $pdo->prepare('INSERT INTO lessons (subject_id,title,raw_text,content_hash,normalized_content_hash,version) VALUES (?,?,?,?,?,1)');
        $insertLesson->execute([$subjectId, $title, $lessonText, $lessonHashes['content_hash'], $lessonHashes['normalized_content_hash']]);
        $lessonId = (int)$pdo->lastInsertId();
        $newLessonCreated = true;
    }

    $insertDocument = $pdo->prepare('INSERT INTO documents (user_id,subject_id,lesson_id,original_filename,storage_path,file_type,file_mime,file_hash,extracted_content_hash) VALUES (?,?,?,?,?,?,?,?,?)');
    $insertDocument->execute([$uid, $subjectId, $lessonId, $originalName, $storedPath, $extension, $mime, hash_file('sha256', $storedPath), $documentContentHash]);
    $documentId = (int)$pdo->lastInsertId();

    $insertKnowledge = $pdo->prepare('INSERT IGNORE INTO knowledge_chunks (user_id,content_hash,content) VALUES (?,?,?)');
    $findKnowledge = $pdo->prepare('SELECT id FROM knowledge_chunks WHERE user_id=? AND content_hash=?');
    $insertChunk = $pdo->prepare('INSERT INTO document_chunks (document_id,chunk_index,page_number,slide_number,is_novel,knowledge_chunk_id) VALUES (?,?,?,?,?,?)');
    $lessonChunkIndex = 0;
    foreach ($newKnowledge as $chunkIndex => $chunk) {
        $isNovel = $chunk['is_novel'] ? 1 : 0;
        $chunkHash = $chunk['hash'];
        if ($chunk['knowledge_id'] === null) {
            $insertKnowledge->execute([$uid, $chunkHash, $chunk['content']]);
            $findKnowledge->execute([$uid, $chunkHash]);
            $chunk['knowledge_id'] = (int)$findKnowledge->fetchColumn();
        }
        $knowledgeChunkId = $chunk['knowledge_id'];
        $insertChunk->execute([$documentId, $chunkIndex, $chunk['page'], $chunk['slide'], $isNovel, $knowledgeChunkId]);
        if ($newLessonCreated && $typedText === '' && $isNovel) {
            $linkLessonChunk = $pdo->prepare('INSERT INTO lesson_chunks (lesson_id,source_version,chunk_index,knowledge_chunk_id) VALUES (?,?,?,?)');
            $linkLessonChunk->execute([$lessonId,1,$lessonChunkIndex++,$knowledgeChunkId]);
        }
    }
    $pdo->commit();

    $_SESSION['upload_success'] = $existingLesson
        ? 'Document saved separately and linked to existing lesson “' . $existingLesson['title'] . '”. No AI was called.'
        : ($typedText !== ''
            ? 'Document text and your lesson notes were combined and saved as one lesson. No AI was called.'
            : 'Document extracted and saved as a lesson. Choose an output when you are ready; no AI was called.');
    header('Location: lessons.php?subject_id=' . $subjectId);
    exit;
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($storedPath && is_file($storedPath)) {
        unlink($storedPath);
    }
    uploadError($error instanceof RuntimeException ? $error->getMessage() : 'The document could not be processed safely.', $subjectId);
}