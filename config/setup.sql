CREATE DATABASE IF NOT EXISTS studimate_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE studimate_db;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    university VARCHAR(150) DEFAULT '',
    major VARCHAR(150) DEFAULT '',
    role ENUM('student','admin') DEFAULT 'student',
    notif_reviews TINYINT(1) DEFAULT 1,
    notif_quizzes TINYINT(1) DEFAULT 0,
    unsubscribe_token CHAR(64) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_unsubscribe_token (unsubscribe_token)
);

CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject_name VARCHAR(150) NOT NULL,
    subject_code VARCHAR(50) DEFAULT '',
    color VARCHAR(7) DEFAULT '#5B5FDE',
    icon VARCHAR(10) DEFAULT '📘',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    raw_text LONGTEXT,
    content_hash CHAR(64) DEFAULT NULL,
    normalized_content_hash CHAR(64) DEFAULT NULL,
    version INT NOT NULL DEFAULT 1,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS lesson_embeddings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    source_hash CHAR(64) NOT NULL,
    model VARCHAR(100) NOT NULL,
    embedding_json LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_lesson_embedding (lesson_id, source_hash, model),
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS documents (
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
);

CREATE TABLE IF NOT EXISTS knowledge_chunks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_hash CHAR(64) NOT NULL,
    content LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_knowledge_chunk (user_id, content_hash),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS lesson_chunks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    source_version INT NOT NULL,
    chunk_index INT NOT NULL,
    knowledge_chunk_id INT NOT NULL,
    UNIQUE KEY unique_lesson_version_chunk (lesson_id, source_version, chunk_index),
    KEY lesson_knowledge_chunk (knowledge_chunk_id),
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    FOREIGN KEY (knowledge_chunk_id) REFERENCES knowledge_chunks(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS document_chunks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    chunk_index INT NOT NULL,
    page_number INT DEFAULT NULL,
    slide_number INT DEFAULT NULL,
    is_novel TINYINT(1) NOT NULL DEFAULT 1,
    knowledge_chunk_id INT NOT NULL,
    UNIQUE KEY unique_document_chunk (document_id, chunk_index),
    KEY document_knowledge_chunk (knowledge_chunk_id),
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (knowledge_chunk_id) REFERENCES knowledge_chunks(id) ON DELETE RESTRICT
);

-- UNIQUE prevents duplicate AI calls = saves Hugging Face tokens
CREATE TABLE IF NOT EXISTS study_tools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    type ENUM('explanation','quiz','flashcard') NOT NULL,
    content LONGTEXT NOT NULL,
    source_hash CHAR(64) DEFAULT NULL,
    source_version INT DEFAULT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY lesson_id_idx (lesson_id),
    UNIQUE KEY unique_versioned_tool (lesson_id, type, source_version),
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS combined_study_tools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    cache_key CHAR(64) NOT NULL,
    type ENUM('explanation','quiz','flashcard') NOT NULL,
    source_manifest LONGTEXT NOT NULL,
    content LONGTEXT NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_combined_tool (user_id, cache_key, type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS quiz_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    lesson_id INT NOT NULL,
    score INT NOT NULL,
    total INT NOT NULL,
    taken_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
);

-- UNIQUE prevents duplicate schedule rows per user+lesson
CREATE TABLE IF NOT EXISTS review_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    lesson_id INT NOT NULL,
    next_review_date DATE NOT NULL,
    interval_days INT NOT NULL DEFAULT 1,
    last_reminded_on DATE DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_schedule (user_id, lesson_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS recommendations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    lesson_id INT NOT NULL,
    reason VARCHAR(255),
    priority INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rec (user_id, lesson_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
);

-- Microlearning: short lesson units with a quick check question (generated once, then reused)
CREATE TABLE IF NOT EXISTS micro_units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    source_hash CHAR(64) DEFAULT NULL,
    source_version INT NOT NULL,
    units LONGTEXT NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_micro_lesson_version (lesson_id, source_version),
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS micro_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    lesson_id INT NOT NULL,
    source_version INT NOT NULL,
    unit_index INT NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    attempts INT NOT NULL DEFAULT 1,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_micro_progress (user_id, lesson_id, source_version, unit_index),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
);

-- Administrator dashboard: activity log and evaluation (ISO/IEC 25010) ratings
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    kind VARCHAR(30) NOT NULL,
    source VARCHAR(10) NOT NULL,
    status ENUM('ok','error') NOT NULL DEFAULT 'ok',
    message VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_activity_kind (kind, created_at)
);

CREATE TABLE IF NOT EXISTS evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    respondent_type ENUM('student','expert') NOT NULL,
    comments TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_evaluation_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS evaluation_scores (
    evaluation_id INT NOT NULL,
    item_key VARCHAR(10) NOT NULL,
    characteristic VARCHAR(40) NOT NULL,
    score TINYINT NOT NULL,
    PRIMARY KEY (evaluation_id, item_key),
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE
);
