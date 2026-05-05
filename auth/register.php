<?php
require_once __DIR__ . '/../includes/session.php';

// Redirect if already logged in
if (is_logged_in()) {
  $roleUrl = current_user()['role'] === 'adviser' ? 'faculty/index.php' : 'student/index.php';
  header('Location: ' . BASE_URL . $roleUrl);
  exit;
}

$error = null;
$success = null;

// ── Live Stats ────────────────────────────────────────────────────────────────
$researcherCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'")->fetchColumn();
function fmt_reg_stat(int $n): string
{
  if ($n >= 1000)
    return number_format(round($n / 1000, 1)) . 'k+';
  return $n > 0 ? $n . '+' : '0';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $first_name = trim($_POST['first_name'] ?? '');
  $last_name = trim($_POST['last_name'] ?? '');
  $email = strtolower(trim($_POST['email'] ?? ''));
  $role = $_POST['role'] ?? 'student';
  if (!in_array($role, ['student', 'adviser'], true)) {
    $role = 'student';
  }
  $college = $_POST['college'] ?? '';
  $student_id = trim($_POST['student_id'] ?? '');
  $course = trim($_POST['course'] ?? '');
  $year_level = trim($_POST['year_level'] ?? '');
  $preferred_adviser = $_POST['preferred_adviser'] ?? null;
  $max_advisees = (int) ($_POST['max_advisees'] ?? 10);
  $password = $_POST['password'] ?? '';
  $confirm_password = $_POST['confirm_password'] ?? '';

  if (!$first_name || !$last_name || !$email || !$password || !$confirm_password || !$college) {
    $error = 'Please fill in all required fields.';
  } elseif ($role === 'adviser' && $max_advisees < 1) {
    $error = 'Advisers must be able to handle at least 1 student.';
  } elseif ($role === 'student' && (!$student_id || !$course || !$year_level || !$preferred_adviser)) {
    $error = 'Please fill in all student details including preferred adviser.';
  } elseif ($password !== $confirm_password) {
    $error = 'Passwords do not match.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Please enter a valid email address.';
  } elseif (!str_ends_with($email, '@wmsu.edu.ph')) {
    $error = 'Please use an official wmsu.edu.ph email address ending in @wmsu.edu.ph.';
  } elseif (strlen($password) < 8 || !preg_match('/[^a-zA-Z0-9]/', $password)) {
    $error = 'Password must be at least 8 chars and include a special character.';
  } else {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
      $error = 'Email is already registered. Try signing in.';
    }

    if (!$error) {
      $hash = password_hash($password, PASSWORD_BCRYPT);
      $initialStatus = 'active';
      $stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password, role, student_id, college, course, year_level, max_advisees, status) VALUES (:first_name, :last_name, :email, :password, :role, :student_id, :college, :course, :year_level, :max_advisees, :status)');
      $stmt->execute([
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'password' => $hash,
        'role' => $role,
        'student_id' => $role === 'student' ? $student_id : null,
        'college' => $college,
        'course' => $role === 'student' ? $course : null,
        'year_level' => $role === 'student' ? $year_level : null,
        'max_advisees' => $role === 'adviser' ? $max_advisees : 10,
        'status' => $initialStatus,
      ]);

      $new_user_id = $pdo->lastInsertId();

      if ($role === 'student' && $preferred_adviser) {
        $reqStmt = $pdo->prepare('INSERT INTO adviser_requests (student_id, adviser_id, status) VALUES (?, ?, "pending")');
        $reqStmt->execute([$new_user_id, $preferred_adviser]);
        
        // Notify adviser of new adviser request
        $notifyMsg = htmlspecialchars($first_name . ' ' . $last_name) . " has requested you as their adviser.";
        $pdo->prepare("INSERT INTO notifications (recipient_user_id, sender_user_id, type, message) VALUES (?, ?, 'adviser_request', ?)")
          ->execute([$preferred_adviser, $new_user_id, $notifyMsg]);
      }

      $success = 'Registration successful. You can now sign in.';

      // Clear inputs on success
      $first_name = $last_name = $email = $role = $student_id = $college = $course = $year_level = '';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account - WMSU Thesis Repository</title>
  <!-- Fonts & Icons -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/nunito.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/playfair-display.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/cormorant-garamond.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/phosphor/css/phosphor-all.css">

  <!-- Styles -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/global.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/login.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/register.css">
  <style>
    /* Register-specific overrides — inline for maximum specificity */
    html,
    body {
      overflow: visible !important;
    }

    .auth-page {
      overflow: visible !important;
    }

    .auth-left.auth-left-register {
      overflow: visible !important;
    }

    .auth-right {
      overflow-y: auto !important;
    }
  </style>
</head>

<body class="auth-page">

  <!-- Left Side: Branding & Info -->
  <div class="auth-left auth-left-register" style="padding: 2rem 3rem;">
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

    <div class="auth-hero-text" style="margin-top: 0.75rem;">
      <h1 style="font-size: 2.6rem; margin-bottom: 0.5rem;">WMSU<br><span class="gold-text">Repository</span></h1>
      <p style="font-size: 0.9rem; margin-bottom: 0; line-height: 1.6;">Preserving institutional wisdom through a
        premium digital archive of undergraduate and graduate research.</p>
    </div>

    <div class="auth-info-card">
      <div class="aic-header">
        <i class="ph-fill ph-shield-check"></i> INSTITUTIONAL ACCESS
      </div>
      <p>Join the community of researchers and scholars using your official @wmsu.edu.ph email address.</p>
    </div>

    <div class="avatar-stack-wrap" style="margin-top: auto; padding-top: 1.5rem;">
      <div class="avatar-stack">
        <div
          style="width:36px;height:36px;border-radius:50%;background:#A00000;color:white;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.8rem;border:2px solid #6B0000;">
          MR</div>
        <div
          style="width:36px;height:36px;border-radius:50%;background:#7A0000;color:white;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.8rem;border:2px solid #6B0000;margin-left:-10px;">
          JD</div>
        <div
          style="width:36px;height:36px;border-radius:50%;background:#C8952A;color:white;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.8rem;border:2px solid #6B0000;margin-left:-10px;">
          AL</div>
      </div>
      <div class="avatar-stack-text">
        JOINED BY <?= fmt_reg_stat($researcherCount) ?><br>RESEARCHERS
      </div>
    </div>
  </div>

  <!-- Right Side: Register Form -->
  <div class="auth-right"
    style="flex-direction: column; justify-content: flex-start; align-items: flex-start; padding: 3rem 5% 3rem 6%; overflow-y: auto;">
    <div class="auth-form-wrapper" style="max-width: 620px; width: 100%;">
      <h2 style="font-size: 1.8rem;">Create your account</h2>
      <p style="margin-bottom: 2rem;">Begin your journey into the WMSU digital archives.</p>

      <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom: 1rem; color: #B91C1C;"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom: 1rem; color: #047857;"><?= htmlspecialchars($success) ?>
        </div>
      <?php endif; ?>

      <form action="" method="POST">
        <div class="form-grid-2">
          <div class="input-group" style="margin-bottom: 0;">
            <div class="input-header"><span class="input-label" style="font-size: 0.65rem;">FIRST NAME</span></div>
            <input type="text" name="first_name" class="input-plain" placeholder="Juan" required
              value="<?= isset($first_name) ? htmlspecialchars($first_name) : '' ?>">
          </div>
          <div class="input-group" style="margin-bottom: 0;">
            <div class="input-header"><span class="input-label" style="font-size: 0.65rem;">LAST NAME</span></div>
            <input type="text" name="last_name" class="input-plain" placeholder="Dela Cruz" required
              value="<?= isset($last_name) ? htmlspecialchars($last_name) : '' ?>">
          </div>
        </div>

        <div class="form-grid-2">
          <div class="input-group" style="margin-bottom: 0;">
            <div class="input-header"><span class="input-label" style="font-size: 0.65rem;">WMSU EMAIL</span></div>
            <input type="email" name="email" class="input-plain" placeholder="juan.delacruz@wmsu.edu.ph" required
              value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">
          </div>
          <div class="input-group" style="margin-bottom: 0;">
            <div class="input-header"><span class="input-label" style="font-size: 0.65rem;">ROLE</span></div>
            <select name="role" class="select-plain">
              <option value="student" <?= (!isset($role) || $role === 'student') ? 'selected' : '' ?>>Student</option>
              <option value="adviser" <?= (isset($role) && $role === 'adviser') ? 'selected' : '' ?>>Adviser</option>
            </select>
          </div>
        </div>

        <div class="input-group" style="margin-bottom: 0;">
          <div class="input-header"><span class="input-label" style="font-size: 0.65rem;">COLLEGE / DEPARTMENT</span>
          </div>
          <select name="college" class="select-plain" required>
            <option value="">Select college</option>
            <option value="College of Computing Studies" <?= (isset($college) && $college === 'College of Computing Studies') ? 'selected' : '' ?>>College of Computing Studies</option>
            <option value="College of Engineering" <?= (isset($college) && $college === 'College of Engineering') ? 'selected' : '' ?>>College of Engineering</option>
            <option value="College of Liberal Arts" <?= (isset($college) && $college === 'College of Liberal Arts') ? 'selected' : '' ?>>College of Liberal Arts</option>
            <option value="College of Science and Mathematics" <?= (isset($college) && $college === 'College of Science and Mathematics') ? 'selected' : '' ?>>College of Science and Mathematics</option>
            <option value="College of Education" <?= (isset($college) && $college === 'College of Education') ? 'selected' : '' ?>>College of Education</option>
            <option value="College of Business Administration" <?= (isset($college) && $college === 'College of Business Administration') ? 'selected' : '' ?>>College of Business Administration</option>
            <option value="College of Nursing" <?= (isset($college) && $college === 'College of Nursing') ? 'selected' : '' ?>>College of Nursing</option>
            <option value="College of Social Work and Community Development" <?= (isset($college) && $college === 'College of Social Work and Community Development') ? 'selected' : '' ?>>College of Social Work and Community
              Development</option>
            <option value="College of Home Economics" <?= (isset($college) && $college === 'College of Home Economics') ? 'selected' : '' ?>>College of Home Economics</option>
            <option value="College of Forestry and Environmental Studies" <?= (isset($college) && $college === 'College of Forestry and Environmental Studies') ? 'selected' : '' ?>>College of Forestry and Environmental Studies
            </option>
            <option value="College of Agriculture" <?= (isset($college) && $college === 'College of Agriculture') ? 'selected' : '' ?>>College of Agriculture</option>
            <option value="College of Law" <?= (isset($college) && $college === 'College of Law') ? 'selected' : '' ?>>
              College of Law</option>
          </select>
        </div>

        <div id="student_details_section" style="margin-top: 1.5rem; display: <?= (!isset($role) || $role === 'student') ? 'block' : 'none' ?>;">
          <div class="input-group" style="margin-bottom: 1.5rem;">
            <div class="input-header"><span class="input-label" style="font-size: 0.65rem;">STUDENT ID</span></div>
            <input type="text" name="student_id" class="input-plain" placeholder="e.g. 2021-00001" value="<?= isset($student_id) ? htmlspecialchars($student_id) : '' ?>">
          </div>
          
          <div class="form-grid-2">
            <div class="input-group" style="margin-bottom: 0;">
              <div class="input-header"><span class="input-label" style="font-size: 0.65rem;">DEGREE / COURSE</span></div>
              <input type="text" name="course" class="input-plain" placeholder="e.g. BS Computer Science" value="<?= isset($course) ? htmlspecialchars($course) : '' ?>">
            </div>
            <div class="input-group" style="margin-bottom: 0;">
              <div class="input-header"><span class="input-label" style="font-size: 0.65rem;">YEAR LEVEL</span></div>
              <select name="year_level" class="select-plain">
                <option value="">Select year</option>
                <option value="1st Year" <?= (isset($year_level) && $year_level === '1st Year') ? 'selected' : '' ?>>1st Year</option>
                <option value="2nd Year" <?= (isset($year_level) && $year_level === '2nd Year') ? 'selected' : '' ?>>2nd Year</option>
                <option value="3rd Year" <?= (isset($year_level) && $year_level === '3rd Year') ? 'selected' : '' ?>>3rd Year</option>
                <option value="4th Year" <?= (isset($year_level) && $year_level === '4th Year') ? 'selected' : '' ?>>4th Year</option>
                <option value="5th Year" <?= (isset($year_level) && $year_level === '5th Year') ? 'selected' : '' ?>>5th Year</option>
                <option value="Alumni" <?= (isset($year_level) && $year_level === 'Alumni') ? 'selected' : '' ?>>Alumni</option>
              </select>
            </div>
          </div>
          
          <div class="input-group" style="margin-top: 1.5rem; margin-bottom: 0;">
            <div class="input-header"><span class="input-label" style="font-size: 0.65rem;">PREFERRED ADVISER</span></div>
            <select name="preferred_adviser" id="preferred_adviser" class="select-plain">
              <option value="">Select college first</option>
            </select>
            <span class="password-hint" style="margin-top: 0.35rem; display: block;">Advisers with full capacity cannot be selected.</span>
          </div>
        </div>

        <div id="adviser_details_section" style="margin-top: 1.5rem; display: <?= (isset($role) && $role === 'adviser') ? 'block' : 'none' ?>;">
          <div class="input-group" style="margin-bottom: 1.5rem;">
            <div class="input-header"><span class="input-label" style="font-size: 0.65rem;">MAX ADVISEES</span></div>
            <input type="number" name="max_advisees" class="input-plain" placeholder="e.g. 10" min="1" max="50" value="<?= isset($max_advisees) ? htmlspecialchars($max_advisees) : '10' ?>">
            <span class="password-hint" style="margin-top: 0.35rem; display: block;">How many students can you advise? (minimum 1, maximum 50)</span>
          </div>
        </div>

        <div class="form-grid-2">
          <div class="input-group" style="margin-top: 1.5rem; margin-bottom: 0;">
            <div class="input-header"><span class="input-label" style="font-size: 0.65rem;">SECURITY PASSWORD</span>
            </div>
            <div class="input-icon-right">
              <input type="password" name="password" id="password" class="input-plain" placeholder="••••••••••••"
                required>
              <i class="ph-bold ph-eye password-toggle" data-target="password"></i>
            </div>
            <span class="password-hint">Min 8 chars with a special character.</span>
          </div>

          <div class="input-group" style="margin-top: 1.5rem; margin-bottom: 0;">
            <div class="input-header"><span class="input-label" style="font-size: 0.65rem;">CONFIRM PASSWORD</span>
            </div>
            <div class="input-icon-right">
              <input type="password" name="confirm_password" id="confirm_password" class="input-plain"
                placeholder="••••••••••••" required>
              <i class="ph-bold ph-eye password-toggle" data-target="confirm_password"></i>
            </div>
          </div>
        </div>

        <div class="activation-callout">
          <div class="ac-icon"><i class="ph-bold ph-info"></i></div>
          <div class="ac-content">
            <h5>Access Policy</h5>
            <p>Both student and adviser accounts are available after successful registration with a valid institutional
              email.</p>
          </div>
        </div>

        <div class="register-action-row">
          <button type="submit" class="btn-register">Register Account <i class="ph-bold ph-arrow-right"></i></button>
          <div class="login-redirect">Already have an account? <a href="login.php">Sign In</a></div>
        </div>
      </form>

    </div><!-- /.auth-form-wrapper -->

    <div class="bottom-legal-links"
      style="background: transparent; border-top: none; margin-top: 1.5rem; padding-top: 0;">
      <a href="<?= BASE_URL ?>public/guidelines.php">GUIDELINES</a>
      <a href="#">PRIVACY POLICY</a>
      <a href="<?= BASE_URL ?>public/about.php">SUPPORT CENTER</a>
    </div>

  </div>

  <script>
    document.querySelectorAll('.password-toggle').forEach(icon => {
      icon.addEventListener('click', function () {
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        if (input.type === 'password') {
          input.type = 'text';
          this.classList.replace('ph-eye', 'ph-eye-slash');
        } else {
          input.type = 'password';
          this.classList.replace('ph-eye-slash', 'ph-eye');
        }
      });
    });

    const roleSelect = document.querySelector('select[name="role"]');
    const studentSection = document.getElementById('student_details_section');
    const adviserSection = document.getElementById('adviser_details_section');
    const collegeSelect = document.querySelector('select[name="college"]');
    const adviserSelect = document.getElementById('preferred_adviser');

    if (roleSelect && studentSection) {
      roleSelect.addEventListener('change', function() {
        if (this.value === 'student') {
          studentSection.style.display = 'block';
          adviserSection.style.display = 'none';
        } else {
          studentSection.style.display = 'none';
          adviserSection.style.display = 'block';
        }
      });
    }

    if (collegeSelect && adviserSelect) {
      collegeSelect.addEventListener('change', function() {
        const college = this.value;
        adviserSelect.innerHTML = '<option value="">Loading advisers...</option>';
        
        if (!college) {
          adviserSelect.innerHTML = '<option value="">Select college first</option>';
          return;
        }

        fetch(`../api/get_advisers.php?college=${encodeURIComponent(college)}`)
          .then(response => response.json())
          .then(data => {
            if (data.success && data.advisers.length > 0) {
              adviserSelect.innerHTML = '<option value="">Select a preferred adviser</option>';
              data.advisers.forEach(adv => {
                const opt = document.createElement('option');
                opt.value = adv.id;
                opt.textContent = `${adv.name} (${adv.current}/${adv.max} Advisees)`;
                if (adv.is_full) {
                  opt.disabled = true;
                  opt.textContent += ' - FULL';
                }
                adviserSelect.appendChild(opt);
              });
            } else {
              adviserSelect.innerHTML = '<option value="">No active advisers found in this college</option>';
            }
          })
          .catch(err => {
            console.error(err);
            adviserSelect.innerHTML = '<option value="">Error loading advisers</option>';
          });
      });

      // Trigger change on load if college is pre-selected (e.g., after validation error)
      if (collegeSelect.value) {
        collegeSelect.dispatchEvent(new Event('change'));
      }
    }
  </script>
</body>

</html>