<?php
require_once __DIR__ . '/../includes/session.php';

// Download handler for WTRS Artifacts
// Increments the download counter before serving/redirecting to the file.

$thesisId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($thesisId <= 0) {
    header('Location: ' . BASE_URL . 'public/archive.php');
    exit;
}

// 1. Fetch thesis to find the latest version and verify it's archived/publicly accessible
$stmt = $pdo->prepare("SELECT t.id, t.status, tv.file_path 
                       FROM theses t 
                       JOIN thesis_versions tv ON t.id = tv.thesis_id 
                       WHERE t.id = ? AND t.status = 'archived' 
                       ORDER BY tv.submitted_at DESC LIMIT 1");
$stmt->execute([$thesisId]);
$thesis = $stmt->fetch();

if (!$thesis || empty($thesis['file_path'])) {
    // If not archived, check if the current user is the author or the adviser
    $user = current_user();
    if ($user) {
        $checkStmt = $pdo->prepare("SELECT t.id, t.author_id, t.adviser_id, tv.file_path 
                                    FROM theses t 
                                    JOIN thesis_versions tv ON t.id = tv.thesis_id 
                                    WHERE t.id = ? 
                                    ORDER BY tv.submitted_at DESC LIMIT 1");
        $checkStmt->execute([$thesisId]);
        $privateThesis = $checkStmt->fetch();
        
        if ($privateThesis && ((int)$privateThesis['author_id'] === (int)$user['id'] || (int)$privateThesis['adviser_id'] === (int)$user['id'])) {
            $thesis = $privateThesis;
        }
    }
}

if ($thesis) {
    // 2. Increment download count in the theses table
    $upd = $pdo->prepare("UPDATE theses SET downloads = downloads + 1 WHERE id = ?");
    $upd->execute([$thesis['id']]);

    // 3. Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $userId = $_SESSION['user_id'] ?? null;
    $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action_type, description, ip_address) VALUES (?, 'download', ?, ?)");
    $logStmt->execute([$userId, "Downloaded manuscript for thesis ID: " . $thesis['id'], $ip]);

    // 4. Redirect to the actual file
    $filePath = BASE_URL . 'public/uploads/' . $thesis['file_path'];
    header('Location: ' . $filePath);
    exit;
} else {
    // Access denied or not found
    header('Location: ' . BASE_URL . 'public/archive.php');
    exit;
}
