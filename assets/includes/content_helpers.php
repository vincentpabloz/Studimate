<?php

function normalizeLessonContent(string $content): string
{
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    if (class_exists('Normalizer')) {
        $normalized = Normalizer::normalize($content, Normalizer::FORM_C);
        if ($normalized !== false) {
            $content = $normalized;
        }
    }

    $content = preg_replace('/[\t ]+/u', ' ', $content) ?? $content;
    $content = preg_replace('/ *\n */u', "\n", $content) ?? $content;
    $content = preg_replace('/\n{3,}/u', "\n\n", $content) ?? $content;

    return trim($content);
}

function lessonContentHashes(string $content): array
{
    $normalized = normalizeLessonContent($content);

    return [
        'content_hash' => hash('sha256', $content),
        'normalized_content_hash' => hash('sha256', $normalized),
        'normalized_content' => $normalized,
    ];
}

function combineLessonSourceText(string $typedText, string $extractedText): string
{
    $parts = array_filter([trim($typedText), trim($extractedText)], static fn($part) => $part !== '');
    return implode("\n\n", $parts);
}

function studyOutputCacheKey(array $lessons, string $type): string
{
    $manifest = [];
    foreach ($lessons as $lesson) {
        $manifest[] = [
            'id' => (int)$lesson['id'],
            'version' => (int)($lesson['version'] ?? 1),
            'content_hash' => (string)($lesson['content_hash'] ?? ''),
        ];
    }

    return hash('sha256', json_encode(
        ['type' => $type, 'lessons' => $manifest],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ));
}

function mergeStoredStudyContent(array $lessons, string $type): ?string
{
    foreach ($lessons as $lesson) {
        if (!isset($lesson['tool_content']) || trim((string)$lesson['tool_content']) === '') {
            return null;
        }
    }

    $seen = [];
    $sections = [];
    foreach ($lessons as $lesson) {
        $content = trim((string)$lesson['tool_content']);
        if ($type === 'explanation') {
            $paragraphs = preg_split('/\n\s*\n/u', $content) ?: [$content];
            $uniqueParagraphs = [];
            foreach ($paragraphs as $paragraph) {
                $paragraph = trim($paragraph);
                if ($paragraph === '') {
                    continue;
                }
                $identity = hash('sha256', normalizeLessonContent($paragraph));
                if (!isset($seen[$identity])) {
                    $seen[$identity] = true;
                    $uniqueParagraphs[] = $paragraph;
                }
            }
            if ($uniqueParagraphs) {
                $sections[] = '## ' . trim((string)$lesson['title']) . "\n\n" . implode("\n\n", $uniqueParagraphs);
            }
            continue;
        }

        $marker = $type === 'quiz' ? '/(?=^Q:\s*)/mi' : '/(?=^FRONT:\s*)/mi';
        $blocks = preg_split($marker, $content, -1, PREG_SPLIT_NO_EMPTY) ?: [$content];
        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }
            $identity = hash('sha256', normalizeLessonContent($block));
            if (!isset($seen[$identity])) {
                $seen[$identity] = true;
                $sections[] = $block;
            }
        }
    }

    return $sections ? implode("\n\n", $sections) : null;
}

function partitionKnownKnowledgeChunks(array $chunks, array $knownHashes): array
{
    $newChunks = [];
    $novelContent = [];
    foreach ($chunks as $chunk) {
        $chunk['hash'] = hash('sha256', normalizeLessonContent((string)$chunk['content']));
        $chunk['knowledge_id'] = $knownHashes[$chunk['hash']] ?? null;
        $chunk['is_novel'] = $chunk['knowledge_id'] === null;
        if ($chunk['knowledge_id'] === null) {
            $novelContent[] = $chunk['content'];
        }
        $newChunks[] = $chunk;
    }

    return [
        'chunks' => $newChunks,
        'novel_content' => trim(implode("\n\n", $novelContent)),
    ];
}

function combineLessonChunkContent(array $chunks): ?string
{
    if (!$chunks) {
        return null;
    }
    $parts = [];
    $seen = [];
    foreach ($chunks as $chunk) {
        $content = trim((string)($chunk['content'] ?? ''));
        if ($content === '') {
            continue;
        }
        $hash = hash('sha256', normalizeLessonContent($content));
        if (!isset($seen[$hash])) {
            $seen[$hash] = true;
            $parts[] = $content;
        }
    }
    return $parts ? implode("\n\n", $parts) : null;
}

function splitKnowledgeContent(string $content, int $maxBytes = 6000): array
{
    $paragraphs = preg_split('/(?<=\n\n)/u', trim($content)) ?: [trim($content)];
    $chunks = [];
    $buffer = '';
    foreach ($paragraphs as $paragraph) {
        if (strlen($buffer) + strlen($paragraph) > $maxBytes && trim($buffer) !== '') {
            $chunks[] = trim($buffer);
            $buffer = '';
        }
        $buffer .= $paragraph;
    }
    if (trim($buffer) !== '') {
        $chunks[] = trim($buffer);
    }
    return $chunks;
}

function saveLessonKnowledgeChunks(PDO $pdo, int $userId, int $lessonId, int $version, string $content): void
{
    $insertKnowledge = $pdo->prepare('INSERT IGNORE INTO knowledge_chunks (user_id,content_hash,content) VALUES (?,?,?)');
    $findKnowledge = $pdo->prepare('SELECT id FROM knowledge_chunks WHERE user_id=? AND content_hash=?');
    $linkKnowledge = $pdo->prepare('INSERT INTO lesson_chunks (lesson_id,source_version,chunk_index,knowledge_chunk_id) VALUES (?,?,?,?)');
    foreach (splitKnowledgeContent($content) as $index => $chunk) {
        $hash = hash('sha256', normalizeLessonContent($chunk));
        $insertKnowledge->execute([$userId, $hash, $chunk]);
        $findKnowledge->execute([$userId, $hash]);
        $linkKnowledge->execute([$lessonId, $version, $index, (int)$findKnowledge->fetchColumn()]);
    }
}

function lessonSourceVersion(array $lesson): int
{
    return (int)($lesson['version'] ?? 1);
}