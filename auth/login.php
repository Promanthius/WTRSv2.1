<?php
require_once __DIR__ . '/../includes/session.php';

$error = null;

// ── Live Stats ──────────────────────────────────────────────────────────────
$archivedCount = (int) $pdo->query("SELECT COUNT(*) FROM theses WHERE status = 'archived'")->fetchColumn();
$researcherCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'")->fetchColumn();

function fmt_stat(int $n): string
{
  if ($n >= 1000)
    return round($n / 1000, 1) . 'k+';
  return $n > 0 ? $n . '+' : '0';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  if (!$email || !$password) {
    $error = 'Please provide both email and password.';
  } else {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    $validPassword = false;

    if ($user) {
      if (password_verify($password, $user['password'])) {
        $validPassword = true;
      } elseif (strlen($user['password']) === 40 && hash_equals($user['password'], sha1($password))) {
        // Legacy SHA1 password hash fallback and migration
        $validPassword = true;
        $rehash = password_hash($password, PASSWORD_BCRYPT);
        $updateStmt = $pdo->prepare('UPDATE users SET password = :hash WHERE id = :id');
        $updateStmt->execute(['hash' => $rehash, 'id' => $user['id']]);
      }
    }

    if (!$user || !$validPassword) {
      $error = 'Invalid credentials.';
    } elseif ($user['status'] !== 'active') {
      $error = 'Your account is not active. Please contact your adviser.';
    } else {
      // Regenerate session ID to prevent session fixation attacks
      session_regenerate_id(true);

      $_SESSION['user_id'] = $user['id'];
      $_SESSION['user'] = [
        'id' => $user['id'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email'],
        'role' => $user['role'],
      ];

      if ($user['role'] === 'adviser') {
        header('Location: ' . BASE_URL . 'faculty/index.php');
      } else {
        header('Location: ' . BASE_URL . 'student/index.php');
      }
      exit;

    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — WMSU Repository</title>

  <!-- Fonts & Icons -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/nunito.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/playfair-display.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/cormorant-garamond.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/phosphor/css/phosphor-all.css">

  <!-- Styles -->
  <link rel="stylesheet"
    href="<?= BASE_URL ?>assets/css/global.css?v=<?= filemtime(__DIR__ . '/../assets/css/global.css') ?>">
  <link rel="stylesheet"
    href="<?= BASE_URL ?>assets/css/login.css?v=<?= filemtime(__DIR__ . '/../assets/css/login.css') ?>">
</head>

<body class="auth-page">

  <!-- ═══════════════════════════════════════
       LEFT PANEL — Branding
  ═══════════════════════════════════════ -->
  <div class="auth-left">

    <!-- Decorative accent line at top -->
    <div class="auth-left-top-line"></div>

    <!-- Decorative depth rings -->
    <div class="deco-ring deco-ring-1"></div>
    <div class="deco-ring deco-ring-2"></div>
    <div class="deco-ring deco-ring-3"></div>

    <!-- Brand -->
    <div class="auth-brand">
      <div class="brand-seal-box">
        <img src="../assets/images/wmsu-logo.png" alt="WMSU Logo"
          onerror="this.style.display='none'; this.nextElementSibling.style.display='block';" />
        <span
          style="display:none; font-size:0.65rem; font-weight:800; color:#8B0000; text-align:center; line-height:1.2;">WMSU</span>
      </div>
      <div class="brand-text">
        <h2>WMSU</h2>
        <p>Thesis Repository</p>
      </div>
    </div>

    <!-- Hero Text -->
    <div class="auth-hero-text">
      <h1>Preserving <span class="gold-text">Academic</span> Excellence.</h1>
      <div class="hero-rule"></div>
      <p>Access the collective wisdom of Western Mindanao State University — a centralized digital archive of pioneering
        research and scholarly artifacts.</p>

      <div class="auth-stats">
        <div class="auth-stat-item">
          <h3><?= fmt_stat($archivedCount) ?></h3>
          <p>Archived Theses</p>
        </div>
        <div class="stat-divider"></div>
        <div class="auth-stat-item">
          <h3><?= fmt_stat($researcherCount) ?></h3>
          <p>Active Researchers</p>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="auth-footer-text">
      Western Mindanao State University
    </div>

  </div>

  <!-- ═══════════════════════════════════════
       RIGHT PANEL — Form
  ═══════════════════════════════════════ -->
  <div class="auth-right">

    <div class="watermark">
      <span>WTRS</span>
      <i class="ph-fill ph-seal-check"></i>
    </div>

    <div class="auth-form-wrapper">

      <h2>Welcome Back</h2>
      <p>Sign in with your institutional credentials to continue.</p>

      <?php if ($error): ?>
        <div class="alert alert-error">
          <i class="ph-fill ph-warning-circle" style="margin-right:0.4rem;"></i>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form action="" method="POST">

        <div class="input-group">
          <div class="input-header">
            <span class="input-label">Institutional Email</span>
          </div>
          <div class="input-icon-wrapper">
            <i class="ph ph-at"></i>
            <input type="email" name="email" placeholder="username@wmsu.edu.ph" required
              value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" autocomplete="email">
          </div>
        </div>

        <div class="input-group">
          <div class="input-header">
            <span class="input-label">Password</span>
            <a href="forgot.php" class="forgot-link">Forgot password?</a>
          </div>
          <div class="input-icon-wrapper">
            <i class="ph-fill ph-lock-key"></i>
            <input type="password" name="password" placeholder="••••••••••••" required autocomplete="current-password">
          </div>
        </div>



        <button type="submit" class="btn-login">
          Sign In to Repository
          <i class="ph-bold ph-arrow-right"></i>
        </button>

      </form>

      <div class="auth-bottom">
        <p>New to the repository? <a href="register.php">Create an account</a></p>
      </div>

    </div><!-- /.auth-form-wrapper -->
  </div><!-- /.auth-right -->

</body>

</html>