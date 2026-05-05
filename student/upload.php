<?php
require_once __DIR__ . '/../includes/session.php';
require_login(['student']);

$user = current_user();
$error = null;
$success = null;

// Fetch advisers for the dropdown
$advStmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE role = 'adviser' AND status = 'active'");
$advStmt->execute();
$advisers = $advStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = trim($_POST['title'] ?? '');
  $abstract = trim($_POST['abstract'] ?? '');
  $adviser_id = $_POST['adviser_id'] ?? null;
  $submission_year = (int) ($_POST['submission_year'] ?? date('Y'));
  $author_name = trim($_POST['author_name'] ?? '');
  $thesis_type = $_POST['thesis_type'] ?? 'solo';
  $co_authors_list = $_POST['co_authors'] ?? [];
  $co_authors_str = ($thesis_type === 'group' && !empty($co_authors_list)) ? implode(', ', array_filter(array_map('trim', $co_authors_list))) : null;

  error_log("WTRS DEBUG: Upload attempt started. Title: $title, Adviser ID: $adviser_id, Type: $thesis_type");

  // Validate thesis_type
  if (!in_array($thesis_type, ['solo', 'group'])) {
    $error = "Invalid thesis type.";
  } elseif (empty($title) || empty($abstract) || empty($adviser_id) || empty($author_name)) {
    $error = "Please fill in all required metadata fields.";
  } elseif ($thesis_type === 'group' && empty($co_authors_str)) {
    $error = "For group theses, please add at least one co-author.";
  } elseif (!isset($_FILES['manuscript']) || $_FILES['manuscript']['error'] !== UPLOAD_ERR_OK) {
    $error = "Please upload a valid PDF file. Error Code: " . ($_FILES['manuscript']['error'] ?? 'N/A');
    error_log("WTRS DEBUG: Upload error or file missing. Error Code: " . ($_FILES['manuscript']['error'] ?? 'N/A'));
  } else {
    $file = $_FILES['manuscript'];
    $fileSize = $file['size'];
    $fileTmp = $file['tmp_name'];
    // basic mime check
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $fileType = finfo_file($finfo, $fileTmp);
    finfo_close($finfo);

    error_log("WTRS DEBUG: File detected as $fileType, Size: $fileSize bytes");

    if ($fileType !== 'application/pdf') {
      $error = "Only PDF files are allowed.";
    } elseif ($fileSize > 20 * 1024 * 1024) { // 20MB limit
      $error = "File size exceeds the 20MB limit.";
    } else {
      $thesisId = null;
      $versionNum = "1.0";

      $uploadDir = __DIR__ . '/../public/uploads/';
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
      }
      $fileName = uniqid('thesis_') . '.pdf';
      $destination = $uploadDir . $fileName;

      error_log("WTRS DEBUG: Moving file to $destination");

      if (move_uploaded_file($fileTmp, $destination)) {
        error_log("WTRS DEBUG: File moved successfully. Starting database transaction.");
        $pdo->beginTransaction();
        try {
          $thesisCode = 'THS-' . date('Y') . '-' . strtoupper(substr(uniqid(), -4));
          $insStmt = $pdo->prepare("INSERT INTO theses (thesis_code, title, co_authors, abstract, author_id, adviser_id, submission_year, thesis_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending_review')");
          $insStmt->execute([$thesisCode, $title, $co_authors_str, $abstract, $user['id'], $adviser_id, $submission_year, $thesis_type]);
          $thesisId = $pdo->lastInsertId();

          $insVStmt = $pdo->prepare("INSERT INTO thesis_versions (thesis_id, version_number, file_path, file_size, status) VALUES (?, ?, ?, ?, 'pending')");
          $insVStmt->execute([$thesisId, $versionNum, $fileName, $fileSize]);

          error_log("WTRS DEBUG: Committing transaction.");
          $pdo->commit();
          error_log("WTRS DEBUG: Transaction committed successfully.");
          $_SESSION['student_dash_flash'] = [
            'type' => 'success',
            'message' => 'Thesis uploaded successfully and is now pending review.',
          ];
          header('Location: ' . BASE_URL . 'student/index.php', true, 303);
          exit;
        } catch (Exception $e) {
          $pdo->rollBack();
          error_log("WTRS DEBUG: Database error: " . $e->getMessage());
          $error = "Database error: " . $e->getMessage();
          if (file_exists($destination))
            unlink($destination);
        }
      } else {
        error_log("WTRS DEBUG: Failed to move the uploaded file. Path: $destination");
        $error = "Failed to move the uploaded file. Check directory permissions.";
      }
    }
  }
}

// Custom CSS for Upload
ob_start();
?>
<style>
  /* Larger, more readable scale (overrides compact dashboard defaults on this page) */
  .upload-page {
    max-width: 1320px;
    margin: 0 auto;
  }

  .upload-page .page-title h1 {
    font-size: clamp(2.05rem, 3vw, 2.75rem);
    line-height: 1.12;
  }

  .upload-page .page-title p {
    font-size: 1.08rem;
    max-width: 46rem;
    line-height: 1.65;
    margin-top: 0.5rem;
  }

  .upload-page .page-header {
    margin-bottom: 2rem;
    padding-bottom: 1.75rem;
  }

  .upload-page .page-header-actions .btn {
    font-size: 1rem;
    padding: 0.7rem 1.35rem;
  }

  .upload-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.8rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--crimson);
    background: var(--crimson-light);
    padding: 0.5rem 1rem;
    border-radius: 999px;
    margin-bottom: 0.75rem;
  }

  .upload-progress {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.85rem;
    margin-bottom: 2rem;
  }

  @media (max-width: 600px) {
    .upload-progress {
      grid-template-columns: 1fr;
    }
  }

  .upload-progress-step {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 1rem 1.15rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    box-shadow: var(--shadow-sm);
  }

  .upload-progress-step span {
    flex-shrink: 0;
    width: 1.95rem;
    height: 1.95rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 800;
    color: #fff;
    background: var(--crimson);
    border-radius: 50%;
  }

  .upload-progress-step strong {
    display: block;
    font-size: 0.98rem;
    font-weight: 800;
    color: var(--text-dark);
    margin-bottom: 0.2rem;
  }

  .upload-progress-step p {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-muted);
    line-height: 1.5;
  }

  .upload-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(320px, 1fr);
    gap: 2.25rem;
    align-items: start;
  }

  @media (max-width: 960px) {
    .upload-layout {
      grid-template-columns: 1fr;
    }
  }

  .upload-col--aside {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    position: sticky;
    top: calc(var(--topbar-h) + 1rem);
  }

  @media (max-width: 960px) {
    .upload-col--aside {
      position: static;
    }
  }

  .upload-alert {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.75rem;
    padding: 1.25rem 1.5rem;
    border-radius: var(--radius-sm);
    font-weight: 700;
    font-size: 1.05rem;
    line-height: 1.5;
    box-shadow: var(--shadow-md);
  }

  .upload-alert i {
    font-size: 1.55rem;
    flex-shrink: 0;
    margin-top: 0.05rem;
  }

  .upload-alert--error {
    color: #fff;
    background: linear-gradient(135deg, #991B1B 0%, #7F1D1D 100%);
    border: 1px solid rgba(255, 255, 255, 0.12);
  }

  .upload-alert--success {
    color: #fff;
    background: linear-gradient(135deg, #047857 0%, #065F46 100%);
    border: 1px solid rgba(255, 255, 255, 0.12);
  }

  .upload-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 2.35rem 2.35rem 2.5rem;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    position: relative;
    overflow: hidden;
  }

  .upload-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--crimson), var(--gold));
    opacity: 0.9;
  }

  .card-header {
    border-bottom: 1px solid var(--border);
    padding-bottom: 1.25rem;
    margin-bottom: 1.85rem;
    display: flex;
    align-items: flex-start;
    gap: 1.1rem;
  }

  .card-header i {
    font-size: 1.9rem;
    color: var(--crimson);
    background: var(--crimson-light);
    padding: 0.65rem;
    border-radius: var(--radius-sm);
    margin-top: 0.1rem;
  }

  .card-header h3 {
    font-family: var(--font-serif);
    font-size: 1.52rem;
    font-weight: 800;
    color: var(--text-dark);
    margin: 0;
  }

  .card-header p {
    margin: 0.35rem 0 0;
    font-size: 0.98rem;
    color: var(--text-muted);
    line-height: 1.55;
    max-width: 40rem;
  }

  .form-group {
    margin-bottom: 1.65rem;
  }

  .form-group:last-child {
    margin-bottom: 0;
  }

  .form-label {
    display: block;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
  }

  .form-hint {
    display: block;
    margin-top: 0.4rem;
    font-size: 0.88rem;
    color: var(--text-muted);
    font-weight: 600;
    line-height: 1.45;
  }

  .form-control {
    width: 100%;
    box-sizing: border-box;
    background: var(--off-white);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 0.95rem 1.15rem;
    font-family: var(--font-base);
    font-size: 1.06rem;
    color: var(--text-dark);
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
  }

  .form-control:focus {
    background: var(--surface);
    border-color: var(--crimson);
    box-shadow: 0 0 0 3px var(--crimson-faint);
    outline: none;
  }

  textarea.form-control {
    min-height: 260px;
    resize: vertical;
    line-height: 1.6;
  }

  .upload-split {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 1.5rem;
  }

  @media (max-width: 640px) {
    .upload-split {
      grid-template-columns: 1fr;
    }
  }

  .dropzone-area {
    border: 2px dashed var(--border-strong);
    border-radius: var(--radius);
    padding: 3.25rem 1.75rem;
    text-align: center;
    background: var(--off-white);
    transition: border-color 0.25s ease, background 0.25s ease, box-shadow 0.25s ease, transform 0.25s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
  }

  .dropzone-area:hover,
  .dropzone-area:focus-within,
  .dropzone-area.drag-over {
    border-color: var(--crimson);
    background: #FFF5F5;
    box-shadow: var(--shadow-md);
    transform: translateY(-1px);
  }

  .dropzone-area i {
    font-size: 3.6rem;
    color: var(--crimson);
    margin-bottom: 1rem;
    display: block;
    transition: transform 0.25s ease;
  }

  .dropzone-area:hover i {
    transform: scale(1.06);
  }

  .dropzone-area h4 {
    font-family: var(--font-serif);
    font-size: 1.35rem;
    color: var(--text-dark);
    margin: 0 0 0.45rem;
    font-weight: 800;
  }

  .dropzone-area p {
    font-size: 0.98rem;
    color: var(--text-muted);
    margin: 0;
  }

  .dropzone-hint {
    margin-top: 0.75rem;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-muted);
    letter-spacing: 0.04em;
  }

  .file-input {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
  }

  .selected-file {
    margin-top: 1.15rem;
    padding: 1.25rem 1.3rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    display: none;
    align-items: center;
    gap: 0.85rem;
    text-align: left;
    animation: uploadSlideUp 0.3s ease forwards;
    box-shadow: var(--shadow-sm);
    border-left: 4px solid var(--crimson);
  }

  .selected-file.active {
    display: flex;
  }

  .selected-file i {
    font-size: 2.1rem;
    color: var(--crimson);
    margin: 0;
    flex-shrink: 0;
  }

  .selected-file-info {
    flex: 1;
    min-width: 0;
  }

  .selected-file-name {
    font-weight: 700;
    color: var(--text-dark);
    font-size: 1.02rem;
    margin-bottom: 0.2rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .selected-file-size {
    font-size: 0.88rem;
    color: var(--text-muted);
  }

  #removeFile {
    flex-shrink: 0;
    background: #FEE2E2;
    color: #991B1B;
    border: none;
    width: 2.55rem;
    height: 2.55rem;
    padding: 0;
    border-radius: 50%;
    cursor: pointer;
    transition: background 0.2s, transform 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  #removeFile:hover {
    background: #FECACA;
    transform: rotate(90deg);
  }

  .upload-specs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1.1rem;
    justify-content: center;
  }

  .upload-specs span {
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--text-muted);
    background: var(--off-white);
    border: 1px solid var(--border);
    padding: 0.45rem 0.85rem;
    border-radius: 999px;
  }

  .upload-checklist {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1.5rem 1.65rem;
    box-shadow: var(--shadow-sm);
  }

  .upload-checklist h4 {
    margin: 0 0 1.1rem;
    font-size: 0.82rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--text-muted);
  }

  .upload-checklist ul {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
  }

  .upload-checklist li {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    font-size: 0.98rem;
    color: var(--text-mid);
    line-height: 1.5;
  }

  .upload-checklist li i {
    flex-shrink: 0;
    margin-top: 0.15rem;
    color: var(--crimson);
    font-size: 1.15rem;
  }

  .policy-box {
    background: linear-gradient(180deg, #FFFBEB 0%, #FEF3C7 100%);
    border: 1px solid #FDE68A;
    border-radius: var(--radius-sm);
    padding: 1.45rem 1.5rem;
    box-shadow: var(--shadow-sm);
  }

  .policy-title {
    font-weight: 800;
    color: #92400E;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    font-size: 1rem;
  }

  .policy-text {
    font-family: var(--font-serif);
    font-style: italic;
    color: #92400E;
    font-size: 1.05rem;
    line-height: 1.65;
    margin: 0;
  }

  .upload-actions {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
  }

  .upload-actions .btn {
    width: 100%;
    justify-content: center;
    padding: 1.15rem 1.4rem;
    font-weight: 800;
    font-size: 1.08rem;
    border-radius: var(--radius-sm);
  }

  .upload-actions .btn-secondary {
    font-weight: 700;
    font-size: 1.02rem;
  }

  /* Slightly roomier main column on this screen */
  .main-content:has(.upload-page) {
    padding: 2.35rem 2.5rem 3.5rem;
  }

  @media (max-width: 768px) {
    .main-content:has(.upload-page) {
      padding: 1.35rem 1.25rem 2.5rem;
    }
  }

  @keyframes uploadSlideUp {
    from {
      opacity: 0;
      transform: translateY(8px);
    }

    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
</style>
<?php
$extraCss = ob_get_clean();

require_once __DIR__ . '/../includes/layout_top.php';
require_once __DIR__ . '/../includes/layout_sidebar.php';
?>

<main class="main-content">
  <div class="upload-page">

    <header class="page-header">
      <div class="page-title">
        <span class="upload-eyebrow"><i class="ph-fill ph-plus-circle"></i> New submission</span>
        <h1>Submit your <span>thesis</span></h1>
        <p>Enter your thesis metadata, choose your adviser, and upload a single PDF. You can save time by having your
          abstract ready before you start.</p>
      </div>
      <div class="page-header-actions">
        <a href="index.php" class="btn btn-secondary"><i class="ph-bold ph-arrow-left"></i> Back to dashboard</a>
      </div>
    </header>

    <?php if ($error): ?>
      <div class="upload-alert upload-alert--error" role="alert">
        <i class="ph-bold ph-warning-circle"></i>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="upload-alert upload-alert--success" role="status">
        <i class="ph-bold ph-check-circle"></i>
        <span><?= htmlspecialchars($success) ?></span>
      </div>
    <?php endif; ?>

    <div class="upload-progress" aria-hidden="true">
      <div class="upload-progress-step">
        <span>1</span>
        <div>
          <strong>Details</strong>
          <p>Title, abstract, and adviser</p>
        </div>
      </div>
      <div class="upload-progress-step">
        <span>2</span>
        <div>
          <strong>PDF</strong>
          <p>One file, max 20&nbsp;MB</p>
        </div>
      </div>
      <div class="upload-progress-step">
        <span>3</span>
        <div>
          <strong>Submit</strong>
          <p>Queued for review</p>
        </div>
      </div>
    </div>

    <form action="<?= BASE_URL ?>student/upload.php" method="POST" enctype="multipart/form-data" class="upload-layout form-confirm" 
      data-confirm-title="Submit Thesis?" data-confirm-message="Are you sure you want to submit this thesis for review? Ensure the PDF and metadata are correct.">

      <div class="upload-col upload-col--main">
        <div class="upload-card">
          <div class="card-header">
            <i class="ph-fill ph-book-open"></i>
            <div>
              <h3>Thesis information</h3>
              <p>This metadata appears on your repository record and is shared with your adviser.</p>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="upload-title">Title</label>
            <input id="upload-title" type="text" name="title" required class="form-control"
              placeholder="Full title as it should appear in the archive" value="" autocomplete="off">
          </div>

          <div class="form-group">
            <label class="form-label" for="upload-abstract">Abstract</label>
            <textarea id="upload-abstract" name="abstract" required class="form-control" rows="9"
              placeholder="Objectives, methods, key findings, and conclusions — keep it concise and self-contained."></textarea>
          </div>

          <div class="upload-split">
            <div class="form-group">
              <label class="form-label" for="upload-year">Submission Year</label>
              <input id="upload-year" type="number" name="submission_year" required class="form-control" 
                placeholder="e.g. 2026" value="<?= date('Y') ?>" min="2000" max="<?= date('Y') ?>" autocomplete="off">
            </div>
            <div class="form-group">
              <label class="form-label" for="upload-author-name">Your Name</label>
              <input id="upload-author-name" type="text" name="author_name" required class="form-control"
                placeholder="Your full name as author" value="<?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>" autocomplete="off">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Thesis Type</label>
            <div style="display: flex; gap: 1.5rem; margin-top: 0.75rem;">
              <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; font-weight: 600; margin: 0;">
                <input type="radio" name="thesis_type" value="solo" required checked style="cursor: pointer;">
                Solo
              </label>
              <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; font-weight: 600; margin: 0;">
                <input type="radio" name="thesis_type" value="group" required style="cursor: pointer;">
                Group
              </label>
            </div>
            <span class="form-hint">Select "Group" if other authors will be tagged to this thesis.</span>
          </div>

          <div class="form-group" id="co-authors-section" style="display: none;">
            <label class="form-label">Co-authors</label>
            <p style="font-size: 0.98rem; color: var(--text-muted); margin-bottom: 1rem;">
              Add other students who contributed to this thesis. They will see this submission in their dashboard.
            </p>
            <div id="co-authors-list" style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1rem;">
              <!-- Co-author entries will be added here -->
            </div>
            <button type="button" id="add-co-author-btn" class="btn" style="width: fit-content; padding: 0.75rem 1.25rem; background: var(--surface); border: 1px solid var(--border); color: var(--text-dark); font-weight: 700;">
              <i class="ph-bold ph-plus"></i> Add co-author
            </button>
          </div>

          <div class="upload-split">
            <div class="form-group">
              <label class="form-label" for="upload-adviser">Research adviser</label>
              <select id="upload-adviser" name="adviser_id" required class="form-control">
                <option value="">Select faculty member</option>
                <?php foreach ($advisers as $adv): ?>
                  <option value="<?= $adv['id'] ?>">
                    <?= htmlspecialchars(trim(($adv['first_name'] ?? '') . ' ' . ($adv['last_name'] ?? ''))) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
      </div>

      <aside class="upload-col upload-col--aside">
        <div class="upload-card">
          <div class="card-header">
            <i class="ph-fill ph-file-pdf"></i>
            <div>
              <h3>Thesis (PDF)</h3>
              <p>Click or drag your file into the area below. Only one PDF per submission.</p>
            </div>
          </div>

          <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1.25rem;">
            <button type="button" id="previewSelectedBtn" class="btn btn-secondary" style="display: none; padding: 0.45rem 0.85rem; font-size: 0.75rem; font-weight: 800; border-color: var(--crimson); color: var(--crimson);">
              <i class="ph ph-file-search"></i> PREVIEW SELECTED
            </button>
          </div>

          <div class="dropzone-area" id="dropzone" role="button" tabindex="0" aria-label="Upload PDF file">
            <i class="ph-fill ph-cloud-arrow-up"></i>
            <h4>Drop PDF here or browse</h4>
            <p>Your file stays on this device until you submit.</p>
            <p class="dropzone-hint">PDF only · 20 MB maximum</p>
            <input type="file" name="manuscript" required accept="application/pdf,.pdf" class="file-input"
              id="fileInput" aria-label="Choose PDF file">
          </div>

          <div class="selected-file" id="selectedFile">
            <i class="ph-fill ph-file-pdf"></i>
            <div class="selected-file-info">
              <div class="selected-file-name" id="fileName">thesis_manuscript.pdf</div>
              <div class="selected-file-size" id="fileSize">—</div>
            </div>
            <button type="button" id="removeFile" aria-label="Remove selected file"><i class="ph ph-x"></i></button>
          </div>
        </div>

        <div class="upload-checklist">
          <h4>Before you submit</h4>
          <ul>
            <li><i class="ph-fill ph-check-circle"></i> Title and abstract match the PDF cover page</li>
            <li><i class="ph-fill ph-check-circle"></i> Adviser is selected and aware of the submission</li>
            <li><i class="ph-fill ph-check-circle"></i> PDF is final or clearly labeled if a draft</li>
          </ul>
        </div>

        <div class="policy-box">
          <div class="policy-title"><i class="ph-fill ph-shield-check"></i> Integrity affirmation</div>
          <p class="policy-text">
            I certify that this thesis is my original work and complies with Western Mindanao State
            University academic integrity policies.
          </p>
        </div>

        <div class="upload-actions">
          <button type="submit" class="btn btn-primary">
            <i class="ph-bold ph-upload-simple"></i> Submit for review
          </button>
          <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>
      </aside>

    </form>

  </div>
</main>

<?php
ob_start();
?>
<script>
  (function () {
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('file-field');
    const selectedFile = document.getElementById('selectedFileInfo');
    const fileNameDisp = document.getElementById('selectedFileName');
    const fileSizeDisp = document.getElementById('selectedFileSize');
    const removeBtn = document.getElementById('removeFile');
    const previewBtn = document.getElementById('previewSelectedBtn');
    
    const pdfModal = document.getElementById('pdfPreviewModal');
    const pdfFrame = document.getElementById('pdfFrame');
    const closePreview = document.getElementById('closePreview');
    const modalTitle = document.getElementById('modalTitle');

    let selectedFileUrl = null;
    const MAX_BYTES = 20 * 1024 * 1024;

    function formatSize(bytes) {
      if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
      if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
      return bytes + ' B';
    }

    function showPickedFile(file) {
      if (!file) return;
      const isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name);
      if (!isPdf) {
        alert('Please choose a PDF manuscript (.pdf).');
        return false;
      }
      if (file.size > MAX_BYTES) {
        alert('This file is larger than 20 MB.');
        return false;
      }
      fileNameDisp.textContent = file.name;
      fileSizeDisp.textContent = formatSize(file.size);
      dropzone.style.display = 'none';
      selectedFile.classList.add('active');
      previewBtn.style.display = 'flex';
      
      if (selectedFileUrl) URL.revokeObjectURL(selectedFileUrl);
      selectedFileUrl = URL.createObjectURL(file);
      return true;
    }

    fileInput.addEventListener('change', function () {
      const file = this.files && this.files[0];
      if (!file) return;
      if (!showPickedFile(file)) this.value = '';
    });

    removeBtn.addEventListener('click', function () {
      fileInput.value = '';
      selectedFile.classList.remove('active');
      dropzone.style.display = 'block';
      previewBtn.style.display = 'none';
      if (selectedFileUrl) URL.revokeObjectURL(selectedFileUrl);
      selectedFileUrl = null;
    });

    // Preview Logic
    previewBtn.addEventListener('click', function() {
      if (selectedFileUrl) {
        pdfFrame.src = selectedFileUrl;
        pdfModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
      }
    });

    closePreview.addEventListener('click', function() {
      pdfModal.style.display = 'none';
      document.body.style.overflow = 'auto';
      pdfFrame.src = "";
    });

    window.onclick = (e) => { if (e.target == pdfModal) closePreview.click(); };

    // ... rest of co-authors logic ...
    const thesisTypeRadios = document.querySelectorAll('input[name="thesis_type"]');
    const coAuthorsSection = document.getElementById('co-authors-section');
    const addCoAuthorBtn = document.getElementById('add-co-author-btn');
    const coAuthorsList = document.getElementById('co-authors-list');
    let coAuthorCount = 0;

    function toggleCoAuthorsSection() {
      const isGroup = document.querySelector('input[name="thesis_type"]:checked')?.value === 'group';
      coAuthorsSection.style.display = isGroup ? 'block' : 'none';
    }

    addCoAuthorBtn.addEventListener('click', function(e) {
      e.preventDefault();
      const div = document.createElement('div');
      div.className = 'co-author-input';
      div.style.cssText = 'display: flex; gap: 0.75rem; align-items: flex-end;';
      div.innerHTML = `
        <div style="flex: 1;"><input type="text" name="co_authors[]" placeholder="Student name or email" class="form-control" required></div>
        <button type="button" class="remove-btn" style="background: #FEE2E2; color: #991B1B; border: none; width: 2.5rem; height: 2.5rem; border-radius: 50%; cursor: pointer;"><i class="ph ph-x"></i></button>
      `;
      coAuthorsList.appendChild(div);
      div.querySelector('.remove-btn').addEventListener('click', () => div.remove());
    });

    thesisTypeRadios.forEach(radio => radio.addEventListener('change', toggleCoAuthorsSection));
  })();
</script>
<?php
$extraScripts = ob_get_clean();
require_once __DIR__ . '/../includes/layout_bottom.php';
?>