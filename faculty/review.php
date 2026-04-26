<?php
require_once __DIR__ . '/../includes/session.php';
require_login(['adviser']);

$user = current_user();
$thesis_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

function faculty_status_label(string $status): string
{
    if ($status === 'pending_review') return 'Pending Adviser Approval';
    if ($status === 'revision_requested') return 'Needs Revision';
    if ($status === 'approved') return 'Accepted';
    if ($status === 'archived') return 'Published';
    if ($status === 'rejected') return 'Rejected';
    if ($status === 'draft') return 'Awaiting Adviser';
    return ucwords(str_replace('_', ' ', $status));
}

function deleteThesisWithFiles(PDO $pdo, int $thesisId): bool
{
    $vStmt = $pdo->prepare("SELECT file_path FROM thesis_versions WHERE thesis_id = ?");
    $vStmt->execute([$thesisId]);
    $paths = $vStmt->fetchAll(PDO::FETCH_COLUMN);

    $del = $pdo->prepare('DELETE FROM theses WHERE id = ?');
    $result = $del->execute([$thesisId]);

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

    return $result !== false;
}

// ─── DETAIL VIEW ───────────────────────────────────────────────────────────────
if ($thesis_id) {
    $stmt = $pdo->prepare("SELECT t.*, u.first_name, u.last_name, u.college
                           FROM theses t
                           JOIN users u ON t.author_id = u.id
                           WHERE t.id = :id AND t.adviser_id = :adviser_id");
    $stmt->execute(['id' => $thesis_id, 'adviser_id' => $user['id']]);
    $thesis = $stmt->fetch();

    if (!$thesis) {
        header('Location: ' . BASE_URL . 'faculty/review.php');
        exit;
    }

    $vStmt = $pdo->prepare("SELECT * FROM thesis_versions WHERE thesis_id = :id ORDER BY submitted_at DESC");
    $vStmt->execute(['id' => $thesis_id]);
    $versions = $vStmt->fetchAll();
    $latestVersion = $versions[0] ?? null;

    $error = null;
    $success = null;

    // --- Auto-mark Notifications as Read ---
    if ($thesis['status'] === 'draft') {
        $markReadStmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_user_id = :recipient_id AND thesis_id = :thesis_id AND type = 'thesis_request'");
        $markReadStmt->execute(['recipient_id' => $user['id'], 'thesis_id' => $thesis_id]);
    }

    // --- ADVISER DELETE: Full authority to remove assigned thesis records ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_thesis_id'])) {
        $deleteId = (int) $_POST['delete_thesis_id'];
        if ($deleteId <= 0 || $deleteId !== $thesis_id) {
            header('Location: ' . BASE_URL . 'faculty/review.php?deleted=0');
            exit;
        }

        $check = $pdo->prepare('SELECT id, adviser_id FROM theses WHERE id = ?');
        $check->execute([$deleteId]);
        $row = $check->fetch(PDO::FETCH_ASSOC);

        if (!$row || (int) $row['adviser_id'] !== (int) $user['id']) {
            header('Location: ' . BASE_URL . 'faculty/review.php?deleted=0');
            exit;
        }

        if (deleteThesisWithFiles($pdo, $deleteId)) {
            header('Location: ' . BASE_URL . 'faculty/review.php?deleted=1');
            exit;
        }

        header('Location: ' . BASE_URL . 'faculty/review.php?deleted=0');
        exit;
    }

    // --- ADVISER EDIT: Accept/Decline Request ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_action'])) {
        $action = $_POST['request_action'];
        if (in_array($action, ['accept_request', 'decline_request'], true)) {
            $pdo->beginTransaction();
            try {
                if ($action === 'accept_request') {
                    $updT = $pdo->prepare("UPDATE theses SET status = 'pending_review', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $updT->execute([$thesis_id]);
                    $thesis['status'] = 'pending_review';
                    $studentMsg = "Your thesis request was accepted by Prof. " . $user['last_name'] . ". Review has started.";
                    $success = "Thesis request accepted. It is now in your review queue.";
                } else {
                    $updT = $pdo->prepare("UPDATE theses SET adviser_id = NULL, status = 'draft', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $updT->execute([$thesis_id]);
                    $thesis['status'] = 'draft';
                    $thesis['adviser_id'] = null; // Important to reflect in UI
                    $studentMsg = "Your thesis request was declined by Prof. " . $user['last_name'] . ". Please choose another adviser.";
                    $success = "Thesis request declined. The student has been notified.";
                }

                $pdo->prepare("INSERT INTO notifications (recipient_user_id, sender_user_id, thesis_id, type, message) VALUES (?, ?, ?, 'thesis_request_decision', ?)")
                    ->execute([$thesis['author_id'], $user['id'], $thesis_id, $studentMsg]);

                $pdo->commit();
                
                // If declined, the adviser shouldn't see this anymore. Redirect to index.
                if ($action === 'decline_request') {
                    header("Location: " . BASE_URL . "faculty/index.php");
                    exit;
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Transaction failed: " . $e->getMessage();
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['request_action']) && $latestVersion && $latestVersion['status'] === 'pending') {
        $action   = $_POST['action'] ?? '';
        $feedback = trim($_POST['feedback'] ?? '');

        if (!in_array($action, ['approved', 'revision_requested', 'rejected'])) {
            $error = "Please select a valid formal decision.";
        } elseif (empty($feedback) && in_array($action, ['revision_requested', 'rejected'])) {
            $error = "Constructive feedback is mandatory for requesting revisions or rejections.";
        } else {
            // Auto-fill feedback for approvals if adviser left it empty
            if (empty($feedback) && $action === 'approved') {
                $feedback = 'Your manuscript has been verified and approved for publication in the repository. Congratulations!';
            }
            $pdo->beginTransaction();
            try {
                // Map internal status for versioning
                $versionStatus = ($action === 'approved') ? 'approved' : 'rejected';

                // 1. Update Version Record
                $updV = $pdo->prepare("UPDATE thesis_versions SET status = ?, feedback = ? WHERE id = ?");
                $updV->execute([$versionStatus, $feedback, $latestVersion['id']]);

                // 2. Update Thesis Master Status
                // "Accept" (approved) action maps to 'approved' (Needs editorial polish)
                // instead of immediate 'archived'.
                $thesisStatus = $action;
                $updT = $pdo->prepare("UPDATE theses SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $updT->execute([$thesisStatus, $thesis_id]);

                // 3. Log Activity
                $logMsg = "Faculty review processed: '$action' for artifact {$thesis['thesis_code']}";
                $pdo->prepare("INSERT INTO activity_logs (user_id, action_type, description, ip_address) VALUES (?, 'review', ?, ?)")
                    ->execute([$user['id'], $logMsg, $_SERVER['REMOTE_ADDR']]);

                // 4. Notify the student about the decision
                $decisionMsgs = [
                    'approved'           => "Your thesis has been Accepted.",
                    'revision_requested' => "Revision requested: " . (mb_strlen($feedback) > 50 ? mb_substr($feedback, 0, 50) . '...' : $feedback),
                    'rejected'           => "Thesis rejected."
                ];
                $studentMsg = $decisionMsgs[$action] ?? ("Update on your thesis: " . $action);
                
                $pdo->prepare("INSERT INTO notifications (recipient_user_id, sender_user_id, thesis_id, type, message) VALUES (?, ?, ?, 'thesis_review_decision', ?)")
                    ->execute([$thesis['author_id'], $user['id'], $thesis_id, $studentMsg]);

                $pdo->commit();
                $success = "Review processed successfully.";

                // Update local vars for immediate UI reflection
                $thesis['status'] = $thesisStatus;
                $latestVersion['status'] = $versionStatus;
                $latestVersion['feedback'] = $feedback;

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Transaction failed: " . $e->getMessage();
            }
        }
    }

    // --- ADVISER EDIT: Update Metadata (only if approved/accepted) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_metadata' && $thesis['status'] === 'approved') {
        $newTitle = trim($_POST['title'] ?? '');
        $newAbstract = trim($_POST['abstract'] ?? '');
        
        if (empty($newTitle)) {
            $error = "Thesis title cannot be empty.";
        } else {
            $updMeta = $pdo->prepare("UPDATE theses SET title = ?, abstract = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            if ($updMeta->execute([$newTitle, $newAbstract, $thesis_id])) {
                $thesis['title'] = $newTitle;
                $thesis['abstract'] = $newAbstract;
                $success = "Archival metadata refined successfully.";
                
                // Log activity
                $pdo->prepare("INSERT INTO activity_logs (user_id, action_type, description, ip_address) VALUES (?, 'edit', ?, ?)")
                    ->execute([$user['id'], "Refined manuscript metadata for {$thesis['thesis_code']}", $_SERVER['REMOTE_ADDR']]);
            } else {
                $error = "Failed to update metadata.";
            }
        }
    }

    // --- ADVISER EDIT: Publish Artifact ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'publish_artifact' && $thesis['status'] === 'approved') {
        $updPub = $pdo->prepare("UPDATE theses SET status = 'archived', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            if ($updPub->execute([$thesis_id])) {
                $thesis['status'] = 'archived';
                $success = "Scholarly artifact has been formally published to the repository.";
                
                // Notify student
                $studentMsg = "Your thesis has been formally published to the Institutional Repository.";
                $pdo->prepare("INSERT INTO notifications (recipient_user_id, sender_user_id, thesis_id, type, message) VALUES (?, ?, ?, 'thesis_published', ?)")
                    ->execute([$thesis['author_id'], $user['id'], $thesis_id, $studentMsg]);
                
            // Log activity
            $pdo->prepare("INSERT INTO activity_logs (user_id, action_type, description, ip_address) VALUES (?, 'publish', ?, ?)")
                ->execute([$user['id'], "Formally published artifact {$thesis['thesis_code']}", $_SERVER['REMOTE_ADDR']]);
        } else {
            $error = "Failed to publish artifact.";
        }
    }

    ob_start();
?>
<style>
  /* ---------------------------------------------------------
     ACADEMIC WORKSPACE LAYOUT (Crimsonian Pro)
     --------------------------------------------------------- */
  .workspace-container {
    display: flex;
    gap: 0;
    height: calc(100vh - var(--topbar-h));
    margin: calc(var(--topbar-h) * -1) -2.5rem 0; /* Negate main-content padding to go edge-to-edge */
    overflow: hidden;
  }

  /* Manuscript Side */
  .manuscript-viewport {
    flex: 1;
    background: #525659; /* Standard PDF viewer background */
    padding: 0;
    position: relative;
    display: flex;
    flex-direction: column;
  }
  
  .manuscript-header {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 1rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    z-index: 10;
  }

  .manuscript-frame {
    flex: 1;
    width: 100%;
    border: none;
    background: #E5E7EB;
  }

  /* Evaluation Sidebar */
  .evaluation-sidebar {
    width: 440px;
    background: var(--surface);
    border-left: 2px solid var(--border);
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    box-shadow: -4px 0 24px rgba(0,0,0,0.05);
  }

  .sidebar-section {
    padding: 2.5rem;
    border-bottom: 1px solid var(--border-faint);
  }

  .scholarly-header {
    border-bottom: 3px double var(--gold-faint);
    padding-bottom: 1.5rem;
    margin-bottom: 2rem;
  }
  
  .scholarly-title {
    font-family: var(--font-serif);
    font-size: 1.5rem;
    line-height: 1.3;
    color: var(--crimson);
    margin: 0.5rem 0;
  }
  
  .author-badge {
    background: var(--off-white);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
  }

  .sidebar-form-card {
    background: var(--off-white);
    border: 1px solid var(--border-strong);
    border-radius: var(--radius-sm);
    padding: 1.75rem;
    margin-top: 1rem;
  }

  /* Version Switcher Navigation */
  .version-nav {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
    overflow-x: auto;
    padding-bottom: 0.5rem;
  }
  .v-pill {
    padding: 0.4rem 0.8rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 800;
    text-decoration: none;
    background: white;
    border: 1px solid var(--border);
    color: var(--text-muted);
    white-space: nowrap;
  }
  .v-pill.active {
    background: var(--crimson);
    color: white;
    border-color: var(--crimson);
  }

  /* Hide global main-content padding for workspace */
  .main-content:has(.workspace-container) {
    padding: 0 !important;
    overflow: hidden;
  }
</style>
<?php
    $extraCss = ob_get_clean();
    require_once __DIR__ . '/../includes/layout_top.php';
    require_once __DIR__ . '/../includes/layout_sidebar.php';
?>

  <main class="main-content">

    <?php if ($error): ?>
      <div style="position: absolute; top: 80px; left: 50%; transform: translateX(-50%); z-index: 100; width: 80%; padding: 1rem; background: #991B1B; color: white; border-radius: var(--radius-sm); font-weight: 700; box-shadow: var(--shadow-lg);">
        <i class="ph-bold ph-warning-circle" style="margin-right:0.75rem;"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div style="position: absolute; top: 80px; left: 50%; transform: translateX(-50%); z-index: 100; width: 80%; padding: 1rem; background: #065F46; color: white; border-radius: var(--radius-sm); font-weight: 700; box-shadow: var(--shadow-lg);">
        <i class="ph-bold ph-check-circle" style="margin-right:0.75rem;"></i> <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>

    <div class="workspace-container">
      
      <!-- 📄 MANUSCRIPT VIEWPORT -->
      <section class="manuscript-viewport">
        <div class="manuscript-header">
           <div>
              <span class="badge badge-pending" style="font-size: 0.6rem; letter-spacing: 0.1em; padding: 0.25rem 0.6rem;">
                ITERATION <?= $latestVersion['version_number'] ?>
              </span>
              <h2 style="font-size: 1rem; margin: 0.4rem 0 0; color: var(--text-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 500px;">
                <?= htmlspecialchars($thesis['title']) ?>
              </h2>
           </div>
           <div style="display: flex; gap: 0.75rem;">
              <a href="<?= BASE_URL ?>public/uploads/<?= htmlspecialchars($latestVersion['file_path']) ?>" target="_blank" class="btn btn-secondary" style="padding: 0.5rem 1rem;">
                <i class="ph ph-arrow-square-out"></i> External View
              </a>
              <button onclick="document.querySelector('.manuscript-frame').requestFullscreen()" class="btn btn-primary" style="padding: 0.5rem 1rem; background: var(--text-dark);">
                <i class="ph ph-corners-out"></i> Full Screen
              </button>
           </div>
        </div>

        <?php if ($latestVersion): ?>
          <iframe 
            src="<?= BASE_URL ?>public/uploads/<?= htmlspecialchars($latestVersion['file_path']) ?>#toolbar=0" 
            class="manuscript-frame"
            title="Manuscript Viewer"
          ></iframe>
        <?php else: ?>
          <div style="flex:1; display:flex; align-items:center; justify-content:center; color:white; background:#1A1108;">
             <div style="text-align:center;">
                <i class="ph ph-file-x" style="font-size:4rem; opacity:0.3;"></i>
                <p style="margin-top:1rem;">No valid manuscript submission found for this repository artifact.</p>
             </div>
          </div>
        <?php endif; ?>
      </section>

      <!-- 🖋️ EVALUATION SIDEBAR -->
      <aside class="evaluation-sidebar">
         
         <div class="sidebar-section">
            <nav style="margin-bottom: 2rem; font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">
               <a href="<?= BASE_URL ?>faculty/review.php" style="color:var(--text-muted);">Review Queue</a>
               <i class="ph ph-caret-right" style="margin: 0 0.4rem; opacity:0.5;"></i>
               <span style="color:var(--crimson);">Workspace</span>
            </nav>

            <div class="scholarly-header">
               <span style="font-size: 0.65rem; font-weight: 800; color: var(--gold); letter-spacing: 0.2em; text-transform: uppercase;">Repository Artifact</span>
               <h1 class="scholarly-title"><?= htmlspecialchars($thesis['thesis_code']) ?></h1>
               <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">Institutional submission assigned for scholarly verification.</div>
            </div>

            <div class="author-badge">
               <div style="width: 45px; height: 45px; background: var(--crimson); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800;">
                 <?= htmlspecialchars(strtoupper(substr($thesis['first_name'], 0, 1) . substr($thesis['last_name'], 0, 1))) ?>
               </div>
               <div>
                  <div style="font-weight: 800; color: var(--text-dark); font-size: 0.9rem;"><?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?></div>
                  <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;"><?= htmlspecialchars($thesis['college']) ?></div>
               </div>
            </div>

            <!-- Version Switcher -->
            <div style="margin-bottom: 1.5rem;">
               <span style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Manuscript History</span>
               <div class="version-nav">
                  <?php foreach ($versions as $idx => $v): ?>
                    <a href="<?= BASE_URL ?>faculty/review.php?id=<?= $thesis_id ?>&v=<?= $v['id'] ?>" class="v-pill <?= ($idx === 0) ? 'active' : '' ?>">
                       v<?= $v['version_number'] ?>
                    </a>
                  <?php endforeach; ?>
               </div>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--border-faint); margin: 2rem 0;">

               <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-bottom: 1.5rem;">
                  <form action="" method="POST" onsubmit="return confirm('Delete this thesis and all uploaded versions? This cannot be undone.');">
                     <input type="hidden" name="delete_thesis_id" value="<?= $thesis_id ?>">
                     <button type="submit" class="btn btn-secondary">Delete Thesis</button>
                  </form>
               </div>

            <?php if ($thesis['status'] === 'draft'): ?>
               <div style="background: var(--off-white); border: 1px solid var(--border-strong); border-radius: var(--radius-sm); padding: 1.75rem; margin-bottom: 2rem;">
                  <h4 style="font-family: var(--font-serif); font-size: 1.1rem; color: var(--crimson); margin-bottom: 1.25rem;">Pending Advisory Request</h4>
                  <p style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 1.5rem;">The student has requested you as their research adviser. Please review the manuscript details before accepting or declining.</p>
                  
                  <form action="" method="POST" style="display: flex; gap: 1rem; flex-direction: column;">
                     <button type="submit" name="request_action" value="accept_request" class="btn btn-primary" style="width: 100%; padding: 1rem; font-size: 0.85rem; letter-spacing: 0.05em; background: #065F46; border-color: #065F46; box-shadow: var(--shadow-sm);">
                        ACCEPT REQUEST
                     </button>
                     <button type="submit" name="request_action" value="decline_request" class="btn btn-secondary" style="width: 100%; padding: 1rem; font-size: 0.85rem; letter-spacing: 0.05em; color: #B91C1C; box-shadow: var(--shadow-sm);">
                        DECLINE REQUEST
                     </button>
                  </form>
               </div>
            <?php elseif ($latestVersion && $latestVersion['status'] === 'pending' && $thesis['status'] === 'pending_review'): ?>
               <form action="" method="POST">
                  <div style="margin-bottom: 1.5rem;">
                     <label style="display: block; font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.75rem;">Decision Decree</label>
                     <select name="action" required style="width: 100%; padding: 1rem; border-radius: 8px; border: 1px solid var(--border-strong); font-family: var(--font-base); font-weight: 700;">
                        <option value="">Select scholarly verdict...</option>
                        <option value="approved">ACCEPT</option>
                        <option value="revision_requested">REQUEST REVISION</option>
                        <option value="rejected">REJECT</option>
                     </select>
                  </div>

                  <div style="margin-bottom: 1.5rem;">
                     <label style="display: block; font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.75rem;">Adviser Review & Critique</label>
                     <textarea name="feedback" required rows="10" placeholder="Enter your scholarly feedback..." style="width: 100%; padding: 1rem; border-radius: 8px; border: 1px solid var(--border-strong); font-family: var(--font-serif); font-size: 0.95rem; line-height: 1.6;"></textarea>
                     <p style="font-size: 0.65rem; color: var(--text-muted); margin-top: 0.5rem; font-style: italic;">Detailed feedback is required for institutional transparency.</p>
                  </div>

                  <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1.25rem; font-size: 0.9rem; letter-spacing: 0.05em; box-shadow: var(--shadow-md);">
                     FINALIZE SCHOLARLY DECISION
                  </button>
               </form>
            <?php elseif ($thesis['status'] === 'approved'): ?>
               <!-- EDIT METADATA SECTION -->
               <div style="background: var(--off-white); border: 1px solid var(--border-strong); border-radius: var(--radius-sm); padding: 1.75rem; margin-bottom: 2rem;">
                  <h4 style="font-family: var(--font-serif); font-size: 1.1rem; color: var(--crimson); margin-bottom: 1.25rem;">Editorial Refinement</h4>
                  <p style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 1.5rem;">As the supervisor, you may now refine the scholarship metadata before formal publication.</p>
                  
                  <form action="" method="POST">
                     <input type="hidden" name="action" value="update_metadata">
                     <div style="margin-bottom: 1.25rem;">
                        <label style="display: block; font-size: 0.6rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">Scholarly Title</label>
                        <input type="text" name="title" value="<?= htmlspecialchars($thesis['title']) ?>" required style="width: 100%; padding: 0.75rem; border-radius: 6px; border: 1px solid var(--border); font-family: var(--font-serif); font-size: 0.9rem;">
                     </div>
                     <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-size: 0.6rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">Abstract / Executive Summary</label>
                        <textarea name="abstract" rows="6" style="width: 100%; padding: 0.75rem; border-radius: 6px; border: 1px solid var(--border); font-family: var(--font-serif); font-size: 0.85rem; line-height: 1.5;"><?= htmlspecialchars($thesis['abstract']) ?></textarea>
                     </div>
                     <button type="submit" class="btn btn-secondary" style="width: 100%; padding: 0.8rem; font-size: 0.75rem; font-weight: 800;">SAVE EDITORIAL CHANGES</button>
                  </form>
               </div>

               <div style="background: var(--crimson-faint); border: 2px solid var(--crimson); border-radius: var(--radius-sm); padding: 1.75rem; text-align: center;">
                  <i class="ph-fill ph-check-circle" style="font-size: 2.5rem; color: var(--crimson);"></i>
                  <h4 style="margin-top: 1rem; margin-bottom: 0.5rem;">Accepted for Archival</h4>
                  <p style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 1.5rem;">Peer review is complete. Ready for formal institutional publication.</p>
                  
                  <form action="" method="POST">
                     <input type="hidden" name="action" value="publish_artifact">
                     <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1.25rem; font-size: 0.9rem; letter-spacing: 0.1em; background: var(--crimson);">
                        PUBLISH TO ARCHIVE
                     </button>
                  </form>
               </div>

            <?php else: ?>
               <div style="background: var(--off-white); border-radius: var(--radius-sm); padding: 2rem; text-align: center; border: 2px dashed var(--border-strong);">
                  <i class="ph-bold ph-seal-check" style="font-size: 2.5rem; color: var(--crimson); opacity: 0.4;"></i>
                  <h4 style="margin-top: 1rem; margin-bottom: 0.5rem;">Review Concluded</h4>
                  <p style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.5;">This manuscript iteration has already been processed by the assigned faculty.</p>
               </div>
               
               <?php if (!empty($latestVersion['feedback'])): ?>
                  <div style="margin-top: 2rem; padding: 1.5rem; background: white; border-left: 3px solid var(--gold); box-shadow: var(--shadow-sm); font-family: var(--font-serif); font-style: italic; line-height: 1.6; color: var(--text-mid);">
                     <?= nl2br(htmlspecialchars($latestVersion['feedback'])) ?>
                  </div>
               <?php endif; ?>

               <a href="<?= BASE_URL ?>faculty/review.php" class="btn btn-secondary" style="width: 100%; margin-top: 2rem; padding: 1rem;">
                  Back to Queue
               </a>
            <?php endif; ?>
         </div>
      </aside>

    </div>
  </main>
<?php require_once __DIR__ . '/../includes/layout_bottom.php'; exit; ?>

<?php
} // END IF THESIS_ID

// ─── QUEUE LIST VIEW ───────────────────────────────────────────────────────────

$filterStatus = $_GET['status'] ?? 'all';
$search       = trim($_GET['search'] ?? '');

$params = [];
$where  = [];

$where[] = "t.adviser_id = :adviser_id";
$params['adviser_id'] = $user['id'];
$where[] = "t.status <> 'draft'";

if ($filterStatus !== 'all') {
    $where[] = "t.status = :status";
    $params['status'] = $filterStatus;
}
if ($search !== '') {
    $where[] = "(t.thesis_code LIKE :search OR t.title LIKE :search)";
    $params['search'] = "%$search%";
}

$whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

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
$theses = $thesesStmt->fetchAll();

// Filter summaries
$countStmt = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM theses WHERE adviser_id = :id GROUP BY status");
$countStmt->execute(['id' => $user['id']]);
$counts = $countStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$totalInQueue = array_sum($counts);
$queueDeleteFlash = $_GET['deleted'] ?? null;

function getCnt($c, $k) { return (int)($c[$k] ?? 0); }

require_once __DIR__ . '/../includes/layout_top.php';
require_once __DIR__ . '/../includes/layout_sidebar.php';
?>

<main class="main-content">
  <div class="page-header">
    <div class="page-title">
      <h1>Review <span>Queue</span></h1>
      <p>Analyze and manage scholarly submissions assigned for institutional verification.</p>
    </div>
  </div>

  <!-- Filter Controls -->
  <?php if ($queueDeleteFlash === '1'): ?>
    <div class="submissions-flash submissions-flash--ok" role="status" style="margin-bottom: 1.5rem;">
      <i class="ph-bold ph-check-circle"></i>
      <span>Thesis record deleted successfully.</span>
    </div>
  <?php elseif ($queueDeleteFlash === '0'): ?>
    <div class="submissions-flash submissions-flash--err" role="alert" style="margin-bottom: 1.5rem;">
      <i class="ph-bold ph-warning-circle"></i>
      <span>Unable to delete that thesis. Please refresh and try again.</span>
    </div>
  <?php endif; ?>

  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem; gap: 1rem; flex-wrap: wrap;">
     <div class="filter-tabs" style="display: flex; gap: 0.5rem; background: var(--off-white); padding: 0.4rem; border-radius: var(--radius-sm); border: 1px solid var(--border);">
        <?php
          $tabs = [
            'all'                => 'All Artifacts',
            'pending_review'     => 'In Review',
            'approved'           => 'Accepted',
            'revision_requested' => 'Revision Required',
            'rejected'           => 'Rejected'
          ];
          foreach ($tabs as $k => $l):
            $active = ($filterStatus === $k) ? 'background: var(--crimson); color: white; box-shadow: var(--shadow-sm);' : 'color: var(--text-muted);';
            $c = ($k === 'all') ? $totalInQueue : getCnt($counts, $k);
        ?>
          <a href="<?= BASE_URL ?>faculty/review.php?status=<?= $k ?>&search=<?= urlencode($search) ?>" style="text-decoration: none; padding: 0.5rem 1.25rem; border-radius: 4px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; transition: all 0.2s; <?= $active ?>">
             <?= $l ?> <span style="opacity: 0.6; margin-left: 0.4rem;"><?= $c ?></span>
          </a>
        <?php endforeach; ?>
     </div>

     <form method="GET" style="display: flex; gap: 0.5rem; align-items: center;">
        <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
        <div style="position: relative;">
           <i class="ph ph-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
           <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search code or title..." style="padding: 0.75rem 1rem 0.75rem 2.8rem; border-radius: var(--radius-sm); border: 1px solid var(--border); background: white; font-size: 0.88rem; width: 280px;">
        </div>
        <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.25rem;"><i class="ph-bold ph-funnel"></i></button>
     </form>
  </div>

  <!-- Table -->
  <div class="queue-table-wrap">
    <table class="queue-table">
      <thead>
        <tr>
          <th style="padding-left: 2rem;">Thesis Artifact</th>
          <th>Student Submitter</th>
          <th>Last Update</th>
          <th style="padding-right: 2rem; text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($theses)): ?>
          <tr><td colspan="4" style="padding: 5rem; text-align: center; color: var(--text-muted);">No entries match the specified filter criteria.</td></tr>
        <?php else: ?>
          <?php foreach ($theses as $t): ?>
            <tr>
              <td style="padding-left: 2rem;">
                 <span class="thesis-code-tag"><?= htmlspecialchars($t['thesis_code']) ?></span>
                 <div style="font-weight: 800; color: var(--text-dark); margin-top: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 450px;"><?= htmlspecialchars($t['title']) ?></div>
                 <div style="margin-top: 0.35rem; font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">
                   <?= htmlspecialchars(faculty_status_label($t['status'])) ?>
                 </div>
              </td>
              <td>
                 <div class="student-cell">
                    <div class="student-av"><?= htmlspecialchars(strtoupper(substr($t['first_name'], 0, 1) . substr($t['last_name'], 0, 1))) ?></div>
                    <div><div class="student-nm"><?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?></div><div class="student-cl"><?= htmlspecialchars($t['college']) ?></div></div>
                 </div>
              </td>
              <td style="font-size: 0.8rem; color: var(--text-muted);"><?= $t['latest_submitted_at'] ? date('M j, Y', strtotime($t['latest_submitted_at'])) : date('M j, Y', strtotime($t['created_at'])) ?></td>
              <td style="padding-right: 2rem; text-align: right;">
                 <a href="<?= BASE_URL ?>faculty/review.php?id=<?= $t['id'] ?>" class="btn-view-details">View Details <i class="ph-bold ph-arrow-right"></i></a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
