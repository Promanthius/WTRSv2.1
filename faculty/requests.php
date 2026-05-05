<?php
require_once __DIR__ . '/../includes/session.php';
require_login(['adviser']);

$user = current_user();
$error = null;
$success = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $request_id = (int)($_POST['request_id'] ?? 0);

    if ($request_id > 0) {
        try {
            $pdo->beginTransaction();

            // Lock the request
            $reqStmt = $pdo->prepare("SELECT student_id, status FROM adviser_requests WHERE id = ? AND adviser_id = ? FOR UPDATE");
            $reqStmt->execute([$request_id, $user['id']]);
            $reqData = $reqStmt->fetch();

            if (!$reqData || $reqData['status'] !== 'pending') {
                throw new Exception("Request not found or no longer pending.");
            }

            if ($action === 'accept') {
                // Lock the adviser row to safely check max_advisees
                $stmt = $pdo->prepare("SELECT max_advisees, (SELECT COUNT(*) FROM users WHERE adviser_id = ?) as current_advisees FROM users WHERE id = ? FOR UPDATE");
                $stmt->execute([$user['id'], $user['id']]);
                $adviserData = $stmt->fetch();

                if ((int)$adviserData['current_advisees'] >= (int)$adviserData['max_advisees']) {
                    throw new Exception("You have reached your maximum number of advisees.");
                }

                // Update request
                $pdo->prepare("UPDATE adviser_requests SET status = 'approved' WHERE id = ?")->execute([$request_id]);
                
                // Assign adviser to student (this handles both new students and 'change adviser' replacements)
                $pdo->prepare("UPDATE users SET adviser_id = ? WHERE id = ?")->execute([$user['id'], $reqData['student_id']]);
                
                // Notify student of approval
                $notifyMsg = "Prof. " . htmlspecialchars($user['last_name']) . " has accepted your adviser request!";
                $pdo->prepare("INSERT INTO notifications (recipient_user_id, sender_user_id, type, message) VALUES (?, ?, 'adviser_request_approved', ?)")
                    ->execute([$reqData['student_id'], $user['id'], $notifyMsg]);
                
                $success = "Student request approved. They have been added to your advisees.";
            } elseif ($action === 'reject') {
                $pdo->prepare("UPDATE adviser_requests SET status = 'rejected' WHERE id = ?")->execute([$request_id]);
                
                // Notify student of rejection
                $notifyMsg = "Prof. " . htmlspecialchars($user['last_name']) . " has declined your adviser request. You can request another adviser.";
                $pdo->prepare("INSERT INTO notifications (recipient_user_id, sender_user_id, type, message) VALUES (?, ?, 'adviser_request_rejected', ?)")
                    ->execute([$reqData['student_id'], $user['id'], $notifyMsg]);
                
                $success = "Student request rejected.";
            }

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

// Fetch current stats
$stmt = $pdo->prepare("SELECT max_advisees, (SELECT COUNT(*) FROM users WHERE adviser_id = ?) as current_advisees FROM users WHERE id = ?");
$stmt->execute([$user['id'], $user['id']]);
$stats = $stmt->fetch();
$current = (int)$stats['current_advisees'];
$max = (int)$stats['max_advisees'];
$isFull = $current >= $max;

// Fetch pending requests
$reqStmt = $pdo->prepare("
    SELECT ar.id, ar.created_at, ar.message, 
           u.id as student_id, u.first_name, u.last_name, u.college, u.email,
           u.bio, u.research_interests, u.experience
    FROM adviser_requests ar
    JOIN users u ON ar.student_id = u.id
    WHERE ar.adviser_id = ? AND ar.status = 'pending'
    ORDER BY ar.created_at ASC
");
$reqStmt->execute([$user['id']]);
$requests = $reqStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Student Research Portfolio
$studentPapers = [];
foreach ($requests as $req) {
    $pStmt = $pdo->prepare("
        SELECT t.id, t.title, u.last_name as adviser_name,
               (SELECT file_path FROM thesis_versions WHERE thesis_id = t.id ORDER BY submitted_at DESC LIMIT 1) as file_path,
               (SELECT GROUP_CONCAT(CONCAT(u2.first_name, ' ', u2.last_name) SEPARATOR ', ')
                FROM users u2
                WHERE u2.id IN (SELECT author_id FROM thesis_authors WHERE thesis_id = t.id)
                   OR u2.id = t.author_id) as author_list
        FROM theses t
        LEFT JOIN users u ON t.adviser_id = u.id
        WHERE t.author_id = :sid1
           OR t.id IN (SELECT thesis_id FROM thesis_authors WHERE author_id = :sid2)
        ORDER BY t.created_at DESC LIMIT 5
    ");
    $pStmt->execute(['sid1' => $req['student_id'], 'sid2' => $req['student_id']]);
    $studentPapers[$req['student_id']] = $pStmt->fetchAll();
}

ob_start();
?>
<style>
  .req-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 2rem;
    margin-bottom: 1.5rem;
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 1.5rem;
    box-shadow: var(--shadow-sm);
  }
  .req-info h4 { margin-bottom: 0.25rem; font-size: 1.2rem; font-family: var(--font-serif); }
  .req-meta { font-size: 0.85rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
  .req-message { margin-top: 1rem; padding: 1rem; background: var(--off-white); border-radius: var(--radius-sm); font-size: 0.95rem; color: var(--text-mid); line-height: 1.5; border-left: 3px solid var(--crimson); font-style: italic; }
  .req-actions { display: flex; gap: 0.75rem; flex-direction: column; }
  
  .capacity-bar {
    background: var(--off-white);
    border-radius: 100px;
    height: 8px;
    width: 100%;
    margin-top: 0.5rem;
    overflow: hidden;
  }
  .capacity-fill {
    height: 100%;
    background: var(--crimson);
    transition: width 0.3s ease;
  }
  .capacity-fill.full {
    background: #DC2626;
  }
</style>
<?php
$extraCss = ob_get_clean();

$current_page = 'requests.php';
require_once __DIR__ . '/../includes/layout_top.php';
require_once __DIR__ . '/../includes/layout_sidebar.php';
?>

<main class="main-content">
  <div class="page-header">
    <div class="page-title">
      <h1>Advisee <span>Requests</span></h1>
      <p>Manage incoming requests from students who wish to select you as their adviser.</p>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-error" style="background:#FEE2E2; color:#991B1B; padding:1rem; border-radius:4px; margin-bottom:1.5rem; border:1px solid #FECACA;">
      <i class="ph-bold ph-warning-circle"></i> <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>
  
  <?php if ($success): ?>
    <div class="alert alert-success" style="background:#D1FAE5; color:#065F46; padding:1rem; border-radius:4px; margin-bottom:1.5rem; border:1px solid #6EE7B7;">
      <i class="ph-bold ph-check-circle"></i> <?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>

  <div style="display: grid; grid-template-columns: 2.5fr 1fr; gap: 2rem;">
    <div>
        <h3 style="font-family: var(--font-serif); font-size: 1.3rem; margin-bottom: 1.5rem;">Pending Requests (<?= count($requests) ?>)</h3>
        
        <?php if (empty($requests)): ?>
            <div class="empty-state card-academic" style="padding: 3rem 2rem;">
                <i class="ph-fill ph-inbox" style="font-size:3rem; color:var(--text-muted); opacity:0.3;"></i>
                <p style="margin-top:1rem;">You currently have no pending advisee requests.</p>
            </div>
        <?php else: ?>
            <?php foreach ($requests as $req): ?>
                <div class="req-card">
                    <div class="req-info">
                        <div class="req-meta"><?= htmlspecialchars($req['college']) ?> &bull; <?= time_ago($req['created_at']) ?></div>
                        <h4><?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?></h4>
                        
                        <?php if (!empty($req['message'])): ?>
                            <div class="req-message">
                                "<?= nl2br(htmlspecialchars($req['message'])) ?>"
                            </div>
                        <?php endif; ?>

                        <div style="margin-top: 1rem;">
                            <button type="button" onclick='viewStudentProfile(<?= json_encode($req) ?>, <?= json_encode($studentPapers[$req['student_id']]) ?>)' class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.4rem 0.8rem; border-style: dashed;">
                                <i class="ph ph-user-list"></i> View Student Profile & Track Record
                            </button>
                        </div>
                    </div>
                    <div class="req-actions">
                        <form method="POST" style="display:contents;" class="form-confirm" data-confirm-title="Accept Request?" data-confirm-message="This student will be assigned to you as an advisee.">
                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                            <input type="hidden" name="action" value="accept">
                            <?php if ($isFull): ?>
                                <button type="button" class="btn btn-primary" style="opacity:0.5; cursor:not-allowed;" title="Capacity reached">Accept</button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-primary">Accept Request</button>
                            <?php endif; ?>
                        </form>

                        <form method="POST" style="display:contents;" class="form-confirm" data-confirm-title="Decline Request?" data-confirm-message="This student will be notified of your decision.">
                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn btn-secondary" style="color:#DC2626; border-color:#FECACA;">Decline</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div>
        <div class="card-academic" style="padding: 1.5rem;">
            <h4 style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); margin-bottom: 1.25rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">CAPACITY</h4>
            
            <div style="font-size: 2rem; font-weight: 800; color: var(--text-dark); line-height: 1;">
                <?= $current ?> <span style="font-size:1rem; color:var(--text-muted);">/ <?= $max ?></span>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">Active Advisees</div>
            
            <?php $pct = min(100, ($current / max(1, $max)) * 100); ?>
            <div class="capacity-bar">
                <div class="capacity-fill <?= $isFull ? 'full' : '' ?>" style="width: <?= $pct ?>%;"></div>
            </div>
            
            <?php if ($isFull): ?>
                <div style="margin-top: 1rem; font-size: 0.8rem; color: #DC2626; font-weight: 700;">
                    <i class="ph-bold ph-warning"></i> You have reached your maximum advisee limit. You cannot accept new requests.
                </div>
            <?php endif; ?>
        </div>
    </div>
  </div>

  <!-- Student Profile Modal -->
  <div id="studentProfileModal" class="pdf-preview-modal" style="display: none; position: fixed; z-index: 10500; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); align-items: center; justify-content: center; backdrop-filter: blur(4px);">
      <div class="card-academic" style="width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; padding: 0; border: none;">
          <div style="background: var(--crimson); color: white; padding: 2rem; position: relative;">
              <button onclick="closeStudentModal()" style="position: absolute; right: 1.5rem; top: 1.25rem; background: none; border: none; color: white; font-size: 2rem; cursor: pointer;">&times;</button>
              <div style="display: flex; gap: 1.5rem; align-items: center;">
                  <div id="stuInitial" style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; font-weight: 800;"></div>
                  <div>
                      <h2 id="stuName" style="font-family: var(--font-serif); margin: 0; font-size: 1.5rem;"></h2>
                      <div id="stuMeta" style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; opacity: 0.8; letter-spacing: 0.05em;"></div>
                  </div>
              </div>
          </div>
          <div style="padding: 2.5rem;">
              <div style="margin-bottom: 2rem;">
                  <h4 style="font-weight: 800; color: var(--crimson); margin-bottom: 0.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em;">Student Biography</h4>
                  <p id="stuBio" style="line-height: 1.6; color: var(--text-mid); font-family: var(--font-serif); font-size: 1.05rem;"></p>
              </div>
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                  <div>
                      <h4 style="font-weight: 800; color: var(--crimson); margin-bottom: 0.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em;">Research Interests</h4>
                      <p id="stuInterests" style="line-height: 1.5; font-size: 0.95rem; color: var(--text-dark);"></p>
                  </div>
                  <div>
                      <h4 style="font-weight: 800; color: var(--crimson); margin-bottom: 0.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em;">Academic Track Record</h4>
                      <p id="stuExperience" style="line-height: 1.5; font-size: 0.95rem; color: var(--text-dark);"></p>
                  </div>
              </div>

              <div style="margin-bottom: 1.5rem;">
                  <h4 style="font-weight: 800; color: var(--crimson); margin-bottom: 1rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em;">Research Portfolio / History</h4>
                  <div id="stuPapersList" style="display: flex; flex-direction: column; gap: 0.75rem;">
                      <!-- Papers will be injected here -->
                  </div>
              </div>

              <div style="padding-top: 1.5rem; border-top: 1px solid var(--border); text-align: right;">
                  <button onclick="closeStudentModal()" class="btn btn-secondary" style="padding: 0.8rem 2rem;">Close Profile</button>
              </div>
          </div>
      </div>
  </div>

  <!-- Portfolio PDF Viewer Modal -->
  <div id="portfolioPdfModal" class="pdf-preview-modal" style="display: none; position: fixed; z-index: 10600; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); align-items: center; justify-content: center; backdrop-filter: blur(8px);">
      <div style="background: white; width: 95%; height: 95%; border-radius: 12px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);">
          <div style="padding: 1rem 2rem; background: var(--off-white); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
              <div>
                  <div style="font-size: 0.65rem; font-weight: 800; color: var(--crimson); text-transform: uppercase; letter-spacing: 0.05em;">Portfolio Preview</div>
                  <h3 id="portfolioPdfTitle" style="font-size: 1.1rem; font-family: var(--font-serif); margin: 0; color: var(--text-dark);"></h3>
              </div>
              <button onclick="closePdfModal()" class="btn btn-secondary" style="padding: 0.5rem 1rem;">Close Preview</button>
          </div>
          <div style="flex: 1; background: #525659;">
              <iframe id="portfolioPdfFrame" src="" style="width: 100%; height: 100%; border: none;"></iframe>
          </div>
      </div>
  </div>

</main>

<script>
function viewStudentProfile(student, papers) {
    document.getElementById('stuInitial').innerText = student.first_name[0] + student.last_name[0];
    document.getElementById('stuName').innerText = student.first_name + " " + student.last_name;
    document.getElementById('stuMeta').innerText = student.college + " • " + student.email;
    document.getElementById('stuBio').innerText = student.bio || "No biography provided.";
    document.getElementById('stuInterests').innerText = student.research_interests || "Not specified.";
    document.getElementById('stuExperience').innerText = student.experience || "No track record provided.";
    
    // Inject Papers
    const list = document.getElementById('stuPapersList');
    list.innerHTML = "";
    if (!papers || papers.length === 0) {
        list.innerHTML = "<p style='font-size:0.85rem; color:var(--text-muted); font-style:italic;'>No prior research papers found for this student.</p>";
    } else {
        papers.forEach(p => {
            const div = document.createElement('div');
            div.style.cssText = "padding: 1rem; background: var(--off-white); border-radius: var(--radius-sm); border-left: 3px solid var(--gold); display: flex; justify-content: space-between; align-items: center; gap: 1rem;";
            div.innerHTML = `
                <div style="flex: 1;">
                    <div style="font-weight:700; font-size:0.9rem; color:var(--text-dark); margin-bottom:0.2rem;">${p.title}</div>
                    <div style="font-size:0.7rem; color:var(--text-muted); font-weight:800; text-transform:uppercase;">
                        Authors: ${p.author_list || 'Unknown'} <br>
                        Adviser: Dr. ${p.adviser_name || 'Unassigned'}
                    </div>
                </div>
                ${p.file_path ? `<button onclick="viewFullPaper('${p.file_path}', '${p.title.replace(/'/g, "\\'")}')" class="btn btn-secondary" style="font-size: 0.65rem; padding: 0.4rem 0.6rem; flex-shrink: 0; background: white; white-space: nowrap;">
                    <i class="ph ph-file-pdf"></i> View Full Paper
                </button>` : ''}
            `;
            list.appendChild(div);
        });
    }

    document.getElementById('studentProfileModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function viewFullPaper(path, title) {
    const frame = document.getElementById('portfolioPdfFrame');
    const modal = document.getElementById('portfolioPdfModal');
    const titleEl = document.getElementById('portfolioPdfTitle');
    
    frame.src = "<?= BASE_URL ?>public/uploads/" + path;
    titleEl.innerText = title;
    modal.style.display = 'flex';
    // Don't change body overflow here, keep it hidden from profile modal
}

function closePdfModal() {
    document.getElementById('portfolioPdfModal').style.display = 'none';
    document.getElementById('portfolioPdfFrame').src = "";
}

function closeStudentModal() {
    document.getElementById('studentProfileModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

window.onclick = function(e) { 
    if (e.target == document.getElementById('studentProfileModal')) closeStudentModal(); 
}
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
