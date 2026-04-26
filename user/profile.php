<?php
require_once __DIR__ . '/../includes/session.php';
require_login();

$user = current_user();
$flash = null;

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $college = trim($_POST['college'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    if (empty($first_name) || empty($last_name)) {
        $flash = ['type' => 'error', 'message' => 'First name and last name are required.'];
    } else {
        // Update basic info
        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, college = ? WHERE id = ?");
        $stmt->execute([$first_name, $last_name, $college, $user['id']]);

        // Update session
        $_SESSION['user']['first_name'] = $first_name;
        $_SESSION['user']['last_name'] = $last_name;
        $user = current_user();

        // If they want to change password
        if (!empty($new_password)) {
            if (empty($current_password)) {
                $flash = ['type' => 'error', 'message' => 'Please enter your current password to set a new one.'];
            } elseif (strlen($new_password) < 8) {
                $flash = ['type' => 'error', 'message' => 'New password must be at least 8 characters.'];
            } else {
                // Verify current password
                $pwStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $pwStmt->execute([$user['id']]);
                $dbUser = $pwStmt->fetch();

                if (!password_verify($current_password, $dbUser['password'])) {
                    $flash = ['type' => 'error', 'message' => 'Current password is incorrect.'];
                } else {
                    $hash = password_hash($new_password, PASSWORD_BCRYPT);
                    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $user['id']]);
                    $flash = ['type' => 'success', 'message' => 'Profile and password updated successfully.'];
                }
            }
        } else {
            if (!$flash) {
                $flash = ['type' => 'success', 'message' => 'Profile updated successfully.'];
            }
        }
    }
}

// Fetch fresh user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

// Custom CSS for Profile
ob_start();
?>
<style>
  .profile-grid { display: grid; grid-template-columns: 280px 1fr; gap: 2rem; }
  
  .profile-sidebar-card { background: var(--surface); border-radius: var(--radius); padding: 2rem; border: 1px solid var(--border); box-shadow: var(--shadow-sm); text-align: center; height: fit-content; }
  .profile-avatar-large { width: 100px; height: 100px; background: var(--crimson); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 800; margin: 0 auto 1.5rem; border: 4px solid var(--crimson-faint); }
  .profile-name-title { font-family: 'Playfair Display', serif; font-size: 1.25rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.25rem; }
  .profile-email-sub { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem; }
  
  .profile-main-card { background: var(--surface); border-radius: var(--radius); padding: 2.5rem; border: 1px solid var(--border); box-shadow: var(--shadow-sm); }
  
  .profile-section-title { font-family: 'Playfair Display', serif; font-size: 1.15rem; font-weight: 800; color: var(--text-dark); margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--off-white); display: flex; align-items: center; gap: 0.6rem; }
  .profile-section-title i { color: var(--crimson); }

  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
  .form-group { margin-bottom: 1.5rem; }
  .form-label { display: block; font-size: 0.7rem; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.5rem; }
  .form-control { width: 100%; padding: 0.75rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--off-white); font-family: 'Nunito', sans-serif; font-size: 0.9rem; transition: all var(--transition); }
  .form-control:focus { border-color: var(--crimson); background: #fff; outline: none; box-shadow: 0 0 0 3px var(--crimson-light); }

  .account-meta-list { list-style: none; text-align: left; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--off-white); }
  .account-meta-item { display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 0.75rem; }
  .account-meta-item span:first-child { font-weight: 750; color: var(--text-muted); }
  .account-meta-item span:last-child { color: var(--text-dark); font-weight: 700; }
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
        <h1>My <span>Academic Profile</span></h1>
        <p>Manage your institutional credentials, personal information, and platform security settings.</p>
      </div>
    </div>

    <?php if ($flash): ?>
      <div style="margin-bottom: 2rem; padding: 1rem 1.5rem; border-radius: var(--radius-sm); font-weight:600; font-size: 0.85rem; color: #fff; background: <?= $flash['type'] === 'success' ? '#065F46' : '#991B1B' ?>;">
        <i class="ph-bold <?= $flash['type'] === 'success' ? 'ph-check-circle' : 'ph-warning-circle' ?>" style="margin-right:0.5rem; font-size:1.1rem; vertical-align:middle;"></i>
        <?= htmlspecialchars($flash['message']) ?>
      </div>
    <?php endif; ?>

    <div class="profile-grid">
      
      <!-- Left: Summary -->
      <div>
        <div class="profile-sidebar-card">
          <div class="profile-avatar-large">
            <?= htmlspecialchars(strtoupper(substr($profile['first_name'], 0, 1) . substr($profile['last_name'], 0, 1))) ?>
          </div>
          <h3 class="profile-name-title"><?= htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']) ?></h3>
          <p class="profile-email-sub"><?= htmlspecialchars($profile['email']) ?></p>
          
          <div style="display: flex; justify-content: center; gap: 0.5rem; margin-bottom: 1rem;">
            <span class="tag" style="background:var(--crimson); color:#fff; font-size:0.65rem; border-radius:4px;"><?= strtoupper(htmlspecialchars($profile['role'])) ?></span>
            <span class="tag" style="background:var(--gold-faint); color:var(--gold); border-radius:4px; font-size:0.65rem;">ACTIVE</span>
          </div>

          <ul class="account-meta-list">
            <li class="account-meta-item">
              <span>Account ID</span>
              <span>#<?= htmlspecialchars($profile['id']) ?></span>
            </li>
            <li class="account-meta-item">
              <span>Joined</span>
              <span><?= date('M j, Y', strtotime($profile['created_at'])) ?></span>
            </li>
            <li class="account-meta-item" style="margin-bottom:0;">
              <span>Institution</span>
              <span>WMSU</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Right: Form -->
      <div>
        <form action="profile.php" method="POST" class="profile-main-card">
          
          <h3 class="profile-section-title"><i class="ph-fill ph-user-circle"></i> Personal Information</h3>
          
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">First Name</label>
              <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($profile['first_name']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Last Name</label>
              <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($profile['last_name']) ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Institutional Affiliation (College)</label>
            <select name="college" class="form-control">
              <option value="">Select your college</option>
              <option value="College of Computing Studies" <?= $profile['college'] === 'College of Computing Studies' ? 'selected' : '' ?>>College of Computing Studies</option>
              <option value="College of Engineering" <?= $profile['college'] === 'College of Engineering' ? 'selected' : '' ?>>College of Engineering</option>
              <option value="College of Liberal Arts" <?= $profile['college'] === 'College of Liberal Arts' ? 'selected' : '' ?>>College of Liberal Arts</option>
              <option value="College of Science and Mathematics" <?= $profile['college'] === 'College of Science and Mathematics' ? 'selected' : '' ?>>College of Science and Mathematics</option>
              <option value="College of Education" <?= $profile['college'] === 'College of Education' ? 'selected' : '' ?>>College of Education</option>
              <option value="College of Business Administration" <?= $profile['college'] === 'College of Business Administration' ? 'selected' : '' ?>>College of Business Administration</option>
              <option value="College of Nursing" <?= $profile['college'] === 'College of Nursing' ? 'selected' : '' ?>>College of Nursing</option>
              <option value="College of Social Work and Community Development" <?= $profile['college'] === 'College of Social Work and Community Development' ? 'selected' : '' ?>>College of Social Work and Community Development</option>
              <option value="College of Home Economics" <?= $profile['college'] === 'College of Home Economics' ? 'selected' : '' ?>>College of Home Economics</option>
              <option value="College of Forestry and Environmental Studies" <?= $profile['college'] === 'College of Forestry and Environmental Studies' ? 'selected' : '' ?>>College of Forestry and Environmental Studies</option>
              <option value="College of Agriculture" <?= $profile['college'] === 'College of Agriculture' ? 'selected' : '' ?>>College of Agriculture</option>
              <option value="College of Law" <?= $profile['college'] === 'College of Law' ? 'selected' : '' ?>>College of Law</option>
            </select>
          </div>

          <h3 class="profile-section-title" style="margin-top: 2rem;"><i class="ph-fill ph-lock-key"></i> Security Settings</h3>
          <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1.5rem;">To update your password, please provide your current credentials first.</p>

          <div class="form-group">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" placeholder="Required for password change only">
          </div>

          <div class="form-group">
            <label class="form-label">New Secure Password</label>
            <input type="password" name="new_password" class="form-control" placeholder="Minimum 8 characters">
          </div>

          <div class="settings-footer" style="margin-top: 2.5rem; pt: 1rem; border-top: 1px solid var(--off-white); padding-top: 1.5rem;">
            <button type="reset" class="btn btn-secondary">Reset Changes</button>
            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">Save Academic Profile</button>
          </div>

        </form>
      </div>

    </div>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
