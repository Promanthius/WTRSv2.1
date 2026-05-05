<?php
require_once __DIR__ . '/../includes/session.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$email) {
        $error = 'Please enter your institutional email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (empty($new_password) || strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Check if user exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            // Generic message to prevent email enumeration
            $success = 'If an account exists with that email, your password has been reset. Try signing in with your new password.';
        } else {
            // Update password
            $hash = password_hash($new_password, PASSWORD_BCRYPT);
            $updateStmt = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
            $updateStmt->execute(['password' => $hash, 'id' => $user['id']]);

            // Log the action
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action_type, description, ip_address) VALUES (?, 'password_reset', 'Password was reset via forgot password form', ?)");
            $logStmt->execute([$user['id'], $ip]);

            $success = 'If an account exists with that email, your password has been reset. Try signing in with your new password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - WMSU Thesis Repository</title>
  <!-- Fonts & Icons -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/nunito.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/playfair-display.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/cormorant-garamond.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/phosphor/css/phosphor-all.css">

  <!-- Styles -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/global.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/login.css">
</head>
<body class="auth-page">

  <!-- Left Side: Branding -->
  <div class="auth-left">
    <div class="auth-brand">
      <div class="brand-seal-box" style="width: 72px; height: 72px; overflow: hidden; border-radius: 12px;">
        <img src="../assets/images/wmsu-logo.png" alt="WMSU Logo" style="width: 100%; height: 100%; object-fit: contain;" />
        <p>THESIS REPOSITORY</p>
      </div>
    </div>

    <div class="auth-hero-text">
      <h1>Account <span class="gold-text">Recovery</span></h1>
      <p>Reset your credentials to regain access to the Western Mindanao State University Thesis Repository system.</p>
    </div>

    <div class="auth-footer-text">
      Office of the Vice President for Academic Affairs
    </div>
  </div>

  <!-- Right Side: Reset Form -->
  <div class="auth-right">
    <div class="watermark">
      <span>Password Recovery</span>
      <i class="ph-fill ph-lock-key"></i>
    </div>

    <div class="auth-form-wrapper">
      <h2>Reset Your Password</h2>
      <p>Enter your institutional email and set a new password below.</p>

      <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom: 1rem; color: #B91C1C; background: #FEE2E2; padding: 0.75rem 1rem; border-radius: 6px;"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom: 1rem; color: #065F46; background: #D1FAE5; padding: 0.75rem 1rem; border-radius: 6px;"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <form action="" method="POST">
        <div class="input-group">
          <div class="input-header"><span class="input-label">Institutional Email</span></div>
          <div class="input-icon-wrapper">
            <i class="ph ph-at"></i>
            <input type="email" name="email" placeholder="username@wmsu.edu.ph" required value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">
          </div>
        </div>

        <div class="input-group">
          <div class="input-header"><span class="input-label">New Password</span></div>
          <div class="input-icon-wrapper">
            <i class="ph-fill ph-lock-key"></i>
            <input type="password" name="new_password" placeholder="••••••••••••" required>
          </div>
        </div>

        <div class="input-group">
          <div class="input-header"><span class="input-label">Confirm New Password</span></div>
          <div class="input-icon-wrapper">
            <i class="ph-fill ph-lock-key"></i>
            <input type="password" name="confirm_password" placeholder="••••••••••••" required>
          </div>
        </div>

        <button type="submit" class="btn-login">Reset Password <i class="ph-bold ph-arrow-right"></i></button>
      </form>

      <div class="auth-bottom">
        <p>Remember your password? <a href="login.php">Sign In</a></p>
      </div>
    </div>
  </div>
</body>
</html>
