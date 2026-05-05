<?php
require_once __DIR__ . '/../includes/session.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$user = current_user();
$notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;

if ($notificationId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid notification ID']);
    exit;
}

try {
    // Verify the notification belongs to the current user and mark as read
    $stmt = $pdo->prepare("UPDATE notifications 
                          SET is_read = 1 
                          WHERE id = ? AND recipient_user_id = ?");
    $stmt->execute([$notificationId, $user['id']]);

    // Get updated unread counts
    $unreadStmt = $pdo->prepare("SELECT type, COUNT(*) AS cnt
                                 FROM notifications
                                 WHERE recipient_user_id = ? AND is_read = 0
                                 GROUP BY type");
    $unreadStmt->execute([$user['id']]);
    $unreadRows = $unreadStmt->fetchAll();
    
    $unreadNotifCount = 0;
    $unreadRequestCount = 0;
    $unreadDecisionCount = 0;
    
    foreach ($unreadRows as $row) {
        $type = $row['type'] ?? '';
        $cnt = (int)($row['cnt'] ?? 0);
        $unreadNotifCount += $cnt;
        if ($type === 'thesis_request') {
            $unreadRequestCount += $cnt;
        } elseif ($type === 'thesis_request_decision') {
            $unreadDecisionCount += $cnt;
        }
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'unreadNotifCount' => $unreadNotifCount,
        'unreadRequestCount' => $unreadRequestCount,
        'unreadDecisionCount' => $unreadDecisionCount
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
