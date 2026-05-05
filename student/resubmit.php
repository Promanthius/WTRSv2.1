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
  .resubmit-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    padding: 4rem;
    box-shadow: var(--shadow-md);
    max-width: 900px;
    margin: 0 auto;
  }

  .resubmit-header {
    border-bottom: 2px solid var(--off-white);
    padding-bottom: 2.5rem;
    margin-bottom: 3.5rem;
  }

  .form-section {
    margin-bottom: 3rem;
  }

  .form-label {
    display: block;
    font-size: 0.7rem;
    font-weight: 800;
    color: var(--text-muted);
    letter-spacing: 0.15em;
    text-transform: uppercase;
    margin-bottom: 1.25rem;
  }

  .feedback-display {
    background: #FFF9E6;
    border-left: 4px solid var(--gold);
    padding: 2rem;
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
  }

  .feedback-meta {
    font-size: 0.7rem;
    font-weight: 800;
    color: var(--gold);
    letter-spacing: 0.1em;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
  }

  .feedback-text {
    font-family: 'Georgia', serif;
    font-style: italic;
    font-size: 1.1rem;
    color: var(--text-dark);
    line-height: 1.6;
  }

  .dropzone-refined {
    border: 2px dashed var(--border-strong);
    border-radius: var(--radius);
    padding: 5rem 2.5rem;
    text-align: center;
    background: var(--off-white);
    transition: all 0.2s;
    cursor: pointer;
    position: relative;
  }

  .dropzone-refined:hover {
    border-color: var(--crimson);
    background: var(--crimson-faint);
  }

  .dropzone-icon {
    font-size: 4rem;
    color: var(--crimson);
    margin-bottom: 1.5rem;
    opacity: 0.8;
  }

  .detailed-info {
    display: none;
    background: var(--off-white);
    padding: 1.5rem;
    border-radius: var(--radius-sm);
    font-size: 0.85rem;
    border-left: 3px solid var(--crimson);
  }
</style>
<?php
$extraCss = ob_get_clean();

require_once __DIR__ . '/../includes/layout_top.php';
require_once __DIR__ . '/../includes/layout_sidebar.php';
?>

<main class="main-content">

  <!-- Breadcrumb -->
  <nav
    style="margin-bottom: 2.5rem; font-size: 0.7rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
    <a href="index.php" style="color:var(--text-muted); text-decoration:none;">DASHBOARD</a>
    <i class="ph ph-caret-right" style="margin: 0 0.5rem; opacity:0.5;"></i>
    <a href="tracker.php?id=<?= $thesis_id ?>" style="color:var(--text-muted); text-decoration:none;">TRACKER</a>
    <i class="ph ph-caret-right" style="margin: 0 0.5rem; opacity:0.5;"></i>
    <span style="color:var(--crimson);">REVISION</span>
  </nav>

  <?php if ($error): ?>
    <div style="max-width:900px; margin: 0 auto 2rem; background:#991B1B; color:white; padding:1.25rem 2rem; border-radius:var(--radius-sm); font-weight:700;">
      <i class="ph-bold ph-warning-circle" style="margin-right:0.5rem;"></i> <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <form action="" method="POST" enctype="multipart/form-data" class="resubmit-card">

    <header class="resubmit-header">
       <span class="type-label">Drafting Revision Artifact</span>
       <h1 class="thesis-title-big" style="font-family: var(--font-serif); font-size: 2rem; margin: 0.5rem 0;"><?= htmlspecialchars($thesis['title']) ?></h1>
       <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 700;">
         TRACKING CODE: <span style="color: var(--crimson);"><?= htmlspecialchars($thesis['thesis_code']) ?></span> &bull; 
         VERSION: <span style="color: var(--text-dark);"><?= htmlspecialchars(isset($latestVersion['version_number']) ? number_format(floatval($latestVersion['version_number']) + 0.1, 1) : "1.0") ?></span>
       </div>
    </header>

    <div onclick="toggleDetails('proc-info')" style="cursor: pointer; font-size: 0.7rem; font-weight: 800; color: var(--crimson); display: flex; align-items: center; gap: 0.4rem; margin-bottom: 2rem;">
       <i class="ph ph-info"></i> SHOW DETAILED PROCESS DATA
    </div>
    <div id="proc-info" class="detailed-info" style="margin-bottom: 3rem;">
       <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
         <div><strong>Registry Created:</strong><br><?= date('F j, Y, g:i A', strtotime($thesis['created_at'])) ?></div>
         <div><strong>Last Modified:</strong><br><?= date('F j, Y, g:i A', strtotime($thesis['updated_at'])) ?></div>
         <div><strong>Latest Version:</strong><br>v<?= htmlspecialchars($latestVersion['version_number'] ?? '1.0') ?></div>
         <div><strong>Author Reference:</strong><br><?= htmlspecialchars($user['last_name'] . ', ' . $user['first_name']) ?></div>
       </div>
    </div>

    <!-- Feedback -->
    <?php if ($latestVersion && !empty($latestVersion['feedback'])): ?>
      <div class="form-section">
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1.25rem;">
          <span class="form-label" style="margin: 0;">Active Faculty Feedback</span>
          <button type="button" id="previewBtn" class="btn btn-secondary" style="padding: 0.45rem 0.85rem; font-size: 0.75rem; font-weight: 800; display: flex; align-items: center; gap: 0.4rem;">
            <i class="ph ph-eye"></i> PREVIEW CURRENT
          </button>
        </div>
        <div class="feedback-display">
          <div class="feedback-meta"><i class="ph-fill ph-chat-centered-text"></i> DR. <?= strtoupper(htmlspecialchars($thesis['adviser_last'])) ?></div>
          <div class="feedback-text">"<?= nl2br(htmlspecialchars($latestVersion['feedback'])) ?>"</div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Manuscript Action -->
    <div class="form-section">
      <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1.25rem;">
        <span class="form-label" style="margin: 0;">Updated Manuscript Artifact (PDF)</span>
        <button type="button" id="previewSelectedBtn" class="btn btn-secondary" style="display: none; padding: 0.45rem 0.85rem; font-size: 0.75rem; font-weight: 800; border-color: var(--crimson); color: var(--crimson);">
          <i class="ph ph-file-search"></i> PREVIEW SELECTED
        </button>
      </div>
      <div class="dropzone-refined" onclick="document.getElementById('file-field').click()">
        <div class="dropzone-icon"><i class="ph-fill ph-cloud-arrow-up"></i></div>
        <h4 id="file-label" style="font-family: var(--font-serif); font-size: 1.25rem; font-weight: 800; margin-bottom: 0.5rem; color: var(--text-dark);">upload file</h4>
        <p style="font-size: 0.9rem; color: var(--text-muted);">or drag and drop verified PDF manuscript here</p>
        <input type="file" name="manuscript" id="file-field" required accept="application/pdf" style="display:none;" onchange="handleFileSelect(this)">
      </div>
    </div>

    <!-- Actions -->
    <div style="display: flex; gap: 1.5rem; margin-top: 4rem;">
      <a href="tracker.php?id=<?= $thesis_id ?>" class="btn btn-secondary" style="flex:1; justify-content: center; text-decoration: none; padding: 1.25rem;">CANCEL </a>
      <button type="submit" class="btn btn-primary" style="flex:2; justify-content: center; padding: 1.25rem; font-weight: 800; font-size: 1rem;">
        <i class="ph-fill ph-paper-plane-tilt"></i> SUBMIT REVISION
      </button>
    </div>

  </form>

  <!-- PDF Preview Modal -->
  <div id="pdfPreviewModal" class="pdf-preview-modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); align-items: center; justify-content: center;">
    <div class="pdf-preview-content" style="background: white; border-radius: var(--radius-lg); width: 90%; height: 90vh; max-width: 1000px; display: flex; flex-direction: column; box-shadow: var(--shadow-lg);">
      <div class="pdf-preview-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; border-bottom: 1px solid var(--border); flex-shrink: 0;">
        <h3 id="modalTitle" style="margin: 0; font-size: 1.2rem; color: var(--text-dark);">Manuscript Preview</h3>
        <button type="button" class="pdf-preview-close" onclick="closeModal()" style="background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--text-muted);">&times;</button>
      </div>
      <div class="pdf-preview-viewer" style="flex: 1; background: #f5f5f5;">
        <iframe id="pdfFrame" src="" type="application/pdf" style="width: 100%; height: 100%; border: none;"></iframe>
      </div>
    </div>
  </div>

  <script>
    let selectedFileUrl = null;

    function toggleDetails(id) {
      const el = document.getElementById(id);
      el.style.display = el.style.display === 'none' ? 'block' : 'none';
    }

    function handleFileSelect(input) {
      const label = document.getElementById('file-label');
      const previewBtn = document.getElementById('previewSelectedBtn');
      if (input.files && input.files[0]) {
        const file = input.files[0];
        label.innerText = file.name;
        previewBtn.style.display = 'flex';
        if (selectedFileUrl) URL.revokeObjectURL(selectedFileUrl);
        selectedFileUrl = URL.createObjectURL(file);
      }
    }

    const modal = document.getElementById('pdfPreviewModal');
    const frame = document.getElementById('pdfFrame');
    const title = document.getElementById('modalTitle');

    document.getElementById('previewBtn')?.addEventListener('click', () => {
      title.innerText = "Current Version Preview (v<?= htmlspecialchars($latestVersion['version_number'] ?? '1.0') ?>)";
      frame.src = '../public/uploads/<?= htmlspecialchars($latestVersion['file_path'] ?? '') ?>';
      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    });

    document.getElementById('previewSelectedBtn')?.addEventListener('click', () => {
      if (selectedFileUrl) {
        title.innerText = "New Selection Preview";
        frame.src = selectedFileUrl;
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
      }
    });

    function closeModal() {
      modal.style.display = 'none';
      document.body.style.overflow = 'auto';
      frame.src = "";
    }

    window.onclick = (e) => { if (e.target == modal) closeModal(); };
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
  </script>

</main>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>