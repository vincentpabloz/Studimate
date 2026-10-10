<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/content_helpers.php';

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $query = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $query->execute([$column]);
    return (bool)$query->fetch();
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $query = $pdo->prepare("SHOW INDEX FROM `$table` WHERE Key_name = ?");
    $query->execute([$index]);
    return (bool)$query->fetch();
}

$lessonColumns = [
    'content_hash' => 'CHAR(64) DEFAULT NULL',
    'normalized_content_hash' => 'CHAR(64) DEFAULT NULL',
    'version' => 'INT NOT NULL DEFAULT 1',
];
foreach ($lessonColumns as $name => $definition) {
    if (!columnExists($pdo, 'lessons', $name)) {
        $pdo->exec("ALTER TABLE lessons ADD COLUMN `$name` $definition");
    }
}

$toolColumns = [
    'source_hash' => 'CHAR(64) DEFAULT NULL',
    'source_version' => 'INT DEFAULT NULL',
];
foreach ($toolColumns as $name => $definition) {
    if (!columnExists($pdo, 'study_tools', $name)) {
        $pdo->exec("ALTER TABLE study_tools ADD COLUMN `$name` $definition");
    }
}

if (!indexExists($pdo, 'study_tools', 'lesson_id_idx')) {
    $pdo->exec('ALTER TABLE study_tools ADD INDEX lesson_id_idx (lesson_id)');
}
if (indexExists($pdo, 'study_tools', 'unique_tool')) {
    $pdo->exec('ALTER TABLE study_tools DROP INDEX unique_tool');
}
if (!indexExists($pdo, 'study_tools', 'unique_versioned_tool')) {
    $pdo->exec('ALTER TABLE study_tools ADD UNIQUE KEY unique_versioned_tool (lesson_id, type, source_version)');
}

$pdo->exec("CREATE TABLE IF NOT EXISTS combined_study_tools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    cache_key CHAR(64) NOT NULL,
    type ENUM('explanation','quiz','flashcard') NOT NULL,
    source_manifest LONGTEXT NOT NULL,
    content LONGTEXT NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_combined_tool (user_id, cache_key, type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS lesson_embeddings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    source_hash CHAR(64) NOT NULL,
    model VARCHAR(100) NOT NULL,
    embedding_json LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_lesson_embedding (lesson_id, source_hash, model),
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject_id INT NOT NULL,
    lesson_id INT DEFAULT NULL,
    original_filename VARCHAR(255) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(20) NOT NULL,
    file_mime VARCHAR(150) NOT NULL,
    file_hash CHAR(64) NOT NULL,
    extracted_content_hash CHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY document_user_content (user_id, extracted_content_hash),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE SET NULL
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS knowledge_chunks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_hash CHAR(64) NOT NULL,
    content LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_knowledge_chunk (user_id, content_hash),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS lesson_chunks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    source_version INT NOT NULL,
    chunk_index INT NOT NULL,
    knowledge_chunk_id INT NOT NULL,
    UNIQUE KEY unique_lesson_version_chunk (lesson_id, source_version, chunk_index),
    KEY lesson_knowledge_chunk (knowledge_chunk_id),
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    FOREIGN KEY (knowledge_chunk_id) REFERENCES knowledge_chunks(id) ON DELETE RESTRICT
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS document_chunks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    chunk_index INT NOT NULL,
    page_number INT DEFAULT NULL,
    slide_number INT DEFAULT NULL,
    knowledge_chunk_id INT NOT NULL,
    UNIQUE KEY unique_document_chunk (document_id, chunk_index),
    KEY document_knowledge_chunk (knowledge_chunk_id),
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (knowledge_chunk_id) REFERENCES knowledge_chunks(id) ON DELETE RESTRICT
)");

if (!columnExists($pdo, 'document_chunks', 'knowledge_chunk_id')) {
    $pdo->exec('ALTER TABLE document_chunks ADD COLUMN knowledge_chunk_id INT DEFAULT NULL');
}
if (!columnExists($pdo, 'document_chunks', 'is_novel')) {
    $pdo->exec('ALTER TABLE document_chunks ADD COLUMN is_novel TINYINT(1) NOT NULL DEFAULT 1');
}
if (columnExists($pdo, 'document_chunks', 'content_hash') && columnExists($pdo, 'document_chunks', 'content')) {
    $oldChunks = $pdo->query('SELECT dc.id,dc.content_hash,dc.content,d.user_id FROM document_chunks dc JOIN documents d ON d.id=dc.document_id WHERE dc.knowledge_chunk_id IS NULL')->fetchAll();
    $insertKnowledge = $pdo->prepare('INSERT IGNORE INTO knowledge_chunks (user_id,content_hash,content) VALUES (?,?,?)');
    $findKnowledge = $pdo->prepare('SELECT id FROM knowledge_chunks WHERE user_id=? AND content_hash=?');
    $linkChunk = $pdo->prepare('UPDATE document_chunks SET knowledge_chunk_id=? WHERE id=?');
    foreach ($oldChunks as $chunk) {
        $content = (string)$chunk['content'];
        $hash = $chunk['content_hash'] ?: hash('sha256', normalizeLessonContent($content));
        $insertKnowledge->execute([$chunk['user_id'], $hash, $content]);
        $findKnowledge->execute([$chunk['user_id'], $hash]);
        $linkChunk->execute([(int)$findKnowledge->fetchColumn(), $chunk['id']]);
    }
    if (indexExists($pdo, 'document_chunks', 'chunk_content_hash')) {
        $pdo->exec('ALTER TABLE document_chunks DROP INDEX chunk_content_hash');
    }
    $pdo->exec('ALTER TABLE document_chunks DROP COLUMN content_hash, DROP COLUMN content');
}
if (columnExists($pdo, 'document_chunks', 'knowledge_chunk_id')) {
    $pdo->exec('ALTER TABLE document_chunks MODIFY knowledge_chunk_id INT NOT NULL');
}
if (!indexExists($pdo, 'document_chunks', 'document_knowledge_chunk')) {
    $pdo->exec('ALTER TABLE document_chunks ADD INDEX document_knowledge_chunk (knowledge_chunk_id)');
}
$foreignKey = $pdo->prepare("SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='document_chunks' AND COLUMN_NAME='knowledge_chunk_id' AND REFERENCED_TABLE_NAME='knowledge_chunks' LIMIT 1");
$foreignKey->execute();
if (!$foreignKey->fetchColumn()) {
    $pdo->exec('ALTER TABLE document_chunks ADD CONSTRAINT fk_document_knowledge_chunk FOREIGN KEY (knowledge_chunk_id) REFERENCES knowledge_chunks(id) ON DELETE RESTRICT');
}

$lessons = $pdo->query('SELECT id, raw_text FROM lessons')->fetchAll();
$updateLesson = $pdo->prepare('UPDATE lessons SET content_hash=?, normalized_content_hash=?, version=COALESCE(version, 1) WHERE id=?');
foreach ($lessons as $lesson) {
    $hashes = lessonContentHashes((string)$lesson['raw_text']);
    $updateLesson->execute([$hashes['content_hash'], $hashes['normalized_content_hash'], $lesson['id']]);
}

$pdo->exec("UPDATE study_tools AS st
    INNER JOIN lessons AS l ON l.id=st.lesson_id
    SET st.source_hash=l.content_hash, st.source_version=l.version
    WHERE st.source_hash IS NULL OR st.source_version IS NULL");

echo 'Content reuse migration completed. ' . count($lessons) . " lesson hashes backfilled.\n";