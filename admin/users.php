<?php
require_once __DIR__ . '/../includes/session.php';
require_login(['adviser']);

$user = current_user();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $action = $_POST['action'] ?? '';

    if ($userId <= 0 || !in_array($action, ['activate', 'deactivate'], true)) {
        $flash = ['type' => 'error', 'message' => 'Invalid action.'];
    } else {
        $newStatus = $action === 'activate' ? 'active' : 'inactive';
        $stmt = $pdo->prepare('UPDATE users SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $newStatus, 'id' => $userId]);

        if ($stmt->rowCount() > 0) {
            $flash = ['type' => 'success', 'message' => 'User status updated successfully.'];
        } else {
            $flash = ['type' => 'error', 'message' => 'Unable to update user status (user may not exist).'];
        }
    }
}

$statsStmt = $pdo->query("SELECT
    SUM(status = 'active') AS total_active,
    SUM(status = 'pending') AS total_pending,
    SUM(status = 'inactive') AS total_inactive,
    COUNT(*) AS total_users
FROM users");
$stats = $statsStmt->fetch();

$usersStmt = $pdo->query("SELECT id, first_name, last_name, email, role, college, status, created_at FROM users ORDER BY FIELD(status, 'pending', 'active', 'inactive'), last_name ASC");
$users = $usersStmt->fetchAll();

require_once __DIR__ . '/../includes/layout_top.php';
require_once __DIR__ . '/../includes/layout_sidebar.php';
?>

  <main class="main-content">
    
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-title">
        <h1>User Management</h1>
        <p>Oversee academic profiles and access permissions across colleges.</p>
      </div>
      <div class="page-header-actions">
        <button class="btn btn-secondary">
          <i class="ph-bold ph-download-simple"></i> Export List
        </button>
        <button class="btn btn-primary" style="background:var(--gold); color:#fff;">
          <i class="ph-bold ph-user-plus"></i> Add New User
        </button>
      </div>
    </div>

    <?php if ($flash): ?>
      <div style="margin-bottom: 2rem; padding: 1rem 1.5rem; border-radius: var(--radius-sm); font-weight:600; font-size: 0.85rem; color: #fff; background: <?= $flash['type'] === 'success' ? '#065F46' : '#991B1B' ?>;">
        <i class="ph-bold <?= $flash['type'] === 'success' ? 'ph-check-circle' : 'ph-warning-circle' ?>" style="margin-right:0.5rem; font-size:1.1rem; vertical-align:middle;"></i>
        <?= htmlspecialchars($flash['message']) ?>
      </div>
    <?php endif; ?>

    <!-- Stat Cards -->
    <div class="stat-cards">
      <div class="stat-card accent-teal">
        <div class="stat-icon"><i class="ph-fill ph-check-circle"></i></div>
        <div class="stat-title">Total Active</div>
        <div class="stat-value"><?= intval($stats['total_active']) ?></div>
        <div class="stat-meta">
          <i class="ph ph-trend-up meta-icon"></i>
          <span>Verified accounts</span>
        </div>
      </div>

      <div class="stat-card accent-gold">
        <div class="stat-icon"><i class="ph-fill ph-clock-user"></i></div>
        <div class="stat-title">Pending Approvals</div>
        <div class="stat-value"><?= intval($stats['total_pending']) ?></div>
        <?php if ($stats['total_pending'] > 0): ?>
          <div class="stat-meta">
            <i class="ph ph-warning meta-icon text-accent"></i>
            <span class="text-accent">Requires attention</span>
          </div>
        <?php else: ?>
          <div class="stat-meta">
            <i class="ph ph-check meta-icon text-success"></i>
            <span class="text-success">All caught up</span>
          </div>
        <?php endif; ?>
      </div>

      <div class="stat-card accent-red">
        <div class="stat-icon"><i class="ph-fill ph-users"></i></div>
        <div class="stat-title">Total Users</div>
        <div class="stat-value"><?= intval($stats['total_users']) ?></div>
        <div class="stat-meta">
          <i class="ph ph-info meta-icon"></i>
          <span>Including inactive accounts</span>
        </div>
      </div>
    </div>

    <!-- Table Container -->
    <div class="table-container">
      <div class="table-toolbar">
        <div>
          <h3>System Users</h3>
          <p>Complete directory of all registered individuals</p>
        </div>
        <div class="search-input-wrap">
          <i class="ph ph-magnifying-glass"></i>
          <input type="text" placeholder="Search users by name..." id="searchInput" oninput="filterTable()">
        </div>
      </div>

      <table id="usersTable">
        <thead>
          <tr>
            <th>Full Name</th>
            <th>Email &amp; Role</th>
            <th>College</th>
            <th>Status</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($users) > 0): ?>
            <?php foreach ($users as $u): ?>
            <tr>
              <td>
                <div class="submitter-cell">
                  <div class="avatar" style="background:var(--crimson);">
                    <?= htmlspecialchars(strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1))) ?>
                  </div>
                  <div>
                    <div class="submitter-name"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></div>
                    <div class="submitter-college">ID: <?= htmlspecialchars($u['id']) ?></div>
                  </div>
                </div>
              </td>
              <td>
                <div style="font-weight:700; font-size:0.8rem; color:var(--text-dark); margin-bottom:0.2rem;">
                  <?= htmlspecialchars($u['email']) ?>
                </div>
                <span class="tag" style="background:var(--gold-faint); color:var(--gold);">
                  <?= strtoupper(htmlspecialchars($u['role'])) ?>
                </span>
              </td>
              <td>
                <span style="font-size:0.8rem; color:var(--text-muted); font-weight:600;">
                  <?= htmlspecialchars($u['college'] ?? '—') ?>
                </span>
              </td>
              <td>
                <?php if ($u['status'] === 'active'): ?>
                  <span class="badge badge-approved"><span class="dot dot-approved"></span> Active</span>
                <?php elseif ($u['status'] === 'pending'): ?>
                  <span class="badge badge-pending"><span class="dot dot-pending"></span> Pending</span>
                <?php else: ?>
                  <span class="badge badge-revision"><span class="dot dot-revision"></span> Inactive</span>
                <?php endif; ?>
              </td>
              <td style="text-align: right;">
                <?php if ($u['status'] === 'pending'): ?>
                  <form method="post" style="display:inline-block;">
                    <input type="hidden" name="user_id" value="<?= intval($u['id']) ?>">
                    <input type="hidden" name="action" value="activate">
                    <button type="submit" class="btn btn-primary" style="padding:0.3rem 0.6rem; font-size:0.75rem;">Approve</button>
                  </form>
                  <form method="post" style="display:inline-block; margin-left:0.25rem;">
                    <input type="hidden" name="user_id" value="<?= intval($u['id']) ?>">
                    <input type="hidden" name="action" value="deactivate">
                    <button type="submit" class="btn btn-secondary" style="padding:0.3rem 0.6rem; font-size:0.75rem;">Reject</button>
                  </form>
                <?php else: ?>
                  <form method="post" style="display:inline-block;">
                    <input type="hidden" name="user_id" value="<?= intval($u['id']) ?>">
                    <input type="hidden" name="action" value="<?= $u['status'] === 'active' ? 'deactivate' : 'activate' ?>">
                    <?php if ($u['status'] === 'active'): ?>
                      <button type="submit" class="btn-action-outline" style="border-color:#DC2626; color:#DC2626; padding:0.3rem 0.6rem; font-size:0.75rem;">Deactivate</button>
                    <?php else: ?>
                      <button type="submit" class="btn-action-outline" style="border-color:#059669; color:#059669; padding:0.3rem 0.6rem; font-size:0.75rem;">Activate</button>
                    <?php endif; ?>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5">
                <div class="empty-state">
                  <i class="ph ph-users"></i>
                  <p>No users found</p>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </main>

<?php
ob_start();
?>
<script>
function filterTable() {
  const q = document.getElementById('searchInput').value.toLowerCase();
  document.querySelectorAll('#usersTable tbody tr').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
</script>
<?php
$extraScripts = ob_get_clean();
require_once __DIR__ . '/../includes/layout_bottom.php';
?>
