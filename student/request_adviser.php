<?php
require_once __DIR__ . '/../includes/session.php';
require_login(['student']);

$user = current_user();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adviser_id = (int)($_POST['adviser_id'] ?? 0);
    
    if ($adviser_id > 0) {
        // Check if there's already a pending request to prevent spam
        $chkStmt = $pdo->prepare("SELECT id FROM adviser_requests WHERE student_id = ? AND status = 'pending'");
        $chkStmt->execute([$user['id']]);
        if ($chkStmt->fetch()) {
            $error = "You already have a pending adviser request. Please wait for it to be processed.";
        } else {
            // Verify adviser is valid and not full
            $advStmt = $pdo->prepare("SELECT id, max_advisees, (SELECT COUNT(*) FROM users WHERE adviser_id = ?) as current_advisees FROM users WHERE id = ? AND role = 'adviser' AND status = 'active'");
            $advStmt->execute([$adviser_id, $adviser_id]);
            $advData = $advStmt->fetch();
            
            if (!$advData) {
                $error = "Selected adviser is invalid.";
            } elseif ((int)$advData['current_advisees'] >= (int)$advData['max_advisees']) {
                $error = "The selected adviser has reached their maximum capacity.";
            } else {
                // Create new request
                $pdo->prepare("INSERT INTO adviser_requests (student_id, adviser_id, status) VALUES (?, ?, 'pending')")->execute([$user['id'], $adviser_id]);
                $_SESSION['student_dash_flash'] = ['type' => 'success', 'message' => 'Your request for a new adviser has been submitted.'];
                header('Location: index.php');
                exit;
            }
        }
    } else {
        $error = "Please select an adviser.";
    }
}

// Fetch active advisers for the student's college
$stmt = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name, u.max_advisees,
           (SELECT COUNT(*) FROM users WHERE adviser_id = u.id) as current_advisees
    FROM users u
    WHERE u.role = 'adviser' AND u.status = 'active' AND u.college = ?
    ORDER BY u.last_name ASC
");
$stmt->execute([$user['college']]);
$advisers = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    .select-plain { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border); border-radius: var(--radius-sm); font-family: var(--font-base); font-size: 0.95rem; background: var(--off-white); }
    .select-plain:focus { outline: none; border-color: var(--crimson); background: white; }
</style>
<?php
$extraCss = ob_get_clean();

$current_page = 'request_adviser.php';
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
    <h2>Select an Adviser</h2>
    <p>Choose an available faculty member from <strong><?= htmlspecialchars($user['college']) ?></strong>.</p>
    
    <?php if ($error): ?>
      <div class="alert alert-error" style="background:#FEE2E2; color:#991B1B; padding:1rem; border-radius:4px; margin-bottom:1.5rem; border:1px solid #FECACA;">
        <i class="ph-bold ph-warning-circle"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST">
        <div class="input-group">
            <label class="input-label">Available Advisers</label>
            <select name="adviser_id" class="select-plain" required>
                <option value="">Select a preferred adviser...</option>
                <?php foreach ($advisers as $adv): ?>
                    <?php 
                    $isFull = (int)$adv['current_advisees'] >= (int)$adv['max_advisees'];
                    $text = 'Dr. ' . $adv['first_name'] . ' ' . $adv['last_name'] . ' (' . $adv['current_advisees'] . '/' . $adv['max_advisees'] . ' Advisees)';
                    if ($isFull) $text .= ' - FULL';
                    ?>
                    <option value="<?= $adv['id'] ?>" <?= $isFull ? 'disabled' : '' ?>><?= htmlspecialchars($text) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="background:#FFFBEB; padding:1rem; border-radius:4px; margin-bottom:1.5rem; font-size:0.85rem; color:#92400E; border:1px solid #FEF3C7;">
            <strong>Note:</strong> Submitting this request will place it in a pending state. If you currently have an adviser, they will remain your adviser until the new request is approved.
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.85rem; font-size: 0.95rem;">Submit Request</button>
    </form>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
