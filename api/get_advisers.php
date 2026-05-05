<?php
require_once __DIR__ . '/../includes/session.php';
// This API is used publicly during registration, so no login required

header('Content-Type: application/json');

$college = $_GET['college'] ?? '';

if (!$college) {
    echo json_encode(['success' => false, 'error' => 'College parameter missing.']);
    exit;
}

try {
    // We want to fetch advisers for the college, and also compute how many active/approved/pending requests they have.
    // For simplicity, we just fetch advisers and their active advisees count.
    // Wait, the user has adviser_id in the users table, and pending requests in adviser_requests.
    // Max capacity applies to accepted students + approved requests.
    // But for the dropdown, we just show them if they are an active adviser in that college.

    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.max_advisees,
               (SELECT COUNT(*) FROM users WHERE adviser_id = u.id) as current_advisees
        FROM users u
        WHERE u.role = 'adviser' AND u.status = 'active' AND u.college = :college
        ORDER BY u.last_name ASC
    ");

    $stmt->execute(['college' => $college]);
    $advisers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the response
    $results = [];
    foreach ($advisers as $adv) {
        $full = (int) $adv['current_advisees'] >= (int) $adv['max_advisees'];
        $results[] = [
            'id' => $adv['id'],
            'name' => 'Dr. ' . $adv['first_name'] . ' ' . $adv['last_name'],
            'is_full' => $full,
            'current' => $adv['current_advisees'],
            'max' => $adv['max_advisees']
        ];
    }

    echo json_encode(['success' => true, 'advisers' => $results]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
