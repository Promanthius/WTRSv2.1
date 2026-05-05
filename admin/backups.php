<?php
require_once __DIR__ . '/../includes/session.php';
require_login(['adviser']);

$user = current_user();
$flash = null;

// Handle backup download
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'backup') {
    $mysqldump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
    $dbName = 'wtrs_db';
    $backupDir = __DIR__ . '/../database/backups/';
    
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $filename = 'wtrs_backup_' . date('Y-m-d_His') . '.sql';
    $filepath = $backupDir . $filename;
    
    $command = "\"$mysqldump\" --user=root --password= $dbName > \"$filepath\" 2>&1";
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($filepath)) {
        // Log this action
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action_type, description, ip_address) VALUES (?, 'database_backup', ?, ?)");
        $logStmt->execute([$user['id'], "Database backup created: $filename", $ip]);
        
        // Serve the file for download
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        $flash = ['type' => 'error', 'message' => 'Backup failed. Ensure mysqldump is available at: ' . $mysqldump];
    }
}

// Fetch existing backups
$backupDir = __DIR__ . '/../database/backups/';
$backups = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . '*.sql');
    foreach ($files as $file) {
        $backups[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'date' => filemtime($file),
        ];
    }
    usort($backups, function($a, $b) { return $b['date'] - $a['date']; });
}

// Fetch recent backup logs
$logStmt = $pdo->query("SELECT l.*, u.first_name, u.last_name 
                        FROM activity_logs l 
                        LEFT JOIN users u ON l.user_id = u.id 
                        WHERE l.action_type = 'database_backup' 
                        ORDER BY l.created_at DESC LIMIT 5");
$backupLogs = $logStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>System Backups - WTRS Admin</title>
  
  <link rel="stylesheet" href="../assets/fonts/google/css/nunito.css">
  <link rel="stylesheet" href="../assets/fonts/google/css/playfair-display.css">
  <link rel="stylesheet" href="../assets/fonts/google/css/cormorant-garamond.css">
  <link rel="stylesheet" href="../assets/vendor/phosphor/css/phosphor-all.css">

  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body style="background: #FAFAFA;">

  <!-- Top Navigation Bar -->
  <header class="topbar">
    <div class="topbar-left" style="flex:1;">
      <div class="logo">
        <i class="ph-fill ph-seal" style="color: var(--color-primary);"></i> WMSU Repository
      </div>
    </div>
    <div class="topbar-right" style="flex:1; justify-content: flex-end;">
      <a href="../auth/logout.php" class="btn btn-secondary" style="margin-right:0.5rem; text-decoration:none;">Logout</a>
      <button class="icon-btn"><i class="ph-fill ph-bell"></i></button>
      <div style="display: flex; align-items: center; gap: 0.5rem; margin-left: 1rem;">
        <div style="width: 32px; height: 32px; background: #720000; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700;">
          <?= htmlspecialchars(strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1))) ?>
        </div>
      </div>
    </div>
  </header>

  <div class="app-body">
    <!-- Left Sidebar -->
    <aside class="sidebar" style="background:#F3F4F6;">
      <div class="sidebar-brand" style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 2rem;">
        <i class="ph-fill ph-seal" style="font-size: 2.2rem; color: var(--color-primary);"></i>
        <div>
          <h3 style="font-size: 1.05rem; line-height: 1.2; margin-bottom: 0; color: var(--color-primary);">WTRS Admin</h3>
          <p style="font-size: 0.6rem; color: #9CA3AF; letter-spacing: 0.1em; text-transform: uppercase;">OFFICIAL REPOSITORY</p>
        </div>
      </div>

      <nav class="sidebar-menu">
        <a href="users.php" class="menu-item"><i class="ph-fill ph-users-three"></i> User Management</a>
        <a href="activations.php" class="menu-item"><i class="ph-fill ph-user-switch"></i> Activations</a>
        <a href="logs.php" class="menu-item"><i class="ph-bold ph-clock-counter-clockwise"></i> Activity Logs</a>
        <a href="backups.php" class="menu-item active" style="background: white; color: var(--color-primary); border-radius: var(--radius-sm); border-left: 4px solid var(--color-primary);"><i class="ph-fill ph-cloud-arrow-up"></i> Backups</a>
        <a href="settings.php" class="menu-item"><i class="ph-fill ph-gear"></i> Settings</a>
      </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content" style="padding: 2.5rem 3rem;">
      
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
        <div>
          <h1 style="font-size: 1.8rem; margin-bottom: 0.25rem;">System Backups</h1>
          <p style="color: #6B7280; font-size: 0.9rem;">Create and manage database backups for the WMSU Thesis Repository.</p>
        </div>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="action" value="backup">
          <button type="submit" style="background: var(--color-primary); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem;">
            <i class="ph-bold ph-database"></i> Create New Backup
          </button>
        </form>
      </div>

      <?php if ($flash): ?>
        <div style="margin-bottom: 1.5rem; padding: 0.8rem 1rem; border-radius: 0.45rem; color: #fff; background: <?= $flash['type'] === 'success' ? '#047857' : '#B91C1C' ?>;">
          <?= htmlspecialchars($flash['message']) ?>
        </div>
      <?php endif; ?>

      <!-- Existing Backups -->
      <div style="background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #E5E7EB;">
          <h3 style="font-size: 1rem; margin: 0;">Saved Backups</h3>
        </div>
        
        <?php if (count($backups) > 0): ?>
        <table style="width: 100%; border-collapse: collapse;">
          <thead>
            <tr style="background: #F9FAFB; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #6B7280; font-weight: 700;">
              <th style="padding: 0.75rem 1.5rem; text-align: left;">Filename</th>
              <th style="padding: 0.75rem 1.5rem; text-align: left;">Size</th>
              <th style="padding: 0.75rem 1.5rem; text-align: left;">Date Created</th>
              <th style="padding: 0.75rem 1.5rem; text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($backups as $bk): ?>
            <tr style="border-bottom: 1px solid #F3F4F6;">
              <td style="padding: 1rem 1.5rem; font-weight: 600; font-size: 0.9rem;">
                <i class="ph-fill ph-file-sql" style="color: var(--color-primary); margin-right: 0.5rem;"></i>
                <?= htmlspecialchars($bk['name']) ?>
              </td>
              <td style="padding: 1rem 1.5rem; color: #6B7280; font-size: 0.85rem;"><?= number_format($bk['size'] / 1024, 1) ?> KB</td>
              <td style="padding: 1rem 1.5rem; color: #6B7280; font-size: 0.85rem;"><?= date('M j, Y h:i A', $bk['date']) ?></td>
              <td style="padding: 1rem 1.5rem; text-align: right;">
                <a href="../database/backups/<?= htmlspecialchars($bk['name']) ?>" download style="background: #F3F4F6; color: var(--color-primary); border: none; padding: 0.4rem 1rem; border-radius: 4px; font-size: 0.8rem; font-weight: 600; text-decoration: none;">
                  <i class="ph-bold ph-download-simple"></i> Download
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
        <div style="padding: 3rem; text-align: center; color: #9CA3AF;">
          <i class="ph ph-database" style="font-size: 2.5rem; display: block; margin-bottom: 0.75rem;"></i>
          <p>No backups created yet. Click "Create New Backup" to generate your first backup.</p>
        </div>
        <?php endif; ?>
      </div>

      <!-- Recent Backup Activity -->
      <?php if (count($backupLogs) > 0): ?>
      <div style="margin-top: 2rem; background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #E5E7EB;">
          <h3 style="font-size: 1rem; margin: 0;">Recent Backup Activity</h3>
        </div>
        <div style="padding: 1rem 1.5rem;">
          <?php foreach ($backupLogs as $bl): ?>
          <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #F3F4F6; font-size: 0.85rem;">
            <span><strong><?= htmlspecialchars($bl['first_name'] . ' ' . $bl['last_name']) ?></strong> — <?= htmlspecialchars($bl['description']) ?></span>
            <span style="color: #9CA3AF;"><?= date('M j, Y h:i A', strtotime($bl['created_at'])) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </main>
  </div>

<script src="../assets/js/main.js"></script>
</body>
</html>
