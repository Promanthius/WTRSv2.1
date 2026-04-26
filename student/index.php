<?php
require_once __DIR__ . '/../includes/session.php';
require_login(['student']);

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_thesis_id'])) {
    $tid = (int) $_POST['delete_thesis_id'];
    if ($tid <= 0) {
        header('Location: index.php?view=submissions&err=invalid');
        exit;
    }

    $check = $pdo->prepare('SELECT id, status, author_id FROM theses WHERE id = ?');
    $check->execute([$tid]);
    $row = $check->fetch(PDO::FETCH_ASSOC);

    if (!$row || (int) $row['author_id'] !== (int) $user['id']) {
        header('Location: index.php?view=submissions&err=forbidden');
        exit;
    }

    $nonDeletable = ['approved', 'archived'];
    if (in_array($row['status'], $nonDeletable, true)) {
        header('Location: index.php?view=submissions&err=locked');
        exit;
    }

    $vStmt = $pdo->prepare('SELECT file_path FROM thesis_versions WHERE thesis_id = ?');
    $vStmt->execute([$tid]);
    $paths = $vStmt->fetchAll(PDO::FETCH_COLUMN);

    $uploadDir = realpath(__DIR__ . '/../public/uploads');
    if ($uploadDir && $paths) {
        foreach ($paths as $fp) {
            $base = basename((string) $fp);
            if ($base === '' || $base === '.' || $base === '..') {
                continue;
            }
            $full = $uploadDir . DIRECTORY_SEPARATOR . $base;
            if (strpos($full, $uploadDir) === 0 && is_file($full)) {
                @unlink($full);
            }
        }
    }

    $del = $pdo->prepare('DELETE FROM theses WHERE id = ? AND author_id = ?');
    $del->execute([$tid, $user['id']]);

    header('Location: index.php?view=submissions&deleted=1');
    exit;
}

$dashFlash = null;
if (!empty($_SESSION['student_dash_flash'])) {
    $dashFlash = $_SESSION['student_dash_flash'];
    unset($_SESSION['student_dash_flash']);
}

$view = $_GET['view'] ?? 'overview';

// Fetch ALL theses by the student
$stmtAll = $pdo->prepare("SELECT t.*, u.first_name as adviser_first, u.last_name as adviser_last 
                         FROM theses t 
                         LEFT JOIN users u ON t.adviser_id = u.id 
                         WHERE t.author_id = :author_id 
                         ORDER BY t.created_at DESC");
$stmtAll->execute(['author_id' => $user['id']]);
$allTheses = $stmtAll->fetchAll();

// Active thesis is either the requested ID or the latest one
$requestedId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$activeThesis = null;

if ($requestedId) {
    foreach ($allTheses as $t) {
        if ((int)$t['id'] === $requestedId) {
            $activeThesis = $t;
            break;
        }
    }
}

if (!$activeThesis) {
    $activeThesis = $allTheses[0] ?? null;
}

// Fetch versions if there is an active thesis
$versions = [];
$totalIterations = 0;
if ($activeThesis) {
    // Note: order DESC so versions[0] is the latest
    $vStmt = $pdo->prepare("SELECT * FROM thesis_versions WHERE thesis_id = :thesis_id ORDER BY submitted_at DESC");
    $vStmt->execute(['thesis_id' => $activeThesis['id']]);
    $versions = $vStmt->fetchAll();
    $totalIterations = count($versions);
}

// Custom CSS to match the premium "Crimsonian" screenshot exactly
ob_start();
?>
<style>
  /* Manuscript Iterations Styling (Matching Screenshot) */
  .iterations-card {
    background: white;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    padding: 0;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    margin-bottom: 2rem;
  }
  
  .iterations-header {
    padding: 2.5rem 2.5rem 1.5rem;
  }
  
  .iterations-header h3 {
    font-family: var(--font-serif);
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text-dark);
    margin-bottom: 1rem;
  }
  
  .iterations-header p {
    font-size: 0.88rem;
    color: var(--text-muted);
  }
  
  .iteration-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem 2.5rem;
    border-top: 1px solid var(--border);
    transition: background 0.2s ease;
  }
  
  .iteration-row:hover {
    background: var(--off-white);
  }
  
  .iteration-left {
    display: flex;
    align-items: center;
    gap: 1.25rem;
  }
  
  .pdf-icon-box {
    color: #8B0000;
    font-size: 2.5rem;
    display: flex;
    align-items: center;
  }
  
  .version-title-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.4rem;
  }
  
  .version-name {
    font-weight: 800;
    color: var(--text-dark);
    font-size: 1rem;
  }
  
  .active-tag {
    background: #FFF0F0;
    color: #8B0000;
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    padding: 0.2rem 0.6rem;
    border-radius: 4px;
    text-transform: uppercase;
  }
  
  .iteration-meta {
    font-size: 0.8rem;
    color: var(--text-muted);
  }
  
  .iteration-actions {
    display: flex;
    align-items: center;
    gap: 1.5rem;
  }
  
  /* Status Badge matching Screenshot */
  .status-badge-pending {
    background: #FEF3C7;
    color: #92400E;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    text-transform: uppercase;
  }
  
  .status-badge-pending::before {
    content: '';
    display: inline-block;
    width: 6px;
    height: 6px;
    background: #D97706;
    border-radius: 50%;
  }

  /* Timeline Specifics */
  .timeline { padding: 2.5rem 1rem; position: relative; }
  .timeline::before { content: ''; position: absolute; top: 0; bottom: 0; left: 32px; width: 4px; background: var(--border); }
  .timeline-item { position: relative; padding-left: 88px; margin-bottom: 3rem; }
  .timeline-icon-wrap { position: absolute; top: -4px; left: 9px; width: 50px; height: 50px; border-radius: 50%; background: var(--surface); border: 4px solid var(--border); display: flex; align-items: center; justify-content: center; z-index: 2; color: var(--text-muted); font-size: 1.4rem; }
  .timeline-item.active .timeline-icon-wrap { border-color: var(--gold); background: var(--gold-faint); color: var(--gold); box-shadow: 0 0 0 6px white; }
  .timeline-item.completed .timeline-icon-wrap { border-color: var(--crimson); background: var(--crimson); color: white; box-shadow: 0 0 0 6px white; }

  .submissions-flash {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    margin: 0 1.5rem 1.25rem;
    padding: 0.9rem 1.1rem;
    border-radius: var(--radius-sm);
    font-size: 0.88rem;
    font-weight: 700;
    line-height: 1.45;
  }
  .submissions-flash i { font-size: 1.2rem; flex-shrink: 0; margin-top: 0.05rem; }
  .submissions-flash--ok {
    background: #D1FAE5;
    color: #065F46;
    border: 1px solid #6EE7B7;
  }
  .submissions-flash--err {
    background: #FEE2E2;
    color: #991B1B;
    border: 1px solid #FECACA;
  }

  .submission-actions {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: 0.5rem;
  }
  .submission-delete-form { display: inline; margin: 0; padding: 0; }
  .btn-action-danger {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.45rem 0.85rem;
    font-family: var(--font-base);
    font-size: 0.78rem;
    font-weight: 800;
    color: #991B1B;
    background: #FEF2F2;
    border: 1px solid #FECACA;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
  }
  .btn-action-danger:hover {
    background: #FEE2E2;
    border-color: #F87171;
  }
  .submission-delete-na {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    color: var(--text-muted);
    opacity: 0.55;
    font-size: 1.1rem;
  }
</style>
<?php
$extraCss = ob_get_clean();

require_once __DIR__ . '/../includes/layout_top.php';
require_once __DIR__ . '/../includes/layout_sidebar.php';
?>

  <main class="main-content">
    
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-title">
        <?php if ($view === 'submissions'): ?>
          <h1>My <span>Submissions</span></h1>
          <p>A comprehensive archive of all your scholarly submissions and their current statuses.</p>
        <?php else: ?>
          <h1>Welcome, <span><?= htmlspecialchars($user['first_name']) ?></span></h1>
          <p>Manage your research artifacts, track peer review status, and maintain a historical record of your academic contributions.</p>
        <?php endif; ?>
      </div>
      <div class="page-header-actions">
        <?php if ($view === 'submissions'): ?>
          <a href="index.php" class="btn btn-secondary" style="text-decoration:none;">
            <i class="ph-bold ph-arrow-left"></i> Back to Dashboard
          </a>
        <?php endif; ?>
        <a href="upload.php" class="btn btn-primary" style="text-decoration:none;">
          <i class="ph-bold ph-upload-simple"></i> Submit New Manuscript
        </a>
      </div>
    </div>

    <?php if (!empty($dashFlash)): ?>
      <div class="submissions-flash <?= ($dashFlash['type'] ?? '') === 'success' ? 'submissions-flash--ok' : 'submissions-flash--err' ?>" role="status" style="margin: 0 0 1.5rem;">
        <i class="ph-bold <?= ($dashFlash['type'] ?? '') === 'success' ? 'ph-check-circle' : 'ph-warning-circle' ?>"></i>
        <span><?= htmlspecialchars($dashFlash['message'] ?? '') ?></span>
      </div>
    <?php endif; ?>

    <?php if ($view === 'submissions'): ?>
      <!-- ── ALL SUBMISSIONS VIEW ────────────────────────────────────────── -->
      <div class="table-container">
        <div class="table-toolbar">
          <div>
            <h3>Submission Archive</h3>
            <p>List of all research manuscripts you have submitted to the repository.</p>
          </div>
        </div>

        <?php
        $subFlash = $_GET['deleted'] ?? '';
        $subErr = $_GET['err'] ?? '';
        if ($subFlash === '1'): ?>
          <div class="submissions-flash submissions-flash--ok" role="status">
            <i class="ph-bold ph-check-circle"></i>
            <span>That submission and its uploaded files were removed.</span>
          </div>
        <?php elseif ($subErr === 'locked'): ?>
          <div class="submissions-flash submissions-flash--err" role="alert">
            <i class="ph-bold ph-lock-key"></i>
            <span>Approved or archived theses cannot be deleted. Contact support if you need changes.</span>
          </div>
        <?php elseif ($subErr === 'forbidden' || $subErr === 'invalid'): ?>
          <div class="submissions-flash submissions-flash--err" role="alert">
            <i class="ph-bold ph-warning-circle"></i>
            <span>Could not delete that submission. Refresh the page and try again.</span>
          </div>
        <?php endif; ?>

        <?php if (empty($allTheses)): ?>
          <div class="empty-state">
            <i class="ph-fill ph-files"></i>
            <p>No submissions found.</p>
            <a href="upload.php" class="btn btn-primary">Start Your First Submission</a>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="queue-table" style="width:100%;">
              <thead>
                <tr>
                  <th>Thesis Details</th>
                  <th>Adviser</th>
                  <th>Status</th>
                  <th>Last Updated</th>
                  <th style="text-align: center;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($allTheses as $t): ?>
                  <tr>
                    <td>
                      <span style="font-size: 0.6rem; font-weight: 800; color: var(--gold); text-transform: uppercase;"><?= htmlspecialchars($t['thesis_code'] ?? 'PENDING') ?></span>
                      <div style="font-weight: 700; color: var(--text-dark); font-size: 0.88rem; margin-top: 0.2rem;"><?= htmlspecialchars($t['title']) ?></div>
                    </td>
                    <td><?= $t['adviser_last'] ? 'Dr. ' . htmlspecialchars($t['adviser_last']) : '<span class="text-muted">Not Assigned</span>' ?></td>
                    <td>
                      <?php
                        $st = $t['status'];
                        if ($st === 'approved') echo '<span class="badge badge-approved"><span class="dot dot-approved"></span> Approved</span>';
                        elseif ($st === 'revision_requested') echo '<span class="badge badge-revision"><span class="dot dot-revision"></span> Revision</span>';
                        elseif ($st === 'pending_review') echo '<span class="badge badge-pending"><span class="dot dot-pending"></span> Pending</span>';
                        else echo '<span class="badge badge-default">' . ucfirst($st) . '</span>';
                      ?>
                    </td>
                    <td class="text-muted" style="font-size:0.8rem;">
                      <?= date('M j, Y', strtotime($t['updated_at'] ?? $t['created_at'])) ?>
                    </td>
                    <td style="text-align: center;">
                      <div class="submission-actions">
                        <a href="tracker.php?id=<?= (int) $t['id'] ?>" class="btn-action-outline">
                          <i class="ph-bold ph-eye"></i> View
                        </a>
                        <?php
                        $canDelete = !in_array($t['status'], ['approved', 'archived'], true);
                        ?>
                        <?php if ($canDelete): ?>
                          <form method="post" action="index.php?view=submissions" class="submission-delete-form"
                            onsubmit="return confirm('Delete this submission and all of its uploaded PDF versions? This cannot be undone.');">
                            <input type="hidden" name="delete_thesis_id" value="<?= (int) $t['id'] ?>">
                            <button type="submit" class="btn-action-danger" title="Delete submission">
                              <i class="ph-bold ph-trash"></i> Delete
                            </button>
                          </form>
                        <?php else: ?>
                          <span class="submission-delete-na" title="Approved or archived records cannot be deleted">
                            <i class="ph-bold ph-lock-key"></i>
                          </span>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

    <?php else: ?>
      <!-- ── DASHBOARD OVERVIEW VIEW ────────────────────────────────────── -->
      
      <!-- Stats Summary -->
      <div class="stat-cards">
        <div class="stat-card accent-red">
          <div class="stat-icon"><i class="ph-fill ph-files"></i></div>
          <div class="stat-title">Manuscript Iterations</div>
          <div class="stat-value"><?= htmlspecialchars($totalIterations) ?></div>
          <div class="stat-meta"><i class="ph ph-clock-counter-clockwise"></i> <span>Total versions uploaded</span></div>
        </div>

        <div class="stat-card accent-gold">
          <div class="stat-icon"><i class="ph-fill ph-activity"></i></div>
          <div class="stat-title">Current Status</div>
          <div class="stat-value">
            <?php if (!$activeThesis): ?> None
            <?php elseif ($activeThesis['status'] === 'pending_review'): ?> In Review
            <?php elseif ($activeThesis['status'] === 'revision_requested'): ?> Revise
            <?php elseif ($activeThesis['status'] === 'approved'): ?> Approved
            <?php else: ?> <?= htmlspecialchars(ucfirst($activeThesis['status'])) ?>
            <?php endif; ?>
          </div>
          <div class="stat-meta">
            <?php if ($activeThesis && $activeThesis['status'] === 'approved'): ?>
              <i class="ph ph-check text-success"></i> <span class="text-success">Ready for archive</span>
            <?php else: ?>
              <i class="ph ph-info"></i> <span>Latest status update</span>
            <?php endif; ?>
          </div>
        </div>

        <div class="stat-card accent-teal">
          <div class="stat-icon"><i class="ph-fill ph-chalkboard-teacher"></i></div>
          <div class="stat-title">Adviser</div>
          <div class="stat-value" style="font-size:1.15rem; line-height:1.2;">
            <?php if ($activeThesis && $activeThesis['adviser_last']): ?> Dr. <?= htmlspecialchars($activeThesis['adviser_last']) ?>
            <?php else: ?> <span style="opacity:0.5">Not Assigned</span> <?php endif; ?>
          </div>
          <div class="stat-meta"><i class="ph ph-user-circle"></i> <span>Assigned faculty member</span></div>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
        
        <!-- Center Primary Content -->
        <div>
          <?php if ($activeThesis): ?>
            
            <!-- Iterations List (Matching Screenshot Style) -->
            <div class="iterations-card">
              <div class="iterations-header">
                <h3>Manuscript Iterations</h3>
                <p>History of all uploaded versions</p>
              </div>
              
              <div class="iteration-list">
                <?php if (empty($versions)): ?>
                  <div style="padding: 3rem; text-align: center; color: var(--text-muted);">No versions recorded yet.</div>
                <?php else: ?>
                  <?php foreach ($versions as $index => $v): ?>
                    <div class="iteration-row">
                      <div class="iteration-left">
                        <div class="pdf-icon-box"><i class="ph-fill ph-file-pdf"></i></div>
                        <div>
                          <div class="version-title-row">
                            <span class="version-name">Version <?= htmlspecialchars($v['version_number']) ?></span>
                            <?php if ($index === 0): ?> <span class="active-tag">ACTIVE</span> <?php endif; ?>
                          </div>
                          <div class="iteration-meta">
                            <?php 
                              $mb = round($v['file_size'] / 1024 / 1024, 2);
                              echo ($mb < 1) ? round($v['file_size'] / 1024, 1) . ' KB' : $mb . ' MB';
                            ?> 
                            &bull; Uploaded <?= date('M j, Y \a\t h:i A', strtotime($v['submitted_at'])) ?>
                          </div>
                        </div>
                      </div>
                      
                      <div class="iteration-actions">
                        <?php if ($v['status'] === 'approved'): ?>
                          <span class="badge badge-approved" style="background:#D1FAE5; color:#065F46; border-radius:20px; font-weight:800; font-size:0.7rem; padding:0.4rem 0.8rem; letter-spacing:0.05em; display:flex; align-items:center; gap:0.4rem; text-transform:uppercase;">
                             <span class="dot dot-approved" style="width:6px; height:6px; background:#059669; border-radius:50%;"></span> APPROVED
                          </span>
                        <?php elseif ($v['status'] === 'revision_requested'): ?>
                          <span class="badge badge-revision" style="background:#FEE2E2; color:#991B1B; border-radius:20px; font-weight:800; font-size:0.7rem; padding:0.4rem 0.8rem; letter-spacing:0.05em; display:flex; align-items:center; gap:0.4rem; text-transform:uppercase;">
                             <span class="dot dot-revision" style="width:6px; height:6px; background:#DC2626; border-radius:50%;"></span> REVISION
                          </span>
                        <?php else: ?>
                          <span class="status-badge-pending">PENDING</span>
                        <?php endif; ?>
                        
                        <a href="../public/uploads/<?= htmlspecialchars($v['file_path']) ?>" target="_blank" class="btn-action-outline" style="text-decoration:none; padding: 0.6rem 1.75rem; font-weight: 800; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; color: #8B0000; border-color: #8B0000;">
                          <i class="ph-bold ph-download-simple"></i> View
                        </a>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

            <!-- Review Timeline -->
            <div class="table-container" style="background:white; margin-bottom: 2rem;">
              <div class="table-toolbar">
                <div><h3>Review Lifecycle</h3><p>Tracking the progression of your active submission through the archival process</p></div>
              </div>
              <div style="padding: 0 1.5rem;">
                <div class="timeline">
                  <?php
                    $status = $activeThesis['status'];
                    $step1Class = 'completed'; $step1Date = date('M j, Y', strtotime($activeThesis['created_at'])); $step1Icon = '<i class="ph-bold ph-check"></i>';
                    $step2Class = 'pending'; $step2Title = 'Adviser Review'; $step2Date = 'Pending Execution'; $step2Icon = '<i class="ph-fill ph-chat-text"></i>';
                    $step3Class = 'pending'; $step3Title = 'Repository Archival'; $step3Date = 'Pending Execution'; $step3Icon = '<i class="ph-fill ph-seal-check"></i>';

                    if ($status === 'pending_review') { $step2Class = 'active'; $step2Date = 'Verification in Progress'; }
                    elseif ($status === 'revision_requested') { $step2Class = 'active'; $step2Title = 'Refinement Required'; $step2Date = 'Adviser requested revisions'; $step2Icon = '<i class="ph-bold ph-warning"></i>'; }
                    elseif ($status === 'approved') { $step2Class = 'completed'; $step2Date = 'Refractive verified'; $step3Class = 'completed'; $step3Date = date('M j, Y', strtotime($activeThesis['updated_at'] ?? $activeThesis['created_at'])); $step3Icon = '<i class="ph-bold ph-check"></i>'; }
                  ?>
                  <div class="timeline-item <?= $step1Class ?>"><div class="timeline-icon-wrap"><?= $step1Icon ?></div><div class="timeline-content"><h4>Artifact Registered</h4><span class="timeline-meta">Registration Completed &bull; <?= $step1Date ?></span></div></div>
                  <div class="timeline-item <?= $step2Class ?>"><div class="timeline-icon-wrap"><?= $step2Icon ?></div><div class="timeline-content"><h4><?= $step2Title ?></h4><span class="timeline-meta"><?= $step2Date ?></span></div></div>
                  <div class="timeline-item <?= $step3Class ?>"><div class="timeline-icon-wrap"><?= $step3Icon ?></div><div class="timeline-content"><h4><?= $step3Title ?></h4><span class="timeline-meta"><?= $step3Date ?></span></div></div>
                </div>
              </div>
            </div>

          <?php else: ?>
            <div class="empty-state card-academic" style="padding: 5rem 2rem;">
              <i class="ph-fill ph-files" style="color: var(--gold); font-size: 4rem; opacity: 0.3;"></i>
              <h3 style="font-family: var(--font-serif); margin: 1.5rem 0 0.5rem;">No Academic Entries</h3>
              <p>You have not registered any research artifacts yet. Begin your archival journey today.</p>
              <a href="upload.php" class="btn btn-primary" style="margin-top: 2rem;">Initialize Submission</a>
            </div>
          <?php endif; ?>
        </div>

        <!-- Sidebar / Supplemental -->
        <div>
          <!-- Adviser Feedback Note -->
          <?php if (!empty($versions) && !empty($versions[0]['feedback'])): ?>
            <div class="feedback-note">
              <div class="feedback-note-header"><i class="ph-fill ph-chat-centered-text"></i> Adviser Direct Note</div>
              <div class="feedback-body">"<?= nl2br(htmlspecialchars($versions[0]['feedback'])) ?>"</div>
            </div>
          <?php endif; ?>

          <?php if ($activeThesis && $activeThesis['status'] === 'revision_requested'): ?>
            <div class="upload-box" onclick="window.location.href='resubmit.php?id=<?= $activeThesis['id'] ?>'">
              <i class="ph-fill ph-cloud-arrow-up"></i>
              <h3>Refinement Required</h3>
              <p>Your research adviser has recommended editorial or technical refinements to your manuscript.</p>
              <button class="btn btn-primary" style="width:100%; margin-top: 1.5rem;">Proceed to Resubmit</button>
            </div>
          <?php elseif ($activeThesis && $activeThesis['status'] !== 'approved'): ?>
            <div class="upload-box" style="opacity:0.6; cursor:default;">
              <i class="ph-fill ph-lock-key"></i>
              <h3>Registry Locked</h3>
              <p>The archival entry is currently under rigorous faculty evaluation and cannot be modified.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

  </main>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>