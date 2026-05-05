<?php
require_once __DIR__ . '/../includes/session.php';
require_login(['adviser']);

$user = current_user();

function faculty_status_label(string $status): string {
  if ($status === 'pending_review') return 'Pending Adviser Approval';
  if ($status === 'revision_requested') return 'Needs Revision';
  if ($status === 'approved') return 'Accepted';
  if ($status === 'archived') return 'Published';
  if ($status === 'rejected') return 'Rejected';
  if ($status === 'draft') return 'Awaiting Submission';
  return ucwords(str_replace('_', ' ', $status));
}

// --- Filter & Search Logic ---
$filterStatus = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$params = ['adviser_id' => $user['id']];
$where = ["t.adviser_id = :adviser_id", "t.status <> 'draft'"];

if ($filterStatus !== 'all') {
  $where[] = "t.status = :status";
  $params['status'] = $filterStatus;
}
if ($search !== '') {
  $where[] = "(t.thesis_code LIKE :search OR t.title LIKE :search2)";
  $params['search'] = "%$search%";
  $params['search2'] = "%$search%";
}

$whereClause = "WHERE " . implode(' AND ', $where);

// Fetch Queue for Sidebar
$thesesStmt = $pdo->prepare("
    SELECT t.*, u.first_name, u.last_name, u.college,
           tv.version_number AS latest_version,
           tv.submitted_at   AS latest_submitted_at
    FROM theses t
    JOIN users u ON t.author_id = u.id
    LEFT JOIN thesis_versions tv ON tv.id = (
        SELECT id FROM thesis_versions
        WHERE thesis_id = t.id
        ORDER BY submitted_at DESC LIMIT 1
    )
    $whereClause
    ORDER BY COALESCE(tv.submitted_at, t.created_at) DESC
");
$thesesStmt->execute($params);
$myQueue = $thesesStmt->fetchAll();

// --- Selection Logic ---
$thesis_id = isset($_GET['id']) ? (int) $_GET['id'] : null;
if (!$thesis_id && !empty($myQueue)) {
    // Check if we should pick the first one
    // $thesis_id = $myQueue[0]['id']; 
}

$thesis = null;
$versions = [];
$selectedVersion = null;

if ($thesis_id) {
  $stmt = $pdo->prepare("SELECT t.*, u.first_name, u.last_name, u.college
                           FROM theses t
                           JOIN users u ON t.author_id = u.id
                           WHERE t.id = :id AND t.adviser_id = :adviser_id");
  $stmt->execute(['id' => $thesis_id, 'adviser_id' => $user['id']]);
  $thesis = $stmt->fetch();

  if ($thesis) {
    $vStmt = $pdo->prepare("SELECT * FROM thesis_versions WHERE thesis_id = :id ORDER BY submitted_at DESC");
    $vStmt->execute(['id' => $thesis_id]);
    $versions = $vStmt->fetchAll();
    
    $selected_version_id = isset($_GET['v']) ? (int)$_GET['v'] : null;
    if ($selected_version_id) {
      foreach ($versions as $v) {
        if ((int)$v['id'] === $selected_version_id) {
          $selectedVersion = $v;
          break;
        }
      }
    }
    if (!$selectedVersion) $selectedVersion = $versions[0] ?? null;
  }
}

// --- Action Logic (POST) ---
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $thesis) {
    if (isset($_POST['request_action'])) {
        // Handle Accept/Decline (for draft requests)
    } elseif (isset($_POST['action'])) {
        $action = $_POST['action'];
        $feedback = trim($_POST['feedback'] ?? '');

        if ($action === 'publish_artifact' && $thesis['status'] === 'approved') {
            $pdo->prepare("UPDATE theses SET status = 'archived', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$thesis_id]);
            $success = "Artifact formally published.";
            $thesis['status'] = 'archived';
        } elseif (in_array($action, ['approved', 'revision_requested', 'rejected'])) {
            if (empty($feedback) && $action !== 'approved') {
                $error = "Feedback is mandatory for revisions/rejections.";
            } else {
                if (empty($feedback)) $feedback = "Your manuscript has been verified.";
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE thesis_versions SET status = ?, feedback = ? WHERE id = ?")->execute([($action === 'approved' ? 'approved' : 'rejected'), $feedback, $selectedVersion['id']]);
                $pdo->prepare("UPDATE theses SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$action, $thesis_id]);
                $pdo->commit();
                $success = "Decision recorded.";
                $thesis['status'] = $action;
            }
        }
    }
}

// Progress Stepper Helper
function get_step_class($currentStatus, $stepStatus) {
  $order = ['draft' => 1, 'pending_review' => 2, 'revision_requested' => 3, 'approved' => 4, 'archived' => 5];
  $curr = $order[$currentStatus] ?? 0;
  $target = $order[$stepStatus] ?? 0;
  if ($curr > $target) return 'completed';
  if ($curr === $target) return 'active';
  return '';
}

ob_start();
?>
<style>
  .faculty-layout {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 0;
    margin: 0 -2.5rem 0; /* Removed negative top margin */
    height: calc(100vh - var(--topbar-h));
    overflow: hidden;
  }

  .faculty-sidebar {
    background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    z-index: 10;
    padding-left: 1.5rem; 
  }

  .sidebar-header {
    padding: 2rem 1.5rem;
    border-bottom: 1px solid var(--border);
  }

  .sidebar-filters {
    display: flex;
    gap: 0.4rem;
    margin-top: 1rem;
    overflow-x: auto;
    padding-bottom: 0.5rem;
  }

  .filter-pill {
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 800;
    white-space: nowrap;
    background: var(--off-white);
    border: 1px solid var(--border);
    color: var(--text-muted);
  }

  .filter-pill.active {
    background: var(--crimson);
    color: white;
    border-color: var(--crimson);
  }

  .queue-scroll {
    flex: 1;
    overflow-y: auto;
  }

  .queue-item {
    display: block;
    padding: 1.5rem;
    border-bottom: 1px solid var(--off-white);
    text-decoration: none;
    color: inherit;
    transition: all 0.2s;
  }

  .queue-item:hover { background: var(--off-white); }
  .queue-item.active { background: var(--crimson-faint); border-left: 4px solid var(--crimson); }

  .queue-item-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
  .queue-item-code { font-size: 0.65rem; font-weight: 800; color: var(--crimson); }
  .queue-item-date { font-size: 0.65rem; color: var(--text-muted); }
  .queue-item-title { font-weight: 700; font-size: 0.9rem; line-height: 1.4; margin-bottom: 0.5rem; }
  .queue-item-student { font-size: 0.75rem; color: var(--text-mid); }

  .faculty-viewport {
    background: var(--off-white);
    overflow-y: auto;
    padding: 3rem;
  }

  /* Stepper Logic */
  .stepper-wrap {
    display: flex;
    justify-content: space-between;
    margin-bottom: 4rem;
    position: relative;
    padding: 0 2rem;
  }

  .stepper-wrap::before {
    content: '';
    position: absolute;
    top: 24px;
    left: 5rem;
    right: 5rem;
    height: 3px;
    background: var(--border);
    z-index: 1;
  }

  .step { position: relative; z-index: 2; text-align: center; width: 100px; }
  .step-icon {
    width: 50px; height: 50px; background: white; border: 3px solid var(--border); border-radius: 50%;
    display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;
    font-size: 1.25rem; color: var(--text-muted); transition: all 0.3s;
  }
  .step.active .step-icon { border-color: var(--crimson); color: var(--crimson); box-shadow: 0 0 0 6px var(--crimson-faint); }
  .step.completed .step-icon { background: var(--crimson); border-color: var(--crimson); color: white; }
  .step-label { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); }
  .step.active .step-label { color: var(--crimson); }

  .workspace-card { background: white; border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow-sm); margin-bottom: 2rem; overflow: hidden; }
  .workspace-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
  .workspace-body { padding: 2.5rem; }

  .version-nav { display: flex; gap: 0.5rem; margin-bottom: 2rem; border-bottom: 1px solid var(--off-white); padding-bottom: 1rem; }
  .v-pill { padding: 0.4rem 0.8rem; border-radius: 4px; font-size: 0.7rem; font-weight: 800; text-decoration: none; background: white; border: 1px solid var(--border); color: var(--text-muted); }
  .v-pill.active { background: var(--crimson); color: white; border-color: var(--crimson); }

  .main-content:has(.faculty-layout) { padding: 0 !important; }
  .detailed-info { display: none; background: var(--off-white); padding: 1.5rem; border-radius: var(--radius-sm); font-size: 0.85rem; margin-top: 1rem; border-left: 3px solid var(--crimson); }
  .detailed-info.show { display: block; }
</style>
<?php
$extraCss = ob_get_clean();
require_once __DIR__ . '/../includes/layout_top.php';
require_once __DIR__ . '/../includes/layout_sidebar.php';
?>

<main class="main-content">
  <div class="faculty-layout">
    
    <!-- LEFT: QUEUE SIDEBAR -->
    <aside class="faculty-sidebar">
      <div class="sidebar-header">
        <h2 style="font-size: 1.25rem; font-family: var(--font-serif);">Review Queue</h2>
        <form method="GET" style="position: relative; margin-top: 1rem;">
          <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
          <i class="ph ph-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
          <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search student or code..." 
            style="width: 100%; padding: 0.75rem 1rem 0.75rem 2.5rem; border-radius: 8px; border: 1px solid var(--border); background: var(--off-white); font-size: 0.85rem;">
        </form>
        <div class="sidebar-filters">
          <?php 
            $tabs = ['all' => 'ALL', 'pending_review' => 'PENDING', 'revision_requested' => 'REVISION', 'approved' => 'ACCEPTED'];
            foreach($tabs as $k => $l): 
          ?>
            <a href="?status=<?= $k ?>&search=<?= urlencode($search) ?>" class="filter-pill <?= $filterStatus == $k ? 'active' : '' ?>"><?= $l ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="queue-scroll">
        <?php if (empty($myQueue)): ?>
          <div style="padding: 4rem 2rem; text-align: center; color: var(--text-muted);">
            <i class="ph ph-tray" style="font-size: 3rem; opacity: 0.2; margin-bottom: 1rem;"></i>
            <p>Your review queue is currently empty.</p>
          </div>
        <?php else: ?>
          <?php foreach ($myQueue as $item): ?>
            <a href="review.php?id=<?= $item['id'] ?>&status=<?= $filterStatus ?>&search=<?= urlencode($search) ?>" 
               class="queue-item <?= $thesis_id == $item['id'] ? 'active' : '' ?>">
               <div class="queue-item-meta">
                 <span class="queue-item-code"><?= htmlspecialchars($item['thesis_code']) ?></span>
                 <span class="queue-item-date" title="<?= date('F j, Y, g:i A', strtotime($item['latest_submitted_at'] ?? $item['created_at'])) ?>">
                   <?= time_ago($item['latest_submitted_at'] ?? $item['created_at']) ?>
                 </span>
               </div>
               <div class="queue-item-title"><?= htmlspecialchars($item['title']) ?></div>
               <div class="queue-item-student"><i class="ph ph-user"></i> <?= htmlspecialchars($item['first_name'].' '.$item['last_name']) ?></div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </aside>

    <!-- RIGHT: WORKSPACE -->
    <section class="faculty-viewport">
      <?php if (!$thesis): ?>
        <div style="height: 100%; display: flex; align-items: center; justify-content: center; text-align: center;">
          <div>
            <i class="ph ph-seal-check" style="font-size: 6rem; color: var(--crimson); opacity: 0.05; margin-bottom: 2rem;"></i>
            <h1 style="font-family: var(--font-serif); color: var(--text-muted);">Select a submission to begin evaluation</h1>
            <p style="color: var(--text-muted);">Institutional verification workspace for faculty advisers.</p>
          </div>
        </div>
      <?php else: ?>

        <header style="margin-bottom: 4rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <span class="type-label">Reviewing Artifact</span>
              <h1 class="thesis-title-big" style="margin-bottom: 0.5rem;"><?= htmlspecialchars($thesis['title']) ?></h1>
              <div style="display: flex; gap: 1.5rem; font-size: 0.85rem; font-weight: 700; color: var(--text-muted);">
                <span>AUTHOR: <span style="color: var(--text-dark);"><?= strtoupper(htmlspecialchars($thesis['first_name'].' '.$thesis['last_name'])) ?></span></span>
                <span>COLLEGE: <span style="color: var(--text-dark);"><?= htmlspecialchars($thesis['college']) ?></span></span>
              </div>
            </div>
            <div style="text-align: right;">
               <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); margin-bottom: 0.5rem;">REGISTRY STATUS</div>
               <span class="badge" style="padding: 0.5rem 1rem;"><?= strtoupper($thesis['status']) ?></span>
            </div>
          </div>
        </header>

        <!-- LIVE STEPPER -->
        <div class="stepper-wrap">
          <div class="step <?= get_step_class($thesis['status'], 'draft') ?>">
            <div class="step-icon"><i class="ph ph-pencil-line"></i></div>
            <div class="step-label">Draft</div>
          </div>
          <div class="step <?= get_step_class($thesis['status'], 'pending_review') ?>">
            <div class="step-icon"><i class="ph ph-eye"></i></div>
            <div class="step-label">Review</div>
          </div>
          <div class="step <?= get_step_class($thesis['status'], 'revision_requested') ?>">
            <div class="step-icon"><i class="ph ph-chat-centered-dots"></i></div>
            <div class="step-label">Revision</div>
          </div>
          <div class="step <?= get_step_class($thesis['status'], 'approved') ?>">
            <div class="step-icon"><i class="ph ph-seal-check"></i></div>
            <div class="step-label">Verified</div>
          </div>
          <div class="step <?= get_step_class($thesis['status'], 'archived') ?>">
            <div class="step-icon"><i class="ph ph-books"></i></div>
            <div class="step-label">Published</div>
          </div>
        </div>

        <div class="workspace-card">
          <div class="workspace-header">
            <h3 style="font-family: var(--font-serif); margin: 0;">Manuscript Iterations</h3>
            <div class="version-nav" style="margin: 0; border: 0; padding: 0;">
              <?php foreach ($versions as $v): ?>
                <a href="?id=<?= $thesis_id ?>&v=<?= $v['id'] ?>&status=<?= $filterStatus ?>&search=<?= urlencode($search) ?>" 
                   class="v-pill <?= $selectedVersion['id'] == $v['id'] ? 'active' : '' ?>">v<?= $v['version_number'] ?></a>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="workspace-body">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
              <div>
                <div style="font-weight: 800; font-size: 1.1rem;">Version <?= $selectedVersion['version_number'] ?> Artifact</div>
                <div style="font-size: 0.85rem; color: var(--text-muted);" title="<?= date('F j, Y, g:i A', strtotime($selectedVersion['submitted_at'])) ?>">
                   Submitted <?= time_ago($selectedVersion['submitted_at']) ?> &bull; <?= format_size($selectedVersion['file_size']) ?>
                </div>
              </div>
              <div style="display: flex; gap: 1rem;">
                <button onclick="previewPdf('<?= htmlspecialchars($selectedVersion['file_path']) ?>', 'v<?= $selectedVersion['version_number'] ?>')" class="btn btn-secondary">
                  <i class="ph ph-eye"></i> PREVIEW CONTENT
                </button>
                <a href="<?= BASE_URL ?>public/uploads/<?= htmlspecialchars($selectedVersion['file_path']) ?>" download class="btn btn-secondary">
                  <i class="ph ph-download-simple"></i> DOWNLOAD
                </a>
              </div>
            </div>

            <div onclick="toggleDetails('proc-info')" style="cursor: pointer; font-size: 0.7rem; font-weight: 800; color: var(--crimson); display: flex; align-items: center; gap: 0.4rem; margin-bottom: 1.5rem;">
               <i class="ph ph-info"></i> SHOW DETAILED PROCESS DATA
            </div>
            <div id="proc-info" class="detailed-info">
               <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                 <div><strong>Registry Code:</strong><br><?= htmlspecialchars($thesis['thesis_code']) ?></div>
                 <div><strong>Submission Timestamp:</strong><br><?= date('F j, Y, g:i A', strtotime($selectedVersion['submitted_at'])) ?></div>
                 <div><strong>File Integrity:</strong><br>Verified PDF Document</div>
                 <div><strong>System ID:</strong><br>#<?= $selectedVersion['id'] ?></div>
               </div>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--border-faint); margin: 2.5rem 0;">

            <?php if ($thesis['status'] === 'pending_review' && $selectedVersion['status'] === 'pending'): ?>
              <h3 style="font-family: var(--font-serif); font-size: 1.3rem; margin-bottom: 1.5rem;">Formal Evaluation</h3>
              <form action="" method="POST" class="form-confirm" data-confirm-title="Submit Evaluation?" data-confirm-message="Your decision and feedback will be sent to the student immediately.">
                <input type="hidden" name="action" id="decisionAction" value="approved">
                <div style="margin-bottom: 1.5rem;">
                   <label style="display: block; font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.75rem;">Adviser Critique & Feedback</label>
                   <textarea name="feedback" rows="8" placeholder="Enter your detailed scholarly feedback here..." 
                     style="width: 100%; padding: 1.25rem; border-radius: 8px; border: 1px solid var(--border); font-family: var(--font-serif); font-size: 1rem; line-height: 1.6;"></textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                   <button type="submit" onclick="document.getElementById('decisionAction').value='approved'" class="btn btn-primary" style="background: #059669; border-color: #059669;">ACCEPT MANUSCRIPT</button>
                   <button type="submit" onclick="document.getElementById('decisionAction').value='revision_requested'" class="btn btn-primary" style="background: var(--gold); border-color: var(--gold);">REQUEST REVISION</button>
                   <button type="submit" onclick="document.getElementById('decisionAction').value='rejected'" class="btn btn-primary" style="background: #DC2626; border-color: #DC2626;">REJECT SUBMISSION</button>
                </div>
              </form>
            <?php elseif ($thesis['status'] === 'approved'): ?>
               <div style="background: var(--crimson-faint); padding: 2rem; border-radius: 12px; border: 2px solid var(--crimson); text-align: center;">
                 <i class="ph ph-seal-check" style="font-size: 3rem; color: var(--crimson);"></i>
                 <h3 style="margin: 1rem 0 0.5rem;">Verification Complete</h3>
                 <p style="color: var(--text-muted); margin-bottom: 2rem;">The manuscript has been accepted. You may now formally publish it to the institutional archives.</p>
                 <form action="" method="POST">
                   <input type="hidden" name="action" value="publish_artifact">
                   <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem;">PUBLISH TO ARCHIVE</button>
                 </form>
               </div>
            <?php else: ?>
               <div style="padding: 2rem; background: var(--off-white); border-radius: 12px; border: 1px solid var(--border); text-align: center;">
                 <i class="ph ph-info" style="font-size: 2.5rem; opacity: 0.2;"></i>
                 <h3 style="margin: 1rem 0 0.5rem;"><?= $thesis['status'] === 'revision_requested' ? 'Awaiting Student Action' : 'Review Concluded' ?></h3>
                 <p style="color: var(--text-muted);"><?= $thesis['status'] === 'revision_requested' ? 'This iteration has been processed. We are waiting for the student to upload a revision.' : 'This iteration has been formally processed with status: <strong>'.strtoupper($selectedVersion['status']).'</strong>' ?></p>
                 <?php if ($thesis['status'] === 'revision_requested'): ?>
                    <div style="margin-top: 1rem; font-weight: 800; color: var(--gold); font-size: 0.75rem;">REVISION PENDING</div>
                 <?php endif; ?>
               </div>
               <?php if ($selectedVersion['feedback']): ?>
                  <div style="margin-top: 2rem; padding: 1.5rem; background: white; border-left: 4px solid var(--gold); font-family: var(--font-serif); font-style: italic;">
                    "<?= nl2br(htmlspecialchars($selectedVersion['feedback'])) ?>"
                  </div>
               <?php endif; ?>
            <?php endif; ?>

          </div>
        </div>

      <?php endif; ?>
    </section>
  </div>

  <!-- PDF Preview Modal -->
  <div id="pdfPreviewModal" class="pdf-preview-modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); align-items: center; justify-content: center;">
    <div class="pdf-preview-content" style="background: white; border-radius: var(--radius-lg); width: 90%; height: 90vh; max-width: 1100px; display: flex; flex-direction: column; box-shadow: var(--shadow-lg);">
      <div class="pdf-preview-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; border-bottom: 1px solid var(--border); flex-shrink: 0;">
        <h3 id="modalTitle" style="margin: 0; font-size: 1.2rem; color: var(--text-dark);">Manuscript Preview</h3>
        <button class="pdf-preview-close" onclick="closeModal()" style="background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--text-muted);">&times;</button>
      </div>
      <div class="pdf-preview-viewer" style="flex: 1; background: #f5f5f5;">
        <iframe id="pdfFrame" src="" type="application/pdf" style="width: 100%; height: 100%; border: none;"></iframe>
      </div>
    </div>
  </div>

</main>

<script>
  function toggleDetails(id) {
    document.getElementById(id).classList.toggle('show');
  }

  function previewPdf(path, ver) {
    const modal = document.getElementById('pdfPreviewModal');
    const frame = document.getElementById('pdfFrame');
    const title = document.getElementById('modalTitle');
    
    title.innerText = "Manuscript Evaluation (" + ver + ")";
    frame.src = "<?= BASE_URL ?>public/uploads/" + path;
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    const modal = document.getElementById('pdfPreviewModal');
    const frame = document.getElementById('pdfFrame');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
    frame.src = "";
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeModal();
  });
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>