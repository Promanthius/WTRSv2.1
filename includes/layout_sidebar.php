<?php
$role = $user['role'] ?? 'student';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- ═══════════════════════════════════════════
     APP BODY & SIDEBAR
═══════════════════════════════════════════ -->
<div class="app-body">

  <aside class="sidebar">
    <div class="sidebar-brand">
      <h3>WMSU Repository</h3>
      <p>
        <?php
        if ($role === 'student') echo 'Student <br>Scholar';
        elseif ($role === 'adviser') echo 'Faculty &amp; <br>Reviewer';

        ?>
      </p>
    </div>

    <nav class="sidebar-menu">
      <?php if ($role === 'student'): ?>
        <span class="menu-section-label">Main</span>
        <a href="<?= BASE_URL ?>student/index.php" class="menu-item <?= ($current_page == 'index.php') ? 'active' : '' ?>">
          <i class="ph-fill ph-squares-four"></i> Dashboard
        </a>
        <a href="<?= BASE_URL ?>student/index.php?view=submissions" class="menu-item <?= (($view ?? '') == 'submissions') ? 'active' : '' ?>">
          <i class="ph-fill ph-file-text"></i> My Submissions
        </a>
        <a href="<?= BASE_URL ?>student/tracker.php" class="menu-item <?= ($current_page == 'tracker.php') ? 'active' : '' ?>">
          <i class="ph-fill ph-map-pin-line"></i> Tracker
        </a>

      <?php elseif ($role === 'adviser'): ?>
        <span class="menu-section-label">Main</span>
        <a href="<?= BASE_URL ?>faculty/index.php" class="menu-item <?= ($current_page == 'index.php') ? 'active' : '' ?>">
          <i class="ph-fill ph-squares-four"></i> Dashboard
        </a>
        <a href="<?= BASE_URL ?>faculty/review.php" class="menu-item <?= ($current_page == 'review.php') ? 'active' : '' ?>">
          <i class="ph-fill ph-clipboard-text"></i> Review Queue
        </a>

        <span class="menu-section-label" style="margin-top:0.5rem;">Manage</span>
        <a href="<?= BASE_URL ?>faculty/students.php" class="menu-item <?= ($current_page == 'students.php') ? 'active' : '' ?>">
          <i class="ph-fill ph-users"></i> Students
        </a>
        <a href="<?= BASE_URL ?>faculty/review.php?status=approved" class="menu-item <?= ($current_page == 'review.php' && ($_GET['status'] ?? '') == 'approved') ? 'active' : '' ?>">
          <i class="ph-fill ph-archive"></i> Archives
        </a>


      <?php endif; ?>
    </nav>

    <div class="sidebar-bottom">
      <?php if ($role === 'student'): ?>
        <a href="<?= BASE_URL ?>student/upload.php?new=1" class="btn btn-primary" style="width:100%; justify-content:center; padding: 0.65rem; font-size:0.8rem; margin-bottom: 0.75rem;">
          <i class="ph-bold ph-upload-simple"></i> Upload Thesis
        </a>

      <?php endif; ?>
      
      <div class="sidebar-bottom-links">
        <a href="<?= BASE_URL ?>user/profile.php" class="<?= ($current_page == 'profile.php') ? 'active' : '' ?>">
          <i class="ph-fill ph-gear"></i> Settings
        </a>
        <?php if ($role === 'adviser'): ?>
        <a href="<?= BASE_URL ?>public/about.php"><i class="ph-fill ph-question"></i> Support</a>
        <?php endif; ?>
      </div>
    </div>
  </aside>
