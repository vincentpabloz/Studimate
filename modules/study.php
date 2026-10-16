<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}
require '../config/db.php';
require '../includes/study_helpers.php';

$uid = (int)$_SESSION['user_id'];
$lessonId = (int)($_GET['lesson_id'] ?? 0);
$lessonQuery = $pdo->prepare('
  SELECT l.id,l.title,l.version,l.content_hash,s.id AS subject_id,s.subject_name,s.color
  FROM lessons l
  JOIN subjects s ON s.id=l.subject_id
  WHERE l.id=? AND s.user_id=?
');
$lessonQuery->execute([$lessonId, $uid]);
$lesson = $lessonQuery->fetch();
if (!$lesson) {
    http_response_code(404);
    exit('Lesson not found.');
}

$toolQuery = $pdo->prepare('SELECT type,content FROM study_tools WHERE lesson_id=? AND source_version=? AND source_hash=?');
$toolQuery->execute([$lessonId, (int)$lesson['version'], $lesson['content_hash']]);
$savedOutputs = [];
foreach ($toolQuery->fetchAll() as $output) {
    $savedOutputs[$output['type']] = $output['content'];
}

$documentQuery = $pdo->prepare('
  SELECT d.id,d.original_filename,d.file_type,d.file_mime,d.created_at
  FROM documents d
  WHERE d.lesson_id=? AND d.subject_id=? AND d.user_id=?
  ORDER BY d.created_at DESC,d.id DESC
  LIMIT 1
');
$documentQuery->execute([$lessonId, (int)$lesson['subject_id'], $uid]);
$document = $documentQuery->fetch() ?: null;

$resultsQuery = $pdo->prepare('SELECT score,total,taken_at FROM quiz_results WHERE lesson_id=? AND user_id=? ORDER BY taken_at DESC,id DESC LIMIT 5');
$resultsQuery->execute([$lessonId, $uid]);
$quizResults = $resultsQuery->fetchAll();

$tabs = ['file' => 'Uploaded File', 'explanation' => 'Summary', 'quiz' => 'Quiz', 'flashcard' => 'Flashcards'];
$activeTab = $_GET['tab'] ?? 'file';
if (!is_string($activeTab) || !isset($tabs[$activeTab])) {
    $activeTab = 'file';
}
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
$quizQuestions = isset($savedOutputs['quiz']) ? parseQuiz($savedOutputs['quiz']) : [];
$flashcards = isset($savedOutputs['flashcard']) ? parseFC($savedOutputs['flashcard']) : [];

$pageTitle = htmlspecialchars($lesson['title']) . ' – Study – StudiMate';
$topbarTitle = 'Study';
$base = '../';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="page-body">
  <a href="lessons.php?subject_id=<?=(int)$lesson['subject_id']?>" style="display:inline-flex;align-items:center;gap:6px;font-size:0.78rem;font-weight:600;color:var(--text-mid);margin-bottom:14px;text-decoration:none">
    <i class="fas fa-chevron-left"></i> <?=htmlspecialchars($lesson['subject_name'])?>
  </a>

  <div class="card mb-4">
    <div class="card-body">
      <div style="font-size:0.76rem;color:var(--text-light);margin-bottom:4px"><?=htmlspecialchars($lesson['subject_name'])?></div>
      <h2 style="font-size:1.15rem;margin:0;color:var(--text-dark)"><?=htmlspecialchars($lesson['title'])?></h2>
      <div class="study-output-tabs" role="tablist" aria-label="Lesson study materials" style="display:flex;gap:8px;flex-wrap:wrap;border-top:1px solid var(--border);padding-top:14px;margin-top:16px">
        <?php foreach ($tabs as $key => $label): ?>
        <button type="button" id="study-tab-<?=$key?>" class="btn btn-outline btn-pill btn-sm" role="tab" aria-controls="study-panel-<?=$key?>" aria-selected="<?=$activeTab === $key ? 'true' : 'false'?>" onclick="showStudyTab('<?=$key?>')" style="width:auto;<?=$activeTab === $key ? 'background:var(--primary);color:#fff;border-color:var(--primary)' : ''?>">
          <?=htmlspecialchars($label)?>
        </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <section id="study-panel-file" role="tabpanel" aria-labelledby="study-tab-file" style="<?=$activeTab === 'file' ? '' : 'display:none'?>">
    <?php if ($document): ?>
    <div class="card">
      <div class="card-header"><h3>Original lesson file</h3></div>
      <div class="card-body">
        <p class="text-sm"><?=htmlspecialchars($document['original_filename'])?></p>
        <p class="text-sm text-muted">Uploaded <?=htmlspecialchars(date('M j, Y', strtotime($document['created_at'])))?> · <?=htmlspecialchars(strtoupper($document['file_type']))?></p>
        <a class="btn btn-primary btn-pill btn-sm" style="width:auto" href="download_document.php?id=<?=(int)$document['id']?>">
          <i class="fas fa-download"></i> Download original file
        </a>
      </div>
    </div>
    <?php else: ?>
    <div class="empty-state"><i class="fas fa-file-alt"></i><h4>No uploaded file</h4><p>This lesson has no original uploaded document.</p></div>
    <?php endif; ?>
  </section>

  <section id="study-panel-explanation" role="tabpanel" aria-labelledby="study-tab-explanation" style="<?=$activeTab === 'explanation' ? '' : 'display:none'?>">
    <?php if (isset($savedOutputs['explanation'])): ?>
    <div class="card">
      <div class="card-header"><h3>Summary</h3></div>
      <div class="card-body" style="line-height:1.8;font-size:0.88rem;color:var(--text-dark);white-space:pre-wrap"><?=htmlspecialchars($savedOutputs['explanation'])?></div>
    </div>
    <?php else: ?>
    <div class="empty-state"><i class="fas fa-file-alt"></i><h4>No saved summary</h4><p>Generate a summary and use “Save to Subject” to keep it with this lesson.</p><a class="btn btn-primary btn-pill btn-sm" style="width:auto" href="ai_tools.php?lesson_id=<?=$lessonId?>&amp;tab=explanation">Generate summary</a></div>
    <?php endif; ?>
  </section>

  <section id="study-panel-quiz" role="tabpanel" aria-labelledby="study-tab-quiz" style="<?=$activeTab === 'quiz' ? '' : 'display:none'?>">
    <?php if (!isset($savedOutputs['quiz'])): ?>
    <div class="empty-state"><i class="fas fa-question-circle"></i><h4>No saved quiz</h4><p>Generate a quiz and use “Save to Subject” to keep it with this lesson.</p><a class="btn btn-primary btn-pill btn-sm" style="width:auto" href="ai_tools.php?lesson_id=<?=$lessonId?>&amp;tab=quiz">Generate quiz</a></div>
    <?php elseif (!$quizQuestions): ?>
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> The saved quiz format could not be read.</div>
    <?php else: ?>
    <input type="hidden" id="lessonId" value="<?=$lessonId?>">
    <input type="hidden" id="quizCsrfToken" value="<?=htmlspecialchars($csrfToken)?>">
    <div id="quizWrap">
      <div class="card" style="padding:24px">
        <div style="margin-bottom:16px">
          <div class="quiz-prog-bar"><div class="quiz-prog-fill" id="quizFill" style="width:0%"></div></div>
          <div class="quiz-meta-row"><span>Question <span id="qCurrent">1</span> / <span id="qTotal"><?=count($quizQuestions)?></span></span><span style="color:var(--primary);font-weight:700">StudiMate Quiz</span></div>
        </div>
        <div class="quiz-q" id="qText"></div>
        <div class="quiz-options" id="qOpts"></div>
        <div style="display:flex;justify-content:flex-end;margin-top:18px">
          <button id="qNext" onclick="nextQ()" class="btn btn-primary btn-pill btn-sm" style="display:none;width:auto;padding:9px 22px">Next <i class="fas fa-arrow-right"></i></button>
        </div>
      </div>
    </div>
    <div id="quizResult" style="display:none">
      <div class="card" style="padding:32px;text-align:center">
        <div style="font-size:1rem;font-weight:700;margin-bottom:20px">Quiz Complete!</div>
        <div class="score-circle"><span class="score-num"></span><span class="score-denom"></span></div>
        <div style="font-size:1.6rem;font-weight:800;color:var(--primary);margin-bottom:6px" class="score-pct"></div>
        <p class="score-msg text-sm text-muted mb-6"></p>
        <button onclick="location.reload()" class="btn btn-outline btn-pill btn-sm" style="width:auto"><i class="fas fa-redo"></i> Retake</button>
      </div>
    </div>
    <?php endif; ?>
    <?php if ($quizResults): ?>
    <div class="card" style="margin-top:16px">
      <div class="card-header"><h3>Recent quiz results</h3></div>
      <div class="card-body">
        <?php foreach ($quizResults as $result): ?>
        <div style="display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px solid var(--border)">
          <span class="text-sm"><?=htmlspecialchars(date('M j, Y g:i A', strtotime($result['taken_at'])))?></span>
          <strong><?= (int)$result['score'] ?> / <?= (int)$result['total'] ?> (<?= (int)round(((int)$result['score'] / max(1, (int)$result['total'])) * 100) ?>%)</strong>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </section>

  <section id="study-panel-flashcard" role="tabpanel" aria-labelledby="study-tab-flashcard" style="<?=$activeTab === 'flashcard' ? '' : 'display:none'?>">
    <?php if (!isset($savedOutputs['flashcard'])): ?>
    <div class="empty-state"><i class="fas fa-clone"></i><h4>No saved flashcards</h4><p>Generate flashcards and use “Save to Subject” to keep them with this lesson.</p><a class="btn btn-primary btn-pill btn-sm" style="width:auto" href="ai_tools.php?lesson_id=<?=$lessonId?>&amp;tab=flashcard">Generate flashcards</a></div>
    <?php elseif (!$flashcards): ?>
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> The saved flashcards format could not be read.</div>
    <?php else: ?>
    <div class="card" style="padding:20px">
      <div style="text-align:center;font-size:0.78rem;font-weight:600;color:var(--text-light);margin-bottom:6px">Card <span id="fcNum">1</span> of <?=count($flashcards)?></div>
      <?php foreach ($flashcards as $index => $card): ?>
      <div class="fc-scene" id="fc<?=$index?>" onclick="this.classList.toggle('flipped')" style="<?=$index > 0 ? 'display:none' : ''?>">
        <div class="fc-inner">
          <div class="fc-front"><div class="fc-label">Question</div><div class="fc-text"><?=htmlspecialchars($card['front'])?></div></div>
          <div class="fc-back"><div class="fc-label">Answer</div><div class="fc-text"><?=htmlspecialchars($card['back'])?></div></div>
        </div>
      </div>
      <?php endforeach; ?>
      <p class="fc-hint">Click card to flip · Use arrow keys to navigate</p>
      <div style="display:flex;justify-content:center;gap:10px;margin-top:14px">
        <button class="btn btn-outline btn-pill btn-sm" style="width:auto" onclick="fcNav(-1)"><i class="fas fa-arrow-left"></i> Prev</button>
        <button class="btn btn-primary btn-pill btn-sm" style="width:auto;padding:9px 20px" onclick="fcNav(1)">Next <i class="fas fa-arrow-right"></i></button>
      </div>
    </div>
    <?php endif; ?>
  </section>
</div>

<?php
$quizJson = json_encode($quizQuestions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$flashcardCount = count($flashcards);
$extraJs = "window.showStudyTab=function(key){['file','explanation','quiz','flashcard'].forEach(function(name){var tab=document.getElementById('study-tab-'+name);var panel=document.getElementById('study-panel-'+name);var active=name===key;if(tab){tab.setAttribute('aria-selected',active?'true':'false');tab.style.background=active?'var(--primary)':'';tab.style.color=active?'#fff':'';tab.style.borderColor=active?'var(--primary)':'';}if(panel)panel.style.display=active?'block':'none';});history.replaceState(null,'','study.php?lesson_id=".$lessonId."&tab='+encodeURIComponent(key));};initFlashcards(".$flashcardCount.");";
if ($quizQuestions) {
    $extraJs .= "document.addEventListener('DOMContentLoaded',function(){initQuiz(" . ($quizJson ?: '[]') . ");});";
}
if ($flashcards) {
    $extraJs .= "document.addEventListener('keydown',function(event){if(event.key==='ArrowRight')fcNav(1);else if(event.key==='ArrowLeft')fcNav(-1);else if(event.key===' '){event.preventDefault();var card=document.getElementById('fc'+fcIdx);if(card)card.classList.toggle('flipped');}});";
}
include '../includes/footer.php';
?>
