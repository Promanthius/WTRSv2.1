<?php
require_once __DIR__ . '/includes/db.php';

$hash = password_hash('Admin@1234', PASSWORD_BCRYPT);

// Create adviser account (already active, no activation needed)
$stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password, role, college, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
$stmt->execute(['Elena', 'Rodriguez', 'elena.rodriguez@wmsu.edu.ph', $hash, 'adviser', 'College of Computing Studies', 'active']);
echo "Adviser created! ID: " . $pdo->lastInsertId() . "\n";

// Also activate the student we just registered
$stmt2 = $pdo->prepare("UPDATE users SET status = 'active' WHERE email = 'juan.delacruz@wmsu.edu.ph'");
$stmt2->execute();
echo "Student juan.delacruz@wmsu.edu.ph activated!\n";

echo "\n--- TEST ACCOUNTS ---\n";
echo "STUDENT: juan.delacruz@wmsu.edu.ph / Test@1234\n";
echo "ADVISER: elena.rodriguez@wmsu.edu.ph / Admin@1234\n";
?>
