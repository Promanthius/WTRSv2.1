<?php
require_once __DIR__ . '/includes/db.php';

// Migrate old feedback from thesis_versions to notifications
// This converts existing feedback entries to notification records

try {
    // Get all feedback from thesis_versions that don't have corresponding notifications
    $stmt = $pdo->prepare("
        SELECT tv.id, tv.thesis_id, tv.feedback, tv.submitted_at, t.author_id, t.adviser_id
        FROM thesis_versions tv
        JOIN theses t ON tv.thesis_id = t.id
        WHERE tv.feedback IS NOT NULL AND tv.feedback != ''
        AND NOT EXISTS (
            SELECT 1 FROM notifications n
            WHERE n.thesis_id = tv.thesis_id
            AND n.type = 'thesis_feedback'
            AND n.message = tv.feedback
        )
    ");
    $stmt->execute();
    $feedbacks = $stmt->fetchAll();
    
    echo "Found " . count($feedbacks) . " old feedback entries to migrate.\n";
    
    $count = 0;
    foreach ($feedbacks as $fb) {
        // Create a notification for this feedback
        // Mark it as read since it's old feedback
        $insertStmt = $pdo->prepare("
            INSERT INTO notifications (recipient_user_id, sender_user_id, thesis_id, type, message, is_read, created_at)
            VALUES (?, ?, ?, 'thesis_feedback', ?, 1, ?)
        ");
        $insertStmt->execute([
            $fb['author_id'],
            $fb['adviser_id'],
            $fb['thesis_id'],
            $fb['feedback'],
            $fb['submitted_at']
        ]);
        $count++;
        echo "Migrated feedback for thesis_id: " . $fb['thesis_id'] . "\n";
    }
    
    echo "Migration complete! Migrated $count feedback entries.\n";
    echo "\nNow checking notification counts...\n";
    
    // Get statistics
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
            SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read
        FROM notifications
        WHERE type = 'thesis_feedback'
    ");
    $statsStmt->execute();
    $stats = $statsStmt->fetch();
    
    echo "Thesis Feedback Notifications:\n";
    echo "  Total: " . $stats['total'] . "\n";
    echo "  Unread: " . $stats['unread'] . "\n";
    echo "  Read: " . $stats['read'] . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
