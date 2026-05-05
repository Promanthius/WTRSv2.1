<?php
require_once __DIR__ . '/../includes/session.php';
require_login(['student']);

$user = current_user();

$dashFlash = null;
if (!empty($_SESSION['student_dash_flash'])) {
  $dashFlash = $_SESSION['student_dash_flash'];
  unset($_SESSION['student_dash_flash']);
}

// 1. Fetch ALL relevant theses for the search/sidebar
$search = trim($_GET['q'] ?? '');
$filterStatus = $_GET['status'] ?? 'all';

$listSql = "SELECT DISTINCT t.id, t.thesis_code, t.title, t.status, t.created_at, u.last_name as adviser_name
            FROM theses t 
            LEFT JOIN users u ON t.adviser_id = u.id 
            LEFT JOIN thesis_authors ta ON t.id = ta.thesis_id
            WHERE (t.author_id = :uid OR ta.author_id = :uid2)";

if ($search !== '') {
  $listSql .= " AND (t.title LIKE :search OR t.thesis_code LIKE :search2)";
}
if ($filterStatus !== 'all') {
  $listSql .= " AND t.status = :status";
}
$listSql .= " ORDER BY t.created_at DESC";

$listStmt = $pdo->prepare($listSql);
$params = ['uid' => $user['id'], 'uid2' => $user['id']];
if ($search !== '') {
  $params['search'] = "%$search%";
  $params['search2'] = "%$search%";
}
if ($filterStatus !== 'all') {
  $params['status'] = $filterStatus;
}
$listStmt->execute($params);
$myTheses = $listStmt->fetchAll();

// 2. Determine which thesis to display
$thesis_id = $_GET['id'] ?? null;
if (!$thesis_id && !empty($myTheses)) {
  $thesis_id = $myTheses[0]['id'];
}

$thesis = null;
$versions = [];
if ($thesis_id) {
  $stmt = $pdo->prepare("SELECT t.*, u.first_name as adviser_first, u.last_name as adviser_last 
                         FROM theses t 
                         LEFT JOIN users u ON t.adviser_id = u.id 
                         LEFT JOIN thesis_authors ta ON t.id = ta.thesis_id
                         WHERE t.id = :id AND (t.author_id = :uid OR ta.author_id = :uid2)");
  $stmt->execute(['id' => $thesis_id, 'uid' => $user['id'], 'uid2' => $user['id']]);
  $thesis = $stmt->fetch();

  if ($thesis) {
    $vStmt = $pdo->prepare("SELECT * FROM thesis_versions WHERE thesis_id = :tid ORDER BY submitted_at DESC");
    $vStmt->execute(['tid' => $thesis['id']]);
    $versions = $vStmt->fetchAll();
  }
}

$latestVersion = $versions[0] ?? null;

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
  .tracker-layout {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 0;
    margin: 0 -2.5rem 0; /* Removed negative top margin */
    height: calc(100vh - var(--topbar-h));
    overflow: hidden;
  }

  /* Sidebar Submissions List */
  .tracker-sidebar {
    background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    z-index: 10;
    padding-left: 1.5rem; /* Separates from main sidebar */
  }

  .sidebar-header {
    padding: 2rem 1.5rem;
    border-bottom: 1px solid var(--border);
  }

  .search-input-wrap {
    position: relative;
    margin-top: 1rem;
  }

  .search-input-wrap i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
  }

  .search-input-wrap input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    background: var(--off-white);
    font-size: 0.85rem;
  }

  .submissions-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 1rem 0;
  }

  .thesis-item {
    display: block;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--off-white);
    transition: all 0.2s;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
  }

  .thesis-item:hover {
    background: var(--off-white);
  }

  .thesis-item.active {
    background: var(--crimson-faint);
    border-left: 4px solid var(--crimson);
  }

  .thesis-item-code {
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--crimson);
    letter-spacing: 0.05em;
    margin-bottom: 0.35rem;
  }

  .thesis-item-title {
    font-weight: 700;
    font-size: 0.88rem;
    color: var(--text-dark);
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  /* Main Viewport */
  .tracker-viewport {
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

  .step {
    position: relative;
    z-index: 2;
    text-align: center;
    width: 100px;
  }

  .step-icon {
    width: 50px;
    height: 50px;
    background: white;
    border: 3px solid var(--border);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 1.25rem;
    color: var(--text-muted);
    transition: all 0.3s;
  }

  .step.active .step-icon {
    border-color: var(--crimson);
    color: var(--crimson);
    box-shadow: 0 0 0 6px var(--crimson-faint);
  }

  .step.completed .step-icon {
    background: var(--crimson);
    border-color: var(--crimson);
    color: white;
  }

  .step-label {
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
  }

  .step.active .step-label {
    color: var(--crimson);
  }

  /* Process Cards */
  .process-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    padding: 2.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-sm);
  }

  .process-detail-toggle {
    font-size: 0.7rem;
    font-weight: 800;
    color: var(--crimson);
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-bottom: 1.5rem;
  }

  .detailed-info {
    display: none;
    background: var(--off-white);
    padding: 1.5rem;
    border-radius: var(--radius-sm);
    font-size: 0.85rem;
    color: var(--text-mid);
    margin-top: 1rem;
    border-left: 3px solid var(--crimson);
  }

  .detailed-info.show {
    display: block;
  }

  .main-content:has(.tracker-layout) {
    padding: 0 !important;
  }
</style>
<?php
$extraCss = ob_get_clean();
require_once __DIR__ . '/../includes/layout_top.php';
require_once __DIR__ . '/../includes/layout_sidebar.php';
?>

<main class="main-content">
  <div class="tracker-layout">
    
    <!-- LEFT: SEARCHABLE SIDEBAR -->
    <aside class="tracker-sidebar">
      <div class="sidebar-header">
        <h2 style="font-size: 1.25rem; font-family: var(--font-serif);">My Submissions</h2>
        <form action="" method="GET" class="search-input-wrap">
          <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
          <i class="ph ph-magnifying-glass"></i>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search thesis code or title...">
        </form>
      </div>
      
      <div class="submissions-scroll">
        <?php if (empty($myTheses)): ?>
          <div style="padding: 3rem 1.5rem; text-align: center; color: var(--text-muted);">
            <i class="ph ph-file-x" style="font-size: 2.5rem; opacity: 0.2; margin-bottom: 1rem;"></i>
            <p style="font-size: 0.85rem;">No submissions found matching criteria.</p>
          </div>
        <?php else: ?>
          <?php foreach ($myTheses as $mt): ?>
            <a href="tracker.php?id=<?= $mt['id'] ?>&q=<?= urlencode($search) ?>&status=<?= $filterStatus ?>" 
               class="thesis-item <?= $thesis_id == $mt['id'] ? 'active' : '' ?>">
              <div class="thesis-item-code"><?= htmlspecialchars($mt['thesis_code']) ?></div>
              <div class="thesis-item-title"><?= htmlspecialchars($mt['title']) ?></div>
              <div style="margin-top: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.6rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted);"><?= time_ago($mt['created_at']) ?></span>
                <span class="badge" style="font-size: 0.55rem; padding: 0.15rem 0.45rem;"><?= htmlspecialchars($mt['status']) ?></span>
              </div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </aside>

    <!-- RIGHT: TRACKING VIEWPORT -->
    <section class="tracker-viewport">
      <?php if (!$thesis): ?>
        <div style="height: 100%; display: flex; align-items: center; justify-content: center; text-align: center;">
          <div>
            <i class="ph ph-activity" style="font-size: 5rem; color: var(--crimson); opacity: 0.1; margin-bottom: 2rem;"></i>
            <h1 style="font-family: var(--font-serif); color: var(--text-muted);">Select an artifact to track progress</h1>
          </div>
        </div>
      <?php else: ?>
        
        <header style="margin-bottom: 4rem; display: flex; justify-content: space-between; align-items: flex-start;">
          <div>
            <span class="type-label" title="<?= date('F j, Y, g:i A', strtotime($thesis['created_at'])) ?>">Registered <?= time_ago($thesis['created_at']) ?></span>
            <h1 class="thesis-title-big"><?= htmlspecialchars($thesis['title']) ?></h1>
            <div style="display: flex; gap: 2rem; color: var(--text-muted); font-weight: 700; font-size: 0.85rem;">
              <span>ID: <span style="color: var(--crimson);"><?= htmlspecialchars($thesis['thesis_code']) ?></span></span>
              <span>Adviser: <span style="color: var(--text-dark);">Dr. <?= htmlspecialchars($thesis['adviser_last'] ?? 'Unassigned') ?></span></span>
            </div>
          </div>
          <?php if ($thesis['status'] === 'revision_requested'): ?>
            <a href="resubmit.php?id=<?= $thesis['id'] ?>" class="btn btn-primary" style="padding: 0.8rem 1.5rem;">
              <i class="ph ph-upload-simple"></i> SUBMIT REVISION
            </a>
          <?php endif; ?>
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

        <!-- PROCESS MONITOR -->
        <div class="process-card">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
            <div>
              <h2 style="font-family: var(--font-serif); font-size: 1.5rem; margin-bottom: 0.5rem;">Process Monitor</h2>
              <p style="color: var(--text-muted); font-size: 0.9rem;">Real-time tracking of the institutional archival workflow.</p>
            </div>
            <div style="text-align: right;">
              <div style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Current Phase</div>
              <div style="font-size: 1.1rem; font-weight: 800; color: var(--crimson);"><?= strtoupper(str_replace('_', ' ', $thesis['status'])) ?></div>
            </div>
          </div>

          <div class="process-detail-toggle" onclick="toggleDetails('main-details')">
            <i class="ph ph-info"></i> View Detailed Process Info
          </div>
          <div id="main-details" class="detailed-info">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
              <div>
                <strong>Entry Created:</strong><br>
                <?= date('F j, Y, g:i A', strtotime($thesis['created_at'])) ?>
              </div>
              <div>
                <strong>Last Activity:</strong><br>
                <?= date('F j, Y, g:i A', strtotime($thesis['updated_at'])) ?>
              </div>
              <div>
                <strong>Submission Code:</strong><br>
                <?= htmlspecialchars($thesis['thesis_code']) ?>
              </div>
              <div>
                <strong>Author IP:</strong><br>
                <?= $_SERVER['REMOTE_ADDR'] ?> (Secured)
              </div>
            </div>
          </div>

          <hr style="border: 0; border-top: 1px solid var(--border); margin: 2rem 0;">

          <h3 style="font-family: var(--font-serif); font-size: 1.2rem; margin-bottom: 1.5rem;">Iteration History</h3>
          <?php if (empty($versions)): ?>
            <p style="color: var(--text-muted);">No versions uploaded yet.</p>
          <?php else: ?>
            <?php foreach ($versions as $idx => $v): ?>
              <div class="iteration-mini-card" style="margin-bottom: 1rem;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                  <div style="width: 40px; height: 40px; background: var(--off-white); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--crimson); font-weight: 800;">
                    v<?= $v['version_number'] ?>
                  </div>
                  <div>
                    <div style="font-weight: 800; font-size: 0.9rem;">Artifact Submission</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); cursor: help;" title="<?= date('F j, Y, g:i A', strtotime($v['submitted_at'])) ?>">
                      <?= time_ago($v['submitted_at']) ?> &bull; <?= format_size($v['file_size']) ?>
                    </div>
                  </div>
                </div>
                <div style="display: flex; gap: 1rem; align-items: center;">
                  <span class="badge" style="font-size: 0.6rem;"><?= strtoupper($v['status']) ?></span>
                  <button onclick="previewPdf('<?= htmlspecialchars($v['file_path']) ?>', 'v<?= $v['version_number'] ?>')" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.7rem;">
                    <i class="ph ph-eye"></i>
                  </button>
                </div>
              </div>
              <?php if (!empty($v['feedback'])): ?>
                <div class="feedback-note" style="margin-left: 3rem; margin-top: -0.5rem; margin-bottom: 1.5rem;">
                   <div class="feedback-note-header"><i class="ph-fill ph-chat-centered-text"></i> ADVISER FEEDBACK</div>
                   <div class="feedback-body">"<?= nl2br(htmlspecialchars($v['feedback'])) ?>"</div>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
           <div class="card-academic" style="padding: 2rem;">
              <h4>Abstract Summary</h4>
              <p style="font-size: 0.88rem; line-height: 1.6; color: var(--text-mid); text-align: justify;"><?= nl2br(htmlspecialchars($thesis['abstract'])) ?></p>
           </div>
           <div class="card-academic" style="padding: 2rem;">
              <h4>Repository Impact</h4>
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1.5rem;">
                <div style="text-align: center; padding: 1.5rem; background: var(--off-white); border-radius: 12px;">
                   <div style="font-size: 1.5rem; font-weight: 800; color: var(--crimson);"><?= number_format($thesis['views']) ?></div>
                   <div style="font-size: 0.6rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Views</div>
                </div>
                <div style="text-align: center; padding: 1.5rem; background: var(--off-white); border-radius: 12px;">
                   <div style="font-size: 1.5rem; font-weight: 800; color: var(--gold);"><?= number_format($thesis['downloads']) ?></div>
                   <div style="font-size: 0.6rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Downloads</div>
                </div>
              </div>
           </div>
        </div>

      <?php endif; ?>
    </section>
  </div>

  <!-- PDF Preview Modal -->
  <div id="pdfPreviewModal" class="pdf-preview-modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); align-items: center; justify-content: center;">
    <div class="pdf-preview-content" style="background: white; border-radius: var(--radius-lg); width: 90%; height: 90vh; max-width: 1000px; display: flex; flex-direction: column; box-shadow: var(--shadow-lg);">
      <div class="pdf-preview-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; border-bottom: 1px solid var(--border); flex-shrink: 0;">
        <h3 id="modalTitle" style="margin: 0; font-size: 1.2rem; color: var(--text-dark);">Thesis Preview</h3>
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
    
    title.innerText = "Manuscript Preview (" + ver + ")";
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

  // Handle ESC key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeModal();
  });
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>