<?php
require_once __DIR__ . '/../includes/session.php';
require_login(['student']);

$user = current_user();
$error = null;
$success = null;

// Handle Request Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adviser_id'])) {
    $adviser_id = (int)$_POST['adviser_id'];
    $message = trim($_POST['message'] ?? '');

    // Check for existing pending request
    $check = $pdo->prepare("SELECT id FROM adviser_requests WHERE student_id = ? AND adviser_id = ? AND status = 'pending'");
    $check->execute([$user['id'], $adviser_id]);
    
    if ($check->fetch()) {
        $error = "You already have a pending request with this faculty member.";
    } elseif (!empty($user['adviser_id'])) {
        $error = "You already have an assigned adviser. You must be released by your current adviser before applying for a new one.";
    } else {
        $ins = $pdo->prepare("INSERT INTO adviser_requests (student_id, adviser_id, message) VALUES (?, ?, ?)");
        if ($ins->execute([$user['id'], $adviser_id, $message])) {
            $success = "Mentorship request sent successfully.";
            
            // Notify the adviser
            $pdo->prepare("INSERT INTO notifications (recipient_user_id, sender_user_id, type, message) VALUES (?, ?, 'adviser_request', ?)")
                ->execute([$adviser_id, $user['id'], "A student has requested you as their research adviser."]);
        } else {
            $error = "Failed to send request.";
        }
    }
}

// Fetch advisers
$stmt = $pdo->prepare("SELECT u.*, 
                        (SELECT COUNT(*) FROM adviser_requests WHERE adviser_id = u.id AND status = 'accepted') as student_count
                      FROM users u WHERE role = 'adviser' AND status = 'active'");
$stmt->execute();
$advisers = $stmt->fetchAll();

// Fetch Research Records for these advisers
$profPapers = [];
foreach ($advisers as $adv) {
    $pStmt = $pdo->prepare("
        SELECT t.id, t.title, 
               (SELECT GROUP_CONCAT(CONCAT(u2.first_name, ' ', u2.last_name) SEPARATOR ', ')
                FROM users u2
                WHERE u2.id IN (SELECT author_id FROM thesis_authors WHERE thesis_id = t.id)
                   OR u2.id = t.author_id) as author_list
        FROM theses t 
        WHERE t.adviser_id = ? AND t.status = 'archived' LIMIT 5
    ");
    $pStmt->execute([$adv['id']]);
    $profPapers[$adv['id']] = $pStmt->fetchAll();
}

// Fetch my requests
$myReqsStmt = $pdo->prepare("SELECT r.*, u.first_name, u.last_name, u.college 
                             FROM adviser_requests r 
                             JOIN users u ON r.adviser_id = u.id 
                             WHERE r.student_id = ? ORDER BY r.created_at DESC");
$myReqsStmt->execute([$user['id']]);
$myRequests = $myReqsStmt->fetchAll();

require_once __DIR__ . '/../includes/layout_top.php';
require_once __DIR__ . '/../includes/layout_sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <div class="page-title">
            <h1>Mentorship <span>Program</span></h1>
            <p>Connect with expert faculty members to guide your research journey.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom: 2rem;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom: 2rem;"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 2fr 1.2fr; gap: 2.5rem;">
        
        <section>
            <h2 style="font-family: var(--font-serif); margin-bottom: 1.5rem;">Available Advisers</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <?php foreach ($advisers as $adv): ?>
                    <div class="card-academic" style="padding: 2rem; display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1.25rem;">
                                <div style="width: 50px; height: 50px; background: var(--crimson); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.2rem;">
                                    <?= strtoupper(substr($adv['first_name'],0,1).substr($adv['last_name'],0,1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight: 800; font-size: 1.1rem;">Prof. <?= htmlspecialchars($adv['last_name']) ?></div>
                                    <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;"><?= htmlspecialchars($adv['college']) ?></div>
                                </div>
                            </div>
                            <div style="font-size: 0.9rem; color: var(--text-mid); margin-bottom: 1.5rem;">
                                Currently advising <strong style="color: var(--crimson);"><?= $adv['student_count'] ?></strong> students.
                            </div>
                            <div style="margin-bottom: 1.5rem;">
                                <button type="button" 
                                    onclick='viewProfessorProfile(<?= json_encode($adv) ?>, <?= json_encode($profPapers[$adv['id']]) ?>)' 
                                    class="btn btn-secondary" style="width: 100%; font-size: 0.8rem; border-style: dashed;">
                                    <i class="ph ph-user-focus"></i> View Profile & Track Record
                                </button>
                            </div>
                        </div>
                        <?php if (empty($user['adviser_id'])): ?>
                            <button onclick="openRequestModal(<?= $adv['id'] ?>, '<?= htmlspecialchars($adv['first_name'].' '.$adv['last_name']) ?>')" class="btn btn-primary" style="width: 100%;">
                                Request Advising
                            </button>
                        <?php else: ?>
                            <button disabled class="btn btn-secondary" style="width: 100%; opacity: 0.5; cursor: not-allowed;" title="You already have an adviser">
                                Request Advising
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <aside>
            <h2 style="font-family: var(--font-serif); margin-bottom: 1.5rem;">My Requests</h2>
            <?php if (empty($myRequests)): ?>
                <div class="card-academic" style="text-align: center; padding: 3rem;">
                    <p style="color: var(--text-muted);">No active requests found.</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php foreach ($myRequests as $req): ?>
                        <div class="card-academic" style="padding: 1.5rem; border-left: 4px solid <?= $req['status'] === 'accepted' ? '#059669' : ($req['status'] === 'rejected' ? '#DC2626' : 'var(--gold)') ?>">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div>
                                    <div style="font-weight: 800; font-size: 0.95rem;">Prof. <?= htmlspecialchars($req['last_name']) ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= time_ago($req['created_at']) ?></div>
                                </div>
                                <span class="badge"><?= strtoupper($req['status']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </aside>

    </div>

    <!-- Request Modal -->
    <div id="requestModal" class="pdf-preview-modal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); align-items: center; justify-content: center;">
        <form action="" method="POST" class="card-academic" style="width: 90%; max-width: 500px; padding: 2.5rem;">
            <h3 id="modalAdviserName" style="font-family: var(--font-serif); margin-bottom: 0.5rem;">Request Mentorship</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">Introduce yourself and briefly describe your research interests.</p>
            
            <input type="hidden" name="adviser_id" id="modalAdviserId">
            <div class="form-group">
                <label class="form-label">Message (Optional)</label>
                <textarea name="message" rows="5" class="form-control" placeholder="Tell the professor why you want them as your adviser..."></textarea>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="button" onclick="closeModal()" class="btn btn-secondary" style="flex: 1;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex: 1;">Send Request</button>
            </div>
        </form>
    </div>

    <!-- Professor Profile Modal -->
    <div id="profileModal" class="pdf-preview-modal" style="display: none; position: fixed; z-index: 10500; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div class="card-academic" style="width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; padding: 0; border: none;">
            <div style="background: var(--crimson); color: white; padding: 2.5rem; position: relative;">
                <button onclick="closeProfileModal()" style="position: absolute; right: 1.5rem; top: 1.5rem; background: none; border: none; color: white; font-size: 2rem; cursor: pointer;">&times;</button>
                <div style="display: flex; gap: 1.5rem; align-items: center;">
                    <div id="profInitial" style="width: 80px; height: 80px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 800;"></div>
                    <div>
                        <h2 id="profName" style="font-family: var(--font-serif); margin: 0; font-size: 1.8rem;"></h2>
                        <div id="profCollege" style="font-size: 0.8rem; font-weight: 800; text-transform: uppercase; opacity: 0.8; letter-spacing: 0.05em;"></div>
                    </div>
                </div>
            </div>
            <div style="padding: 2.5rem;">
                <div style="margin-bottom: 2rem;">
                    <h4 style="font-weight: 800; color: var(--crimson); margin-bottom: 0.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em;">Biography & Background</h4>
                    <p id="profBio" style="line-height: 1.6; color: var(--text-mid); font-family: var(--font-serif); font-size: 1.05rem;"></p>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                    <div>
                        <h4 style="font-weight: 800; color: var(--crimson); margin-bottom: 0.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em;">Research Interests</h4>
                        <p id="profInterests" style="line-height: 1.5; font-size: 0.95rem; color: var(--text-dark);"></p>
                    </div>
                    <div>
                        <h4 style="font-weight: 800; color: var(--crimson); margin-bottom: 0.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em;">Track Record / Experience</h4>
                        <p id="profExperience" style="line-height: 1.5; font-size: 0.95rem; color: var(--text-dark);"></p>
                    </div>
                </div>

                <div style="margin-bottom: 2.5rem;">
                    <h4 style="font-weight: 800; color: var(--crimson); margin-bottom: 1rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em;">Mentored Research (Last 5)</h4>
                    <div id="profPapersList" style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <!-- Papers will be injected here -->
                    </div>
                </div>

                <div style="padding-top: 1.5rem; border-top: 1px solid var(--border);">
                    <button onclick="closeProfileModal(); openRequestModal(currentProfId, currentProfName);" class="btn btn-primary" style="width: 100%; padding: 1rem;">
                        Request Mentorship from this Professor
                    </button>
                </div>
            </div>
        </div>
    </div>

</main>

<script>
let currentProfId = null;
let currentProfName = null;

function viewProfessorProfile(prof, papers) {
    currentProfId = prof.id;
    currentProfName = prof.first_name + " " + prof.last_name;
    
    document.getElementById('profInitial').innerText = prof.first_name[0] + prof.last_name[0];
    document.getElementById('profName').innerText = "Prof. " + prof.first_name + " " + prof.last_name;
    document.getElementById('profCollege').innerText = prof.college;
    document.getElementById('profBio').innerText = prof.bio || "No biography provided yet.";
    document.getElementById('profInterests').innerText = prof.research_interests || "Not specified.";
    document.getElementById('profExperience').innerText = prof.experience || "No track record listed.";
    
    // Inject Papers
    const list = document.getElementById('profPapersList');
    list.innerHTML = "";
    if (!papers || papers.length === 0) {
        list.innerHTML = "<p style='font-size:0.85rem; color:var(--text-muted); font-style:italic;'>No archived research papers found for this professor.</p>";
    } else {
        papers.forEach(p => {
            const div = document.createElement('div');
            div.style.cssText = "padding: 0.85rem 1.15rem; background: var(--off-white); border-radius: var(--radius-sm); border-left: 3px solid var(--gold);";
            div.innerHTML = `
                <div style="font-weight:700; font-size:0.9rem; color:var(--text-dark); margin-bottom:0.2rem;">${p.title}</div>
                <div style="font-size:0.7rem; color:var(--text-muted); font-weight:800; text-transform:uppercase;">Students: ${p.author_list || 'Unknown'}</div>
            `;
            list.appendChild(div);
        });
    }

    document.getElementById('profileModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeProfileModal() {
    document.getElementById('profileModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function openRequestModal(id, name) {
    document.getElementById('modalAdviserId').value = id;
    document.getElementById('modalAdviserName').innerText = "Request Prof. " + name;
    document.getElementById('requestModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeModal() {
    document.getElementById('requestModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}
window.onclick = function(e) { if(e.target == document.getElementById('requestModal')) closeModal(); }
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
