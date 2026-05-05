<?php
require_once __DIR__ . '/../includes/session.php';
require_login(['adviser']);

$user = current_user();
$flash = null;

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $repository_name = trim($_POST['repository_name'] ?? '');
    $institution = trim($_POST['institution'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $max_file_size_mb = intval($_POST['max_file_size_mb'] ?? 20);
    $allow_guest_search = isset($_POST['allow_guest_search']) ? '1' : '0';
    $require_wmsu_email = isset($_POST['require_wmsu_email']) ? '1' : '0';

    $updates = [
        'repository_name' => $repository_name,
        'institution' => $institution,
        'contact_email' => $contact_email,
        'max_file_size_mb' => strval($max_file_size_mb),
        'allow_guest_search' => $allow_guest_search,
        'require_wmsu_email' => $require_wmsu_email,
    ];

    $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = :val WHERE setting_key = :key");
    foreach ($updates as $key => $val) {
        $stmt->execute(['val' => $val, 'key' => $key]);
    }

    // Log this action
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action_type, description, ip_address) VALUES (?, 'settings_update', 'System settings updated', ?)");
    $logStmt->execute([$user['id'], $ip]);

    $flash = ['type' => 'success', 'message' => 'Settings saved successfully.'];
}

// Fetch current settings
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$settingsRaw = $settingsStmt->fetchAll();
$settings = [];
foreach ($settingsRaw as $s) {
    $settings[$s['setting_key']] = $s['setting_value'];
}

// Custom CSS for settings
ob_start();
?>
<style>
  .settings-card { background: var(--surface); border-radius: var(--radius); padding: 2rem; border: 1px solid var(--border); box-shadow: var(--shadow-sm); margin-bottom: 2rem; }
  .settings-section-title { font-family: 'Playfair Display', serif; font-size: 1.25rem; font-weight: 800; color: var(--text-dark); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 1px solid var(--off-white); padding-bottom: 0.75rem; }
  .settings-section-title i { color: var(--crimson); }
  
  .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
  .form-group { margin-bottom: 1.25rem; }
  .form-label { display: block; font-size: 0.7rem; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.5rem; }
  .form-control { width: 100%; padding: 0.65rem 0.85rem; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--off-white); font-family: 'Nunito', sans-serif; font-size: 0.88rem; transition: all var(--transition); }
  .form-control:focus { border-color: var(--crimson); background: #fff; outline: none; box-shadow: 0 0 0 3px var(--crimson-light); }
  
  .toggle-group { display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: var(--off-white); border-radius: var(--radius-sm); border: 1px solid var(--border); margin-bottom: 1rem; }
  .toggle-info h4 { font-size: 0.9rem; font-weight: 700; color: var(--text-dark); }
  .toggle-info p { font-size: 0.75rem; color: var(--text-muted); }
  
  /* Toggle Switch */
  .switch { position: relative; display: inline-block; width: 44px; height: 24px; }
  .switch input { opacity: 0; width: 0; height: 0; }
  .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
  .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
  input:checked + .slider { background-color: var(--crimson); }
  input:focus + .slider { box-shadow: 0 0 1px var(--crimson); }
  input:checked + .slider:before { transform: translateX(20px); }
  
  .settings-footer { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1rem; }
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
        <h1>System <span>Settings</span></h1>
        <p>Configure institutional branding, repository metadata, and server-side file constraints.</p>
      </div>
      <div class="page-header-actions">
        <!-- Dashboard actions -->
      </div>
    </div>

    <?php if ($flash): ?>
      <div id="flash-message" style="margin-bottom: 2rem; padding: 1rem 1.5rem; border-radius: var(--radius-sm); font-weight:600; font-size: 0.85rem; color: #fff; background: <?= $flash['type'] === 'success' ? '#065F46' : '#991B1B' ?>; transition: opacity 0.5s ease-out;">
        <i class="ph-bold <?= $flash['type'] === 'success' ? 'ph-check-circle' : 'ph-warning-circle' ?>" style="margin-right:0.5rem; font-size:1.1rem; vertical-align:middle;"></i>
        <?= htmlspecialchars($flash['message']) ?>
      </div>
    <?php endif; ?>

    <form action="settings.php" method="POST">
      <div class="settings-card">
        <h3 class="settings-section-title"><i class="ph-fill ph-buildings"></i> Institutional Branding</h3>
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Repository Name</label>
            <input type="text" name="repository_name" class="form-control" value="<?= htmlspecialchars($settings['repository_name'] ?? 'WMSU Repository') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Institution Name</label>
            <input type="text" name="institution" class="form-control" value="<?= htmlspecialchars($settings['institution'] ?? 'Western Mindanao State University') ?>">
          </div>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Repository Contact Email</label>
          <input type="email" name="contact_email" class="form-control" value="<?= htmlspecialchars($settings['contact_email'] ?? 'repository.support@wmsu.edu.ph') ?>">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
        
        <!-- Upload Settings -->
        <div class="settings-card">
          <h3 class="settings-section-title"><i class="ph-fill ph-file-arrow-up"></i> Upload Constraints</h3>
          <div class="form-group">
            <label class="form-label">Maximum File Size (MB)</label>
            <select name="max_file_size_mb" class="form-control">
              <option value="20" <?= ($settings['max_file_size_mb'] ?? '20') == '20' ? 'selected' : '' ?>>20 MB (Standard)</option>
              <option value="50" <?= ($settings['max_file_size_mb'] ?? '20') == '50' ? 'selected' : '' ?>>50 MB</option>
              <option value="100" <?= ($settings['max_file_size_mb'] ?? '20') == '100' ? 'selected' : '' ?>>100 MB</option>
            </select>
            <p style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.5rem;">Recommended limit is 20MB for archival efficiency.</p>
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Allowed File Types</label>
            <div style="display: flex; gap: 0.5rem; margin-top: 0.25rem;">
               <span class="tag" style="background:var(--crimson); color:#fff; border-radius:4px;">PDF ONLY</span>
               <span class="tag" style="opacity:0.4; border-radius:4px;">DOCX</span>
               <span class="tag" style="opacity:0.4; border-radius:4px;">ZIP</span>
            </div>
          </div>
        </div>

        <!-- Access Controls -->
        <div class="settings-card">
          <h3 class="settings-section-title"><i class="ph-fill ph-shield-check"></i> Security & Access</h3>
          
          <div class="toggle-group">
            <div class="toggle-info">
              <h4>Allow Guest Search</h4>
              <p>Unauthenticated users can browse titles.</p>
            </div>
            <label class="switch">
              <input type="checkbox" name="allow_guest_search" <?= ($settings['allow_guest_search'] ?? '1') == '1' ? 'checked' : '' ?>>
              <span class="slider"></span>
            </label>
          </div>

          <div class="toggle-group" style="margin-bottom:0;">
            <div class="toggle-info">
              <h4>Institutional Email Required</h4>
              <p>Registration limited to @wmsu.edu.ph.</p>
            </div>
            <label class="switch">
              <input type="checkbox" name="require_wmsu_email" <?= ($settings['require_wmsu_email'] ?? '1') == '1' ? 'checked' : '' ?>>
              <span class="slider"></span>
            </label>
          </div>
        </div>

      </div>

      <div class="settings-footer">
        <button type="reset" class="btn btn-secondary">Discard Changes</button>
        <button type="submit" class="btn btn-primary">Save System Settings</button>
      </div>
    </form>

  </main>

<?php
ob_start();
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const flash = document.getElementById('flash-message');
  if (flash) {
    setTimeout(() => {
      flash.style.opacity = '0';
      setTimeout(() => flash.remove(), 500);
    }, 3000);
  }
});
</script>
<?php
$extraScripts = ob_get_clean();
require_once __DIR__ . '/../includes/layout_bottom.php';
?>
