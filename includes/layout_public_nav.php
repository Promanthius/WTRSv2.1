<?php
// Ensure session is started if not already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$sessionRole = $_SESSION['user']['role'] ?? null;
$isLoggedIn = !empty($_SESSION['user_id']) && in_array($sessionRole, ['student', 'adviser'], true);
$currentFile = basename($_SERVER['PHP_SELF']);
?>
<header class="pub-navbar">
  <div class="container pub-nav-container">
    
    <!-- Logo -->
    <div class="nav-left">
      <a href="<?= BASE_URL ?>public/archive.php" class="pub-logo">
        <div class="brand-seal-box">
          <img src="<?= BASE_URL ?>assets/images/wmsu-logo.png" alt="WMSU Logo"
            width="28" height="28"
            style="width:28px;height:28px;display:block;object-fit:contain;"
            onerror="this.style.display='none'; this.nextElementSibling.style.display='block';" />
          <span
            style="display:none; font-size:0.45rem; font-weight:800; color:#8B0000; text-align:center; line-height:1.2;">WMSU</span>
        </div>
        <span>WMSU <span style="font-weight:400; opacity:0.7;">Repository</span></span>
      </a>
    </div>

    <!-- Main Navigation -->
    <nav class="nav-center">
      <a href="<?= BASE_URL ?>public/archive.php" class="nav-link <?= ($currentFile == 'archive.php') ? 'active' : '' ?>">Browse Archives</a>
      <a href="<?= BASE_URL ?>public/guidelines.php" class="nav-link <?= ($currentFile == 'guidelines.php') ? 'active' : '' ?>">Guidelines</a>
      <a href="<?= BASE_URL ?>public/about.php" class="nav-link <?= ($currentFile == 'about.php') ? 'active' : '' ?>">About</a>
    </nav>

    <!-- Auth Actions -->
    <div class="nav-right">
      <?php if ($isLoggedIn): 
          $role = $sessionRole;
          $portalFolder = ($role === 'adviser') ? 'faculty' : $role;
          $homePage = 'index.php';
          $portalHome = BASE_URL . $portalFolder . '/' . $homePage;
      ?>
        <a href="<?= $portalHome ?>" class="nav-btn-solid">
          <i class="ph-bold ph-squares-four"></i> Go to Dashboard
        </a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>auth/login.php" class="nav-btn-text">Sign In</a>
        <a href="<?= BASE_URL ?>auth/register.php" class="nav-btn-solid">Register</a>
      <?php endif; ?>
    </div>

  </div>
</header>

