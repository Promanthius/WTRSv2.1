<?php
require_once __DIR__ . '/../includes/session.php';
require_login(['student']);

$user = current_user();
$thesis_id = $_GET['id'] ?? null;

if (!$thesis_id) {
    header('Location: index.php');
    exit;
}

// Fetch the specific thesis
$stmt = $pdo->prepare("SELECT t.*, u.first_name as adviser_first, u.last_name as adviser_last 
                       FROM theses t 
                       LEFT JOIN users u ON t.adviser_id = u.id 
                       WHERE t.id = :id AND t.author_id = :author_id");
$stmt->execute(['id' => $thesis_id, 'author_id' => $user['id']]);
$thesis = $stmt->fetch();

if (!$thesis) {
    header('Location: index.php');
    exit;
}

// Fetch the latest version for version incrementing and feedback
$vStmt = $pdo->prepare("SELECT * FROM thesis_versions WHERE thesis_id = :thesis_id ORDER BY id DESC LIMIT 1");
$vStmt->execute(['thesis_id' => $thesis_id]);
$latestVersion = $vStmt->fetch();

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['manuscript']) || $_FILES['manuscript']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please upload a valid PDF file.";
    } else {
        $file = $_FILES['manuscript'];
        $fileSize = $file['size'];
        $fileTmp = $file['tmp_name'];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $fileType = finfo_file($finfo, $fileTmp);
        finfo_close($finfo);
        
        if ($fileType !== 'application/pdf') {
            $error = "Only PDF files are allowed.";
        } elseif ($fileSize > 20 * 1024 * 1024) {
            $error = "File size exceeds the 20MB limit.";
        } else {
            $versionNum = "1.0";
            if ($latestVersion) {
                $versionNum = number_format(floatval($latestVersion['version_number']) + 0.1, 1);
            }
            
            $uploadDir = __DIR__ . '/../public/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $fileName = uniqid('thesis_') . '.pdf';
            $destination = $uploadDir . $fileName;
            
            if (move_uploaded_file($fileTmp, $destination)) {
                $pdo->beginTransaction();
                try {
                    // Update thesis status
                    $updStmt = $pdo->prepare("UPDATE theses SET status = 'pending_review', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $updStmt->execute([$thesis_id]);
                    
                    // Insert new version
                    $insVStmt = $pdo->prepare("INSERT INTO thesis_versions (thesis_id, version_number, file_path, file_size, status) VALUES (?, ?, ?, ?, 'pending')");
                    $insVStmt->execute([$thesis_id, $versionNum, $fileName, $fileSize]);
                    
                    $pdo->commit();
                    $_SESSION['student_dash_flash'] = [
                        'type' => 'success',
                        'message' => 'Revision submitted successfully.',
                    ];
                    header('Location: ' . BASE_URL . 'student/tracker.php?id=' . (int) $thesis_id, true, 303);
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Database error: " . $e->getMessage();
                }
            } else {
                $error = "Failed to move the uploaded file. Check directory permissions.";
            }
        }
    }
}

// Custom CSS for Resubmit View
ob_start();
?>
<style>
  .resubmit-card { background: white; border-radius: var(--radius); border: 1px solid var(--border); padding: 4rem; box-shadow: var(--shadow-md); max-width: 900px; margin: 0 auto; }
  .resubmit-header { border-bottom: 2px solid var(--off-white); padding-bottom: 2.5rem; margin-bottom: 3.5rem; text-align: center; }
  .resubmit-header h1 { font-family: var(--font-serif); font-size: 2.5rem; margin-bottom: 1rem; color: var(--text-dark); }
  .resubmit-header p { color: var(--text-muted); font-size: 1.1rem; }

  .form-section { margin-bottom: 3rem; }
  .form-label { display: block; font-size: 0.7rem; font-weight: 800; color: var(--text-muted); letter-spacing: 0.15em; text-transform: uppercase; margin-bottom: 1.25rem; }
  
  .read-only-display { background: var(--off-white); padding: 1.5rem; border-radius: var(--radius-sm); border: 1px solid var(--border); color: var(--text-dark); font-weight: 700; font-size: 1.1rem; }
  
  .feedback-display { background: #FFF9E6; border-left: 4px solid var(--gold); padding: 2rem; border-radius: 0 var(--radius-sm) var(--radius-sm) 0; }
  .feedback-meta { font-size: 0.7rem; font-weight: 800; color: var(--gold); letter-spacing: 0.1em; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; }
  .feedback-text { font-family: 'Georgia', serif; font-style: italic; font-size: 1.1rem; color: var(--text-dark); line-height: 1.6; }

  .dropzone-refined { border: 2px dashed var(--border-strong); border-radius: var(--radius); padding: 5rem 2.5rem; text-align: center; background: var(--off-white); transition: all 0.2s; cursor: pointer; position: relative; }
  .dropzone-refined:hover { border-color: var(--crimson); background: var(--crimson-faint); }
  .dropzone-icon { font-size: 4rem; color: var(--crimson); margin-bottom: 1.5rem; opacity: 0.8; }
</style>
<?php
$extraCss = ob_get_clean();

require_once __DIR__ . '/../includes/layout_top.php';
require_once __DIR__ . '/../includes/layout_sidebar.php';
?>

  <main class="main-content">
    
    <!-- Breadcrumb -->
    <nav style="margin-bottom: 2.5rem; font-size: 0.7rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
       <a href="index.php" style="color:var(--text-muted); text-decoration:none;">DASHBOARD</a>
       <i class="ph ph-caret-right" style="margin: 0 0.5rem; opacity:0.5;"></i>
       <a href="tracker.php?id=<?= $thesis_id ?>" style="color:var(--text-muted); text-decoration:none;">SUBMISSION DETAIL</a>
       <i class="ph ph-caret-right" style="margin: 0 0.5rem; opacity:0.5;"></i>
       <span style="color:var(--crimson);">REVISION SUBMISSION</span>
    </nav>

    <?php if ($error): ?>
      <div style="max-width:900px; margin: 0 auto 2rem; background:#991B1B; color:white; padding:1.25rem 2rem; border-radius:var(--radius-sm); font-weight:700;">
        <i class="ph-bold ph-warning-circle" style="margin-right:0.5rem;"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div style="max-width:900px; margin: 0 auto 2rem; background:#065F46; color:white; padding:1.25rem 2rem; border-radius:var(--radius-sm); font-weight:700;">
        <i class="ph-bold ph-check-circle" style="margin-right:0.5rem;"></i> <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>

    <form id="resubmit-form" action="" method="POST" enctype="multipart/form-data" class="resubmit-card">
      
      <header class="resubmit-header">
         <h1>Iterative Refinement</h1>
         <p>Submitting changes to <strong><?= htmlspecialchars($thesis['thesis_code']) ?></strong> as Version <?= htmlspecialchars(isset($latestVersion['version_number']) ? number_format(floatval($latestVersion['version_number']) + 0.1, 1) : "1.0") ?></p>
      </header>

      <!-- Feedback -->
      <?php if ($latestVersion && !empty($latestVersion['feedback'])): ?>
      <div class="form-section">
         <span class="form-label">Active Faculty Feedback</span>
         <div class="feedback-display">
            <div class="feedback-meta"><i class="ph-fill ph-chat-centered-text"></i> DR. <?= strtoupper(htmlspecialchars($thesis['adviser_last'])) ?></div>
            <div class="feedback-text">"<?= nl2br(htmlspecialchars($latestVersion['feedback'])) ?>"</div>
         </div>
      </div>
      <?php endif; ?>

      <!-- Manuscript Action -->
      <div class="form-section">
         <span class="form-label">Updated Manuscript Artifact (PDF)</span>
         <div class="dropzone-refined" onclick="document.getElementById('file-field').click()">
            <div class="dropzone-icon"><i class="ph-fill ph-cloud-arrow-up"></i></div>
            <h4 id="file-label" style="font-family: var(--font-serif); font-size: 1.25rem; font-weight: 800; margin-bottom: 0.5rem; color: var(--text-dark);">Initialize Payload Selection</h4>
            <p style="font-size: 0.9rem; color: var(--text-muted);">or drag and drop verified PDF manuscript here</p>
            <input type="file" name="manuscript" id="file-field" required accept="application/pdf" style="display:none;" onchange="document.getElementById('file-label').innerText = this.files[0].name">
         </div>
      </div>

      <!-- Actions -->
      <div style="display: flex; flex-direction: column; gap: 1rem; margin-top: 4rem;">
         <a href="tracker.php?id=<?= $thesis_id ?>" class="btn btn-secondary" style="width: 100%; justify-content: center; text-decoration: none; padding: 1.25rem;">CANCEL REFINEMENT</a>
         <button type="button" id="confirmRevisionBtn" class="btn btn-primary" disabled style="width: 100%; justify-content: center; padding: 1.25rem; font-weight: 800; font-size: 1rem;">
            <i class="ph-fill ph-paper-plane-tilt"></i> CONFIRM REVISION SUBMISSION
         </button>
         <p id="confirmHint" style="margin:0; font-size:0.9rem; color:var(--text-muted);">Select a PDF and click Confirm Revision Submission to finalize.</p>
      </div>

    </form>

  </main>

  <script>
    (function () {
      const fileField = document.getElementById('file-field');
      const confirmBtn = document.getElementById('confirmRevisionBtn');
      const resubmitForm = document.getElementById('resubmit-form');
      const fileLabel = document.getElementById('file-label');

      fileField.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (!file) {
          confirmBtn.disabled = true;
          fileLabel.textContent = 'Initialize Payload Selection';
          return;
        }

        confirmBtn.disabled = false;
        fileLabel.textContent = file.name;
      });

      confirmBtn.addEventListener('click', function () {
        const file = fileField.files && fileField.files[0];
        if (!file) {
          alert('Please select a PDF file before confirming the revision.');
          return;
        }

        const message = `Confirm revision upload for file:\n\n${file.name}\n\nThis will submit the new version to your adviser for review.`;
        if (confirm(message)) {
          resubmitForm.submit();
        }
      });
    })();
  </script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
