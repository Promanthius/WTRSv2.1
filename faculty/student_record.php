<?php
require_once __DIR__ . '/../includes/session.php';
require_login(['adviser']);

$user = current_user();
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$student_id) {
    header('Location: ' . BASE_URL . 'faculty/students.php');
    exit;
}

// ── DATA FETCHING ────────────────────────────────────────────────────────────

// 1. Fetch Student Details
$stmtStudent = $pdo->prepare("SELECT * FROM users WHERE id = :id AND role = 'student'");
$stmtStudent->execute(['id' => $student_id]);
$student = $stmtStudent->fetch();

if (!$student) {
    header('Location: ' . BASE_URL . 'faculty/students.php');
    exit;
}

// 2. Fetch All Theses for this Student (Scoped to Adviser)
$adviserConstraint = "AND t.adviser_id = :adviser_id";
$params = ['author_id' => $student_id];
$params['adviser_id'] = $user['id'];

$stmtTheses = $pdo->prepare("
    SELECT t.*, 
           tv.version_number AS latest_version,
           tv.submitted_at   AS latest_activity
    FROM theses t
    LEFT JOIN thesis_versions tv ON tv.id = (
        SELECT id FROM thesis_versions
        WHERE thesis_id = t.id
        ORDER BY submitted_at DESC LIMIT 1
    )
    WHERE t.author_id = :author_id $adviserConstraint
    ORDER BY t.created_at DESC
");
$stmtTheses->execute($params);
$theses = $stmtTheses->fetchAll();

function faculty_record_status_label(string $status): string
{
    if ($status === 'pending_review') return 'AWAITING REVIEW';
    if ($status === 'revision_requested') return 'REVISION REQUIRED';
    if ($status === 'approved') return 'ACCEPTED';
    if ($status === 'archived') return 'PUBLISHED';
    if ($status === 'rejected') return 'REJECTED';
    if ($status === 'draft') return 'AWAITING ADVISER';
    return strtoupper(str_replace('_', ' ', $status));
}

// 3. Stats for this specific student
$stats = [
    'total'    => count($theses),
    'approved' => 0,
    'pending'  => 0,
    'revision' => 0
];
foreach ($theses as $th) {
    if ($th['status'] === 'approved') $stats['approved']++;
    else if ($th['status'] === 'pending_review') $stats['pending']++;
    else if ($th['status'] === 'revision_requested') $stats['revision']++;
}

ob_start();
?>
<style>
  .dossier-header { background: white; border: 1px solid var(--border); border-radius: var(--radius); padding: 3rem; margin-bottom: 2.5rem; position: relative; box-shadow: var(--shadow-sm); overflow: hidden; }
  .dossier-header::before { content: ''; position: absolute; top: 0; left: 0; width: 6px; height: 100%; background: var(--crimson); }
  
  .profile-strip { display: flex; align-items: center; gap: 2rem; }
  .av-dossier { width: 80px; height: 80px; background: var(--crimson); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-family: var(--font-serif); font-size: 2.2rem; font-weight: 800; border: 4px solid var(--off-white); box-shadow: var(--shadow-md); }
  
  .student-name { font-family: var(--font-serif); font-size: 2.4rem; color: var(--text-dark); margin: 0; }
  .student-meta { font-size: 0.9rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.5rem; }

  .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-top: 3rem; border-top: 1px solid var(--border-faint); padding-top: 2rem; }
  .stat-card-mini { background: var(--off-white); border: 1px solid var(--border); padding: 1.25rem; border-radius: var(--radius-sm); }
  .stat-mini-label { display: block; font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.25rem; }
  .stat-mini-val { font-family: var(--font-serif); font-size: 1.5rem; font-weight: 800; color: var(--text-dark); }

  /* Submission List */
  .artifact-list { display: flex; flex-direction: column; gap: 1.5rem; }
  .artifact-card { background: white; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 2rem; display: flex; align-items: center; justify-content: space-between; transition: all 0.2s; }
  .artifact-card:hover { border-color: var(--gold); transform: translateX(5px); box-shadow: var(--shadow-md); }
  
  .artifact-main { flex: 1; }
  .artifact-code { color: var(--gold); font-size: 0.72rem; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 0.5rem; display: block; }
  .artifact-title { font-family: var(--font-serif); font-size: 1.25rem; font-weight: 800; color: var(--text-dark); margin: 0 0 0.5rem; }
  
  .artifact-meta { display: flex; gap: 1.5rem; font-size: 0.8rem; color: var(--text-muted); font-weight: 700; }
</style>
<?php
$extraCss = ob_get_clean();
$current_page = 'students.php';
require_once __DIR__ . '/../includes/layout_top.php';
require_once __DIR__ . '/../includes/layout_sidebar.php';
?>

<main class="main-content">
  <!-- Nav Breadcrumb -->
  <nav style="margin-bottom: 2.5rem; font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
     <a href="<?= BASE_URL ?>faculty/students.php" style="color:var(--text-muted); text-decoration:none;">AUTHOR REGISTRY</a>
     <i class="ph ph-caret-right" style="margin: 0 0.5rem; opacity:0.5;"></i>
     <span style="color:var(--crimson);">STUDENT RESEARCH DOSSIER</span>
  </nav>

  <header class="dossier-header">
     <div class="profile-strip">
        <div class="av-dossier"><?= htmlspecialchars(strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1))) ?></div>
        <div>
           <h1 class="student-name"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h1>
           <div class="student-meta">
              <span style="color: var(--crimson);"><?= htmlspecialchars($student['college']) ?></span> &bull; 
              <?= htmlspecialchars($student['email']) ?> &bull; 
              ID# <?= str_pad($student['id'], 6, '0', STR_PAD_LEFT) ?>
           </div>
        </div>
     </div>

     <div class="stats-grid">
        <div class="stat-card-mini">
           <span class="stat-mini-label">TOTAL ARTIFACTS</span>
           <div class="stat-mini-val"><?= $stats['total'] ?></div>
        </div>
        <div class="stat-card-mini">
           <span class="stat-mini-label">ACCEPTED</span>
           <div class="stat-mini-val" style="color: #065F46;"><?= $stats['approved'] ?></div>
        </div>
        <div class="stat-card-mini">
           <span class="stat-mini-label">AWAITING EVALUATION</span>
           <div class="stat-mini-val" style="color: var(--gold);"><?= $stats['pending'] ?></div>
        </div>
        <div class="stat-card-mini">
           <span class="stat-mini-label">REVISION CYCLE</span>
           <div class="stat-mini-val" style="color: #991B1B;"><?= $stats['revision'] ?></div>
        </div>
     </div>
  </header>

  <div class="section-header" style="margin-bottom: 2rem;">
     <div>
       <div class="section-title">Submission History</div>
       <div class="section-sub">Chronological summary of all scholarly contributions.</div>
     </div>
  </div>

  <div class="artifact-list">
     <?php if (empty($theses)): ?>
        <div style="background: white; border: 2px dashed var(--border); padding: 5rem; text-align: center; color: var(--text-muted); border-radius: var(--radius-sm);">
           <i class="ph ph-mask-sad" style="font-size: 4rem; opacity: 0.2; display: block; margin-bottom: 1rem;"></i>
           This student has not yet contributed any scholarly artifacts to the institutional repository.
        </div>
     <?php else: ?>
        <?php foreach ($theses as $th): ?>
           <div class="artifact-card">
              <div class="artifact-main">
                 <span class="artifact-code"><?= htmlspecialchars($th['thesis_code']) ?></span>
                 <h3 class="artifact-title"><?= htmlspecialchars($th['title']) ?></h3>
                 <div class="artifact-meta">
                    <span style="display:flex; align-items:center; gap:0.4rem;"><i class="ph-bold ph-calendar"></i> Init: <?= date('M j, Y', strtotime($th['created_at'])) ?></span>
                    <span style="display:flex; align-items:center; gap:0.4rem;"><i class="ph-bold ph-clock"></i> Last Activity: <?= $th['latest_activity'] ? date('M j, Y', strtotime($th['latest_activity'])) : 'N/A' ?></span>
                    <span style="display:flex; align-items:center; gap:0.4rem;"><i class="ph-bold ph-git-branch"></i> Version: v<?= htmlspecialchars($th['latest_version'] ?? '1.0') ?></span>
                 </div>
              </div>
              <div style="display: flex; gap: 1rem; align-items: center;">
                  <?php if ($th['status'] === 'pending_review'): ?>
                    <span class="badge badge-pending"><span class="dot dot-pending"></span> AWAITING REVIEW</span>
                  <?php elseif ($th['status'] === 'approved'): ?>
                    <span class="badge badge-approved"><span class="dot dot-approved"></span> ACCEPTED</span>
                  <?php elseif ($th['status'] === 'archived'): ?>
                    <span class="badge badge-approved"><span class="dot dot-approved"></span> PUBLISHED</span>
                  <?php elseif ($th['status'] === 'revision_requested'): ?>
                    <span class="badge badge-revision"><span class="dot dot-revision"></span> REVISION REQUIRED</span>
                  <?php else: ?>
                    <span class="badge badge-default"><?= faculty_record_status_label($th['status']) ?></span>
                  <?php endif; ?>

                  <a href="<?= BASE_URL ?>faculty/review.php?id=<?= $th['id'] ?>" class="btn-action-outline" style="text-decoration:none; padding: 0.6rem 1.25rem; color: var(--crimson); border-color: var(--crimson); font-size: 0.75rem; font-weight: 800;">
                     <i class="ph ph-magnifying-glass"></i> Inspect Artifact
                  </a>
              </div>
           </div>
        <?php endforeach; ?>
     <?php endif; ?>
  </div>

</main>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
