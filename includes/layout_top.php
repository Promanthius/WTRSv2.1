<?php
// Ensure session is active and user is available
if (!isset($user)) {
    $user = current_user();
}

$unreadNotifCount = 0;
$unreadRequestCount = 0;
$unreadDecisionCount = 0;
if (!empty($user['id'])) {
    $unreadStmt = $pdo->prepare("SELECT type, COUNT(*) AS cnt
                                 FROM notifications
                                 WHERE recipient_user_id = :uid AND is_read = 0
                                 GROUP BY type");
    $unreadStmt->execute(['uid' => (int)$user['id']]);
    $unreadRows = $unreadStmt->fetchAll();
    foreach ($unreadRows as $row) {
        $type = $row['type'] ?? '';
        $cnt = (int)($row['cnt'] ?? 0);
        $unreadNotifCount += $cnt;
        if ($type === 'thesis_request') {
            $unreadRequestCount += $cnt;
        } elseif ($type === 'thesis_request_decision') {
            $unreadDecisionCount += $cnt;
        }
    }
}

// Map roles to their respective portal titles
$portalTitle = 'WMSU Repository';
if (isset($user['role'])) {
    if ($user['role'] === 'student') $portalTitle = 'Student Portal — WMSU Repository';
    else if ($user['role'] === 'adviser') $portalTitle = 'Adviser Dashboard — WMSU Repository';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($portalTitle) ?></title>
  
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/nunito.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/playfair-display.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/phosphor/css/phosphor-all.css">

  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/global.css?v=<?= filemtime(__DIR__ . '/../assets/css/global.css') ?>">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../assets/css/dashboard.css') ?>">

  
  <?php if (isset($extraCss)) echo $extraCss; ?>
</head>
<body>

<!-- ═══════════════════════════════════════════
     TOP BAR
═══════════════════════════════════════════ -->
<header class="topbar">
  <div class="topbar-left">
<?php
    $role = $user['role'] ?? 'student';
    $portalFolder = $role === 'adviser' ? 'faculty' : $role;
    $homePage = 'index.php';
    $portalHome = BASE_URL . $portalFolder . '/' . $homePage;
?>
    <a href="<?= $portalHome ?>" class="logo">
      <img src="<?= BASE_URL ?>assets/images/wmsu-logo.png" alt="WMSU"
        onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
      <i class="ph-fill ph-books" style="display:none;"></i>
      WMSU Repository
    </a>

    <nav class="topbar-nav">
      <a href="<?= $portalHome ?>" class="<?= (isset($current_page) && $current_page == $homePage) ? 'active' : '' ?>">
        <?= ($role === 'student') ? 'Student Portal' : 'Dashboard' ?>
      </a>
      <a href="<?= BASE_URL ?>public/archive.php">Archives</a>

      <a href="<?= BASE_URL ?>public/guidelines.php">Guidelines</a>
    </nav>
  </div>
  <div class="topbar-right">
    <a href="<?= BASE_URL ?>auth/logout.php" class="btn btn-secondary" style="text-decoration:none;">
      <i class="ph ph-sign-out"></i> Logout
    </a>

    <a href="<?= BASE_URL ?>user/notifications.php" class="icon-btn" title="Notifications" style="position: relative;">
      <i class="ph-fill ph-bell"></i>
      <?php if ($unreadRequestCount > 0): ?>
        <span style="position:absolute; top:-6px; right:-6px; min-width:18px; height:18px; border-radius:999px; background:#B91C1C; color:#fff; font-size:0.62rem; font-weight:800; display:flex; align-items:center; justify-content:center; padding:0 4px; border:2px solid #fff;">
          <?= $unreadRequestCount > 99 ? '99+' : $unreadRequestCount ?>
        </span>
      <?php endif; ?>
      <?php if ($unreadDecisionCount > 0): ?>
        <span style="position:absolute; bottom:-6px; right:-6px; min-width:18px; height:18px; border-radius:999px; background:#1D4ED8; color:#fff; font-size:0.62rem; font-weight:800; display:flex; align-items:center; justify-content:center; padding:0 4px; border:2px solid #fff;">
          <?= $unreadDecisionCount > 99 ? '99+' : $unreadDecisionCount ?>
        </span>
      <?php elseif ($unreadRequestCount === 0 && $unreadNotifCount > 0): ?>
        <span style="position:absolute; top:-6px; right:-6px; min-width:18px; height:18px; border-radius:999px; background:#6B7280; color:#fff; font-size:0.62rem; font-weight:800; display:flex; align-items:center; justify-content:center; padding:0 4px; border:2px solid #fff;">
          <?= $unreadNotifCount > 99 ? '99+' : $unreadNotifCount ?>
        </span>
      <?php endif; ?>
    </a>

    <a href="<?= BASE_URL ?>user/profile.php" class="user-avatar" title="My Profile Settings" style="text-decoration:none;">
      <?= htmlspecialchars(strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'U', 0, 1))) ?>
    </a>

  </div>
</header>
