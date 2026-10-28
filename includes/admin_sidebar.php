<?php
// Administrator layout: its own sidebar, with none of the student pages (no Subjects, AI Tools, Schedule, etc.).
// Pages set $topbarTitle and $base before including this file, exactly like includes/sidebar.php.
$_adminInitial = strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1));
$_adminPage = basename($_SERVER['PHP_SELF']);
$_adminItems = [
    'admin.php'            => ['Overview',           'fa-tachometer-alt'],
    'admin_trends.php'     => ['Usage trends',       'fa-chart-line'],
    'admin_health.php'     => ['System health',      'fa-heartbeat'],
    'admin_users.php'      => ['User accounts',      'fa-users'],
    'admin_activity.php'   => ['Activity log',       'fa-history'],
    'admin_profile.php'    => ['Profile',            'fa-user'],
];
?>
<div class="app-layout">
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-icon"><i class="fas fa-shield-alt" aria-hidden="true"></i></div>
    <h1>StudiMate <span style="font-size:0.62rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;opacity:0.7;display:block;margin-top:2px">Admin</span></h1>
  </div>

  <nav class="sidebar-nav">
    <?php foreach ($_adminItems as $_file => $_item): $_on = $_adminPage === $_file; ?>
    <div class="nav-item">
      <a href="<?= $base ?? '' ?>modules/<?= $_file ?>" class="<?= $_on ? 'active' : '' ?>">
        <i class="fas <?= $_item[1] ?>"></i> <?= htmlspecialchars($_item[0]) ?>
        <?php if ($_on): ?><span class="nav-dot"></span><?php endif; ?>
      </a>
    </div>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-user">
    <div class="s-avatar"><?= htmlspecialchars($_adminInitial) ?></div>
    <div class="s-user-info">
      <p><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></p>
      <span>Administrator</span>
    </div>
  </div>
  <div class="sidebar-logout">
    <a href="<?= $base ?? '' ?>logout.php"><i class="fas fa-sign-out-alt"></i> Log out</a>
  </div>
</aside>

<div class="main-content">
<div class="topbar">
<div class="flex items-center gap-2">
  <span class="topbar-title"><?= htmlspecialchars($topbarTitle ?? 'Administrator') ?></span>
  </div>
  <div class="topbar-right">
    <button type="button" class="topbar-icon-btn topbar-theme-toggle theme-toggle" data-theme-toggle aria-label="Switch to dark mode" title="Switch to dark mode" aria-pressed="false">
      <span data-theme-icon aria-hidden="true">☾</span>
    </button>
    <a href="<?= $base ?? '' ?>modules/admin_profile.php" class="topbar-avatar" title="Profile"><?= htmlspecialchars($_adminInitial) ?></a>
  </div>
</div>
