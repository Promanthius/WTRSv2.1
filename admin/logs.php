<?php
require_once __DIR__ . '/../includes/session.php';
require_login(['adviser']);

$user = current_user();

// Fetch log count for stats
$countStmt = $pdo->query("SELECT COUNT(*) FROM activity_logs");
$totalLogs = $countStmt->fetchColumn();

// Fetch logs with user info
$logsStmt = $pdo->query("SELECT l.*, u.first_name, u.last_name, u.role 
                         FROM activity_logs l
                         LEFT JOIN users u ON l.user_id = u.id
                         ORDER BY l.created_at DESC
                         LIMIT 50");
$logs = $logsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Activity Logs - WTRS Admin</title>
  
  <link rel="stylesheet" href="../assets/fonts/google/css/nunito.css">
  <link rel="stylesheet" href="../assets/fonts/google/css/playfair-display.css">
  <link rel="stylesheet" href="../assets/fonts/google/css/cormorant-garamond.css">
  <link rel="stylesheet" href="../assets/vendor/phosphor/css/phosphor-all.css">

  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="../assets/css/dashboard-logs.css">
</head>
<body style="background: #FAFAFA;">

  <!-- Global Top Navbar -->
  <header class="topbar" style="background: #FFF; padding: 0 2rem; border-bottom: 1px solid #E5E7EB; box-shadow: none;">
    <div class="topbar-left" style="flex:1;">
      <div class="logo" style="font-size: 1.1rem; color: #720000;">
        WMSU REPOSITORY
      </div>
    </div>
    
    <div class="topbar-right" style="flex:1.5; justify-content: flex-end; gap: 1.5rem;">
      <a href="../auth/logout.php" class="btn btn-secondary" style="margin-right:0.5rem; text-decoration:none;">Logout</a>
      <i class="ph-fill ph-bell" style="color: #720000; font-size: 1.25rem;"></i>
      <div style="display: flex; align-items: center; gap: 0.5rem;">
        <div style="width: 32px; height: 32px; background: #720000; color: white; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700;">
          <?= htmlspecialchars(strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1))) ?>
        </div>
        <span style="font-size: 0.8rem; font-weight: 700; color: var(--color-text-main);"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
      </div>
    </div>
  </header>

  <!-- Application Body -->
  <div class="app-body">
    
    <!-- Left Sidebar -->
    <aside class="sidebar" style="background:#F3F4F6;">
      <div class="sidebar-brand" style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 2.5rem;">
        <div>
          <h3 style="font-size: 1.1rem; margin-bottom: 0; color: #720000;">WTRS Admin</h3>
          <p style="font-size: 0.6rem; font-weight: 800; color: var(--color-text-muted); letter-spacing: 0.1em; text-transform: uppercase;">OFFICIAL REPOSITORY</p>
        </div>
      </div>

      <nav class="sidebar-menu">
        <a href="users.php" class="menu-item"><i class="ph-fill ph-users"></i> User Management</a>
        <a href="activations.php" class="menu-item"><i class="ph-fill ph-user-switch"></i> Activations</a>
        <a href="logs.php" class="menu-item active" style="background: white; color: #720000; border-left: 4px solid #720000; border-radius: 4px; font-weight: 700;"><i class="ph-bold ph-clock-counter-clockwise"></i> Activity Logs</a>
        <a href="settings.php" class="menu-item"><i class="ph-fill ph-gear"></i> Settings</a>
      </nav>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content" style="padding: 2.5rem 3rem;">
      
      <div class="logs-header">
        <div class="lh-titles">
          <h2>Activity Logs</h2>
          <p>Audit trail and system event tracking for the faculty portal.</p>
        </div>
      </div>

      <!-- Table Box -->
      <div class="logs-table-container">
        <table class="logs-table">
          <thead>
            <tr>
              <th>TIMESTAMP</th>
              <th>USER ENTITY</th>
              <th>ACTION TYPE</th>
              <th>DESCRIPTION</th>
              <th>IP ADDRESS</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($logs) > 0): ?>
              <?php foreach ($logs as $log): ?>
              <tr>
                <td class="tc-timestamp">
                  <strong><?= date('M j, Y', strtotime($log['created_at'])) ?></strong>
                  <span><?= date('h:i:s A', strtotime($log['created_at'])) ?></span>
                </td>
                <td>
                  <div class="tc-user">
                    <div class="tcu-icon"><i class="ph-fill ph-user"></i></div>
                    <div class="tcu-info">
                      <strong><?= $log['first_name'] ? htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) : 'System' ?></strong>
                      <span><?= $log['role'] ? strtoupper(htmlspecialchars($log['role'])) : 'SYSTEM' ?></span>
                    </div>
                  </div>
                </td>
                <td><span class="tc-pill gold"><?= strtoupper(htmlspecialchars($log['action_type'])) ?></span></td>
                <td class="tc-desc">
                  <?= htmlspecialchars($log['description']) ?>
                </td>
                <td class="tc-ip"><?= htmlspecialchars($log['ip_address']) ?></td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" style="text-align: center; padding: 2rem; color: #6B7280;">No activity logs found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>

        <div class="logs-pagination">
          <div>Showing <?= count($logs) ?> of <?= $totalLogs ?> entries</div>
        </div>
      </div>

      <!-- Bottom Cards -->
      <div class="logs-cards-grid">
        <div class="lc-card border-red">
          <div class="lc-title">SYSTEM INTEGRITY</div>
          <div class="lc-val">Stable</div>
          <div class="lc-status green"><i class="ph-bold ph-check-circle"></i> All systems operational</div>
        </div>
        
        <div class="lc-card border-gold">
          <div class="lc-title">TOTAL LOGS</div>
          <div class="lc-val"><?= number_format($totalLogs) ?></div>
        </div>

        <div class="lc-card border-dark">
          <div class="lc-title">CRITICAL ALERTS</div>
          <div class="lc-val">0</div>
          <div class="lc-status gray"><i class="ph-bold ph-shield"></i> No unauthorized access</div>
        </div>
      </div>

      <!-- Footer -->
      <div class="logs-footer">
        <p>&copy; <?= date('Y') ?> WESTERN MINDANAO STATE UNIVERSITY. WTRS.</p>
      </div>

    </main>
  </div>

<script src="../assets/js/main.js"></script>
</body>
</html>
