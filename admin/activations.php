<?php
require_once __DIR__ . '/../includes/session.php';
require_login(['adviser']);

$user = current_user();
$flash = null;

// Handle activation / rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
  $action = $_POST['action'] ?? '';

  if ($userId <= 0 || !in_array($action, ['activate', 'reject'], true)) {
    $flash = ['type' => 'error', 'message' => 'Invalid action.'];
  } else {
    $newStatus = $action === 'activate' ? 'active' : 'inactive';
    $stmt = $pdo->prepare('UPDATE users SET status = :status WHERE id = :id AND status = :pending');
    $stmt->execute(['status' => $newStatus, 'id' => $userId, 'pending' => 'pending']);

    if ($stmt->rowCount() > 0) {
      // Log this action
      $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
      $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action_type, description, ip_address) VALUES (?, 'user_activation', ?, ?)");
      $logStmt->execute([$user['id'], "User ID $userId was " . ($action === 'activate' ? 'activated' : 'rejected'), $ip]);

      $flash = ['type' => 'success', 'message' => 'Account ' . ($action === 'activate' ? 'activated' : 'rejected') . ' successfully.'];
    } else {
      $flash = ['type' => 'error', 'message' => 'Unable to update user status.'];
    }
  }
}

// Fetch pending users
$pendingStmt = $pdo->query("SELECT * FROM users WHERE status = 'pending' ORDER BY created_at DESC");
$pendingUsers = $pendingStmt->fetchAll();

// Stats
$totalPendingStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'");
$totalPending = $totalPendingStmt->fetchColumn();

require_once __DIR__ . '/../includes/layout_top.php';
require_once __DIR__ . '/../includes/layout_sidebar.php';
?>

  <main class="main-content">
      
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-title">
        <h1>Pending <span>Activations</span></h1>
        <p>Verify institutional emails and approve academic access requests.</p>
      </div>
      <div class="page-header-actions">
        <!-- Optional Actions here -->
      </div>
    </div>

    <?php if ($flash): ?>
      <div id="flash-message" style="margin-bottom: 2rem; padding: 1rem 1.5rem; border-radius: var(--radius-sm); font-weight:600; font-size: 0.85rem; color: #fff; background: <?= $flash['type'] === 'success' ? '#065F46' : '#991B1B' ?>; transition: opacity 0.5s ease-out;">
        <i class="ph-bold <?= $flash['type'] === 'success' ? 'ph-check-circle' : 'ph-warning-circle' ?>" style="margin-right:0.5rem; font-size:1.1rem; vertical-align:middle;"></i>
        <?= htmlspecialchars($flash['message']) ?>
      </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 2rem;">
      <!-- Table Container -->
      <div class="table-container">
        <div class="table-toolbar">
          <div>
            <h3>Registration Requests</h3>
            <p>Users waiting for account verification</p>
          </div>
          <div class="search-input-wrap">
            <i class="ph ph-magnifying-glass"></i>
            <input type="text" placeholder="Search pending..." id="searchInput" oninput="filterTable()">
          </div>
        </div>

        <table id="reqTable">
          <thead>
            <tr>
              <th>Applicant</th>
              <th>College</th>
              <th>Date Requested</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($pendingUsers) > 0): ?>
              <?php foreach ($pendingUsers as $pu): ?>
                <tr>
                  <td>
                    <div class="submitter-cell">
                      <div class="avatar" style="background:var(--crimson);">
                        <?= htmlspecialchars(strtoupper(substr($pu['first_name'], 0, 1) . substr($pu['last_name'], 0, 1))) ?>
                      </div>
                      <div>
                        <div class="submitter-name"><?= htmlspecialchars($pu['first_name'] . ' ' . $pu['last_name']) ?></div>
                        <div class="submitter-college" style="font-weight:700; color:var(--text-dark); margin-top:0.2rem;"><?= htmlspecialchars($pu['email']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div style="font-size:0.8rem; font-weight:600; color:var(--text-muted);">
                      <?= htmlspecialchars($pu['college'] ?? '—') ?>
                    </div>
                  </td>
                  <td>
                    <div style="font-size:0.85rem; font-weight:700; color:var(--text-dark);">
                      <?= date('M j, Y', strtotime($pu['created_at'])) ?>
                    </div>
                    <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.15rem;">
                      <?= date('h:i A', strtotime($pu['created_at'])) ?>
                    </div>
                  </td>
                  <td style="text-align: right;">
                    <form method="post" style="display:inline-block;">
                      <input type="hidden" name="user_id" value="<?= intval($pu['id']) ?>">
                      <input type="hidden" name="action" value="activate">
                      <button type="submit" class="btn btn-primary" style="padding:0.35rem 0.75rem; font-size:0.75rem;">Activate</button>
                    </form>
                    <form method="post" style="display:inline-block; margin-left:0.25rem;">
                      <input type="hidden" name="user_id" value="<?= intval($pu['id']) ?>">
                      <input type="hidden" name="action" value="reject">
                      <button type="submit" class="btn btn-secondary" style="padding:0.35rem 0.75rem; font-size:0.75rem; color:#DC2626; border-color:#DC2626;">Reject</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="4">
                  <div class="empty-state">
                    <i class="ph ph-user-plus"></i>
                    <p>No pending requests</p>
                    <span>The queue is empty. Good job!</span>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Right Column Widgets -->
      <div>
        <div class="stat-card accent-gold" style="margin-bottom: 2rem;">
          <div class="stat-icon"><i class="ph-fill ph-clock"></i></div>
          <div class="stat-title">Queue Total</div>
          <div class="stat-value"><?= $totalPending ?></div>
          <div class="stat-meta">
            <i class="ph ph-warning meta-icon text-accent"></i>
            <span class="text-accent">Pending accounts</span>
          </div>
        </div>

        <div style="background: var(--surface); border-left: 4px solid var(--crimson); padding: 1.5rem; border-radius: 0 var(--radius-sm) var(--radius-sm) 0; box-shadow: var(--shadow-sm);">
          <div style="font-size: 0.7rem; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; color: var(--crimson); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.4rem;">
            <i class="ph-fill ph-shield-check"></i> Policy Reminder
          </div>
          <p style="font-family: 'Georgia', serif; font-size: 0.95rem; line-height: 1.6; color: var(--text-dark);">
            Account activations must strictly verify that students are actively enrolled and using their @wmsu.edu.ph institutional email addresses.
          </p>
        </div>
      </div>
    </div>

  </main>

<?php
ob_start();
?>
<script>
function filterTable() {
  const q = document.getElementById('searchInput').value.toLowerCase();
  document.querySelectorAll('#reqTable tbody tr').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

// Auto-dismiss the flash message after 3 seconds
document.addEventListener('DOMContentLoaded', () => {
  const flash = document.getElementById('flash-message');
  if (flash) {
    setTimeout(() => {
      flash.style.opacity = '0';
      setTimeout(() => flash.remove(), 500); // Wait for CSS transition
    }, 3000);
  }
});
</script>
<?php
$extraScripts = ob_get_clean();
require_once __DIR__ . '/../includes/layout_bottom.php';
?>