<?php
require_once __DIR__ . '/includes/db.php';

function clear_test_data() {
    global $pdo;
    $pdo->exec("DELETE FROM users WHERE email = 'test.faculty@wmsu.edu.ph'");
    $pdo->exec("DELETE FROM adviser_invites WHERE invited_email = 'test.faculty@wmsu.edu.ph'");
    $pdo->exec("DELETE FROM adviser_invites WHERE invite_code = 'ADV-TEST-1234'");
}

function generate_test_invite($email = 'test.faculty@wmsu.edu.ph', $code = 'ADV-TEST-1234') {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO adviser_invites (invite_code, invited_email, expires_at) 
        VALUES (:code, :email, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
    $stmt->execute(['code' => $code, 'email' => $email]);
}

function simulate_registration_logic($email, $code, $role = 'adviser') {
    global $pdo;
    $error = null;
    
    if ($role === 'adviser') {
        $inviteStmt = $pdo->prepare("SELECT id, invited_email FROM adviser_invites WHERE invite_code = :code AND used_at IS NULL AND expires_at >= NOW() LIMIT 1");
        $inviteStmt->execute(['code' => $code]);
        $invite = $inviteStmt->fetch();

        if (!$invite) {
            $error = 'Invite code is invalid, expired, or already used.';
        } elseif (strtolower($invite['invited_email']) !== strtolower($email)) {
            $error = 'Invite code does not match this email address.';
        }
    }

    if (!$error) {
        $pdo->beginTransaction();
        try {
            $hash = password_hash('Password123!', PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password, role, college, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute(['Test', 'Faculty', $email, $hash, $role, 'College of Computing Studies', 'active']);
            $newUserId = $pdo->lastInsertId();
            if ($role === 'adviser') {
                $consumeStmt = $pdo->prepare("UPDATE adviser_invites SET used_at = NOW(), used_by_user_id = :uid WHERE invite_code = :code");
                $consumeStmt->execute(['uid' => $newUserId, 'code' => $code]);
            }
            $pdo->commit();
            return ['success' => true, 'message' => 'Registration successful!'];
        } catch (Exception $e) { $pdo->rollBack(); return ['success' => false, 'message' => 'DB error: ' . $e->getMessage()]; }
    }
    return ['success' => false, 'message' => $error];
}

// Case 1: Valid Registration
clear_test_data();
generate_test_invite();
echo "\n--- CASE 1: Valid Registration ---\n";
$res1 = simulate_registration_logic('test.faculty@wmsu.edu.ph', 'ADV-TEST-1234');
var_dump($res1);

// Case 2: Duplicate Registration (Used Code)
echo "\n--- CASE 2: Duplicate Registration (Used Code) ---\n";
$res2 = simulate_registration_logic('test.faculty@wmsu.edu.ph', 'ADV-TEST-1234');
var_dump($res2);

// Case 3: Email Mismatch (Fresh Code)
clear_test_data();
generate_test_invite();
echo "\n--- CASE 3: Email Mismatch (Fresh Code) ---\n";
$res3 = simulate_registration_logic('wrong.email@wmsu.edu.ph', 'ADV-TEST-1234');
var_dump($res3);

// Case 4: Invalid Code
clear_test_data();
echo "\n--- CASE 4: Invalid Code ---\n";
$res4 = simulate_registration_logic('test.faculty@wmsu.edu.ph', 'ADV-WRONG-CODE');
var_dump($res4);

clear_test_data();
?>
