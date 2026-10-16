<?php
function isActive($pages){return in_array(basename($_SERVER['PHP_SELF']),(array)$pages)?'active':'';}
$_initial = strtoupper(substr($_SESSION['user_name']??'S',0,1));
$_notifCount = 0;
if(isset($pdo,$_SESSION['user_id'])){
  $nc=$pdo->prepare("SELECT COUNT(*) FROM review_schedule WHERE user_id=? AND next_review_date<=CURDATE()");
  $nc->execute([$_SESSION['user_id']]);
  $_notifCount=(int)$nc->fetchColumn();
}
?>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="app-layout">
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-icon"><i class="fas fa-book-open" aria-hidden="true"></i></div>
    <h1>StudiMate</h1>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-item">
      <a href="<?=$base??''?>dashboard.php" class="<?=isActive('dashboard.php')?>">
        <i class="fas fa-th-large"></i> Dashboard
        <?php if(isActive('dashboard.php')==='active'):?><span class="nav-dot"></span><?php endif;?>
      </a>
    </div>
    <div class="nav-item">
      <a href="<?=$base??''?>modules/subjects.php" class="<?=isActive('subjects.php')?>">
        <i class="fas fa-book-open"></i> Subjects
        <?php if(isActive('subjects.php')==='active'):?><span class="nav-dot"></span><?php endif;?>
      </a>
    </div>
    <div class="nav-item">
      <a href="<?=$base??''?>modules/ai_tools.php" class="<?=isActive('ai_tools.php')?>">
        <i class="fas fa-magic"></i> AI Tools
        <?php if(isActive('ai_tools.php')==='active'):?><span class="nav-dot"></span><?php endif;?>
      </a>
    </div>
    <div class="nav-item">
      <a href="<?=$base??''?>modules/schedule.php" class="<?=isActive('schedule.php')?>">
        <i class="fas fa-calendar-alt"></i> Schedule
        <?php if(isActive('schedule.php')==='active'):?><span class="nav-dot"></span><?php endif;?>
      </a>
    </div>
    <div class="nav-item">
      <a href="<?=$base??''?>modules/progress.php" class="<?=isActive('progress.php')?>">
        <i class="fas fa-chart-bar"></i> Analytics
        <?php if(isActive('progress.php')==='active'):?><span class="nav-dot"></span><?php endif;?>
      </a>
    </div>
    <div class="nav-item">
      <a href="<?=$base??''?>modules/profile.php" class="<?=isActive('profile.php')?>">
        <i class="fas fa-user"></i> Profile
        <?php if(isActive('profile.php')==='active'):?><span class="nav-dot"></span><?php endif;?>
      </a>
    </div>
  </nav>

  <div class="sidebar-user">
    <div class="s-avatar"><?=htmlspecialchars($_initial)?></div>
    <div class="s-user-info">
      <p><?=htmlspecialchars($_SESSION['user_name']??'')?></p>
      <span><?=htmlspecialchars($_SESSION['user_email']??'')?></span>
    </div>
  </div>
  <div class="sidebar-logout">
    <a href="<?=$base??''?>logout.php"><i class="fas fa-sign-out-alt"></i> Log out</a>
  </div>
</aside>

<div class="main-content">
<div class="topbar">
  <div class="flex items-center gap-2">
    <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
    <span class="topbar-title"><?=$topbarTitle??'Dashboard'?></span>
  </div>
  <div class="topbar-right">
    <button class="topbar-icon-btn" onclick="window.location='<?=$base??''?>modules/schedule.php'" title="Reviews due">
      <i class="fas fa-bell"></i>
      <?php if($_notifCount>0):?><span class="notif-badge"><?=$_notifCount?></span><?php endif;?>
    </button>
    <a href="<?=$base??''?>modules/profile.php" class="topbar-avatar" title="Profile">
      <?=htmlspecialchars($_initial)?>
    </a>
  </div>
</div>
