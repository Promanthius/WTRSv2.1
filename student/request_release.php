<?php
require_once __DIR__ . '/../includes/session.php';
require_login(['student']);

$user = current_user();
$error = null;
$success = null;

// Check if student has an adviser
if (empty($user['adviser_id'])) {
    header('Location: index.php');
    exit;
}

// Check for existing pending release request
$check = $pdo->prepare("SELECT id FROM release_requests WHERE student_id = ? AND status = 'pending'");
$check->execute([$user['id']]);
$pendingRequest = $check->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$pendingRequest) {
    $reason = trim($_POST['reason'] ?? '');
    
    if (empty($reason)) {
        $error = "Please provide a reason for the release request.";
    } else {
        $ins = $pdo->prepare("INSERT INTO release_requests (student_id, adviser_id, reason) VALUES (?, ?, ?)");
        if ($ins->execute([$user['id'], $user['adviser_id'], $reason])) {
            // Notify the adviser
            $pdo->prepare("INSERT INTO notifications (recipient_user_id, sender_user_id, type, message) VALUES (?, ?, 'release_request', ?)")
                ->execute([$user['adviser_id'], $user['id'], "A student has requested to be released from your mentorship."]);
            
            $_SESSION['student_dash_flash'] = ['type' => 'success', 'message' => 'Your release request has been submitted to your adviser.'];
            header('Location: index.php');
            exit;
        } else {
            $error = "Failed to submit request. Please try again.";
        }
    }
}

// Fetch current adviser name
$advStmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$advStmt->execute([$user['adviser_id']]);
$adviser = $advStmt->fetch();

ob_start();
?>
<style>
    .form-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 2.5rem;
        max-width: 600px;
        margin: 0 auto;
        box-shadow: var(--shadow-sm);
    }
    .form-card h2 { font-family: var(--font-serif); margin-bottom: 0.5rem; color: var(--text-dark); }
    .form-card p { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2rem; }
    
    .input-group { margin-bottom: 1.5rem; }
    .input-label { display: block; font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
    .textarea-plain { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border); border-radius: var(--radius-sm); font-family: var(--font-base); font-size: 0.95rem; background: var(--off-white); resize: vertical; min-height: 120px; }
    .textarea-plain:focus { outline: none; border-color: var(--crimson); background: white; }
</style>
<?php
$extraCss = ob_get_clean();

$current_page = 'request_release.php';
require_once __DIR__ . '/../includes/layout_top.php';
require_once __DIR__ . '/../includes/layout_sidebar.php';
?>

<main class="main-content">
  <div class="page-header" style="margin-bottom: 3rem; justify-content: flex-start;">
    <a href="index.php" class="btn btn-secondary" style="text-decoration:none;">
      <i class="ph-bold ph-arrow-left"></i> Back to Dashboard
    </a>
  </div>

  <div class="form-card">
    <h2>Request Release</h2>
    <p>Submit a request to be released from the mentorship of <strong>Dr. <?= htmlspecialchars($adviser['last_name']) ?></strong>.</p>
    
    <?php if ($error): ?>
      <div class="alert alert-error" style="background:#FEE2E2; color:#991B1B; padding:1rem; border-radius:4px; margin-bottom:1.5rem; border:1px solid #FECACA;">
        <i class="ph-bold ph-warning-circle"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <?php if ($pendingRequest): ?>
      <div class="alert alert-warning" style="background:#FFFBEB; color:#92400E; padding:1.5rem; border-radius:4px; border:1px solid #FEF3C7; text-align:center;">
        <i class="ph-bold ph-hourglass-high" style="font-size: 2rem; display:block; margin-bottom:1rem;"></i>
        <strong style="display:block; margin-bottom:0.5rem;">Release Request Pending</strong>
        <p style="font-size:0.85rem; margin:0;">You have already submitted a release request. Please wait for your adviser to process it.</p>
      </div>
    <?php else: ?>
      <form method="POST">
          <div class="input-group">
              <label class="input-label">Reason for Request</label>
              <textarea name="reason" class="textarea-plain" placeholder="Briefly explain why you wish to be released (e.g., change of research topic, personal reasons)..." required></textarea>
          </div>
          
          <div style="background:#EFF6FF; padding:1rem; border-radius:4px; margin-bottom:1.5rem; font-size:0.85rem; color:#1E40AF; border:1px solid #DBEAFE;">
              <strong>Note:</strong> Once you submit this request, your adviser will be notified. You cannot apply for a new adviser until this request is approved or you are otherwise released.
          </div>

          <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.85rem; font-size: 0.95rem;">Submit Release Request</button>
      </form>
    <?php endif; ?>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
