<?php
require_once __DIR__ . '/../includes/session.php';
require_login(['adviser']);

$user = current_user();

$statsStmt = $pdo->query("SELECT
    SUM(role = 'student') AS total_students,
    SUM(role = 'adviser') AS total_advisers,
    SUM(status = 'pending') AS total_pending_users,
    SUM(status = 'active') AS total_active_users
FROM users");
$userStats = $statsStmt->fetch();

$thesisStatsStmt = $pdo->query("SELECT
    SUM(status = 'pending_review') AS pending_reviews,
    SUM(status = 'approved') AS approved_theses,
    COUNT(*) AS total_theses
FROM theses");
$thesisStats = $thesisStatsStmt->fetch();

$recentPendingStmt = $pdo->query("SELECT first_name, last_name, email, role, created_at
FROM users
WHERE status = 'pending'
ORDER BY created_at DESC
LIMIT 5");
$recentPending = $recentPendingStmt->fetchAll();

require_once __DIR__ . '/../includes/layout_top.php';
require_once __DIR__ . '/../includes/layout_sidebar.php';
?>

<main class="main-content">
  <div class="page-header">
    <div class="page-title">
      <h1>System <span>Dashboard</span></h1>
      <p>Quick overview of user onboarding and repository activity.</p>
    </div>
  </div>

  <div class="stat-cards">
    <div class="stat-card accent-red">
      <div class="stat-icon"><i class="ph-fill ph-users-three"></i></div>
      <div class="stat-title">Total Users</div>
      <div class="stat-value"><?= intval($userStats['total_active_users']) + intval($userStats['total_pending_users']) ?></div>
      <div class="stat-meta">
        <span><?= intval($userStats['total_students']) ?> students, <?= intval($userStats['total_advisers']) ?> advisers</span>
      </div>
    </div>

    <div class="stat-card accent-gold">
      <div class="stat-icon"><i class="ph-fill ph-clock-user"></i></div>
      <div class="stat-title">Pending Activations</div>
      <div class="stat-value"><?= intval($userStats['total_pending_users']) ?></div>
      <div class="stat-meta"><span>Needs adviser review</span></div>
    </div>

    <div class="stat-card accent-teal">
      <div class="stat-icon"><i class="ph-fill ph-files"></i></div>
      <div class="stat-title">In Review</div>
      <div class="stat-value"><?= intval($thesisStats['pending_reviews']) ?></div>
      <div class="stat-meta"><span>Thesis submissions awaiting review</span></div>
    </div>

    <div class="stat-card accent-red">
      <div class="stat-icon"><i class="ph-fill ph-check-circle"></i></div>
      <div class="stat-title">Approved Theses</div>
      <div class="stat-value"><?= intval($thesisStats['approved_theses']) ?></div>
      <div class="stat-meta"><span><?= intval($thesisStats['total_theses']) ?> total submissions</span></div>
    </div>
  </div>

  <div class="table-container">
    <div class="table-toolbar">
      <div>
        <h3>Recent Pending Accounts</h3>
        <p>Latest registrations waiting for activation</p>
      </div>
      <div style="display:flex; gap:0.5rem;">
        <a href="<?= BASE_URL ?>admin/activations.php" class="btn btn-primary">Open Activations</a>
        <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-secondary">Manage Users</a>
        <a href="<?= BASE_URL ?>admin/adviser_invites.php" class="btn btn-secondary">Invite Policy</a>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Requested At</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($recentPending) > 0): ?>
          <?php foreach ($recentPending as $rp): ?>
            <tr>
              <td><?= htmlspecialchars($rp['first_name'] . ' ' . $rp['last_name']) ?></td>
              <td><?= htmlspecialchars($rp['email']) ?></td>
              <td><?= strtoupper(htmlspecialchars($rp['role'])) ?></td>
              <td><?= date('M j, Y h:i A', strtotime($rp['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="4">
              <div class="empty-state">
                <i class="ph ph-check-circle"></i>
                <p>No pending accounts</p>
                <span>All newly registered users are already processed.</span>
              </div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
