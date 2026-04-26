<?php
require_once __DIR__ . '/../includes/session.php';
require_login(['adviser']);

$user   = current_user();
$search = trim($_GET['search'] ?? '');

// ── DATA FETCHING ────────────────────────────────────────────────────────────

$params = [];
$params['adviser_id'] = $user['id'];

$searchClause = '';
if ($search !== '') {
    $searchClause     = "AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.college LIKE :search OR u.course LIKE :search OR u.student_id LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

$adviserConstraint = "AND t.adviser_id = :adviser_id";

$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.first_name,
        u.last_name,
        u.college,
        u.course,
        u.year_level,
        u.student_id,
        u.email,
        COUNT(DISTINCT t.id)              AS submission_count,
        MAX(tv.submitted_at)              AS last_activity,
        SUM(t.status = 'approved')        AS approved_count,
        SUM(t.status = 'pending_review')  AS pending_count,
        SUM(t.status = 'revision_requested') AS revision_count
    FROM users u
    JOIN theses t  ON t.author_id  = u.id $adviserConstraint
    LEFT JOIN thesis_versions tv ON tv.thesis_id = t.id
    WHERE u.role = 'student'
    $searchClause
    GROUP BY u.id, u.first_name, u.last_name, u.college, u.course, u.year_level, u.student_id, u.email
    ORDER BY last_activity DESC
");
$stmt->execute($params);
$students = $stmt->fetchAll();

ob_start();
?>
<style>
  .student-identity { display: flex; align-items: center; gap: 0.9rem; }
  .av-box-lg { width: 44px; height: 44px; border-radius: 50%; background: var(--crimson); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1rem; }
  
  .stat-summary-bar { display: flex; gap: 2rem; background: var(--off-white); padding: 1.5rem 2rem; border: 1px solid var(--border); border-radius: var(--radius-sm); margin-bottom: 2rem; }
  .stat-item { flex: 1; }
  .stat-label { font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; display: block; margin-bottom: 0.4rem; }
  .stat-val { font-family: var(--font-serif); font-size: 1.5rem; font-weight: 800; color: var(--text-dark); }
</style>
<?php
$extraCss = ob_get_clean();

$current_page = 'students.php';
require_once __DIR__ . '/../includes/layout_top.php';
require_once __DIR__ . '/../includes/layout_sidebar.php';
?>

  <main class="main-content">
    <div class="page-header">
      <div class="page-title">
        <h1>Research <span>Authors</span></h1>
        <p>Managed students and their progressive scholarly contributions.</p>
      </div>
    </div>

    <!-- Controls -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; gap: 1rem;">
      <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 700;">
         ADVISER REGISTRY &bull; <?= count($students) ?> STUDENTS
      </div>
      <form method="GET" style="display: flex; gap: 0.5rem; align-items: center;">
         <div style="position: relative;">
            <i class="ph ph-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search student name..." style="padding: 0.65rem 1rem 0.65rem 2.8rem; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--off-white); font-size: 0.85rem; width: 280px;">
         </div>
         <button type="submit" class="btn btn-primary" style="padding:0.65rem 1rem;"><i class="ph-bold ph-funnel"></i></button>
      </form>
    </div>

    <div class="stat-summary-bar">
       <div class="stat-item">
          <span class="stat-label">Total Submissions</span>
          <div class="stat-val"><?= array_sum(array_column($students, 'submission_count')) ?></div>
       </div>
       <div class="stat-item" style="border-left: 1px solid var(--border); padding-left: 2rem;">
          <span class="stat-label">Accepted Artifacts</span>
          <div class="stat-val" style="color: #065F46;"><?= array_sum(array_column($students, 'approved_count')) ?></div>
       </div>
       <div class="stat-item" style="border-left: 1px solid var(--border); padding-left: 2rem;">
          <span class="stat-label">In Review</span>
          <div class="stat-val" style="color: var(--gold);"><?= array_sum(array_column($students, 'pending_count')) ?></div>
       </div>
    </div>

    <div class="queue-table-wrap">
      <table class="queue-table">
        <thead>
          <tr>
            <th style="padding-left: 2rem;">Student Information</th>
            <th>Academic Profile</th>
            <th>Submission Activity</th>
            <th style="padding-right: 2rem; text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($students)): ?>
            <tr><td colspan="4" style="padding: 4rem; text-align: center; color: var(--text-muted);">No assigned students match the search parameters.</td></tr>
          <?php else: ?>
            <?php foreach ($students as $s): ?>
              <tr>
                <td style="padding-left: 2rem;">
                  <div class="student-identity">
                    <div class="av-box-lg"><?= htmlspecialchars(strtoupper(substr($s['first_name'], 0, 1) . substr($s['last_name'], 0, 1))) ?></div>
                    <div>
                       <div style="font-weight: 800; color: var(--text-dark);">
                         <?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?>
                         <?php if (!empty($s['student_id'])): ?>
                           <span style="font-size: 0.7rem; color: var(--text-muted); font-weight: normal; margin-left: 0.5rem;">(<?= htmlspecialchars($s['student_id']) ?>)</span>
                         <?php endif; ?>
                       </div>
                       <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($s['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td>
                   <div style="font-weight: 700; font-size: 0.85rem; color: var(--text-dark);"><?= htmlspecialchars($s['course'] ?: 'Course Not Provided') ?></div>
                   <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
                     <?= htmlspecialchars($s['college']) ?> 
                     <?php if (!empty($s['year_level'])): ?>
                       &bull; <?= htmlspecialchars($s['year_level']) ?>
                     <?php endif; ?>
                   </div>
                </td>
                <td>
                   <div style="font-weight: 800; font-size: 1rem; color: var(--text-dark);"><?= (int)$s['submission_count'] ?> Total</div>
                   <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.2rem;">Last active: <?= $s['last_activity'] ? date('M j, Y', strtotime($s['last_activity'])) : 'N/A' ?></div>
                </td>
                 <td style="padding-right: 2rem; text-align: right;">
                    <a href="<?= BASE_URL ?>faculty/student_record.php?id=<?= (int)$s['id'] ?>" class="btn-action-outline" style="text-decoration: none; padding: 0.5rem 1.25rem; font-weight: 800; font-size: 0.75rem; border-color: var(--crimson); color: var(--crimson);">
                       <i class="ph ph-list-magnifying-glass"></i> Inspect Records
                    </a>
                 </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </main>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
