<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}
require '../config/db.php';
require '../includes/study_helpers.php';
require '../includes/quiz_retake.php';

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

$quizStatus = quizAttemptStatus($pdo, $uid, $lessonId);

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

  <?php if(isset($_GET['saved'])):?>
  <div class="alert alert-success mb-4"><i class="fas fa-check-circle"></i>Saved to <?=htmlspecialchars($lesson['subject_name'])?>. You can open it here anytime.</div>
  <?php endif;?>

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
      <div class="card-body" style="line-height:1.7;font-size:0.88rem;color:var(--text-dark)"><?=renderSummary($savedOutputs['explanation'])?></div>
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
    <button type="button" class="study-launch btn btn-primary btn-pill" onclick="window.openStudyMode('quiz')">
      <i class="fas fa-play" aria-hidden="true"></i> Open quiz (<?=count($quizQuestions)?> questions)
    </button>
    <?php endif; ?>
  </section>

  <section id="study-panel-flashcard" role="tabpanel" aria-labelledby="study-tab-flashcard" style="<?=$activeTab === 'flashcard' ? '' : 'display:none'?>">
    <?php if (!isset($savedOutputs['flashcard'])): ?>
    <div class="empty-state"><i class="fas fa-clone"></i><h4>No saved flashcards</h4><p>Generate flashcards and use “Save to Subject” to keep them with this lesson.</p><a class="btn btn-primary btn-pill btn-sm" style="width:auto" href="ai_tools.php?lesson_id=<?=$lessonId?>&amp;tab=flashcard">Generate flashcards</a></div>
    <?php elseif (!$flashcards): ?>
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> The saved flashcards format could not be read.</div>
    <?php else: ?>
    <button type="button" class="study-launch btn btn-primary btn-pill" onclick="window.openStudyMode('flashcard')">
      <i class="fas fa-clone" aria-hidden="true"></i> Open flashcards (<?=count($flashcards)?> cards)
    </button>
    <?php endif; ?>
  </section>
</div>

<?php if ($quizQuestions || $flashcards): ?>
<div class="modal-overlay study-modal-overlay" id="studyModal" aria-hidden="true">
  <section class="modal-box study-modal" role="dialog" aria-modal="true" aria-labelledby="studyModalTitle" aria-describedby="studyModalSubtitle" tabindex="-1">
    <header class="modal-header study-modal-header">
      <div class="study-heading">
        <h2 id="studyModalTitle"></h2>
        <p id="studyModalSubtitle"><?=htmlspecialchars($lesson['title'], ENT_QUOTES, 'UTF-8')?></p>
      </div>
      <div class="study-header-actions">
        <span class="study-progress-pill" id="studyProgressPill"></span>
        <button type="button" class="modal-close study-close" id="studyClose" aria-label="Close study dialog">
          <i class="fas fa-times" aria-hidden="true"></i>
        </button>
      </div>
      <div class="study-progress-track" aria-hidden="true"><span id="studyProgressBar"></span></div>
    </header>
    <div class="modal-body study-modal-body">
      <div id="studyQuizMode" class="study-mode" hidden>
        <div id="studyQuizLoading" class="study-status" role="status">Checking quiz retake eligibility…</div>
        <div id="studyQuizQuestion" hidden>
          <h3 class="study-question" id="studyQuestion"></h3>
          <div class="study-options" id="studyOptions"></div>
          <div class="study-feedback" id="studyFeedback" aria-live="polite"></div>
        </div>
        <section id="studyQuizResults" class="study-results" aria-live="polite" hidden>
          <div class="study-results-icon" aria-hidden="true"><i class="fas fa-chart-line"></i></div>
          <h3 id="studyResultsHeading">Quiz results</h3>
          <div class="study-score" id="studyScore"></div>
          <div class="study-percentage" id="studyPercentage"></div>
          <p class="study-result-message" id="studyResultMessage"></p>
          <div class="study-cooldown" id="studyCooldown" hidden>
            <strong id="studyCooldownRule"></strong>
            <span id="studyCooldownDate"></span>
            <span id="studyCountdown" role="status" aria-live="polite"></span>
          </div>
          <p class="study-last-attempt" id="studyLastAttempt"></p>
          <p class="study-save-error" id="studySaveError" role="alert" hidden></p>
          <div class="study-history" id="studyHistory"></div>
          <button type="button" class="btn btn-primary btn-pill study-retake" id="studyRetake" hidden></button>
        </section>
      </div>
      <div id="studyFlashcardMode" class="study-mode" hidden>
        <?php foreach ($flashcards as $index => $card): ?>
        <div class="fc-scene study-fc-scene" id="studyFc<?=$index?>" role="button" tabindex="-1" aria-pressed="false" aria-label="Flashcard question: <?=htmlspecialchars($card['front'], ENT_QUOTES, 'UTF-8')?>. Flip to reveal the answer" onclick="window.flipStudyCard(this)">
          <div class="fc-inner">
            <div class="fc-front"><div class="fc-label">Question</div><div class="fc-text"><?=htmlspecialchars($card['front'], ENT_QUOTES, 'UTF-8')?></div></div>
            <div class="fc-back" hidden><div class="fc-label">Answer</div><div class="fc-text"><?=htmlspecialchars($card['back'], ENT_QUOTES, 'UTF-8')?></div></div>
          </div>
        </div>
        <?php endforeach; ?>
        <p class="fc-hint">Click card to flip · Use arrow keys to navigate</p>
      </div>
    </div>
    <footer class="modal-footer study-modal-footer" id="studyModalFooter">
      <button type="button" class="btn btn-outline btn-pill" id="studyPrev"><i class="fas fa-arrow-left" aria-hidden="true"></i> Prev</button>
      <button type="button" class="btn btn-primary btn-pill" id="studyNext">Next <i class="fas fa-arrow-right" aria-hidden="true"></i></button>
    </footer>
  </section>
</div>
<?php endif; ?>

<?php
$quizJson = json_encode($quizQuestions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$flashcardCount = count($flashcards);
$quizStatusJson = json_encode($quizStatus, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$extraJs = 'window.showStudyTab=function(key){["file","explanation","quiz","flashcard"].forEach(function(name){var tab=document.getElementById("study-tab-"+name);var panel=document.getElementById("study-panel-"+name);var active=name===key;if(tab){tab.setAttribute("aria-selected",active?"true":"false");tab.style.background=active?"var(--primary)":"";tab.style.color=active?"#fff":"";tab.style.borderColor=active?"var(--primary)":"";}if(panel)panel.style.display=active?"block":"none";});history.replaceState(null,"","study.php?lesson_id='. $lessonId .'&tab="+encodeURIComponent(key));};';
if ($quizQuestions || $flashcards) {
    $extraJs .= 'initStudyModal('.($quizJson ?: '[]').','.($quizStatusJson ?: '{"attempts":[],"latest":null,"eligible":true}').','.$flashcardCount.','. $lessonId .','
        .json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT).');';
}
include '../includes/footer.php';
?>
