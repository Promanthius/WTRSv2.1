<?php
require_once __DIR__ . '/../includes/session.php';
require_login(['adviser']);

$user = current_user();
$flash = ['type' => 'error', 'message' => 'Adviser invite codes are retired. Adviser registration is now direct using @wmsu.edu.ph email.'];

require_once __DIR__ . '/../includes/layout_top.php';
require_once __DIR__ . '/../includes/layout_sidebar.php';
?>

<main class="main-content">
  <div class="page-header">
    <div class="page-title">
      <h1>Adviser <span>Invites Retired</span></h1>
      <p>This endpoint is intentionally retained only to indicate invite flow retirement.</p>
    </div>
  </div>

  <?php if ($flash): ?>
    <div style="margin-bottom: 2rem; padding: 1rem 1.5rem; border-radius: var(--radius-sm); font-weight:600; font-size: 0.85rem; color: #fff; background: <?= $flash['type'] === 'success' ? '#065F46' : '#991B1B' ?>;">
      <?= htmlspecialchars($flash['message']) ?>
    </div>
  <?php endif; ?>

  <div class="table-container">
    <div class="table-toolbar">
      <div>
        <h3>Current Registration Policy</h3>
        <p>Both students and advisers self-register using valid `@wmsu.edu.ph` addresses.</p>
      </div>
    </div>
    <div style="padding: 1.5rem; color: var(--text-muted); font-size: 0.95rem;">
      Invite-code generation is disabled for this deployment.
      Use `auth/register.php` for direct registration and manage accounts in `admin/users.php` (adviser maintenance page).
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
