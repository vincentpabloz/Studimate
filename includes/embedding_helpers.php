<?php

function createLocalLessonEmbedding(string $text): ?array
{
    $endpoint = getenv('STUDIMATE_EMBEDDING_URL') ?: '';
    $parts = parse_url($endpoint);
    $host = strtolower((string)($parts['host'] ?? ''));
    if (!$parts || !in_array($host, ['localhost', '127.0.0.1', '::1'], true)
        || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
        || !function_exists('curl_init')) {
        return null;
    }

    $model = getenv('STUDIMATE_EMBEDDING_MODEL') ?: 'nomic-embed-text';
    $handle = curl_init($endpoint);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['model' => $model, 'input' => $text], JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);
    if (!is_string($response) || $status < 200 || $status >= 300) {
        return null;
    }

    $data = json_decode($response, true);
    $vector = $data['embeddings'][0] ?? $data['embedding'] ?? null;
    if (!is_array($vector) || count($vector) < 32 || count($vector) > 8192) {
        return null;
    }
    foreach ($vector as $value) {
        if (!is_numeric($value) || !is_finite((float)$value)) {
            return null;
        }
    }
    return ['model' => $model, 'vector' => array_map('floatval', $vector)];
}

function vectorsAreCompatible(array $left, array $right): bool
{
    return count($left) >= 32 && count($left) <= 8192 && count($left) === count($right);
}

function embeddingCosineSimilarity(array $left, array $right): float
{
    if (!vectorsAreCompatible($left, $right)) {
        return 0.0;
    }
    $dot = $leftNorm = $rightNorm = 0.0;
    foreach ($left as $index => $value) {
        $other = (float)$right[$index];
        $dot += (float)$value * $other;
        $leftNorm += (float)$value ** 2;
        $rightNorm += $other ** 2;
    }
    return $leftNorm > 0 && $rightNorm > 0 ? $dot / (sqrt($leftNorm) * sqrt($rightNorm)) : 0.0;
}

function findSimilarLesson(PDO $pdo, int $userId, array $embedding, string $excludeHash, float $threshold = 0.93): ?array
{
    $query = $pdo->prepare('SELECT l.id,l.title,l.content_hash,s.subject_name,e.embedding_json FROM lesson_embeddings e JOIN lessons l ON l.id=e.lesson_id JOIN subjects s ON s.id=l.subject_id WHERE s.user_id=? AND e.model=? AND e.source_hash=l.content_hash AND l.normalized_content_hash<>? ORDER BY e.id DESC LIMIT 3000');
    $query->execute([$userId, $embedding['model'], $excludeHash]);
    $best = null;
    foreach ($query->fetchAll() as $candidate) {
        $vector = json_decode($candidate['embedding_json'], true);
        if (!is_array($vector) || !vectorsAreCompatible($embedding['vector'], $vector)) {
            continue;
        }
        $score = embeddingCosineSimilarity($embedding['vector'], $vector);
        if ($score >= $threshold && (!$best || $score > $best['similarity'])) {
            $candidate['similarity'] = $score;
            $best = $candidate;
        }
    }
    return $best;
}

function saveLessonEmbedding(PDO $pdo, int $lessonId, string $sourceHash, array $embedding): void
{
    $save = $pdo->prepare('INSERT INTO lesson_embeddings (lesson_id,source_hash,model,embedding_json) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE embedding_json=VALUES(embedding_json),created_at=CURRENT_TIMESTAMP');
    $save->execute([$lessonId, $sourceHash, $embedding['model'], json_encode($embedding['vector'], JSON_THROW_ON_ERROR)]);
}