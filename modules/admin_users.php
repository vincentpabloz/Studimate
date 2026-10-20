<?php
session_start();
require '../config/db.php';
require_once '../config/env.php';
require '../includes/admin_helpers.php';
$adminId=requireAdmin($pdo);
$base='../';

$csrfToken=$_SESSION['csrf_token']??bin2hex(random_bytes(32));
$_SESSION['csrf_token']=$csrfToken;
$notice='';$error='';

function activeAdminCount(PDO $pdo):int{
  return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();
}

/** Emails a one-time password reset link (valid 30 minutes). Same token flow as the Forgot password page. */
function sendResetLinkToUser(PDO $pdo,array $user,?string &$err=null):bool{
  require_once __DIR__.'/../includes/mail_helpers.php';
  require_once __DIR__.'/../includes/password_reset_helpers.php';
  try{
    ensurePasswordResetTable($pdo);
    $recent=$pdo->prepare('SELECT COUNT(*) FROM password_resets WHERE user_id=? AND created_at > (NOW() - INTERVAL 2 MINUTE)');
    $recent->execute([(int)$user['id']]);
    if((int)$recent->fetchColumn()>0){$err='A reset link was already sent to this student in the last 2 minutes.';return false;}
    $appUrl=rtrim(mailEnv('APP_URL'),'/');
    if($appUrl===''){
      $scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
      $appUrl=$scheme.'://'.$_SERVER['HTTP_HOST'].rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])),'/\\');
    }
    $token=bin2hex(random_bytes(32));
    $pdo->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([(int)$user['id']]);
    $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?, NOW() + INTERVAL 30 MINUTE)')
        ->execute([(int)$user['id'],hash('sha256',$token)]);
    $link=$appUrl.'/reset_password.php?token='.$token;
    $mail=createMailer();
    $mail->addAddress($user['email'],$user['name']);
    $mail->isHTML(true);
    $mail->Subject='Reset your StudiMate password';
    $name=htmlspecialchars($user['name'],ENT_QUOTES);
    $mail->Body="<p>Hi $name,</p><p>A StudiMate administrator sent you a link to reset your password. It works for 30 minutes and can be used once:</p>"
      .'<p><a href="'.htmlspecialchars($link,ENT_QUOTES).'">Reset my password</a></p>'
      .'<p>If the button does not work, copy this address into your browser:<br>'.htmlspecialchars($link,ENT_QUOTES).'</p>'
      .'<p>If you did not expect this, you can ignore this email. Your password will not change.</p>';
    $mail->AltBody="Hi {$user['name']},\n\nA StudiMate administrator sent you a link to reset your password (valid 30 minutes, one use):\n$link\n\nIf you did not expect this, ignore this email.";
    $mail->send();
    return true;
  }catch(Throwable $e){
    error_log('StudiMate admin reset mail failed: '.$e->getMessage());
    $err='The email could not be sent. Check the email settings on the System health page.';
    return false;
  }
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=(string)($_POST['action']??'');
  $targetId=(int)($_POST['user_id']??0);
  if(!hash_equals($csrfToken,(string)($_POST['csrf_token']??''))){
    $error='Your session expired. Reload the page and try again.';
  }else{
    $st=$pdo->prepare('SELECT id,name,email,role,is_active FROM users WHERE id=?');
    $st->execute([$targetId]);
    $target=$st->fetch();
    if(!$target){
      $error='That account no longer exists.';
    }elseif($action==='deactivate'){
      if($targetId===$adminId)$error='You cannot deactivate your own account.';
      elseif($target['role']==='admin'&&(int)$target['is_active']&&activeAdminCount($pdo)<=1)$error='This is the last active administrator and cannot be deactivated.';
      else{$pdo->prepare('UPDATE users SET is_active=0 WHERE id=?')->execute([$targetId]);$notice=$target['name'].' was deactivated and can no longer sign in.';logActivity($pdo,$adminId,'admin_action','admin','ok','deactivate user #'.$targetId);}
    }elseif($action==='activate'){
      $pdo->prepare('UPDATE users SET is_active=1 WHERE id=?')->execute([$targetId]);
      $notice=$target['name'].' was reactivated.';logActivity($pdo,$adminId,'admin_action','admin','ok','activate user #'.$targetId);
    }elseif($action==='promote'){
      if($target['role']==='admin')$error='That account is already an administrator.';
      elseif(!(int)$target['is_active'])$error='Reactivate the account before promoting it.';
      else{$pdo->prepare("UPDATE users SET role='admin' WHERE id=?")->execute([$targetId]);$notice=$target['name'].' is now an administrator.';logActivity($pdo,$adminId,'admin_action','admin','ok','promote user #'.$targetId);}
    }elseif($action==='demote'){
      if($targetId===$adminId)$error='You cannot remove your own administrator role.';
      elseif($target['role']!=='admin')$error='That account is not an administrator.';
      elseif(activeAdminCount($pdo)<=1)$error='This is the last active administrator and cannot be demoted.';
      else{$pdo->prepare("UPDATE users SET role='student' WHERE id=?")->execute([$targetId]);$notice=$target['name'].' is now a student account.';logActivity($pdo,$adminId,'admin_action','admin','ok','demote user #'.$targetId);}
    }elseif($action==='reset'){
      if(!(int)$target['is_active'])$error='Reactivate the account before sending a reset link.';
      else{
        $mailError=null;
        if(sendResetLinkToUser($pdo,$target,$mailError)){$notice='A password reset link was emailed to '.$target['email'].'.';logActivity($pdo,$adminId,'admin_action','admin','ok','reset link user #'.$targetId);}
        else $error=$mailError??'The reset link could not be sent.';
      }
    }else{
      $error='Unknown action.';
    }
  }
}

// List and search accounts (name, email, role and status only. No lessons, files or scores).
$q=trim((string)($_GET['q']??''));
$roleF=in_array($_GET['role']??'',['student','admin'],true)?$_GET['role']:'';
$statusF=in_array($_GET['status']??'',['active','deactivated'],true)?$_GET['status']:'';
$page=max(1,(int)($_GET['page']??1));$perPage=20;
$like='%'.addcslashes($q,'%_\\').'%';
$where='(name LIKE ? OR email LIKE ?)';$params=[$like,$like];
if($roleF!==''){$where.=' AND role=?';$params[]=$roleF;}
if($statusF!==''){$where.=' AND is_active=?';$params[]=$statusF==='active'?1:0;}
$cnt=$pdo->prepare('SELECT COUNT(*) FROM users WHERE '.$where);
$cnt->execute($params);
$totalUsers=(int)$cnt->fetchColumn();
$pages=max(1,(int)ceil($totalUsers/$perPage));$page=min($page,$pages);
$offset=($page-1)*$perPage;
$list=$pdo->prepare('SELECT id,name,email,role,is_active,last_login_at,created_at FROM users WHERE '.$where.' ORDER BY created_at DESC, id DESC LIMIT '.$perPage.' OFFSET '.$offset);
$list->execute($params);
$users=$list->fetchAll();
$counts=adminUserCounts($pdo);
$fmt=fn($t)=>$t?date('M j, Y',strtotime($t)):'Never';
$th='text-align:left;padding:8px 10px;font-size:0.72rem;color:var(--text-light);text-transform:uppercase;letter-spacing:0.03em;border-bottom:1px solid var(--border)';
$td='padding:9px 10px;font-size:0.84rem;border-bottom:1px solid var(--border);vertical-align:top';
$qs=($q!==''?'&q='.urlencode($q):'').($roleF!==''?'&role='.$roleF:'').($statusF!==''?'&status='.$statusF:'');

function actionForm(string $csrf,int $id,string $action,string $label,string $cls,string $confirm=''):string{
  return '<form method="POST" style="display:inline-block;margin:2px 4px 2px 0"'.($confirm!==''?' onsubmit="return confirm(\''.htmlspecialchars($confirm,ENT_QUOTES).'\')"':'').'>'
    .'<input type="hidden" name="csrf_token" value="'.htmlspecialchars($csrf).'">'
    .'<input type="hidden" name="user_id" value="'.$id.'"><input type="hidden" name="action" value="'.$action.'">'
    .'<button type="submit" class="btn '.$cls.' btn-xs" style="width:auto">'.htmlspecialchars($label).'</button></form>';
}

$pageTitle='User accounts – StudiMate Admin';
$topbarTitle='User accounts';
include '../includes/header.php';
include '../includes/admin_sidebar.php';
?>

<div class="page-body">
  <?php if($error):?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($error)?></div><?php endif;?>
  <?php if($notice):?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($notice)?></div><?php endif;?>

  <div class="admin-stats mb-4">
    <div class="analytics-stat"><div class="analytics-stat-val"><?=$counts['total']?></div><div class="analytics-stat-label">Total accounts</div></div>
    <div class="analytics-stat"><div class="analytics-stat-val"><?=$counts['active']?></div><div class="analytics-stat-label">Active</div></div>
    <div class="analytics-stat"><div class="analytics-stat-val" style="<?=$counts['deactivated']?'color:var(--danger)':''?>"><?=$counts['deactivated']?></div><div class="analytics-stat-label">Deactivated</div></div>
    <div class="analytics-stat"><div class="analytics-stat-val"><?=$counts['admins']?></div><div class="analytics-stat-label">Administrators</div></div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><h3>User accounts</h3></div>
    <div class="card-body">
      <form method="GET" style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
        <input type="text" name="q" value="<?=htmlspecialchars($q)?>" class="form-control" placeholder="Search by name or email" style="max-width:320px">
        <select name="role" class="form-control" style="max-width:150px">
          <option value="">All roles</option>
          <option value="student"<?=$roleF==='student'?' selected':''?>>Students</option>
          <option value="admin"<?=$roleF==='admin'?' selected':''?>>Administrators</option>
        </select>
        <select name="status" class="form-control" style="max-width:160px">
          <option value="">All statuses</option>
          <option value="active"<?=$statusF==='active'?' selected':''?>>Active</option>
          <option value="deactivated"<?=$statusF==='deactivated'?' selected':''?>>Deactivated</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm" style="width:auto"><i class="fas fa-search"></i> Search</button>
        <?php if($q!==''||$roleF!==''||$statusF!==''):?><a href="admin_users.php" class="btn btn-outline btn-sm" style="width:auto;text-decoration:none">Clear</a><?php endif;?>
      </form>
      <div style="overflow-x:auto">
      <table class="rtable" style="width:100%;border-collapse:collapse">
        <thead><tr>
          <th style="<?=$th?>">Account</th><th style="<?=$th?>">Role</th><th style="<?=$th?>">Status</th>
          <th style="<?=$th?>">Last login</th><th style="<?=$th?>">Joined</th><th style="<?=$th?>">Actions</th>
        </tr></thead>
        <tbody>
        <?php if(!$users):?>
          <tr><td colspan="6" style="<?=$td?>" class="text-muted">No accounts found.</td></tr>
        <?php endif;?>
        <?php foreach($users as $u):
          $isSelf=(int)$u['id']===$adminId;$isAdmin=$u['role']==='admin';$active=(bool)$u['is_active'];?>
          <tr>
            <td style="<?=$td?>"><strong><?=htmlspecialchars($u['name'])?></strong><?=$isSelf?' <span class="badge badge-blue">You</span>':''?><br><span class="text-muted"><?=htmlspecialchars($u['email'])?></span></td>
            <td data-label="Role" style="<?=$td?>"><span class="badge <?=$isAdmin?'badge-primary':'badge-blue'?>"><?=$isAdmin?'Admin':'Student'?></span></td>
            <td data-label="Status" style="<?=$td?>"><span class="badge <?=$active?'badge-success':'badge-danger'?>"><?=$active?'Active':'Deactivated'?></span></td>
            <td data-label="Last login" style="<?=$td?>"><?=htmlspecialchars($fmt($u['last_login_at']))?></td>
            <td data-label="Joined" style="<?=$td?>"><?=htmlspecialchars($fmt($u['created_at']))?></td>
            <td class="r-actions" style="<?=$td?>">
              <?php if($active):?><?=actionForm($csrfToken,(int)$u['id'],'reset','Send reset link','btn-outline')?><?php endif;?>
              <?php if(!$isSelf):?>
                <?php if($active):?><?=actionForm($csrfToken,(int)$u['id'],'deactivate','Deactivate','btn-danger','Deactivate this account? The person will no longer be able to sign in.')?>
                <?php else:?><?=actionForm($csrfToken,(int)$u['id'],'activate','Reactivate','btn-success')?><?php endif;?>
                <?php if($isAdmin):?><?=actionForm($csrfToken,(int)$u['id'],'demote','Remove admin','btn-outline','Remove administrator access from this account?')?>
                <?php elseif($active):?><?=actionForm($csrfToken,(int)$u['id'],'promote','Make admin','btn-outline','Give this account full administrator access?')?><?php endif;?>
              <?php endif;?>
            </td>
          </tr>
        <?php endforeach;?>
        </tbody>
      </table>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;font-size:0.82rem">
        <span class="text-muted"><?=$totalUsers?> account<?=$totalUsers===1?'':'s'?> · page <?=$page?> of <?=$pages?></span>
        <span>
          <?php if($page>1):?><a href="admin_users.php?page=<?=$page-1?><?=$qs?>" class="btn btn-outline btn-sm" style="width:auto;text-decoration:none">Previous</a><?php endif;?>
          <?php if($page<$pages):?><a href="admin_users.php?page=<?=$page+1?><?=$qs?>" class="btn btn-outline btn-sm" style="width:auto;text-decoration:none">Next</a><?php endif;?>
        </span>
      </div>
      <p class="text-sm text-muted" style="margin:12px 0 0">Administrators can manage accounts but cannot open students' lessons, files or quiz answers.</p>
    </div>
  </div>
</div>

<?php include '../includes/footer.php';?>
