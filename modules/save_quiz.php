<?php
session_start();
header('Content-Type: application/json');
if(!isset($_SESSION['user_id'])){http_response_code(401);echo json_encode(['error'=>'Not logged in']);exit;}
require '../config/db.php';
$uid=$_SESSION['user_id'];
$input=json_decode(file_get_contents('php://input'),true);
if(!is_array($input)){http_response_code(400);echo json_encode(['error'=>'Invalid quiz result.']);exit;}
$lid=(int)($input['lesson_id']??0);
$score=(int)($input['score']??0);
$total=(int)($input['total']??0);
if(!$lid||$total<=0||$score<0||$score>$total){http_response_code(400);echo json_encode(['error'=>'Invalid quiz result.']);exit;}
if(!hash_equals($_SESSION['csrf_token']??'',(string)($input['csrf_token']??''))){http_response_code(403);echo json_encode(['error'=>'Your session expired. Reload and try again.']);exit;}
// Verify ownership
$chk=$pdo->prepare("SELECT l.id FROM lessons l JOIN subjects s ON l.subject_id=s.id WHERE l.id=? AND s.user_id=?");
$chk->execute([$lid,$uid]);
if(!$chk->fetch()){http_response_code(403);echo json_encode(['error'=>'Unauthorized']);exit;}
// Save result
$pdo->prepare("INSERT INTO quiz_results (user_id,lesson_id,score,total) VALUES (?,?,?,?)")->execute([$uid,$lid,$score,$total]);
// Spaced repetition
$pct=$total>0?($score/$total)*100:0;
$interval=$pct>=80?7:($pct>=50?3:1);
$next=date('Y-m-d',strtotime("+$interval days"));
$pdo->prepare("INSERT INTO review_schedule (user_id,lesson_id,next_review_date,interval_days) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE next_review_date=VALUES(next_review_date),interval_days=VALUES(interval_days)")->execute([$uid,$lid,$next,$interval]);
// Recommendations
if($pct<60){
  $reason=$pct<40?'Low quiz score – urgent review needed':'Below target – needs review';
  $pdo->prepare("INSERT INTO recommendations (user_id,lesson_id,reason,priority) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE reason=VALUES(reason),priority=VALUES(priority),created_at=NOW()")->execute([$uid,$lid,$reason,$pct<40?3:2]);
}else{
  $pdo->prepare("DELETE FROM recommendations WHERE user_id=? AND lesson_id=?")->execute([$uid,$lid]);
}
echo json_encode(['success'=>true,'interval'=>$interval,'next_review'=>$next]);
?>
